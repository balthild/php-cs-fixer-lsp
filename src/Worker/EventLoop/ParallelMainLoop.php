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

    /** @var resource $waker */
    protected $waker;

    public function __construct(int $id, Channel $input, Channel $output, string $waker)
    {
        $client = \stream_socket_client($waker);
        if ($client === false) {
            throw new \RuntimeException("Failed to connect to waker at {$waker}");
        }

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
            \fwrite($this->waker, \chr($this->id));
        }

        $this->output->close();
        \fclose($this->waker);
    }
}
