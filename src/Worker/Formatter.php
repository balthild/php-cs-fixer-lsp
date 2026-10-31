<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Balthild\PhpCsFixerLsp\Helpers;
use Phpactor\LanguageServerProtocol\TextEdit;
use PhpCsFixer\Cache\NullCacheManager;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\Progress\ProgressOutputType;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use PhpCsFixer\Runner\Runner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;

class Formatter
{
    protected readonly LoggerInterface $logger;

    protected readonly CapturingDiffer $differ;

    protected readonly Runner $runner;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->differ = new CapturingDiffer();
        $this->runner = $this->createRunner();
    }

    /**
     * @return ?list<TextEdit>
     */
    public function format(string $text): ?array
    {
        $this->differ->reset();

        if ($this->supportsDataUri()) {
            // by default, data URIs are accepted by `file_get_contents`.
            $this->formatWithDataUri($text);
        } else {
            // due to the limitations of PHP-CS-Fixer's implementation, we have
            // to provide it a path to the code.
            $this->formatWithTempFile($text);
        }

        $old = $this->differ->getOriginal();
        $new = $this->differ->getFormatted();

        if ($old === null || $new === null) {
            return null;
        }

        return TextEditBuilder::fromTexts($old, $new);
    }

    protected function supportsDataUri(): bool
    {
        return \filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOLEAN);
    }

    protected function formatWithDataUri(string $text): void
    {
        $this->logger->debug('formatting code');
        $this->callFixer(new DataUriFileInfo($text));
    }

    protected function formatWithTempFile(string $text): void
    {
        $temp = \tempnam(\sys_get_temp_dir(), 'php-cs-fixer-lsp-');
        if (!$temp) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        try {
            $this->logger->debug("writing code to temporary file {$temp}");
            \file_put_contents($temp, $text);

            $this->logger->debug("formatting temporary file {$temp}");
            $this->callFixer(new \SplFileInfo($temp));
        } finally {
            $this->logger->debug("deleting temporary file {$temp}");
            \unlink($temp);
        }
    }

    protected function callFixer(\SplFileInfo $file): void
    {
        $this->runner->setFileIterator(new \ArrayIterator([$file]));
        $this->runner->fix();
    }

    /**
     * @see \PhpCsFixer\Console\Command\WorkerCommand::createRunner()
     */
    protected function createRunner(): Runner
    {
        $resolver = Helpers::getPhpCsFixerResolver([
            'diff' => true,
            'sequential' => true,
            'dry-run' => true,
            'using-cache' => ConfigurationResolver::BOOL_NO,
            'show-progress' => ProgressOutputType::NONE,
        ]);

        return new Runner(
            fileIterator: null,
            fixers: $resolver->getFixers(),
            differ: $this->differ,
            eventDispatcher: null,
            errorsManager: new ErrorsManager(),
            linter: $resolver->getLinter(),
            isDryRun: $resolver->isDryRun(),
            cacheManager: new NullCacheManager(),
            directory: $resolver->getDirectory(),
            stopOnViolation: $resolver->shouldStopOnViolation(),
            parallelConfig: ParallelConfigFactory::sequential(),
            input: new ArrayInput([]),
            configFile: $resolver->getConfigFile(),
            ruleCustomisationPolicy: $resolver->getRuleCustomisationPolicy(),
        );
    }
}
