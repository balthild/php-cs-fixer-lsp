<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\WritableResourceStream;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\Request;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use Psr\Log\LoggerInterface;

use function Balthild\PhpCsFixerLsp\let;

class ProcessEventLoop
{
    protected Worker $worker;

    public function __construct(LoggerInterface $logger)
    {
        $this->worker = new Worker($logger);
    }

    public function run(): void
    {
        /** @var StreamChannel<Request|null, Response|ExceptionInfo> */
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
