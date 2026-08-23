<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Middleware;

use Micro\Exception\BadRequestHttpException;
use Micro\Http\JsonResponse;
use Micro\Middleware\JsonBodyParserMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonBodyParserMiddlewareTest extends TestCase
{
    private JsonBodyParserMiddleware $middleware;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->middleware = new JsonBodyParserMiddleware();
        $this->factory = new Psr17Factory();
    }

    public function testParsesValidJsonBody(): void
    {
        $body = $this->factory->createStream(json_encode(['name' => 'John Doe', 'active' => true]));
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertSame(['name' => 'John Doe', 'active' => true], $handler->parsedBody);
    }

    public function testParsesNestedJson(): void
    {
        $payload = ['user' => ['id' => 1, 'roles' => ['admin', 'editor']]];
        $body = $this->factory->createStream(json_encode($payload));
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertSame($payload, $handler->parsedBody);
    }

    public function testParsesJsonApiContentTypeWithCharset(): void
    {
        $body = $this->factory->createStream(json_encode(['data' => ['type' => 'articles']]));
        $request = (new ServerRequest('POST', '/api/articles'))
            ->withHeader('Content-Type', 'application/vnd.api+json; charset=utf-8')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertSame(['data' => ['type' => 'articles']], $handler->parsedBody);
    }

    public function testMalformedJsonThrowsBadRequestHttpException(): void
    {
        $body = $this->factory->createStream('{ invalid json: true ');
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Malformed JSON payload:');

        $this->middleware->process($request, $handler);
    }

    public function testJsonScalarThrowsBadRequestHttpException(): void
    {
        $body = $this->factory->createStream('"plain json string"');
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Malformed JSON payload: Body must be a JSON object or array.');

        $this->middleware->process($request, $handler);
    }

    public function testEmptyBodySetsEmptyArray(): void
    {
        $body = $this->factory->createStream('');
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertSame([], $handler->parsedBody);
    }

    public function testWhitespaceBodySetsEmptyArray(): void
    {
        $body = $this->factory->createStream("   \n\t  ");
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertSame([], $handler->parsedBody);
    }

    public function testNonJsonRequestPassesThroughUnchanged(): void
    {
        $body = $this->factory->createStream('plain text data');
        $request = (new ServerRequest('POST', '/api/upload'))
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($body);

        $handler = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return JsonResponse::ok();
            }
        };

        $this->middleware->process($request, $handler);

        $this->assertNull($handler->parsedBody);
    }
}
