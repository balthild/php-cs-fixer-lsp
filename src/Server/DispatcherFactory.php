<?php

declare(strict_types=1);

namespace Balthild\PhpCsFixerLsp\Server;

use Balthild\PhpCsFixerLsp\Application;
use Balthild\PhpCsFixerLsp\Model\ServerOptions;
use Phpactor\LanguageServer\Adapter\Psr\AggregateEventDispatcher;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\ChainArgumentResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\LanguageSeverProtocolParamsResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\PassThroughArgumentResolver;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher\MiddlewareDispatcher;
use Phpactor\LanguageServer\Core\Dispatcher\DispatcherFactory as DispatcherFactoryInterface;
use Phpactor\LanguageServer\Core\Handler\HandlerMethodRunner;
use Phpactor\LanguageServer\Core\Handler\Handlers;
use Phpactor\LanguageServer\Core\Server\Transmitter\MessageTransmitter;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Handler\TextDocument\FormattingHandler;
use Phpactor\LanguageServer\Handler\TextDocument\TextDocumentHandler;
use Phpactor\LanguageServer\Listener\WorkspaceListener;
use Phpactor\LanguageServer\Middleware\CancellationMiddleware;
use Phpactor\LanguageServer\Middleware\ErrorHandlingMiddleware;
use Phpactor\LanguageServer\Middleware\HandlerMiddleware;
use Phpactor\LanguageServer\Middleware\InitializeMiddleware;
use Phpactor\LanguageServer\Middleware\ShutdownMiddleware;
use Phpactor\LanguageServerProtocol\InitializeParams;
use Psr\Log\LoggerInterface;

class DispatcherFactory implements DispatcherFactoryInterface
{
    public function __construct(
        protected ServerOptions $options,
        protected LoggerInterface $logger,
    ) {}

    public function create(MessageTransmitter $transmitter, InitializeParams $params): Dispatcher
    {
        $workers = new WorkerPool($this->logger, $this->options);
        $formatter = new Formatter($this->logger, $workers);
        $workspace = new Workspace();

        $dispatcher = new AggregateEventDispatcher(
            $workers,
            new WorkspaceListener($workspace),
        );

        $handlers = new Handlers(
            new TextDocumentHandler($dispatcher),
            new ConfigurationHandler($this->logger),
            new FormattingHandler($workspace, $formatter),
            new TraceHandler($this->logger),
        );

        $runner = new HandlerMethodRunner(
            $handlers,
            new ChainArgumentResolver(
                new LanguageSeverProtocolParamsResolver(),
                new PassThroughArgumentResolver(),
            ),
        );

        return new MiddlewareDispatcher(
            new ErrorHandlingMiddleware($this->logger),
            new InitializeMiddleware($handlers, $dispatcher, [
                'name' => Application::name(),
                'version' => Application::version(),
            ]),
            new ShutdownMiddleware($dispatcher),
            new CancellationMiddleware($runner),
            new HandlerMiddleware($runner),
        );
    }
}
