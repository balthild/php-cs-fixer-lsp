<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker\EventLoop;

use Balthild\PhpCsFixerLsp\Logging\ChannelLogger;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use parallel\Channel;

class ParallelEventLoop
{
    protected Worker $worker;

    protected Channel $input;
    protected Channel $output;
    protected Channel $notifier;

    public function __construct(Channel $input, Channel $output, Channel $notifier)
    {
        $logger = new ChannelLogger($output);
        $this->worker = new Worker($logger);

        $this->input = $input;
        $this->output = $output;
        $this->notifier = $notifier;
    }

    public function run(): void
    {
        // @mago-expect lint:no-assign-in-condition
        while ($request = $this->input->recv()) {
            $response = $this->worker->dispatch($request);

            if ($response instanceof \Throwable) {
                $response = new ExceptionInfo($response);
            }

            $this->output->send($response);
            $this->notifier->send(true);
        }

        $this->output->close();
    }
}
