<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Amp\DeferredFuture;
use Amp\Sync\Lock;

/**
 * Copied and modified from \Amp\Sync\LocalSemaphore.
 * Originally MIT licensed.
 *
 * Unlike LocalSemaphore, which does a round-robin over the available locks (queue-like),
 * this implementation always acquires the most recently released lock (stack-like).
 *
 * @see \Amp\Sync\LocalSemaphore
 */
class BiasedSemaphore
{
    /** @var list<int> */
    private array $locks;

    /** @var DeferredFuture<int>[] */
    private array $queue = [];

    public function __construct(int $maxLocks)
    {
        if ($maxLocks < 1) {
            throw new \LogicException('The number of locks must be greater than 0');
        }

        $this->locks = \range(0, $maxLocks - 1);
    }

    public function acquire(): SemaphoreLock
    {
        if ($this->locks) {
            return $this->lock(\array_pop($this->locks));
        }

        $deferred = new DeferredFuture();
        $this->queue[] = $deferred;

        return $this->lock($deferred->getFuture()->await());
    }

    private function release(int $id): void
    {
        if ($this->queue) {
            $deferred = \array_shift($this->queue);
            $deferred->complete($id);
            return;
        }

        $this->locks[] = $id;
    }

    private function lock(int $id): SemaphoreLock
    {
        $inner = new Lock(fn () => $this->release($id));
        return new SemaphoreLock($id, $inner);
    }
}
