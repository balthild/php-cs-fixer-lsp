<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Logging;

use Balthild\PhpCsFixerLsp\Coroutine\WakerClient;
use parallel\Channel;
use Psr\Log\AbstractLogger;

class ParallelChannelLogger extends AbstractLogger
{
    public function __construct(
        protected int $id,
        protected Channel $output,
        protected WakerClient $waker,
    ) {}

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->output->send(new PsrLogEntry($level, $message, $context));
        $this->waker->send($this->id);
    }
}
