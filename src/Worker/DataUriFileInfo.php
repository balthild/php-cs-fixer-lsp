<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

/**
 * Copied and modified from PhpCsFixer\StdinFileInfo.
 * Originally MIT licensed.
 *
 * @mago-expect lint:too-many-methods
 */
final class DataUriFileInfo extends \SplFileInfo
{
    private string $uri;

    public function __construct(string $text)
    {
        parent::__construct(__FILE__);
        $this->uri = 'data:text/plain;base64,' . base64_encode($text);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getRealPath();
    }

    #[\Override]
    public function getRealPath(): string
    {
        return $this->uri;
    }

    #[\Override]
    public function getATime(): int
    {
        return 0;
    }

    #[\Override]
    public function getBasename($suffix = null): string
    {
        return $this->getFilename();
    }

    #[\Override]
    public function getCTime(): int
    {
        return 0;
    }

    #[\Override]
    public function getExtension(): string
    {
        return 'php';
    }

    #[\Override]
    public function getFileInfo($class = null): \SplFileInfo
    {
        throw new \BadMethodCallException(\sprintf('Method "%s" is not implemented.', __METHOD__));
    }

    #[\Override]
    public function getFilename(): string
    {
        return 'data-uri.php';
    }

    #[\Override]
    public function getGroup(): int
    {
        return 0;
    }

    #[\Override]
    public function getInode(): int
    {
        return 0;
    }

    #[\Override]
    public function getLinkTarget(): string
    {
        return '';
    }

    #[\Override]
    public function getMTime(): int
    {
        return 0;
    }

    #[\Override]
    public function getOwner(): int
    {
        return 0;
    }

    #[\Override]
    public function getPath(): string
    {
        return '';
    }

    #[\Override]
    public function getPathInfo($class = null): \SplFileInfo
    {
        throw new \BadMethodCallException(\sprintf('Method "%s" is not implemented.', __METHOD__));
    }

    #[\Override]
    public function getPathname(): string
    {
        return $this->getFilename();
    }

    #[\Override]
    public function getPerms(): int
    {
        return 0;
    }

    #[\Override]
    public function getSize(): int
    {
        return 0;
    }

    #[\Override]
    public function getType(): string
    {
        return 'file';
    }

    #[\Override]
    public function isDir(): bool
    {
        return false;
    }

    #[\Override]
    public function isExecutable(): bool
    {
        return false;
    }

    #[\Override]
    public function isFile(): bool
    {
        return true;
    }

    #[\Override]
    public function isLink(): bool
    {
        return false;
    }

    #[\Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[\Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[\Override]
    public function openFile($openMode = 'r', $useIncludePath = false, $context = null): \SplFileObject
    {
        throw new \BadMethodCallException(\sprintf('Method "%s" is not implemented.', __METHOD__));
    }
}
