<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Worker;

use Amp\Loop;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Promise;
use Balthild\PhpCsFixerLsp\Helpers;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatRequest;
use Balthild\PhpCsFixerLsp\Model\IPC\FormatResponse;
use Balthild\PhpCsFixerLsp\Model\IPC\Response;
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
                $response = yield $this->handle($request);
                yield $channel->send($response);
            }

            $channel->close();
            Loop::stop();
        });
    }

    /**
     * @return Promise<Response|ExceptionInfo>
     */
    public function handle(mixed $request): Promise
    {
        return \Amp\call(function () use ($request) {
            $type = \is_object($request) ? $request::class : \gettype($request);

            try {
                return match ($type) {
                    FormatRequest::class => yield $this->format($request),
                    default => throw new \RuntimeException("Unknown request type: {$type}"),
                };
            } catch (\Throwable $exception) {
                return new ExceptionInfo($exception);
            }
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
