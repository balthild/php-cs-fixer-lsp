<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Parser;

/**
 * Copied and modified from \Phpactor\Diff\DiffToTextEditsConverter.
 * Originally MIT licensed.
 */
class DiffUtils
{
    /**
     * @return TextEdit[]
     */
    public static function diffToTextEdits(string $before, string $after): array
    {
        $options = ['fromFile' => 'before.php', 'toFile' => 'after.php'];
        $differ = new Differ(new StrictUnifiedDiffOutputBuilder($options));
        $parser = new Parser();

        $diffs = $parser->parse($differ->diff($before, $after));

        $edits = [];
        foreach ($diffs as $diff) {
            foreach ($diff->chunks() as $chunk) {
                $consumer = new DiffLinesConsumer($chunk);

                while ($consumer->current()) {
                    if ($consumer->eatUnchanged()) {
                        continue;
                    }

                    $startLine = $consumer->getOrigLine() - 1;

                    if (($added = $consumer->eatAdded()) !== null) {
                        $edits[] = new TextEdit(
                            new Range(
                                new Position($startLine, 0),
                                new Position($startLine, 0),
                            ),
                            self::linesToString($added),
                        );
                    }

                    if (($removed = $consumer->eatRemoved()) !== null) {
                        $added = $consumer->eatAdded();

                        $edits[] = new TextEdit(
                            new Range(
                                new Position($startLine, 0),
                                new Position($startLine + \count($removed), 0),
                            ),
                            self::linesToString($added),
                        );
                    }
                }
            }
        }

        return $edits;
    }

    /**
     * @param  Line[]|null $lines
     */
    protected static function linesToString(?array $lines): string
    {
        if ($lines === null || \count($lines) === 0) {
            return '';
        }

        $contents = \array_map(static fn (Line $line) => $line->content(), $lines);

        return \implode("\n", $contents) . "\n";
    }
}
