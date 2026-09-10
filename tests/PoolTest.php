<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Amp\Loop;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Balthild\PhpCsFixerLsp\Server\Pool\ParallelPool;
use Balthild\PhpCsFixerLsp\Server\Pool\ProcessPool;
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

            $text = "<?php\necho 'Hello, World!';\n";

            $request = new FormatRequest(text: $text);
            $response = yield $pool->call($request);

            yield Helpers::callPrivate($pool, 'shutdown', []);

            $this->assertInstanceOf(FormatResponse::class, $response);
            $this->assertCount(1, $response->edits);

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

            $text = "<?php\necho 'Hello, World!';\n";

            $request = new FormatRequest(text: $text);
            $response = yield $pool->call($request);

            yield Helpers::callPrivate($pool, 'shutdown', []);

            $this->assertInstanceOf(FormatResponse::class, $response);
            $this->assertCount(1, $response->edits);

            Loop::stop();
        });
    }
}
