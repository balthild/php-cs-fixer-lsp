<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model\IPC;

final class FailingResponse
{
    public readonly string $message;
    public readonly int $code;
    public readonly string $file;
    public readonly int $line;
    public readonly string $trace;

    public function __construct(\Throwable $exception)
    {
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
        $this->trace = $exception->getTraceAsString();
    }

    public function __toString(): string
    {
        return \sprintf(
            "%s: %s in %s(%d)\nStack trace:\n%s",
            static::class,
            $this->message,
            $this->file,
            $this->line,
            $this->trace,
        );
    }
}
