<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\WritableResourceStream;
use Balthild\PhpCsFixerLsp\Logging\AmphpChannelLogger;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use Psr\Log\LoggerInterface;

use function Balthild\PhpCsFixerLsp\let;

class ProcessMainLoop
{
    protected LoggerInterface $logger;
    protected Worker $worker;

    public function __construct()
    {
        $this->logger = new AmphpChannelLogger(\STDERR);
        $this->worker = new Worker($this->logger);
    }

    public function run(int $id): void
    {
        $this->logger->info("worker {$id} running");

        /** @var StreamChannel<?Request, Response|ExceptionInfo> */
        $channel = new StreamChannel(
            new ReadableResourceStream(\STDIN),
            new WritableResourceStream(\STDOUT),
        );

        while (let($request, $channel->receive())) {
            $response = $this->worker->dispatch($request);

            if ($response instanceof \Throwable) {
                $response = new ExceptionInfo($response);
            }

            $channel->send($response);
        }

        $channel->close();
    }
}
