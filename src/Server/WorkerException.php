<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;

class WorkerException extends \Exception
{
    public function __construct(public readonly ExceptionInfo $info)
    {
        parent::__construct($info->message, $info->code);
    }
}
