<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Phpactor\LanguageServer\Core\Handler\Handler;
use Psr\Log\LoggerInterface;

class TraceHandler implements Handler
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function methods(): array
    {
        return ['$/setTrace' => 'setTrace'];
    }

    public function setTrace(string $value): void
    {
        $this->logger->info("server trace level set to {$value}");
    }
}
