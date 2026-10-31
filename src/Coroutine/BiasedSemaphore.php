<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

use Amp\Sync\LocalSemaphore;
use Amp\Sync\Lock;

/**
 * A semaphore guarding resources with an unbalanced strategy. Instead of doing
 * round-robin over the available resources (queue-like), it will always return
 * the most recently released resource (stack-like).
 */
class BiasedSemaphore
{
    private LocalSemaphore $inner;

    /** @var list<int> */
    private array $pool;

    public function __construct(int $max)
    {
        $this->inner = new LocalSemaphore($max);
        $this->pool = \range(0, $max - 1);
    }

    public function acquire(): ResourceLock
    {
        $lock = $this->inner->acquire();
        $id = \array_pop($this->pool);

        return new ResourceLock($id, new Lock(function () use ($id, $lock) {
            $this->pool[] = $id;
            $lock->release();
        }));
    }
}
