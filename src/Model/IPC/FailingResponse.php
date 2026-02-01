<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model\IPC;

final class FailingResponse
{
    protected array $serialized;

    public function __construct(\Throwable $exception)
    {
        $this->serialized = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    public function message(): string
    {
        return $this->serialized['message'];
    }

    public function code(): int
    {
        return $this->serialized['code'];
    }

    public function file(): string
    {
        return $this->serialized['file'];
    }

    public function line(): int
    {
        return $this->serialized['line'];
    }

    public function trace(): string
    {
        return $this->serialized['trace'];
    }

    public function __toString(): string
    {
        return \sprintf(
            "%s: %s in %s(%d)\nStack trace:\n%s",
            static::class,
            $this->serialized['message'],
            $this->serialized['file'],
            $this->serialized['line'],
            $this->serialized['trace'],
        );
    }
}
