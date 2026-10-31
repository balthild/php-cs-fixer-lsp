<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Amp\Sync\Lock;

final class SemaphoreLock
{
    public function __construct(
        public readonly int $id,
        private readonly Lock $lock,
    ) {}

    public function release(): void
    {
        $this->lock->release();
    }
}
