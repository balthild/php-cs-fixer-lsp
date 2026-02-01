<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model;

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Fidry\CpuCoreCounter\Finder\FinderRegistry;
use Symfony\Component\Console\Attribute\Option;

final class ServerOptions
{
    #[Option('Start the server on stdio')]
    public bool $stdio = false;

    #[Option('Start the server on specified network port')]
    public ?int $socket = null;

    #[Option('The number of worker processes. Specify 0 to auto-detect based on CPU cores')]
    public int $workers = 0;

    public function resolve(): void
    {
        if ($this->stdio === ($this->socket !== null)) {
            throw new \InvalidArgumentException('Either --stdio or --socket must be specified.');
        }

        if ($this->socket !== null && ($this->socket < 1 || $this->socket > 65535)) {
            throw new \InvalidArgumentException('The socket port must be between 1 and 65535.');
        }

        if ($this->workers < 0) {
            throw new \InvalidArgumentException('The number of workers must be non-negative.');
        }

        if ($this->workers === 0) {
            $counter = new CpuCoreCounter(FinderRegistry::getDefaultLogicalFinders());
            $cores = $counter->getCountWithFallback(1);
            $this->workers = \max(1, (int) \log($cores - 1, 2) + 1);
        }
    }
}
