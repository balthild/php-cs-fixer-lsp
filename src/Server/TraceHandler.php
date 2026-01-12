<?php

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
use Balthild\PhpCsFixerLsp\DynamicLogger;
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
        if ($this->logger instanceof DynamicLogger) {
            // TODO
        }

        $this->logger->info("trace level set to {$value}");

        return new Success();
    }
}
