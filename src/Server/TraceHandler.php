<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
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

    /**
     * @return Promise<null>
     */
    public function setTrace(string $value, CancellationToken $canellation): Promise
    {
        $this->logger->info("server trace level set to {$value}");
        return new Success();
    }
}
