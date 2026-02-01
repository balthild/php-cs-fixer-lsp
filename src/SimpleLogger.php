<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class SimpleLogger extends AbstractLogger
{
    public function __construct(protected OutputInterface $output) {}

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->output->writeln("[{$level}] {$message}", $this->verbosity($level));
    }

    public function setLevel(string $level): void
    {
        $this->output->setVerbosity($this->verbosity($level));
    }

    private function verbosity(string $level)
    {
        return match ($level) {
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_QUIET,
            LogLevel::ALERT => OutputInterface::VERBOSITY_QUIET,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_QUIET,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_VERBOSE,
            default => throw new \LogicException("The log level '{$level}' does not exist."),
        };
    }
}
