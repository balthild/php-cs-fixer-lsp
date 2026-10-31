<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Server\Pool\WorkerPool;
use Phpactor\LanguageServer\Core\Formatting\Formatter as FormatterInterface;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Psr\Log\LoggerInterface;

class Formatter implements FormatterInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected WorkerPool $workers,
        protected FinderCache $finder,
    ) {}

    /**
     * @return TextEdit[]|null
     */
    public function format(TextDocumentItem $textDocument): ?array
    {
        // non-file URIs are always formatted
        if (\str_starts_with($textDocument->uri, 'file://')) {
            if (!$this->finder->contains($textDocument->uri)) {
                $this->logger->info("skipping excluded file {$textDocument->uri}");
                return null;
            }
        }

        $this->logger->info("formatting {$textDocument->uri}");

        // TODO: make this an implementation detail of the worker?
        if (\filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOLEAN)) {
            // by default, data URIs are accepted by `file_get_contents`.
            return $this->formatWithDataUri($textDocument);
        } else {
            // due to the limitations of PHP-CS-Fixer's implementation, we have
            // to provide a path to the code. Fortunately, on Unix-like systems,
            // the temp directory is usually in memory.
            return $this->formatWithTempFile($textDocument);
        }
    }

    /**
     * @return TextEdit[]|null
     */
    protected function formatWithDataUri(TextDocumentItem $textDocument): ?array
    {
        $this->logger->debug('calling worker with code');
        $request = new FormatRequest(text: $textDocument->text);
        $response = $this->workers->call($request);

        return $response->edits;
    }

    /**
     * @return TextEdit[]|null
     */
    protected function formatWithTempFile(TextDocumentItem $textDocument): ?array
    {
        $temp = \tempnam(\sys_get_temp_dir(), 'php-cs-fixer-lsp-');

        try {
            $this->logger->debug("writing code to temporary file {$temp}");
            \file_put_contents($temp, $textDocument->text);

            $this->logger->debug("calling worker with path {$temp}");
            $request = new FormatRequest(path: $temp);
            $response = $this->workers->call($request);

            return $response->edits;
        } finally {
            $this->logger->debug("deleting temporary file {$temp}");
            \unlink($temp);
        }
    }
}
