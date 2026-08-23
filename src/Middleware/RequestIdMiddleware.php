<?php

declare(strict_types=1);

namespace Micro\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RequestIdMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE_NAME = 'request_id';
    public const string HEADER_NAME = 'X-Request-ID';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $incomingId = $request->getHeaderLine(self::HEADER_NAME);

        if ($this->isValidRequestId($incomingId)) {
            $requestId = $incomingId;
        } else {
            $requestId = bin2hex(random_bytes(16));
        }

        $requestWithAttribute = $request->withAttribute(self::ATTRIBUTE_NAME, $requestId);

        $response = $handler->handle($requestWithAttribute);

        return $response->withHeader(self::HEADER_NAME, $requestId);
    }

    private function isValidRequestId(string $id): bool
    {
        $len = strlen($id);
        if ($len === 0 || $len > 64) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_.-]+$/', $id);
    }
}
