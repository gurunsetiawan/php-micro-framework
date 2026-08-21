<?php

declare(strict_types=1);

namespace Micro\Routing;

use Psr\Http\Server\MiddlewareInterface;

final readonly class RouteResult
{
    /**
     * @param int $status (Dispatcher::FOUND, NOT_FOUND, METHOD_NOT_ALLOWED)
     * @param mixed $handler
     * @param array<string, string> $params
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middlewares
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public int $status,
        public mixed $handler = null,
        public array $params = [],
        public array $middlewares = [],
        public array $allowedMethods = [],
    ) {
    }
}
