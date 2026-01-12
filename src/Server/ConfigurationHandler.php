<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\Promise;
use Amp\Success;
use Balthild\PhpCsFixerLsp\DynamicLogger;
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

    /**
     * @return Promise<null>
     */
    public function didChangeConfiguration(DidChangeConfigurationParams $params): Promise
    {
        $settings = $params->settings['php-cs-fixer-lsp'];

        if ($this->logger instanceof DynamicLogger) {
            $this->logger->setLevel(match ($settings['logLevel'] ?? 'info') {
                'debug' => LogLevel::DEBUG,
                'info' => LogLevel::INFO,
                'warning' => LogLevel::WARNING,
                'error' => LogLevel::ERROR,
                default => LogLevel::INFO,
            });
        }

        $this->logger->info('settings updated');

        return new Success();
    }
}
