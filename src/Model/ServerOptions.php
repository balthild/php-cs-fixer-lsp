<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Model;

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Fidry\CpuCoreCounter\Finder\FinderRegistry;
use Symfony\Component\Console\Attribute\Option;

final class ServerOptions
{
    #[Option('Start the server on stdio')]
    public bool $stdio = true;

    #[Option('The number of worker processes. Specify 0 to auto-detect based on CPU cores')]
    public int $workers = 0;

    public function resolve(): void
    {
        if (!$this->stdio) {
            throw new \InvalidArgumentException('Only stdio mode is supported.');
        }

        if ($this->workers < 0) {
            throw new \InvalidArgumentException('The number of workers must be non-negative.');
        }

        if ($this->workers === 0) {
            $counter = new CpuCoreCounter(FinderRegistry::getDefaultLogicalFinders());
            $cores = $counter->getCountWithFallback(1);
            $this->workers = max(1, $cores - 1);
        }
    }
}
