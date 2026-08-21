<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Micro\Http\JsonResponse;
use Micro\Middleware\CorsMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    public function testOptionsPreflightReturns204WithHeaders(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: ['http://localhost:5173'],
            allowedMethods: ['GET', 'POST', 'OPTIONS'],
            allowedHeaders: ['Authorization', 'Content-Type'],
            maxAge: 3600,
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return JsonResponse::ok(['msg' => 'should not be called']);
            }
        };

        $request = (new ServerRequest('OPTIONS', '/api/v1/auth/login'))
            ->withHeader('Origin', 'http://localhost:5173');

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Authorization, Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('3600', $response->getHeaderLine('Access-Control-Max-Age'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testNormalRequestDecoratedWithCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['*']);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return JsonResponse::ok(['data' => 'test']);
            }
        };

        $request = (new ServerRequest('GET', '/api/v1/items'))
            ->withHeader('Origin', 'https://app.example.com');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
