<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Middleware;

use Micro\Middleware\RequestIdMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddlewareTest extends TestCase
{
    private RequestIdMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RequestIdMiddleware();
    }

    private function createHandler(?string &$capturedAttribute): RequestHandlerInterface
    {
        return new class ($capturedAttribute) implements RequestHandlerInterface {
            public function __construct(private ?string &$capturedAttribute) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedAttribute = $request->getAttribute(RequestIdMiddleware::ATTRIBUTE_NAME);
                return new Response(200);
            }
        };
    }

    public function testGeneratesIdWhenHeaderIsMissing(): void
    {
        $captured = null;
        $request = new ServerRequest('GET', '/api/test');
        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotNull($captured);
        $this->assertSame(32, strlen($captured));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $captured);
        $this->assertSame($captured, $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME));
    }

    public function testPreservesValidIdWithAllowedCharacters(): void
    {
        $validId = 'req_trace.123-ABC';
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $validId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertSame($validId, $captured);
        $this->assertSame($validId, $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME));
    }

    public function testPreservesValidIdLength1(): void
    {
        $validId = 'a';
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $validId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertSame($validId, $captured);
        $this->assertSame($validId, $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME));
    }

    public function testPreservesValidIdLength64(): void
    {
        $validId = str_repeat('a', 64);
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $validId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertSame($validId, $captured);
        $this->assertSame($validId, $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME));
    }

    public function testReplacesEmptyStringId(): void
    {
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, '');

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotSame('', $captured);
        $this->assertSame(32, strlen($captured));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $captured);
    }

    public function testReplacesIdExceeding64Characters(): void
    {
        $longId = str_repeat('x', 65);
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $longId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotSame($longId, $captured);
        $this->assertSame(32, strlen($captured));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $captured);
    }

    public function testReplacesIdWithSpaces(): void
    {
        $invalidId = 'req id with spaces';
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $invalidId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotSame($invalidId, $captured);
        $this->assertSame(32, strlen($captured));
    }

    public function testReplacesIdWithSpecialSymbols(): void
    {
        $invalidId = 'req-id;injection:evil<script>';
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $invalidId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotSame($invalidId, $captured);
        $this->assertSame(32, strlen($captured));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $captured);
    }

    public function testReplacesIdWithColonsAndSlashes(): void
    {
        $invalidId = 'req/id:test=123';
        $captured = null;
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, $invalidId);

        $response = $this->middleware->process($request, $this->createHandler($captured));

        $this->assertNotSame($invalidId, $captured);
        $this->assertSame(32, strlen($captured));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $captured);
    }
}
