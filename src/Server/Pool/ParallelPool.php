<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

use Amp\DeferredFuture;
use Amp\Socket\SocketAddressType;
use Amp\Socket\UdpSocket;
use Balthild\PhpCsFixerLsp\Coroutine\UdgSocket;
use Balthild\PhpCsFixerLsp\Logging\PsrLogEntry;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\WorkerException;
use Balthild\PhpCsFixerLsp\Worker\EventLoop\ParallelMainLoop;
use parallel\Channel;
use parallel\Future;
use parallel\Runtime;
use Psr\Log\LoggerInterface;

use function Balthild\PhpCsFixerLsp\let;

/**
 * @mago-expect lint:too-many-methods
 */
class ParallelPool extends WorkerPool
{
    protected UdpSocket|UdgSocket $waker;

    /** @var list<Runtime> */
    protected array $runtimes = [];

    /** @var list<Channel> */
    protected array $inputs = [];

    /** @var list<Channel> */
    protected array $outputs = [];

    /** @var list<Future> */
    protected array $tasks = [];

    /** @var list<?DeferredFuture<Response|ExceptionInfo>> */
    protected array $resolvers = [];

    public function __construct(LoggerInterface $logger, ServerOptions $options)
    {
        parent::__construct($logger, $options);
    }

    #[\Override]
    public function call(Request $request): Response
    {
        if ($this->status !== WorkerPoolStatus::Initialized) {
            throw new \LogicException('Worker pool is not initialized.');
        }

        $lock = $this->semaphore->acquire();

        $this->logger->debug("requesting worker {$lock->id}");

        /** @var DeferredFuture<Response|ExceptionInfo> $deferred */
        $deferred = new DeferredFuture();
        $this->resolvers[$lock->id] = $deferred;

        $this->inputs[$lock->id]->send($request);
        $response = $deferred->getFuture()->await();

        $this->logger->debug("get response from worker {$lock->id}");

        $lock->release();

        if ($response instanceof ExceptionInfo) {
            throw new WorkerException($response);
        }

        return $response;
    }

    #[\Override]
    protected function initialize(): void
    {
        if ($this->status !== WorkerPoolStatus::Uninitialized) {
            $this->logger->warning('worker pool is already initialized or initializing');
            return;
        }

        $this->logger->info("initializing worker pool with {$this->workers} workers");
        $this->status = WorkerPoolStatus::Transitioning;

        $this->logger->debug('starting waker socket server');
        $this->waker = $this->bindWakerSocket();

        \Amp\async(function (): void {
            while (let($packet, $this->waker->receive())) {
                \Amp\async($this->poll(...), $packet[1]);
            }
        });

        for ($i = 0; $i < $this->workers; $i++) {
            $this->start($i);
        }

        $this->logger->info('worker pool initialized');
        $this->status = WorkerPoolStatus::Initialized;
    }

    #[\Override]
    protected function shutdown(): void
    {
        if ($this->status !== WorkerPoolStatus::Initialized) {
            $this->logger->warning('worker pool is not initialized');
            return;
        }

        $this->logger->info('shutting down worker pool');
        $this->status = WorkerPoolStatus::Transitioning;

        $locks = \array_map(
            $this->semaphore->acquire(...),
            \range(0, $this->workers - 1),
        );

        foreach ($locks as $lock) {
            $this->stop($lock->id);
            $lock->release();
        }

        $this->waker->close();

        $this->logger->info('worker pool shut down');
        $this->status = WorkerPoolStatus::Deinitialized;
    }

    protected function start(int $id): void
    {
        $this->logger->debug("starting worker thread {$id}");

        $runtime = new Runtime($this->getAutoloader());
        $input = Channel::make(name: "{$id}-input", capacity: Channel::Infinite);
        $output = Channel::make(name: "{$id}-output", capacity: 1);

        $this->runtimes[$id] = $runtime;
        $this->inputs[$id] = $input;
        $this->outputs[$id] = $output;
        $this->resolvers[$id] = null;

        $this->tasks[$id] = $runtime->run(
            static function ($id, $input, $output, $waker) {
                $loop = new ParallelMainLoop($id, $input, $output, $waker);
                $loop->run();
            },
            [$id, $input, $output, $this->getWakerAddress()],
        );

        $this->logger->debug("started worker thread {$id}");
    }

    protected function stop(int $id): void
    {
        $this->logger->debug("stopping worker thread {$id}");

        $this->inputs[$id]->send(null);
        $this->inputs[$id]->close();
        $this->runtimes[$id]->close();

        $this->logger->debug("stopped worker thread {$id}");
    }

    protected function poll(string $data): void
    {
        try {
            $id = \ord($data);
            $event = $this->outputs[$id]->recv();
            $this->dispatch($id, $event);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->logger->error("error processing event from worker {$id}: {$message}");
        }
    }

    protected function dispatch(int $id, mixed $event): void
    {
        if ($event instanceof PsrLogEntry) {
            $this->logger->log(
                $event->level,
                $event->message,
                $event->context,
            );
            return;
        }

        $this->logger->debug("processing response from worker {$id}");

        $resolver = $this->resolvers[$id];
        $this->resolvers[$id] = null;

        $resolver->complete($event);
    }

    protected function bindWakerSocket(): UdpSocket|UdgSocket
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            return \Amp\Socket\bindUdpSocket('127.0.0.1:0');
        }

        $path = \tempnam(\sys_get_temp_dir(), 'waker-');
        if (!$path) {
            throw new \RuntimeException('Failed to get a unique path');
        }

        \unlink($path);

        return UdgSocket::bind($path);
    }

    protected function getWakerAddress(): string
    {
        $address = $this->waker->getAddress();
        $protocol = match ($address->getType()) {
            SocketAddressType::Unix => 'udg',
            SocketAddressType::Internet => 'udp',
        };

        return "{$protocol}://{$address->toString()}";
    }

    protected function getAutoloader(): string
    {
        $phar = \Phar::running();
        if ($phar !== '') {
            return "{$phar}/vendor/autoload.php";
        }

        $path = \realpath(__DIR__ . '/../../../vendor/autoload.php');
        if ($path && \is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the autoloader path.');
    }
}
