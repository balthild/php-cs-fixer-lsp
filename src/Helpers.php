<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

use PhpCsFixer\Config;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\ToolInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

final class Helpers
{
    /**
     * Symfony Finder does not provide a way to check this. The following impl
     * is generally fast enough, but could still be O(n) in extreme cases.
     *
     * @param Finder $finder
     * @param string $path
     * @return bool
     */
    public static function found(Finder $finder, string $path): bool
    {
        $path = realpath($path);

        // First check if the file is in the search dirs
        foreach (self::getPrivate($finder, 'dirs') as $dir) {
            $dir = realpath($dir);
            if (str_starts_with($path, $dir)) {
                // Now check if the file is filtered by a traversal starting from its direct parent dir

                /** @var \Iterator<\SplFileInfo> */
                $files = self::callPrivate($finder, 'searchInDirectory', [dirname($path)]);

                foreach ($files as $file) {
                    if ($file->getRealPath() === $path) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getPhpCsFixerResolver(array $options = []): ConfigurationResolver
    {
        $options = [
            'allow-risky' => null,
            'config' => null,
            'dry-run' => false,
            'rules' => null,
            'path' => [],
            'path-mode' => ConfigurationResolver::PATH_MODE_OVERRIDE,
            'using-cache' => null,
            'allow-unsupported-php-version' => null,
            'cache-file' => null,
            'format' => null,
            'diff' => false,
            'stop-on-violation' => false,
            'verbosity' => OutputInterface::VERBOSITY_NORMAL,
            'show-progress' => null,
            'sequential' => false,
            ...$options,
        ];

        return new ConfigurationResolver(
            new Config(),
            $options,
            getcwd(),
            new ToolInfo(),
        );
    }

    public static function getPhpCsFixerFinder(): Finder
    {
        $finder = self::getPhpCsFixerResolver()->getConfig()->getFinder();
        if ($finder instanceof Finder) {
            return $finder;
        }

        throw new \RuntimeException('Unrecognized finder type');
    }

    public static function getPrivate(object $object, string $property): mixed
    {
        $closure = function () use ($property) {
            return $this->{$property};
        };

        return $closure->call($object);
    }

    public static function callPrivate(object $object, string $method, array $args): mixed
    {
        $closure = function () use ($method, $args) {
            return $this->{$method}(...$args);
        };

        return $closure->call($object);
    }
}
