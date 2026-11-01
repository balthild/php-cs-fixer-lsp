<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

class WakerClient
{
    /** @var resource $stream */
    protected $stream;

    public function __construct(string $address)
    {
        $this->stream = \stream_socket_client($address);
        if (!$this->stream) {
            throw new \RuntimeException("Failed to connect to waker at {$address}");
        }
    }

    public function send(int $value): void
    {
        \fwrite($this->stream, \pack('N', $value));
    }

    public function close(): void
    {
        \fclose($this->stream);
    }
}
