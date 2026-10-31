<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Amp\Future;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\Pool\ParallelPool;
use Balthild\PhpCsFixerLsp\Server\Pool\ProcessPool;
use Balthild\PhpCsFixerLsp\Server\Pool\WorkerPool;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PoolTest extends TestCase
{
    public function testFormatWithProcessPool(): void
    {
        $options = new ServerOptions();
        $options->workers = 2;
        $options->opcache = false;

        $logger = $this->createMock(LoggerInterface::class);
        $pool = new ProcessPool($logger, $options);

        Helpers::callPrivate($pool, 'initialize', []);

        $this->assertConcurrentRequests($pool, $options->workers * 2);

        Helpers::callPrivate($pool, 'shutdown', []);
    }

    public function testFormatWithParallelPool(): void
    {
        if (!\extension_loaded('parallel')) {
            $this->markTestSkipped('The parallel extension is not loaded.');
        }

        $options = new ServerOptions();
        $options->workers = 2;
        $options->opcache = false;

        $logger = $this->createMock(LoggerInterface::class);
        $pool = new ParallelPool($logger, $options);

        Helpers::callPrivate($pool, 'initialize', []);

        $this->assertConcurrentRequests($pool, $options->workers * 2);

        Helpers::callPrivate($pool, 'shutdown', []);
    }

    private function assertConcurrentRequests(WorkerPool $pool, int $count): void
    {
        $futures = \array_map(
            fn () => \Amp\async(fn () => $this->assertSingleRequest($pool)),
            \range(0, $count),
        );

        Future\await($futures);
    }

    private function assertSingleRequest(WorkerPool $pool): void
    {
        $text = "<?php\necho 'Hello, World!';\n";

        $request = new FormatRequest(text: $text);
        $response = $pool->call($request);

        $this->assertInstanceOf(FormatResponse::class, $response);
        $this->assertCount(1, $response->edits);
    }
}
