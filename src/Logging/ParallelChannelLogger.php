<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Logging;

use parallel\Channel;
use Psr\Log\AbstractLogger;

class ParallelChannelLogger extends AbstractLogger
{
    public function __construct(
        protected int $id,
        protected Channel $output,
        protected Channel $notifier,
    ) {}

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->output->send(new PsrLogEntry($level, $message, $context));
        $this->notifier->send($this->id);
    }
}
