<?php

declare(strict_types=1);

namespace Micro\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
        private array $allowedHeaders = ['Authorization', 'Content-Type', 'Accept', 'Origin', 'X-Requested-With'],
        private int $maxAge = 86400,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $allowOrigin = in_array('*', $this->allowedOrigins, true)
            ? ($origin !== '' ? $origin : '*')
            : (in_array($origin, $this->allowedOrigins, true) ? $origin : '');

        // Handle CORS Preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new Response(204);
            return $this->withCorsHeaders($response, $allowOrigin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $allowOrigin);
    }

    private function withCorsHeaders(ResponseInterface $response, string $allowOrigin): ResponseInterface
    {
        if ($allowOrigin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
