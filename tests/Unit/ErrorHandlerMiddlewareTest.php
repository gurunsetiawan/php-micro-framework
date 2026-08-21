<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Exception;
use Micro\Exception\NotFoundHttpException;
use Micro\Exception\UnauthorizedHttpException;
use Micro\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testHandlesHttpException(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: false);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new NotFoundHttpException('Item 123 was not found.');
            }
        };

        $request = new ServerRequest('GET', '/items/123');
        $response = $middleware->process($request, $handler);

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $payload['error']['code']);
        $this->assertSame('Item 123 was not found.', $payload['error']['message']);
    }

    public function testHandlesUnauthorizedHttpExceptionWithHeader(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: false);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new UnauthorizedHttpException('Token expired.');
            }
        };

        $request = new ServerRequest('GET', '/protected');
        $response = $middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testProductionSanitizes500Errors(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: false);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new Exception('Database password leaked: secret123');
            }
        };

        $request = new ServerRequest('GET', '/db');
        $response = $middleware->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(500, $payload['error']['code']);
        $this->assertSame('Internal Server Error', $payload['error']['message']);
        $this->assertArrayNotHasKey('trace', $payload['error']);
        $this->assertArrayNotHasKey('file', $payload['error']);
    }

    public function testDebugModeExposesExceptionTrace(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: true);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new Exception('Debug detailed failure');
            }
        };

        $request = new ServerRequest('GET', '/debug');
        $response = $middleware->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(500, $payload['error']['code']);
        $this->assertSame('Debug detailed failure', $payload['error']['message']);
        $this->assertArrayHasKey('trace', $payload['error']);
        $this->assertArrayHasKey('file', $payload['error']);
        $this->assertArrayHasKey('line', $payload['error']);
    }

    public function testLoggerInvocation(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('User not found');

        $middleware = new ErrorHandlerMiddleware(debug: false, logger: $logger);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new NotFoundHttpException('User not found');
            }
        };

        $request = new ServerRequest('GET', '/users/99');
        $middleware->process($request, $handler);
    }
}
