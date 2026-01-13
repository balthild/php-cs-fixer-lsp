<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\File;
use Amp\Promise;
use Amp\Success;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Server\WorkerPool;
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
     * @return Promise<TextEdit[]|null>
     */
    public function format(TextDocumentItem $textDocument): Promise
    {
        // Non-file URIs are always formatted
        if (str_starts_with($textDocument->uri, 'file://')) {
            if (!$this->finder->contains($textDocument->uri)) {
                $this->logger->info(
                    "skipping {$textDocument->uri} because it's excluded by PHP-CS-Fixer configuration",
                );
                return new Success(null);
            }
        }

        $this->logger->info("formatting {$textDocument->uri}");

        // Due to the limitations of PHP-CS-Fixer's implementation, we have to
        // write the code to a file to get it formatted. Fortunately, the temp
        // directory is usually in memory.
        $temp = tempnam(sys_get_temp_dir(), 'php-cs-fixer-lsp-');

        return \Amp\call(function () use ($textDocument, $temp) {
            $this->logger->debug("writing code to temporary file {$temp}");
            yield File\write($temp, $textDocument->text);

            $this->logger->debug("calling worker with path {$temp}");
            $response = yield $this->workers->call(new FormatRequest(path: $temp));

            $this->logger->debug("deleting temporary file {$temp}");
            yield File\deleteFile($temp);

            return $response->edits;
        });
    }
}
