<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Worker\Worker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WorkerTest extends TestCase
{
    public function testFormatWithTempFile(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $main = new Worker($logger);

        $text = "<?php\necho 'Hello, World!';\n";

        $temp = \tempnam(\sys_get_temp_dir(), 'worker-test-');
        \file_put_contents($temp, $text);

        $request = new FormatRequest(path: $temp);
        $response = $main->dispatch($request);

        \unlink($temp);

        $this->assertInstanceOf(FormatResponse::class, $response);
        $this->assertCount(1, $response->edits);
    }

    public function testFormatWithDataUri(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $main = new Worker($logger);

        $text = "<?php\necho 'Hello, World!';\n";

        $request = new FormatRequest(text: $text);
        $response = $main->dispatch($request);

        // added 1 empty line before the echo statement
        $this->assertInstanceOf(FormatResponse::class, $response);
        $this->assertCount(1, $response->edits);
    }

    public function testUnknownRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $main = new Worker($logger);

        foreach (['string', 42, [], new \stdClass()] as $request) {
            $response = $main->dispatch($request);
            $this->assertInstanceOf(\Throwable::class, $response);
        }
    }
}
