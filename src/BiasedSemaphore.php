<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\Sync\Lock;
use Amp\Sync\Semaphore;

/**
 * Copied and modified from \Amp\Sync\LocalSemaphore.
 * Originally MIT licensed.
 *
 * Unlike LocalSemaphore which is FIFO (queue-like), this implementation is
 * LIFO (stack-like) in order to make it biased towards recently used locks.
 */
class BiasedSemaphore implements Semaphore
{
    /** @var int[] */
    private array $locks;

    /** @var Deferred[] */
    private array $queue = [];

    public function __construct(int $maxLocks)
    {
        if ($maxLocks < 1) {
            throw new \Error('The number of locks must be greater than 0');
        }

        $this->locks = \range(0, $maxLocks - 1);
    }

    /** {@inheritdoc} */
    public function acquire(): Promise
    {
        if ($this->locks) {
            return new Success(new Lock(\array_pop($this->locks), $this->release(...)));
        }

        $deferred = new Deferred();
        $this->queue[] = $deferred;

        return $deferred->promise();
    }

    private function release(Lock $lock): void
    {
        $id = $lock->getId();

        if ($this->queue) {
            $deferred = \array_shift($this->queue);
            $deferred->resolve(new Lock($id, $this->release(...)));
            return;
        }

        $this->locks[] = $id;
    }
}
