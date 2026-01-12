<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model\IPC;

/**
 * @implements Request<FormatResponse>
 */
final class FormatRequest implements Request
{
    public function __construct(
        public string $path,
    ) {}
}
