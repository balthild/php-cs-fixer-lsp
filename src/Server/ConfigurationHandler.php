<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Logging\SimpleLogger;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\DidChangeConfigurationParams;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ConfigurationHandler implements Handler
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function methods(): array
    {
        return ['workspace/didChangeConfiguration' => 'didChangeConfiguration'];
    }

    public function didChangeConfiguration(DidChangeConfigurationParams $params): void
    {
        $settings = $params->settings['php-cs-fixer-lsp'];

        if ($this->logger instanceof SimpleLogger) {
            $this->logger->setLevel(match ($settings['logLevel'] ?? 'info') {
                'debug' => LogLevel::DEBUG,
                'info' => LogLevel::INFO,
                'warning' => LogLevel::WARNING,
                'error' => LogLevel::ERROR,
                default => LogLevel::INFO,
            });
        }

        $this->logger->info('settings updated');
    }
}
