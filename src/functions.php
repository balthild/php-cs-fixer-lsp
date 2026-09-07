<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp;

function let(&$target, $source, $sentinal = null): bool
{
    if ($source === $sentinal) {
        return false;
    }

    $target = $source;
    return true;
}
