<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Balthild\PhpCsFixerLsp\Command\ServerCommand;
use Balthild\PhpCsFixerLsp\Command\WorkerCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\StreamOutput;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct(self::name(), self::version());

        $this->addCommand(new ServerCommand());
        $this->addCommand(new WorkerCommand());
    }

    #[\Override]
    public function run($input = null, $output = null): int
    {
        $input = new ArgvInput();
        $input->setInteractive(false);

        $output = new StreamOutput(fopen('php://stderr', 'w'));

        return parent::run($input, $output);
    }

    public static function name(): string
    {
        return 'balthild/php-cs-fixer-lsp';
    }

    public static function version(): string
    {
        return InstalledVersions::getPrettyVersion(self::name());
    }
}
