<?php

declare(strict_types=1);

namespace Micro\Middleware;

use Micro\Exception\HttpException;
use Micro\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private bool $debug = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            $this->logger?->warning($e->getMessage(), [
                'status' => $e->getStatusCode(),
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ]);

            return JsonResponse::create(
                data: [
                    'error' => [
                        'code' => $e->getStatusCode(),
                        'message' => $e->getMessage(),
                    ],
                ],
                statusCode: $e->getStatusCode(),
                headers: $e->getHeaders(),
            );
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage(), [
                'exception' => $e,
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ]);

            $payload = [
                'error' => [
                    'code' => 500,
                    'message' => $this->debug ? $e->getMessage() : 'Internal Server Error',
                ],
            ];

            if ($this->debug) {
                $payload['error']['file'] = $e->getFile();
                $payload['error']['line'] = $e->getLine();
                $payload['error']['trace'] = explode("\n", $e->getTraceAsString());
            }

            return JsonResponse::create($payload, 500);
        }
    }
}
