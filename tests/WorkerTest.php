<?php declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Amp\Loop;
use Amp\Promise;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Worker\IpcMainLoop;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WorkerTest extends TestCase
{
    public function testFormatWithTempFile(): void
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
        $response = Promise\wait($main->handle($request));

        unlink($temp);

        $this->assertInstanceOf(FormatResponse::class, $response);
        $this->assertNotEmpty($response->edits);
    }

    public function testFormatWithDataUri(): void
    {
        $main = new IpcMainLoop($this->createMock(LoggerInterface::class));

        $text = "<?php\necho 'Hello, World!';\n";

        $request = new FormatRequest(text: $text);
        $response = Promise\wait($main->handle($request));

        // added 1 empty line before the echo statement
        $this->assertCount(1, $response->edits);
    }

    public function testUnknownRequest(): void
    {
        $main = new IpcMainLoop($this->createMock(LoggerInterface::class));

        foreach (['string', 42, [], new \stdClass()] as $request) {
            $response = Promise\wait($main->handle($request));
            $this->assertInstanceOf(ExceptionInfo::class, $response);
        }
    }
}
