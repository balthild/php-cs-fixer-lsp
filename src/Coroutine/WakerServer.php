<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

use Amp\ByteStream\BufferedReader;
use Amp\Closable;
use Amp\Socket\ServerSocket;

use function Balthild\PhpCsFixerLsp\attempt;
use function Balthild\PhpCsFixerLsp\let;

class WakerServer implements Closable
{
    public readonly string $proto;
    public readonly string $path;

    private readonly ServerSocket|UdgSocket $socket;

    /**
     * @param \Closure(int): void $wake
     */
    public function __construct(private readonly \Closure $wake)
    {
        $this->proto = match (\PHP_OS_FAMILY) {
            'Windows' => 'unix',
            default => 'udg',
        };

        $this->path = \tempnam(\sys_get_temp_dir(), 'waker-');
        if (!$this->path) {
            throw new \RuntimeException('Failed to get a unique path');
        }

        $this->socket = $this->bindSocket();
    }

    protected function bindSocket(): ServerSocket|UdgSocket
    {
        \unlink($this->path);

        return match ($this->proto) {
            'unix' => $this->bindStreamSocket(),
            'udg' => $this->bindDatagramSocket(),
        };
    }

    protected function bindStreamSocket(): ServerSocket
    {
        $socket = \Amp\Socket\listen("unix://{$this->path}");

        \Amp\async(function () use ($socket) {
            while (let($connection, $socket->accept())) {
                \Amp\async(function () use ($connection) {
                    $reader = new BufferedReader($connection);
                    while (attempt($data, $reader->readLength(4))) {
                        $this->onDataReceived($data);
                    }
                });
            }
        });

        return $socket;
    }

    protected function bindDatagramSocket(): UdgSocket
    {
        $socket = UdgSocket::bind($this->path);

        \Amp\async(function () use ($socket) {
            while (let($packet, $socket->receive())) {
                $this->onDataReceived($packet[1]);
            }
        });

        return $socket;
    }

    protected function onDataReceived(string $data): void
    {
        $result = \unpack('Nvalue', $data);
        \Amp\async($this->wake, $result['value']);
    }

    public function getAddress(): string
    {
        return "{$this->proto}://{$this->path}";
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->socket->close();
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    /**
     * @inheritDoc
     */
    public function onClose(\Closure $onClose): void
    {
        $this->socket->onClose($onClose);
    }
}
