<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Balthild\PhpCsFixerLsp\Coroutine\WakerClient;
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
    protected WakerClient $waker;

    public function __construct(int $id, Channel $input, Channel $output, string $waker)
    {
        $client = new WakerClient($waker);

        $this->logger = new ParallelChannelLogger($id, $output, $client);
        $this->worker = new Worker($this->logger);

        $this->id = $id;
        $this->input = $input;
        $this->output = $output;
        $this->waker = $client;
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
            $this->waker->send($this->id);
        }

        // FIXME
        // Critical memory unsoundness in ext-parallel.
        // See https://github.com/krakjoe/parallel/issues/416
        //
        // Mitigation: after receiving the exiting signal (null), the worker
        // must not send any value into channels but only close them.
        //
        // The logger below sends a message into output channel under the hood,
        // which is a triggering behavior.

        // $this->logger->info("worker {$this->id} exiting");

        $this->output->close();
        $this->waker->close();
    }
}
