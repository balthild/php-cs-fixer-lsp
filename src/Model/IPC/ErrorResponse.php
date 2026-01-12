<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model\IPC;

final class ErrorResponse
{
    public function __construct(
        public \Exception $exception,
    ) {}
}
