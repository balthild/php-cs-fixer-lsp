<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use PhpCsFixer\Differ\DifferInterface;

/**
 * Captures the formatted code instead of rendering a diff.
 *
 * PHP-CS-Fixer always hands the final code to its differ. Rendering that into
 * unified diff text and parsing it back is lossy, so the runner is given this
 * differ and the code is taken directly from it.
 */
final class CapturingDiffer implements DifferInterface
{
    private ?string $original = null;
    private ?string $formatted = null;

    #[\Override]
    public function diff(string $old, string $new, ?\SplFileInfo $file = null): string
    {
        $this->original = $old;
        $this->formatted = $new;

        return '';
    }

    public function reset(): void
    {
        $this->original = null;
        $this->formatted = null;
    }

    public function getOriginal(): ?string
    {
        return $this->original;
    }

    public function getFormatted(): ?string
    {
        return $this->formatted;
    }
}
