<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

use Amp\Closable;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Socket\PendingReceiveError;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Amp\Socket\UnixAddress;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * Copied and modified from \Amp\Socket\ResourceUdpSocket.
 * Originally MIT licensed.
 *
 * @see \Amp\Socket\ResourceUdpSocket
 */
class UdgSocket implements Closable
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var resource|null */
    private $socket;
    private readonly int $limit;
    private readonly UnixAddress $address;

    private readonly string $callbackId;

    private ?Suspension $reader = null;

    private readonly DeferredFuture $onClose;

    public static function bind(string $path, int $limit = 1024): self
    {
        $address = "udg://{$path}";
        $socket = \stream_socket_server($address, $errno, $errstr, \STREAM_SERVER_BIND);

        if (!$socket) {
            throw new SocketException(
                "Could not create datagram {$address}: [#{$errno}] {$errstr}",
                $errno,
            );
        }

        return new self($socket, $limit);
    }

    /**
     * @param resource $socket
     */
    private function __construct($socket, int $limit)
    {
        $this->socket = $socket;
        $this->limit = $limit;
        $this->address = SocketAddress\fromResourceLocal($socket);

        $this->onClose = new DeferredFuture();

        \stream_set_blocking($this->socket, false);
        \stream_set_read_buffer($this->socket, 0);

        $reader = &$this->reader;
        $this->callbackId = EventLoop::onReadable(
            $this->socket,
            static function (string $callbackId, $socket) use (&$reader, &$limit): void {
                \assert($reader !== null, 'Suspension must be set before enabling the callback');

                $data = \stream_socket_recvfrom($socket, $limit, 0, $address);

                if ($data === false) {
                    EventLoop::cancel($callbackId);
                    $reader->resume();
                } else {
                    EventLoop::disable($callbackId);
                    $reader->resume([SocketAddress\fromString($address), $data]);
                }

                $reader = null;
            },
        );

        EventLoop::disable($this->callbackId);
    }

    /**
     * Automatically cancels the loop watcher.
     */
    public function __destruct()
    {
        if (!$this->socket) {
            return;
        }

        $this->free();
    }

    /**
     * @return null|array{UnixAddress, string}
     */
    public function receive(): ?array
    {
        if ($this->reader) {
            throw new PendingReceiveError();
        }

        if (!$this->socket) {
            return null; // Resolve with null when endpoint is closed.
        }

        $this->reader = EventLoop::getSuspension();

        EventLoop::enable($this->callbackId);

        return $this->reader->suspend();
    }

    #[\Override]
    public function close(): void
    {
        if ($this->socket) {
            \fclose($this->socket);
        }

        $this->free();
    }

    public function getAddress(): UnixAddress
    {
        return $this->address;
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->socket === null;
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    private function free(): void
    {
        EventLoop::cancel($this->callbackId);

        $this->socket = null;

        $this->reader?->resume();
        $this->reader = null;

        if (!$this->onClose->isComplete()) {
            $this->onClose->complete();
        }
    }
}
