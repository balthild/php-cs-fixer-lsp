<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Helpers;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Symfony Finder does not provide an efficient way to check if a path is found.
 */
class FinderCache implements ListenerProviderInterface
{
    protected array $cache = [];

    public function __construct()
    {
        $this->refresh();
    }

    public function contains(string $uri): bool
    {
        if (!$this->cache) {
            return true;
        }

        $path = Helpers::uriToPath($uri);

        return \array_key_exists($path, $this->cache);
    }

    public function refresh(): void
    {
        try {
            foreach (Helpers::getPhpCsFixerFinder()->getIterator() as $file) {
                $this->cache[$file->getRealPath()] = true;
            }
        } catch (\LogicException) {
            // defaults to match everything
            $this->cache = [];
        }
    }

    public function getListenersForEvent(object $event): iterable
    {
        match (true) {
            $event instanceof FilesChanged => yield $this->changed(...),
            default => null,
        };
    }

    protected function changed(FilesChanged $event): void
    {
        foreach ($event->events() as $item) {
            if ($item->type === FileChangeType::CREATED) {
                $this->refresh();
                return;
            }
        }
    }
}
