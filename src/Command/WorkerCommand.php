<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Command;

use Balthild\PhpCsFixerLsp\Worker\IpcMainLoop;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'worker', hidden: true)]
class WorkerCommand extends Command
{
    public function __invoke(OutputInterface $output): int
    {
        // logger does not work unless we inherit the stderr from server
        // but amphp/process hardcoded it to a pipe
        $logger = new NullLogger();

        $loop = new IpcMainLoop($logger);
        $loop->run();

        return Command::SUCCESS;
    }
}
