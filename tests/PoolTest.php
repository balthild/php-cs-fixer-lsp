<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Amp\Loop;
use Amp\Promise;
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
        Loop::run(function () {
            $options = new ServerOptions();
            $options->workers = 2;
            $options->opcache = false;

            $logger = $this->createMock(LoggerInterface::class);
            $pool = new ProcessPool($logger, $options);

            yield Helpers::callPrivate($pool, 'initialize', []);

            yield Promise\all(\array_map(
                fn () => $this->assertSingleRequest($pool),
                \range(0, $options->workers * 2),
            ));

            yield Helpers::callPrivate($pool, 'shutdown', []);

            Loop::stop();
        });
    }

    public function testFormatWithParallelPool(): void
    {
        if (!\extension_loaded('parallel')) {
            $this->markTestSkipped('The parallel extension is not loaded.');
        }

        Loop::run(function () {
            $options = new ServerOptions();
            $options->workers = 2;
            $options->opcache = false;

            $logger = $this->createMock(LoggerInterface::class);
            $pool = new ParallelPool($logger, $options);

            yield Helpers::callPrivate($pool, 'initialize', []);

            yield Promise\all(\array_map(
                fn () => $this->assertSingleRequest($pool),
                \range(0, $options->workers * 2),
            ));

            yield Helpers::callPrivate($pool, 'shutdown', []);

            Loop::stop();
        });
    }

    private function assertSingleRequest(WorkerPool $pool): Promise
    {
        return \Amp\call(function () use ($pool) {
            $text = "<?php\necho 'Hello, World!';\n";

            $request = new FormatRequest(text: $text);
            $response = yield $pool->call($request);

            $this->assertInstanceOf(FormatResponse::class, $response);
            $this->assertCount(1, $response->edits);
        });
    }
}
