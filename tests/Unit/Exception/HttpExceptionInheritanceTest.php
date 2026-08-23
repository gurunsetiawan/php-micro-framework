<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Exception;

use Micro\Exception\BadRequestHttpException;
use Micro\Exception\ConflictHttpException;
use Micro\Exception\ForbiddenHttpException;
use Micro\Exception\MethodNotAllowedHttpException;
use Micro\Exception\NotFoundHttpException;
use Micro\Exception\UnauthorizedHttpException;
use Micro\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpExceptionInheritanceTest extends TestCase
{
    public function testCanSubclassNotFoundHttpException(): void
    {
        $customException = new class ('Resource [user:42] not found.') extends NotFoundHttpException {
        };

        $this->assertInstanceOf(NotFoundHttpException::class, $customException);
        $this->assertSame(404, $customException->getStatusCode());
        $this->assertSame('Resource [user:42] not found.', $customException->getMessage());
    }

    public function testCanSubclassConflictHttpException(): void
    {
        $customException = new class ('Duplicate username.') extends ConflictHttpException {
        };

        $this->assertInstanceOf(ConflictHttpException::class, $customException);
        $this->assertSame(409, $customException->getStatusCode());
        $this->assertSame('Duplicate username.', $customException->getMessage());
    }

    public function testCanSubclassBadRequestHttpException(): void
    {
        $customException = new class ('Invalid date format.') extends BadRequestHttpException {
        };

        $this->assertInstanceOf(BadRequestHttpException::class, $customException);
        $this->assertSame(400, $customException->getStatusCode());
    }

    public function testCanSubclassForbiddenHttpException(): void
    {
        $customException = new class ('Access denied.') extends ForbiddenHttpException {
        };

        $this->assertInstanceOf(ForbiddenHttpException::class, $customException);
        $this->assertSame(403, $customException->getStatusCode());
    }

    public function testCanSubclassUnauthorizedHttpException(): void
    {
        $customException = new class ('Bearer token missing.') extends UnauthorizedHttpException {
        };

        $this->assertInstanceOf(UnauthorizedHttpException::class, $customException);
        $this->assertSame(401, $customException->getStatusCode());
    }

    public function testCanSubclassMethodNotAllowedHttpException(): void
    {
        $customException = new class (['GET', 'POST']) extends MethodNotAllowedHttpException {
        };

        $this->assertInstanceOf(MethodNotAllowedHttpException::class, $customException);
        $this->assertSame(405, $customException->getStatusCode());
        $this->assertSame(['Allow' => 'GET, POST'], $customException->getHeaders());
    }

    public function testCanSubclassUnprocessableEntityHttpException(): void
    {
        $customException = new class ('Invalid input payload.') extends \Micro\Exception\UnprocessableEntityHttpException {
        };

        $this->assertInstanceOf(\Micro\Exception\UnprocessableEntityHttpException::class, $customException);
        $this->assertSame(422, $customException->getStatusCode());
        $this->assertSame('Invalid input payload.', $customException->getMessage());
    }

    public function testErrorHandlerRendersSubclassedHttpExceptions(): void
    {
        $errorHandler = new ErrorHandlerMiddleware(debug: false);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new class ('Item out of stock.') extends ConflictHttpException {
                };
            }
        };

        $request = new ServerRequest('POST', '/api/orders');
        $response = $errorHandler->process($request, $handler);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(409, $body['error']['code']);
        $this->assertSame('Item out of stock.', $body['error']['message']);
    }
}
