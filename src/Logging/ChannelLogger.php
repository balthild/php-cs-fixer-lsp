<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Logging;

use parallel\Channel;
use Psr\Log\AbstractLogger;

class ChannelLogger extends AbstractLogger
{
    public function __construct(protected Channel $channel) {}

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        // TODO
        // $this->channel->send("[{$level}] {$message}");
    }
}
