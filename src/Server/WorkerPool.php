<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Sync\LocalSemaphore;
use Amp\Sync\Lock;
use Amp\Sync\Semaphore;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Phpactor\LanguageServer\Event\Initialized;
use Phpactor\LanguageServer\Event\WillShutdown;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class WorkerPool implements ListenerProviderInterface
{
    protected const int STATUS_UNINITIALIZED = 0;
    protected const int STATUS_INITIALIZED = 1;
    protected const int STATUS_TRANSITIONING = 2;

    public readonly int $workers;

    protected int $status;

    protected Semaphore $semaphore;

    /** @var Process[] */
    protected array $processes = [];

    /** @var Channel[] */
    protected array $channels = [];

    public function __construct(ServerOptions $options)
    {
        $this->workers = $options->workers;
        $this->status = self::STATUS_UNINITIALIZED;
        $this->semaphore = new LocalSemaphore($this->workers);
    }

    /**
     * @template T of Response
     * @param Request<T> $request
     * @return Promise<T>
     */
    public function call(Request $request): Promise
    {
        return \Amp\call(function () use ($request) {
            if ($this->status !== self::STATUS_INITIALIZED) {
                throw new \LogicException('Worker pool is not initialized.');
            }

            /** @var Lock */
            $lock = yield $this->semaphore->acquire();

            $channel = $this->channels[$lock->getId()];

            yield $channel->send($request);
            return yield $channel->receive();
        });
    }

    public function getListenersForEvent(object $event): iterable
    {
        match (true) {
            $event instanceof Initialized => yield $this->initialize(...),
            $event instanceof WillShutdown => yield $this->shutdown(...),
        };
    }

    protected function initialize(Initialized $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== self::STATUS_UNINITIALIZED) {
                return;
            }

            $this->status = self::STATUS_TRANSITIONING;

            $php = new PhpExecutableFinder()->find(false);
            $main = $this->getMainScript();
            $command = "{$php} {$main} worker";

            $processes = array_map(
                fn () => new Process($command),
                range(0, $this->workers - 1),
            );

            yield Promise\all(array_map(
                fn (Process $process) => $process->start(),
                $processes,
            ));

            $channels = array_map(
                fn (Process $process) => new ChannelledStream(
                    $process->getStdout(),
                    $process->getStdin(),
                ),
                $processes,
            );

            $this->processes = $processes;
            $this->channels = $channels;

            $this->status = self::STATUS_INITIALIZED;
        });
    }

    protected function shutdown(WillShutdown $event): void
    {
        \Amp\asyncCall(function () {
            if ($this->status !== self::STATUS_INITIALIZED) {
                return;
            }

            $this->status = self::STATUS_TRANSITIONING;

            yield Promise\all(array_map(
                fn () => \Amp\call(function () {
                    /** @var Lock */
                    $lock = yield $this->semaphore->acquire();

                    yield $this->channels[$lock->getId()]->send(null);
                    yield $this->processes[$lock->getId()]->join();

                    yield $lock->release();
                }),
                range(0, $this->workers - 1),
            ));

            $this->status = self::STATUS_UNINITIALIZED;
        });
    }

    protected function getMainScript(): string
    {
        $phar = \Phar::running(false);
        if ($phar !== '') {
            return $phar;
        }

        $path = realpath(__DIR__ . '/../../bin/php-cs-fixer-lsp');
        if (is_file($path)) {
            return $path;
        }

        throw new \RuntimeException('Cannot determine the main script path.');
    }
}
