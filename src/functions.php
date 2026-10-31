<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

/**
 * @template T
 * @template U
 * @param mixed $target
 * @param-out T $target
 * @param T|U $source
 * @param U $sentinal
 */
function let(&$target, $source, $sentinal = null): bool
{
    $target = $source;
    return $target !== $sentinal;
}

/**
 * @template T
 * @template U
 * @param mixed $target
 * @param-out T $target
 * @param \Closure(): T|U $source
 * @param U $sentinal
 */
function attempt(&$target, $source, $sentinal = null): bool
{
    try {
        $target = $source();
    } catch (\Throwable) {
        $target = $sentinal;
    }
    return $target !== $sentinal;
}
