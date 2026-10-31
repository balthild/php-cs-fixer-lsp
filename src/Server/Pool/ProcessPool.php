<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\WritableBuffer;
use Amp\Process\Process;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\WorkerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;

use function Balthild\PhpCsFixerLsp\attempt;

class ProcessPool extends WorkerPool
{
    protected readonly bool $opcache;

    /** @var list<Process> */
    protected array $processes = [];

    /** @var StreamChannel<Response|ExceptionInfo, ?Request>[] */
    protected array $channels = [];

    public function __construct(LoggerInterface $logger, ServerOptions $options)
    {
        parent::__construct($logger, $options);

        $this->opcache = $options->opcache;
    }

    #[\Override]
    public function call(Request $request): Response
    {
        if ($this->status !== WorkerPoolStatus::Initialized) {
            throw new \LogicException('Worker pool is not initialized.');
        }

        $lock = $this->semaphore->acquire();

        $this->logger->debug("requesting worker {$lock->id}");

        $channel = $this->channels[$lock->id];
        $channel->send($request);
        $response = $channel->receive();

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

        $php = $this->getPhpCommand();
        $main = \escapeshellarg($this->getMainScript());
        $command = "{$php} {$main} worker";
        $this->logger->debug("worker command: {$command}");

        for ($i = 0; $i < $this->workers; $i++) {
            $this->start($command, $i);
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

        $this->logger->info('worker pool shut down');
        $this->status = WorkerPoolStatus::Deinitialized;
    }

    protected function start(string $command, int $id): void
    {
        $this->logger->debug("starting worker process {$id}");

        $process = Process::start("{$command} --id={$id}");
        $this->processes[$id] = $process;
        $this->channels[$id] = new StreamChannel(
            $process->getStdout(),
            $process->getStdin(),
        );

        \Amp\async(function () use ($process) {
            $logging = new StreamChannel($process->getStderr(), new WritableBuffer());
            while (attempt($entry, $logging->receive(...))) {
                $this->logger->log(
                    $entry->level,
                    $entry->message,
                    $entry->context,
                );
            }
            $logging->close();
        });

        $this->logger->debug("started worker process {$id}");
    }

    protected function stop(int $id): void
    {
        $this->logger->debug("stopping worker process {$id}");

        $this->channels[$id]->send(null);
        $this->processes[$id]->join();

        $this->logger->debug("stopped worker process {$id}");
    }

    protected function getPhpCommand(): string
    {
        $command = (new PhpExecutableFinder())->find(false);
        if (!$command) {
            throw new \RuntimeException('Unable to find PHP executable.');
        }

        if ($this->opcache) {
            $command .= ' -d opcache.enable=1';
            $command .= ' -d opcache.enable_cli=1';
            $command .= ' -d opcache.validate_timestamps=1';
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

        $path = \realpath(__DIR__ . '/../../../bin/php-cs-fixer-lsp');
        if ($path && \is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the main script path.');
    }
}
