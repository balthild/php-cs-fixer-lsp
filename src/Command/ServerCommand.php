<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Command;

use Balthild\PhpCsFixerLsp\DynamicLogger;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\DispatcherFactory;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'server', description: 'Run the language server')]
class ServerCommand extends Command
{
    public function __invoke(#[MapInput] ServerOptions $options, OutputInterface $output): int
    {
        $options->resolve();

        $logger = new DynamicLogger($output);
        $factory = new DispatcherFactory($options, $logger);
        $server = LanguageServerBuilder::create($factory);

        if ($options->socket !== null) {
            $address = "127.0.0.1:{$options->socket}";
            $logger->info("starting server on {$address}");
            $server->tcpServer($address);
        } else {
            $logger->info('starting server on stdio');
        }

        $server->build()->run();

        return Command::SUCCESS;
    }
}
