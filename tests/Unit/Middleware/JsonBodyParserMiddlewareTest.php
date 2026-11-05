<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Middleware;

use Micro\Exception\BadRequestHttpException;
use Micro\Exception\FrameworkException;
use Micro\Exception\PayloadTooLargeHttpException;
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

    public function testThrowsPayloadTooLargeWhenContentLengthHeaderExceedsLimit(): void
    {
        $middleware = new JsonBodyParserMiddleware(maxBodySizeBytes: 1024); // 1 KB limit
        $body = $this->factory->createStream('{"data": "some content"}');

        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', '2048') // Claims 2 KB
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(PayloadTooLargeHttpException::class);
        $this->expectExceptionMessage('Payload too large: Content-Length of 2048 bytes exceeds maximum limit of 1024 bytes.');

        $middleware->process($request, $handler);
    }

    public function testThrowsPayloadTooLargeWhenStreamSizeExceedsLimit(): void
    {
        $middleware = new JsonBodyParserMiddleware(maxBodySizeBytes: 50);
        $largePayload = json_encode(['data' => str_repeat('A', 100)]);
        $body = $this->factory->createStream($largePayload);

        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(PayloadTooLargeHttpException::class);
        $this->expectExceptionMessage('exceeds maximum limit of 50 bytes.');

        $middleware->process($request, $handler);
    }

    public function testThrowsPayloadTooLargeWhenStreamWithoutKnownSizeExceedsLimit(): void
    {
        $middleware = new JsonBodyParserMiddleware(maxBodySizeBytes: 30);

        // Create stream with unknown size by wrapping php://temp without stat size
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, json_encode(['data' => str_repeat('X', 50)]));
        rewind($resource);

        $stream = new class ($resource) implements \Psr\Http\Message\StreamInterface {
            public function __construct(private $handle) {}
            public function __toString(): string { return ''; }
            public function close(): void { fclose($this->handle); }
            public function detach() { return null; }
            public function getSize(): ?int { return null; } // Unknown size
            public function tell(): int { return ftell($this->handle); }
            public function eof(): bool { return feof($this->handle); }
            public function isSeekable(): bool { return true; }
            public function seek(int $offset, int $whence = SEEK_SET): void { fseek($this->handle, $offset, $whence); }
            public function rewind(): void { fseek($this->handle, 0); }
            public function isWritable(): bool { return false; }
            public function write(string $string): int { return 0; }
            public function isReadable(): bool { return true; }
            public function read(int $length): string { return fread($this->handle, $length); }
            public function getContents(): string { return ''; }
            public function getMetadata(?string $key = null): mixed { return null; }
        };

        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(PayloadTooLargeHttpException::class);
        $this->expectExceptionMessage('exceeds maximum limit of 30 bytes.');

        $middleware->process($request, $handler);
    }

    public function testCustomMaxBodySizeAllowsLegitimatePayload(): void
    {
        $middleware = new JsonBodyParserMiddleware(maxBodySizeBytes: 10 * 1024); // 10 KB
        $payload = ['data' => str_repeat('B', 2000)];
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

        $middleware->process($request, $handler);

        $this->assertSame($payload, $handler->parsedBody);
    }

    public function testNegativeOrZeroMaxBodySizeThrowsFrameworkException(): void
    {
        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('maxBodySizeBytes must be greater than zero.');

        new JsonBodyParserMiddleware(maxBodySizeBytes: 0);
    }

    public function testRejectsNegativeContentLengthHeader(): void
    {
        $body = $this->factory->createStream('{"data": "test"}');
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', '-1')
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid Content-Length header.');

        $this->middleware->process($request, $handler);
    }

    public function testRejectsConflictingMultipleContentLengthHeaders(): void
    {
        $body = $this->factory->createStream('{"data": "test"}');
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withAddedHeader('Content-Length', '100')
            ->withAddedHeader('Content-Length', '200')
            ->withBody($body);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Conflicting or invalid Content-Length headers.');

        $this->middleware->process($request, $handler);
    }

    public function testAcceptsIdenticalMultipleContentLengthHeaders(): void
    {
        $payload = ['data' => 'test'];
        $json = json_encode($payload);
        $len = (string) strlen($json);

        $body = $this->factory->createStream($json);
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withAddedHeader('Content-Length', $len)
            ->withAddedHeader('Content-Length', $len)
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
}
