<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Amp\Failure;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Promise;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\IPC\FailingResponse;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\Progress\ProgressOutputType;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;
use Psr\Log\LoggerInterface;

class IpcMainLoop
{
    protected LoggerInterface $logger;

    protected Runner $runner;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->runner = $this->createRunner();
    }

    public function run(): void
    {
        Loop::run(function () {
            $channel = new ChannelledSocket(
                \fopen('php://stdin', 'r'),
                \fopen('php://stdout', 'w'),
            );

            // @mago-expect lint:no-assign-in-condition
            while ($request = yield $channel->receive()) {
                try {
                    $response = yield match (\get_class($request)) {
                        FormatRequest::class => $this->format($request),
                        default => $this->unknown($request),
                    };
                } catch (\Throwable $exception) {
                    $response = new FailingResponse($exception);
                }

                yield $channel->send($response);
            }

            $channel->close();
            Loop::stop();
        });
    }

    /**
     * @return Promise<FormatResponse>
     */
    public function format(FormatRequest $request): Promise
    {
        return \Amp\call(function () use ($request) {
            $file = match (true) {
                $request->text !== null => new DataUriFileInfo($request->text),
                $request->path !== null => new \SplFileInfo($request->path),
                default => throw new \RuntimeException('Either path or text must be provided'),
            };

            $this->runner->setFileIterator(new \ArrayIterator([$file]));

            $results = $this->runner->fix();
            $info = array_pop($results);
            if ($info === null) {
                return new FormatResponse(null);
            }

            return new FormatResponse(DiffUtils::diffToTextEdits($info['diff']));
        });
    }

    public function unknown(mixed $request): Promise
    {
        $type = match (true) {
            \is_array($request) => 'array',
            \is_object($request) => \get_class($request),
            default => \gettype($request),
        };

        return new Failure(new \RuntimeException("Unknown request: {$type}"));
    }

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
            differ: $resolver->getDiffer(),
            eventDispatcher: null,
            errorsManager: new ErrorsManager(),
            linter: $resolver->getLinter(),
            isDryRun: $resolver->isDryRun(),
            cacheManager: $resolver->getCacheManager(),
            directory: $resolver->getDirectory(),
            stopOnViolation: $resolver->shouldStopOnViolation(),
            parallelConfig: $resolver->getParallelConfig(),
            input: null,
            configFile: $resolver->getConfigFile(),
            ruleCustomisationPolicy: $resolver->getRuleCustomisationPolicy(),
        );
    }
}
