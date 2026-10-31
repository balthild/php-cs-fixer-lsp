<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Balthild\PhpCsFixerLsp\Logging\ParallelChannelLogger;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use parallel\Channel;
use Psr\Log\LoggerInterface;

use function Balthild\PhpCsFixerLsp\let;

class ParallelMainLoop
{
    protected LoggerInterface $logger;
    protected Worker $worker;

    protected int $id;
    protected Channel $input;
    protected Channel $output;
    protected Channel $notifier;

    public function __construct(int $id, Channel $input, Channel $output, Channel $notifier)
    {
        $this->logger = new ParallelChannelLogger($id, $output, $notifier);
        $this->worker = new Worker($this->logger);

        $this->id = $id;
        $this->input = $input;
        $this->output = $output;
        $this->notifier = $notifier;
    }

    public function run(): void
    {
        $this->logger->info("worker {$this->id} running");

        while (let($request, $this->input->recv())) {
            $response = $this->worker->dispatch($request);

            if ($response instanceof \Throwable) {
                $response = new ExceptionInfo($response);
            }

            $this->output->send($response);
            $this->notifier->send($this->id);
        }

        $this->output->close();
    }
}
