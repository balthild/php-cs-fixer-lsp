<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Amp\Loop;
use Amp\Parallel\Sync\ChannelledSocket;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use Psr\Log\LoggerInterface;

class ProcessEventLoop
{
    protected Worker $worker;

    public function __construct(LoggerInterface $logger)
    {
        $this->worker = new Worker($logger);
    }

    public function run(): void
    {
        Loop::run(function () {
            $channel = new ChannelledSocket(
                \fopen('php://stdin', 'r'),
                \fopen('php://stdout', 'w'),
            );

            // @mago-expect lint:no-assign-in-condition
            while ($request = yield $channel->receive()) {
                $response = $this->worker->dispatch($request);

                if ($response instanceof \Throwable) {
                    $response = new ExceptionInfo($response);
                }

                yield $channel->send($response);
            }

            $channel->close();
            Loop::stop();
        });
    }
}
