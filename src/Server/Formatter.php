<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\File;
use Amp\Promise;
use Amp\Success;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Server\WorkerPool;
use Phpactor\LanguageServer\Core\Formatting\Formatter as FormatterInterface;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Symfony\Component\Finder\Finder;

class Formatter implements FormatterInterface
{
    protected WorkerPool $workers;

    protected Finder $finder;

    public function __construct(WorkerPool $workers)
    {
        $this->workers = $workers;
        $this->finder = Helpers::getPhpCsFixerFinder();
    }

    /**
     * @return Promise<TextEdit[]|null>
     */
    public function format(TextDocumentItem $textDocument): Promise
    {
        // Non-file URIs are always formatted
        if (str_starts_with($textDocument->uri, 'file://')) {
            $path = urldecode(parse_url($textDocument->uri, PHP_URL_PATH));
            if (!Helpers::found($this->finder, $path)) {
                return new Success(null);
            }
        }

        // Due to the limitations of PHP-CS-Fixer's implementation, we have to
        // write the code to a file to get it formatted. Fortunately, the temp
        // directory is usually in memory.
        $temp = tempnam(sys_get_temp_dir(), 'php-cs-fixer-lsp-');

        return \Amp\call(function () use ($textDocument, $temp) {
            yield File\write($temp, $textDocument->text);
            $response = yield $this->workers->call(new FormatRequest(path: $temp));
            yield File\deleteFile($temp);
        });
    }
}
