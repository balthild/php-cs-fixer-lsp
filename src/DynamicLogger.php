<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class DynamicLogger extends ConsoleLogger
{
    public const DEBUG = 'debug';
    public const INFO = parent::INFO;
    public const WARN = 'warn';
    public const ERROR = parent::ERROR;
    public const FATAL = 'fatal';

    protected array $verbosityLevelMap = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_QUIET,
        LogLevel::ALERT => OutputInterface::VERBOSITY_QUIET,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_QUIET,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_VERBOSE,
    ];

    protected array $formatLevelMap = [
        LogLevel::EMERGENCY => self::FATAL,
        LogLevel::ALERT => self::FATAL,
        LogLevel::CRITICAL => self::FATAL,
        LogLevel::ERROR => self::ERROR,
        LogLevel::WARNING => self::WARN,
        LogLevel::NOTICE => self::INFO,
        LogLevel::INFO => self::INFO,
        LogLevel::DEBUG => self::DEBUG,
    ];

    public function __construct(protected OutputInterface $output)
    {
        parent::__construct($output, $this->verbosityLevelMap, $this->formatLevelMap);
    }

    public function setLevel(int $level): void
    {
        $this->output->setVerbosity($this->verbosityLevelMap[$level] ?? OutputInterface::VERBOSITY_NORMAL);
    }
}
