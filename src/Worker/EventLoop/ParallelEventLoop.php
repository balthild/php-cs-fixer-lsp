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

    protected int $id;
    protected Channel $input;
    protected Channel $output;
    protected Channel $notifier;

    public function __construct(int $id, Channel $input, Channel $output, Channel $notifier)
    {
        $logger = new ChannelLogger($output);
        $this->worker = new Worker($logger);

        $this->id = $id;
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
            $this->notifier->send($this->id | 0x80);
        }

        $this->output->close();
    }
}
