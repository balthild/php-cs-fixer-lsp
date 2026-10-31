<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Psr\Log\LoggerInterface;

class Worker
{
    protected LoggerInterface $logger;

    protected Formatter $formatter;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->formatter = new Formatter($logger);
    }

    public function dispatch(mixed $request): Response|\Throwable
    {
        $type = \is_object($request) ? $request::class : \gettype($request);

        try {
            return match ($type) {
                FormatRequest::class => $this->format($request),
                default => new \RuntimeException("Unknown request type: {$type}"),
            };
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    public function format(FormatRequest $request): FormatResponse
    {
        $edits = $this->formatter->format($request->text);
        return new FormatResponse($edits);
    }
}
