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
     * @return ?list<TextEdit>
     */
    public function format(TextDocumentItem $textDocument): ?array
    {
        // only files that has a file URI (real files on disk) should be excluded
        if (\str_starts_with($textDocument->uri, 'file://')) {
            if (!$this->finder->contains($textDocument->uri)) {
                $this->logger->info("skipping excluded file {$textDocument->uri}");
                return null;
            }
        }

        $this->logger->info("formatting {$textDocument->uri}");

        $request = new FormatRequest(text: $textDocument->text);
        $response = $this->workers->call($request);

        return $response->edits;
    }
}
