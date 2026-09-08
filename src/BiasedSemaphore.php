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
 * Unlike LocalSemaphore, which does a round-robin over the available locks (queue-like),
 * this implementation always acquires the most recently released lock (stack-like).
 *
 * @see \Amp\Sync\LocalSemaphore
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
            throw new \LogicException('The number of locks must be greater than 0');
        }

        $this->locks = \range(0, $maxLocks - 1);
    }

    /** {@inheritdoc} */
    public function acquire(): Promise
    {
        if ($this->locks) {
            $id = \array_pop($this->locks);
            return new Success(new Lock($id, $this->release(...)));
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
