<?php

namespace Balthild\PhpCsFixerLsp\Server;

use Amp\Promise;
use Balthild\PhpCsFixerLsp\Model\ExceptionInfo;
use Phpactor\LanguageServer\Core\Middleware\Middleware;
use Phpactor\LanguageServer\Core\Middleware\RequestHandler;
use Phpactor\LanguageServer\Core\Rpc\ErrorCodes;
use Phpactor\LanguageServer\Core\Rpc\Message;
use Phpactor\LanguageServer\Core\Rpc\RequestMessage;
use Phpactor\LanguageServer\Core\Rpc\ResponseError;
use Phpactor\LanguageServer\Core\Rpc\ResponseMessage;
use Psr\Log\LoggerInterface;

class ExceptionMiddleware implements Middleware
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @return Promise<?ResponseMessage>
     */
    public function process(Message $request, RequestHandler $handler): Promise
    {
        return \Amp\call(function () use ($request, $handler) {
            try {
                return yield $handler->handle($request);
            } catch (WorkerException $exception) {
                return $this->handleError($request, $exception->info);
            } catch (\Throwable $exception) {
                return $this->handleError($request, new ExceptionInfo($exception));
            }
        });
    }

    protected function handleError(Message $request, ExceptionInfo $info): ?ResponseMessage
    {
        $this->logger->error(\sprintf(
            "error handling %s (%s)\n%s",
            $request::class,
            json_encode($request),
            $info->details(),
        ));

        if (!$request instanceof RequestMessage) {
            return null;
        }

        return new ResponseMessage(
            $request->id,
            null,
            new ResponseError(
                ErrorCodes::InternalError,
                $info->description(),
            ),
        );
    }
}
