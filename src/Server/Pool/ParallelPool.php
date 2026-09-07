<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

use Amp\Deferred;
use Amp\Promise;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Server;
use Amp\Sync\Lock;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\WorkerException;
use Balthild\PhpCsFixerLsp\Worker\EventLoop\ParallelEventLoop;
use parallel\Channel;
use parallel\Future;
use parallel\Runtime;
use Phpactor\LanguageServer\Event\Initialized;
use Phpactor\LanguageServer\Event\WillShutdown;
use Psr\Log\LoggerInterface;

/**
 * @mago-expect lint:kan-defect
 */
class ParallelPool extends WorkerPool
{
    protected Server $waker;
    protected Channel $notifier;
    protected Runtime $bridge;
    protected Future $messenger;

    /** @var Runtime[] */
    protected array $runtimes = [];

    /** @var Channel[] */
    protected array $inputs = [];

    /** @var Channel[] */
    protected array $outputs = [];

    /** @var Future[] */
    protected array $tasks = [];

    /** @var Deferred[] */
    protected array $resolvers = [];

    public function __construct(LoggerInterface $logger, ServerOptions $options)
    {
        parent::__construct($logger, $options);
    }

    #[\Override]
    public function call(Request $request): Promise
    {
        return \Amp\call(function () use ($request) {
            if ($this->status !== WorkerPoolStatus::Initialized) {
                throw new \LogicException('Worker pool is not initialized.');
            }

            /** @var Lock */
            $lock = yield $this->semaphore->acquire();

            $this->logger->debug("requesting worker {$lock->getId()}");

            $deferred = new Deferred();
            $this->resolvers[$lock->getId()] = $deferred;

            $this->inputs[$lock->getId()]->send($request);
            $response = yield $deferred->promise();

            $this->logger->debug("get response from worker {$lock->getId()}");

            $lock->release();

            if ($response instanceof ExceptionInfo) {
                throw new WorkerException($response);
            }

            return $response;
        });
    }

    #[\Override]
    protected function initialize(Initialized $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== WorkerPoolStatus::Uninitialized) {
                $this->logger->warning('worker pool is already initialized or initializing');
                return;
            }

            $this->logger->info("initializing worker pool with {$this->workers} workers");
            $this->status = WorkerPoolStatus::Transitioning;

            // TODO: use unix domain socket on Linux and macOS
            $this->logger->debug('starting waker socket server');
            $this->waker = Server::listen('tcp://127.0.0.1:0');
            \Amp\asyncCall(function () {
                // @mago-expect lint:no-assign-in-condition
                while ($socket = yield $this->waker->accept()) {
                    \Amp\asyncCall($this->poll(...), $socket);
                }
            });

            $this->logger->debug('starting waker bridge thread');
            $this->notifier = new Channel(capacity: Channel::Infinite);
            $this->bridge = new Runtime($this->getAutoloader());
            $this->messenger = $this->bridge->run(
                static function (Channel $notifier, string $waker) {
                    $client = \stream_socket_client($waker);
                    // @mago-expect lint:no-assign-in-condition
                    while ($repr = $notifier->recv()) {
                        \fwrite($client, \chr($repr & 0x7F));
                    }
                    \fclose($client);
                },
                [$this->notifier, $this->waker->getAddress()->toString()],
            );

            for ($i = 0; $i < $this->workers; $i++) {
                $this->start($i);
            }

            $this->logger->info('worker pool initialized');
            $this->status = WorkerPoolStatus::Initialized;
        });
    }

    #[\Override]
    protected function shutdown(WillShutdown $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== WorkerPoolStatus::Initialized) {
                $this->logger->warning('worker pool is not initialized');
                return;
            }

            $this->logger->info('shutting down worker pool');
            $this->status = WorkerPoolStatus::Transitioning;

            $locks = yield Promise\all(\array_map(
                fn () => $this->semaphore->acquire(),
                \range(0, $this->workers - 1),
            ));

            foreach ($locks as $lock) {
                $this->stop($lock->getId());
                $lock->release();
            }

            $this->waker->close();
            $this->notifier->send(null);
            $this->notifier->close();
            $this->bridge->close();

            $this->logger->info('worker pool shut down');
            $this->status = WorkerPoolStatus::Deinitialized;
        });
    }

    protected function start(int $id)
    {
        $this->logger->debug("starting worker {$id}");

        $runtime = new Runtime($this->getAutoloader());
        $input = Channel::make(name: "{$id}-input", capacity: Channel::Infinite);
        $output = Channel::make(name: "{$id}-output", capacity: Channel::Infinite);

        $this->runtimes[$id] = $runtime;
        $this->inputs[$id] = $input;
        $this->outputs[$id] = $output;
        $this->resolvers[$id] = null;

        $this->tasks[$id] = $runtime->run(
            static function ($id, $input, $output, $notifier) {
                $loop = new ParallelEventLoop($id, $input, $output, $notifier);
                $loop->run();
            },
            [$id, $input, $output, $this->notifier],
        );

        $this->logger->debug("started worker {$id}");
    }

    protected function stop(int $id)
    {
        $this->logger->debug("stopping worker {$id}");

        $this->inputs[$id]->send(null);
        $this->inputs[$id]->close();
        $this->runtimes[$id]->close();

        $this->logger->debug("stopped worker {$id}");
    }

    protected function poll(ResourceSocket $socket)
    {
        // @mago-expect lint:no-assign-in-condition
        while ($data = yield $socket->read()) {
            $this->logger->debug('polling events');

            foreach (Helpers::bytes($data) as $byte) {
                $id = \ord($byte);
                $this->logger->debug("processing event from worker {$id}");

                $response = $this->outputs[$id]->recv();
                $this->resolvers[$id]->resolve($response);
                $this->resolvers[$id] = null;
            }

            $this->logger->debug('events idle');
        }
    }

    protected function getAutoloader(): string
    {
        $phar = \Phar::running();
        if ($phar !== '') {
            return "{$phar}/vendor/autoload.php";
        }

        $path = \realpath(__DIR__ . '/../../../vendor/autoload.php');
        if (\is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the autoloader path.');
    }
}
