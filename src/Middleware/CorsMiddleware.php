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
     * @param int $maxAge Preflight cache duration in seconds.
     * @param bool $allowCredentials Whether to allow credentials (cookies, auth headers).
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-ID'],
        private int $maxAge = 86400,
        private bool $allowCredentials = true,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = trim($request->getHeaderLine('Origin'));

        $hasWildcard = in_array('*', $this->allowedOrigins, true);
        $isExplicitMatch = $origin !== '' && in_array($origin, $this->allowedOrigins, true);

        // Determine resolved Allow-Origin header and whether credentials can be permitted
        if ($isExplicitMatch) {
            $resolvedOrigin = $origin;
            $resolvedCredentials = $this->allowCredentials;
        } elseif ($hasWildcard) {
            $resolvedOrigin = '*';
            // Security constraint: Credentials MUST NOT be allowed when origin is wildcard '*'
            $resolvedCredentials = false;
        } else {
            $resolvedOrigin = '';
            $resolvedCredentials = false;
        }

        // Handle CORS Preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new Response(204);
            return $this->withCorsHeaders($response, $resolvedOrigin, $resolvedCredentials);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $resolvedOrigin, $resolvedCredentials);
    }

    private function withCorsHeaders(ResponseInterface $response, string $allowOrigin, bool $allowCredentials): ResponseInterface
    {
        if ($allowOrigin === '') {
            return $response;
        }

        $existingVary = $response->getHeaderLine('Vary');
        $varyHeaders = array_filter(array_map('trim', explode(',', $existingVary)));
        if (!in_array('Origin', $varyHeaders, true)) {
            $varyHeaders[] = 'Origin';
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withHeader('Vary', implode(', ', $varyHeaders));

        if ($allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
