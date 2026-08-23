<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Exception;

use Exception;
use Micro\Exception\HttpException;
use Micro\Exception\UnprocessableEntityHttpException;
use Micro\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UnprocessableEntityHttpExceptionTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $exception = new UnprocessableEntityHttpException();

        $this->assertInstanceOf(HttpException::class, $exception);
        $this->assertSame(422, $exception->getStatusCode());
        $this->assertSame('Unprocessable Entity', $exception->getMessage());
        $this->assertSame([], $exception->getHeaders());
        $this->assertNull($exception->getPrevious());
    }

    public function testCustomValues(): void
    {
        $prev = new Exception('Validation error');
        $exception = new UnprocessableEntityHttpException(
            message: 'Invalid payload provided.',
            headers: ['X-Reason' => 'ValidationFailed'],
            previous: $prev,
        );

        $this->assertSame(422, $exception->getStatusCode());
        $this->assertSame('Invalid payload provided.', $exception->getMessage());
        $this->assertSame(['X-Reason' => 'ValidationFailed'], $exception->getHeaders());
        $this->assertSame($prev, $exception->getPrevious());
    }

    public function testErrorHandlerIntegration(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: false);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new UnprocessableEntityHttpException(
                    message: 'Missing required field: name',
                    headers: ['X-Custom-Validation' => 'Failed'],
                );
            }
        };

        $request = new ServerRequest('POST', '/api/users');
        $response = $middleware->process($request, $handler);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Failed', $response->getHeaderLine('X-Custom-Validation'));

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(422, $payload['error']['code']);
        $this->assertSame('Missing required field: name', $payload['error']['message']);
    }
}
