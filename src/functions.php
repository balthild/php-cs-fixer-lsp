<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

function let(&$target, $source, $sentinal = null): bool
{
    $target = $source;
    return $source !== $sentinal;
}
