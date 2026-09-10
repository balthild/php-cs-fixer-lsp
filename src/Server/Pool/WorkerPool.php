<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

use Amp\Promise;
use Amp\Sync\Semaphore;
use Balthild\PhpCsFixerLsp\BiasedSemaphore;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Phpactor\LanguageServer\Event\Initialized;
use Phpactor\LanguageServer\Event\WillShutdown;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;

abstract class WorkerPool implements ListenerProviderInterface
{
    protected readonly LoggerInterface $logger;

    protected readonly int $workers;

    protected WorkerPoolStatus $status;

    protected Semaphore $semaphore;

    protected function __construct(LoggerInterface $logger, ServerOptions $options)
    {
        $this->logger = $logger;
        $this->workers = $options->workers;
        $this->status = WorkerPoolStatus::Uninitialized;
        $this->semaphore = new BiasedSemaphore($this->workers);
    }

    /**
     * @template T of Response
     * @param Request<T> $request
     * @return Promise<T>
     */
    abstract public function call(Request $request): Promise;

    abstract protected function initialize(): Promise;

    abstract protected function shutdown(): Promise;

    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        return match (true) {
            $event instanceof Initialized => [
                fn () => Promise\rethrow($this->initialize()),
            ],
            $event instanceof WillShutdown => [
                fn () => Promise\rethrow($this->shutdown()),
            ],
            default => [],
        };
    }

    public static function create(LoggerInterface $logger, ServerOptions $options): self
    {
        if (\PHP_ZTS && \extension_loaded('parallel')) {
            $logger->info('workers will be run in threads');
            return new ParallelPool($logger, $options);
        } else {
            $logger->info('workers will be run in child processes');
            return new ProcessPool($logger, $options);
        }
    }
}
