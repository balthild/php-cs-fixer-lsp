<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Logging;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\WritableResourceStream;
use Amp\Sync\Channel;
use Psr\Log\AbstractLogger;

class AmphpChannelLogger extends AbstractLogger
{
    protected Channel $channel;

    /**
     * @param resource $output
     */
    public function __construct($output)
    {
        $this->channel = new StreamChannel(
            new ReadableBuffer(null),
            new WritableResourceStream($output),
        );
    }

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->channel->send(new PsrLogEntry($level, $message, $context));
    }
}
