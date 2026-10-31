<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model;

use Symfony\Component\Console\Attribute\Option;

final class WorkerOptions
{
    #[Option('Worker ID')]
    public int $id = -1;

    public function resolve(): void
    {
        if ($this->id < 0) {
            throw new \InvalidArgumentException('The worker ID must be specified.');
        }
    }
}
