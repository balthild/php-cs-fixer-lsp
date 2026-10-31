<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Tests;

use Balthild\PhpCsFixerLsp\Worker\TextEditBuilder;
use Phpactor\LanguageServerProtocol\TextEdit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextEditBuilderTest extends TestCase
{
    #[DataProvider('provideTexts')]
    public function testRebuildsNewText(string $old, string $new): void
    {
        $edits = TextEditBuilder::fromTexts($old, $new);

        $this->assertSame($new, $this->applyEdits($old, $edits));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTexts(): iterable
    {
        yield 'unchanged' => [
            "<?php\n\$a = 1;\n",
            "<?php\n\$a = 1;\n",
        ];

        yield 'replace line' => [
            "<?php\n\$a=1;\n",
            "<?php\n\$a = 1;\n",
        ];

        yield 'insert blank line' => [
            "<?php\n\$a = 1;\n",
            "<?php\n\n\$a = 1;\n",
        ];

        yield 'insert line at eof' => [
            "<?php\n\$a = 1;\n",
            "<?php\n\$a = 1;\n\$b = 2;\n",
        ];

        yield 'append final newline' => [
            "<?php\n\$a = 1;",
            "<?php\n\$a = 1;\n",
        ];

        yield 'change before last line without final newline' => [
            "<?php\n\$a=1;\n\$b = 2;",
            "<?php\n\$a = 1;\n\$b = 2;",
        ];

        yield 'remove last line without final newline' => [
            "<?php\n\$b = 2;",
            "<?php\n",
        ];

        yield 'remove last line with final newline' => [
            "<?php\n\$b = 2;\n",
            "<?php\n",
        ];

        yield 'into empty document' => [
            '',
            "<?php\n",
        ];

        yield 'everything removed' => [
            "<?php\n",
            '',
        ];

        yield 'crlf document' => [
            "<?php\r\n\$a=1;\r\n",
            "<?php\r\n\$a = 1;\r\n",
        ];

        yield 'line endings changed to crlf' => [
            "<?php\n\$a = 1;\n",
            "<?php\r\n\$a = 1;\r\n",
        ];

        yield 'removed line looks like a file header' => [
            "<?php\n\$x = <<<'TXT'\n-- foo\nTXT;\n",
            "<?php\n\$x = <<<'TXT'\nTXT;\n",
        ];

        yield 'added line looks like a file header' => [
            "<?php\n\$x = <<<'TXT'\nTXT;\n",
            "<?php\n\$x = <<<'TXT'\n++ bar\nTXT;\n",
        ];

        $unchangedBlock = \str_repeat("\$x = 1;\n", 20);

        yield 'multiple changes' => [
            "<?php\n{$unchangedBlock}\$a = 1;\n{$unchangedBlock}\$b = 1;\n",
            "<?php\n{$unchangedBlock}\$a = 2;\n{$unchangedBlock}\$b = 2;\n",
        ];
    }

    /**
     * @param list<TextEdit> $edits
     */
    private function applyEdits(string $text, array $edits): string
    {
        foreach (\array_reverse($edits) as $edit) {
            $start = $this->offsetAt(
                $text,
                $edit->range->start->line,
                $edit->range->start->character,
            );
            $end = $this->offsetAt(
                $text,
                $edit->range->end->line,
                $edit->range->end->character,
            );

            $this->assertGreaterThanOrEqual($start, $end);

            /** @var non-negative-int $length */
            $length = $end - $start;
            $text = \substr_replace($text, $edit->newText, $start, $length);
        }

        return $text;
    }

    private function offsetAt(string $text, int $line, int $character): int
    {
        $offset = 0;

        for ($i = 0; $i < $line; $i++) {
            $offset = (int) \strpos($text, "\n", $offset) + 1;
        }

        return $offset + $character;
    }
}
