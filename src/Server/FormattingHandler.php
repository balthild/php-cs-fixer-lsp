<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\CancellationToken;
use Amp\Promise;
use Phpactor\LanguageServer\Core\Formatting\Formatter;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\DocumentFormattingParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\TextEdit;

/**
 * Copied and modified from \Phpactor\LanguageServer\Handler\TextDocument\FormattingHandler.
 * Originally MIT licensed.
 */
class FormattingHandler implements Handler, CanRegisterCapabilities
{
    public function __construct(
        private Workspace $workspace,
        private Formatter $formatter,
    ) {}

    public function methods(): array
    {
        return ['textDocument/formatting' => 'formatting'];
    }

    /**
     * @return Promise<TextEdit[]|null>
     */
    public function formatting(DocumentFormattingParams $params, CancellationToken $cancellation): Promise
    {
        $document = $this->workspace->get($params->textDocument->uri);
        return $this->formatter->format($document);
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->documentFormattingProvider = true;
    }
}
