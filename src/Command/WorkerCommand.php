<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Command;

use Balthild\PhpCsFixerLsp\Model\WorkerOptions;
use Balthild\PhpCsFixerLsp\Worker\EventLoop\ProcessMainLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'worker', hidden: true)]
class WorkerCommand extends Command
{
    public function __invoke(#[MapInput] WorkerOptions $options, OutputInterface $output): int
    {
        $options->resolve();

        $loop = new ProcessMainLoop();
        $loop->run($options->id);

        return Command::SUCCESS;
    }
}
