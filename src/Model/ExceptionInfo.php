<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model;

final class ExceptionInfo
{
    public readonly string $class;
    public readonly string $message;
    public readonly int $code;
    public readonly string $file;
    public readonly int $line;
    public readonly string $trace;

    public function __construct(\Throwable $exception)
    {
        $this->class = $exception::class;
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
        $this->trace = $exception->getTraceAsString();
    }

    public function description(): string
    {
        return "{$this->class}: {$this->message} in {$this->file}({$this->line})";
    }

    public function details(): string
    {
        return <<<EOF
        {$this->description()}
        Stack trace:
        {$this->trace}
        EOF;
    }
}
