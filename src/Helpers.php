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
        $reflClass = new \ReflectionClass($object);

        while (!$reflClass->hasProperty($property)) {
            $reflClass = $reflClass->getParentClass();
            if ($reflClass === false) {
                throw new \RuntimeException("Property {$property} not found");
            }
        }

        $reflProperty = $reflClass->getProperty($property);
        $reflProperty->setAccessible(true);

        return $reflProperty->getValue($object);
    }

    public static function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflClass = new \ReflectionClass($object);

        while (!$reflClass->hasMethod($method)) {
            $reflClass = $reflClass->getParentClass();
            if ($reflClass === false) {
                throw new \RuntimeException("Method {$method} not found");
            }
        }

        $reflMethod = $reflClass->getMethod($method);
        $reflMethod->setAccessible(true);

        return $reflMethod->invokeArgs($object, $args);
    }
}
