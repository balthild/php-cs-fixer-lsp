<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Logging;

readonly class PsrLogEntry
{
    public function __construct(
        public string $level,
        public string $message,
        public array $context = [],
    ) {}
}
