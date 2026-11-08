<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Coroutine;

trait WakerEncoding
{
    private function encode(int $value): string
    {
        return \pack('N', $value);
    }

    private function decode(string $data): int
    {
        return \unpack('N', $data)[1];
    }
}
