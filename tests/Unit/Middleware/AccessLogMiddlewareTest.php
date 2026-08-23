<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Middleware;

use Exception;
use Micro\Exception\NotFoundHttpException;
use Micro\Middleware\AccessLogMiddleware;
use Micro\Middleware\RequestIdMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class AccessLogMiddlewareTest extends TestCase
{
    private function createHandler(int $statusCode = 200): RequestHandlerInterface
    {
        return new class ($statusCode) implements RequestHandlerInterface {
            public function __construct(private int $statusCode) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response($this->statusCode);
            }
        };
    }

    public function testLogs200AtInfoLevelWithCorrectContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'HTTP request completed',
                $this->callback(function (array $context): bool {
                    return $context['request_id'] === 'req-123'
                        && $context['method'] === 'GET'
                        && $context['path'] === '/api/items'
                        && $context['status'] === 200
                        && is_float($context['duration_ms'])
                        && $context['duration_ms'] >= 0.0;
                })
            );

        $middleware = new AccessLogMiddleware($logger);
        $request = (new ServerRequest('GET', '/api/items'))
            ->withAttribute(RequestIdMiddleware::ATTRIBUTE_NAME, 'req-123');

        $response = $middleware->process($request, $this->createHandler(200));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLogs201AtInfoLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === 201));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('POST', '/api/items');
        $response = $middleware->process($request, $this->createHandler(201));
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testLogsRedirectAtInfoLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === 302));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('GET', '/old-url');
        $response = $middleware->process($request, $this->createHandler(302));
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testLogs4xxStatusesAtWarningLevel(): void
    {
        $statuses = [400, 401, 403, 404, 409, 422];

        foreach ($statuses as $status) {
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects($this->once())
                ->method('warning')
                ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === $status));

            $middleware = new AccessLogMiddleware($logger);
            $request = new ServerRequest('GET', '/test');
            $response = $middleware->process($request, $this->createHandler($status));
            $this->assertSame($status, $response->getStatusCode());
        }
    }

    public function testLogs500AtErrorLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === 500));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->createHandler(500));
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testPassesThroughWithNullLogger(): void
    {
        $middleware = new AccessLogMiddleware(null);
        $request = new ServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->createHandler(200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLoggerFailureDoesNotBreakResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willThrowException(new RuntimeException('Disk full or stream closed'));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->createHandler(200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDownstreamExceptionIsLoggedAndRethrown(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === 500));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('GET', '/crash');

        $failingHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new Exception('Unexpected failure');
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected failure');

        $middleware->process($request, $failingHandler);
    }

    public function testDownstreamHttpExceptionIsLoggedWithExactStatusCode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('HTTP request completed', $this->callback(fn(array $c) => $c['status'] === 404));

        $middleware = new AccessLogMiddleware($logger);
        $request = new ServerRequest('GET', '/missing');

        $failingHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new NotFoundHttpException('Resource not found');
            }
        };

        $this->expectException(NotFoundHttpException::class);
        $middleware->process($request, $failingHandler);
    }
}
