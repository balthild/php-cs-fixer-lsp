<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
use PhpCsFixer\Cache\NullCacheManager;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\Progress\ProgressOutputType;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use PhpCsFixer\Runner\Runner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;

class Worker
{
    protected LoggerInterface $logger;

    protected CapturingDiffer $differ;

    protected Runner $runner;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->differ = new CapturingDiffer();
        $this->runner = $this->createRunner();
    }

    public function dispatch(mixed $request): Response|\Throwable
    {
        $type = \is_object($request) ? $request::class : \gettype($request);

        try {
            return match ($type) {
                FormatRequest::class => $this->format($request),
                default => new \RuntimeException("Unknown request type: {$type}"),
            };
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    public function format(FormatRequest $request): FormatResponse
    {
        $this->differ->reset();

        if (\filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOLEAN)) {
            // by default, data URIs are accepted by `file_get_contents`.
            $this->formatWithDataUri($request->text);
        } else {
            // due to the limitations of PHP-CS-Fixer's implementation, we have
            // to provide it a path to the code.
            $this->formatWithTempFile($request->text);
        }

        $old = $this->differ->getOriginal();
        $new = $this->differ->getFormatted();

        if ($old === null || $new === null) {
            return new FormatResponse(null);
        }

        return new FormatResponse(TextEditBuilder::fromTexts($old, $new));
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
