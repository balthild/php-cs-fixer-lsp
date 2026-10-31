<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Builds whole-line text edits from the difference between two documents.
 *
 * Every edit replaces a range whose ends sit at the start of a line, so a
 * change is always expressed as "remove these lines, insert those lines".
 * The inputs are the documents themselves rather than a rendered diff, which
 * keeps line endings, a missing final newline, and content that looks like
 * diff syntax intact.
 */
final class TextEditBuilder
{
    /**
     * @return list<TextEdit>
     */
    public static function fromTexts(string $old, string $new): array
    {
        // The output builder is mandatory, but `diffToArray()` never uses it.
        $differ = new Differ(new UnifiedDiffOutputBuilder());

        /** @var list<array{string, int}> $diff */
        $diff = $differ->diffToArray($old, $new);

        $lines = self::documentLines($old);
        $edits = [];

        foreach (self::changes($diff) as [$line, $removed, $added]) {
            $start = self::lineStart($lines, $line);
            $end = $removed === 0 ? $start : self::lineStart($lines, $line + $removed);

            $edits[] = new TextEdit(new Range($start, $end), $added);
        }

        return $edits;
    }

    /**
     * Groups the diff into blocks of consecutive removed and added lines.
     *
     * Yields the 0-based line the block starts at, how many lines it removes,
     * and the text it adds.
     *
     * @param list<array{string, int}> $diff
     * @return iterable<array{int, int, string}>
     */
    private static function changes(array $diff): iterable
    {
        $line = 0;
        $count = \count($diff);

        for ($i = 0; $i < $count;) {
            $type = $diff[$i][1];

            if ($type === Differ::OLD) {
                $line++;
                $i++;
                continue;
            }

            if ($type !== Differ::REMOVED && $type !== Differ::ADDED) {
                // a warning about mismatched line endings describes no line of its own
                $i++;
                continue;
            }

            $removed = 0;
            $added = '';

            while ($i < $count && ($diff[$i][1] === Differ::REMOVED || $diff[$i][1] === Differ::ADDED)) {
                if ($diff[$i][1] === Differ::REMOVED) {
                    $removed++;
                } else {
                    $added .= $diff[$i][0];
                }

                $i++;
            }

            yield [$line, $removed, $added];

            $line += $removed;
        }
    }

    /**
     * Splits text into the lines an LSP client numbers: a trailing line
     * terminator opens one more, empty line.
     *
     * @return list<string>
     */
    private static function documentLines(string $text): array
    {
        $lines = \preg_split('/\r\n|\r|\n/', $text);

        return $lines === false ? [''] : $lines;
    }

    /**
     * @param list<string> $lines
     */
    private static function lineStart(array $lines, int $line): Position
    {
        if ($line < \count($lines)) {
            return new Position($line, 0);
        }

        // Only a deletion running past the last line gets here, which happens
        // when the document has no final terminator: the range then ends at
        // the end of its last line.
        $last = \count($lines) - 1;

        return new Position($last, self::utf16CodeUnits($lines[$last]));
    }

    private static function utf16CodeUnits(string $line): int
    {
        // LSP counts UTF-16 code units: 1 per BMP char, 2 per astral char.
        $astral = (int) \preg_match_all('/[^\x{0000}-\x{FFFF}]/u', $line);

        return \mb_strlen($line, 'UTF-8') + $astral;
    }
}
