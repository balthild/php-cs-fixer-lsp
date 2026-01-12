<?php declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Amp\Promise;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Worker\IpcMainLoop;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WorkerTest extends TestCase
{
    public function testFormat(): void
    {
        $main = new IpcMainLoop($this->createMock(LoggerInterface::class));

        $temp = tempnam(sys_get_temp_dir(), 'worker-test-');
        file_put_contents($temp, <<<EOF
        <?php
            namespace A;
        function greet() {
        echo 'Hello, World!';
            }
        EOF);

        $request = new FormatRequest(path: $temp);
        $response = Promise\wait($main->format($request));

        unlink($temp);

        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    }
}
