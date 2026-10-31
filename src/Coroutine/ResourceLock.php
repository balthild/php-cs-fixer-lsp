<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

use Amp\Sync\Lock;

final class ResourceLock
{
    public function __construct(
        public readonly int $id,
        private readonly Lock $inner,
    ) {}

    public function release(): void
    {
        $this->inner->release();
    }
}
