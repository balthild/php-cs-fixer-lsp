<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use Symfony\Component\Finder\Finder;

/**
 * Symfony Finder does not provide an efficient way to check if a path is found.
 */
class FinderCache
{
    protected array $cache = [];

    public function __construct(Finder $finder)
    {
        try {
            foreach ($finder->getIterator() as $file) {
                $this->cache[$file->getRealPath()] = true;
            }
        } catch (\LogicException) {
            // @mago-expect lint:no-empty-catch-clause
            // defaults to match everything
        }
    }

    public function contains(string $path): bool
    {
        return !$this->cache || array_key_exists($path, $this->cache);
    }
}
