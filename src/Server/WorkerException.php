<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Model\IPC\FailingResponse;

class WorkerException extends \Exception
{
    public function __construct(public readonly FailingResponse $response)
    {
        parent::__construct($response->message, $response->code);
    }
}
