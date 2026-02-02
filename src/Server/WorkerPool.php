<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Sync\Lock;
use Amp\Sync\Semaphore;
use Balthild\PhpCsFixerLsp\BiasedSemaphore;
use Balthild\PhpCsFixerLsp\Model\IPC\FailingResponse;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Phpactor\LanguageServer\Event\Initialized;
use Phpactor\LanguageServer\Event\WillShutdown;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class WorkerPool implements ListenerProviderInterface
{
    protected readonly LoggerInterface $logger;
    protected readonly int $workers;
    protected readonly bool $opcache;

    protected WorkerPoolStatus $status;

    protected Semaphore $semaphore;

    /** @var Process[] */
    protected array $processes = [];

    /** @var Channel[] */
    protected array $channels = [];

    public function __construct(LoggerInterface $logger, ServerOptions $options)
    {
        $this->logger = $logger;
        $this->workers = $options->workers;
        $this->opcache = $options->opcache;

        $this->status = WorkerPoolStatus::Uninitialized;
        $this->semaphore = new BiasedSemaphore($this->workers);
    }

    /**
     * @template T of Response
     * @param Request<T> $request
     * @return Promise<T>
     */
    public function call(Request $request): Promise
    {
        return \Amp\call(function () use ($request) {
            if ($this->status !== WorkerPoolStatus::Initialized) {
                throw new \LogicException('Worker pool is not initialized.');
            }

            /** @var Lock */
            $lock = yield $this->semaphore->acquire();

            $channel = $this->channels[$lock->getId()];
            yield $channel->send($request);
            $response = yield $channel->receive();

            $lock->release();

            if ($response instanceof FailingResponse) {
                throw new WorkerException($response);
            }

            return $response;
        });
    }

    public function getListenersForEvent(object $event): iterable
    {
        match (true) {
            $event instanceof Initialized => yield $this->initialize(...),
            $event instanceof WillShutdown => yield $this->shutdown(...),
            default => null,
        };
    }

    protected function initialize(Initialized $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== WorkerPoolStatus::Uninitialized) {
                $this->logger->warning('worker pool is already initialized or initializing');
                return;
            }

            $this->logger->info("initializing worker pool with {$this->workers} workers");
            $this->status = WorkerPoolStatus::Transitioning;

            $php = $this->getPhpCommand();
            $main = escapeshellarg($this->getMainScript());
            $command = "{$php} {$main} worker";
            $this->logger->debug("worker command: {$command}");

            $processes = \array_map(
                static fn () => new Process($command),
                \range(0, $this->workers - 1),
            );

            yield Promise\all(\array_map(
                static fn (Process $process) => $process->start(),
                $processes,
            ));

            $channels = \array_map(
                static fn (Process $process) => new ChannelledStream(
                    $process->getStdout(),
                    $process->getStdin(),
                ),
                $processes,
            );

            $this->processes = $processes;
            $this->channels = $channels;

            $this->logger->info('worker pool initialized');
            $this->status = WorkerPoolStatus::Initialized;
        });
    }

    protected function shutdown(WillShutdown $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== WorkerPoolStatus::Initialized) {
                $this->logger->warning('worker pool is not initialized');
                return;
            }

            $this->logger->info('shutting down worker pool');
            $this->status = WorkerPoolStatus::Transitioning;

            yield Promise\all(\array_map(
                fn () => \Amp\call(function () {
                    /** @var Lock */
                    $lock = yield $this->semaphore->acquire();

                    yield $this->channels[$lock->getId()]->send(null);
                    yield $this->processes[$lock->getId()]->join();

                    yield $lock->release();
                }),
                \range(0, $this->workers - 1),
            ));

            $this->logger->info('worker pool shut down');
            $this->status = WorkerPoolStatus::Deinitialized;
        });
    }

    protected function getPhpCommand(): string
    {
        $command = (new PhpExecutableFinder())->find(false);

        if ($this->opcache) {
            $command .= ' -d opcache.enable=1';
            $command .= ' -d opcache.enable_cli=1';
            $command .= ' -d opcache.validate_timestamps=0';
            $command .= ' -d opcache.preload=';

            // https://wiki.php.net/rfc/opcache.no_cache
            // $command .= ' -d opcache.enable_cache=0';
        }

        return $command;
    }

    protected function getMainScript(): string
    {
        $phar = \Phar::running(false);
        if ($phar !== '') {
            return $phar;
        }

        $path = \realpath(__DIR__ . '/../../bin/php-cs-fixer-lsp');
        if (\is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the main script path.');
    }
}
