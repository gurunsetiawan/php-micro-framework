<?php

declare(strict_types=1);

namespace Micro\Middleware;

use Micro\Exception\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class AccessLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = hrtime(true);

        try {
            $response = $handler->handle($request);
        } catch (Throwable $e) {
            $durationMs = round((hrtime(true) - $startTime) / 1e6, 2);
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $this->emitLog($status, $request, $durationMs);

            throw $e;
        }

        $durationMs = round((hrtime(true) - $startTime) / 1e6, 2);
        $this->emitLog($response->getStatusCode(), $request, $durationMs);

        return $response;
    }

    private function emitLog(int $status, ServerRequestInterface $request, float $durationMs): void
    {
        if ($this->logger === null) {
            return;
        }

        $requestId = (string) ($request->getAttribute(RequestIdMiddleware::ATTRIBUTE_NAME) ?: 'unknown');

        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'duration_ms' => $durationMs,
        ];

        try {
            if ($status >= 500) {
                $this->logger->error('HTTP request completed', $context);
            } elseif ($status >= 400) {
                $this->logger->warning('HTTP request completed', $context);
            } else {
                $this->logger->info('HTTP request completed', $context);
            }
        } catch (Throwable) {
            // Suppress logger failures to protect application request flow
        }
    }
}
