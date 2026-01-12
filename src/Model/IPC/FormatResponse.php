<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model\IPC;

use Phpactor\LanguageServerProtocol\TextEdit;

final class FormatResponse implements Response
{
    public function __construct(
        /** @var TextEdit[] */
        public array $edits,
    ) {}
}
