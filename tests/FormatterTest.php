<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Balthild\PhpCsFixerLsp\Worker\Formatter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FormatterTest extends TestCase
{
    public function testFormatWithTempFile(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $formatter = new class($logger) extends Formatter {
            #[\Override]
            protected function supportsDataUri(): bool
            {
                return false;
            }
        };

        $text = "<?php\necho 'Hello, World!';\n";
        $edits = $formatter->format($text);

        // added 1 empty line before the echo statement
        $this->assertNotNull($edits);
        $this->assertCount(1, $edits);
    }

    public function testFormatWithDataUri(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $formatter = new class($logger) extends Formatter {
            #[\Override]
            protected function supportsDataUri(): bool
            {
                return true;
            }
        };

        $text = "<?php\necho 'Hello, World!';\n";
        $edits = $formatter->format($text);

        // added 1 empty line before the echo statement
        $this->assertNotNull($edits);
        $this->assertCount(1, $edits);
    }

    public function testFormatUnchanged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $formatter = new Formatter($logger);

        $text = "<?php\n\necho 'Hello, World!';\n";
        $edits = $formatter->format($text);

        $this->assertNull($edits);
    }
}
