<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Server;
use Amp\Sync\Lock;
use Amp\Sync\Semaphore;
use Balthild\PhpCsFixerLsp\BiasedSemaphore;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\WorkerException;
use Balthild\PhpCsFixerLsp\Worker\EventLoop\ParallelEventLoop;
use parallel\Channel;
use parallel\Events;
use parallel\Future;
use parallel\Runtime;
use Phpactor\LanguageServer\Event\Initialized;
use Phpactor\LanguageServer\Event\WillShutdown;
use Psr\Log\LoggerInterface;

class ParallelPool extends WorkerPool
{
    protected readonly LoggerInterface $logger;
    protected readonly int $workers;

    protected WorkerPoolStatus $status;

    protected Semaphore $semaphore;

    protected Events $events;
    protected Server $waker;
    protected Channel $notifier;
    protected Runtime $bridge;
    protected Future $replayer;

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
        $this->logger = $logger;
        $this->workers = $options->workers;

        $this->status = WorkerPoolStatus::Uninitialized;
        $this->semaphore = new BiasedSemaphore($this->workers);

        $this->events = new Events();
        $this->events->setBlocking(false);
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
            $this->notifier = Channel::make(name: 'notifier', capacity: 1);
            $this->bridge = new Runtime($this->getAutoloader());
            $this->replayer = $this->bridge->run(
                static function (Channel $notifier, string $waker) {
                    $client = \stream_socket_client($waker);
                    while ($notifier->recv()) {
                        \fwrite($client, "\n");
                    }
                },
                [$this->notifier, $this->waker->getAddress()->toString()],
            );

            for ($i = 0; $i < $this->workers; $i++) {
                $this->logger->debug("starting worker {$i}");

                $runtime = new Runtime($this->getAutoloader());
                $input = Channel::make(name: "{$i}-input", capacity: 1);
                $output = Channel::make(name: "{$i}-output", capacity: 1);

                $this->events->addChannel($output);

                $this->runtimes[] = $runtime;
                $this->inputs[] = $input;
                $this->outputs[] = $output;
                $this->resolvers[] = null;

                $this->tasks[] = $runtime->run(
                    static function ($input, $output, $notifier) {
                        $loop = new ParallelEventLoop($input, $output, $notifier);
                        $loop->run();
                    },
                    [$input, $output, $this->notifier],
                );

                $this->logger->debug("started worker {$i}");
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
                fn () => \Amp\call(function () {
                    /** @var Lock */
                    $lock = yield $this->semaphore->acquire();

                    $this->logger->debug("stopping worker {$lock->getId()}");

                    $this->inputs[$lock->getId()]->send(null);
                    $this->inputs[$lock->getId()]->close();
                    $this->runtimes[$lock->getId()]->close();

                    $this->logger->debug("stopped worker {$lock->getId()}");

                    return $lock;
                }),
                \range(0, $this->workers - 1),
            ));

            foreach ($locks as $lock) {
                $lock->release();
            }

            $this->logger->info('worker pool shut down');
            $this->status = WorkerPoolStatus::Deinitialized;
        });
    }

    protected function poll(ResourceSocket $socket)
    {
        while (yield $socket->read()) {
            $this->logger->debug('polling events');

            // must process at least one event
            $this->events->setBlocking(true);

            // @mago-expect lint:no-assign-in-condition
            while ($event = $this->events->poll()) {
                $this->logger->debug("processing event from {$event->source}");

                if ($event->type === Events\Event\Type::Read) {
                    $id = (int) $event->source;
                    $this->resolvers[$id]->resolve($event->value);
                    $this->resolvers[$id] = null;

                    // the channel was automatically removed when the event fires
                    $this->events->addChannel($event->object);
                }

                $this->events->setBlocking(false);
            }

            $this->logger->debug('finished polling events');
        }
    }

    protected function getAutoloader(): string
    {
        $phar = \Phar::running(false);
        if ($phar !== '') {
            return $phar;
        }

        $path = \realpath(__DIR__ . '/../../../vendor/autoload.php');
        if (\is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the autoloader path.');
    }
}
