<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server\Pool;

enum WorkerPoolStatus
{
    case Uninitialized;
    case Initialized;
    case Deinitialized;
    case Transitioning;
}
