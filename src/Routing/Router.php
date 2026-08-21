<?php

declare(strict_types=1);

namespace Micro\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Micro\Exception\MethodNotAllowedHttpException;
use Micro\Exception\NotFoundHttpException;
use Psr\Http\Server\MiddlewareInterface;
use function FastRoute\simpleDispatcher;

final class Router
{
    /**
     * @var list<array{method: string, path: string, handler: mixed, middlewares: list<MiddlewareInterface|class-string<MiddlewareInterface>>}>
     */
    private array $routes = [];

    private string $groupPrefix = '';

    /**
     * @var list<MiddlewareInterface|class-string<MiddlewareInterface>>
     */
    private array $groupMiddlewares = [];

    private ?Dispatcher $dispatcher = null;

    public function get(string $path, mixed $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route group with a common URL prefix and optional group middleware.
     *
     * @param string $prefix
     * @param callable(Router): void $callback
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middlewares
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): self
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $normalizedPrefix = '/' . trim($prefix, '/');
        if ($normalizedPrefix === '/') {
            $normalizedPrefix = '';
        }

        $this->groupPrefix = $previousPrefix . $normalizedPrefix;
        $this->groupMiddlewares = [...$previousMiddlewares, ...$middlewares];

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;

        return $this;
    }

    /**
     * Add a route with its method, path, and handler.
     */
    public function addRoute(string $method, string $path, mixed $handler): self
    {
        $trimmedPrefix = rtrim($this->groupPrefix, '/');
        $trimmedPath = '/' . ltrim($path, '/');
        if ($trimmedPath === '/' && $trimmedPrefix !== '') {
            $fullPath = $trimmedPrefix;
        } else {
            $fullPath = $trimmedPrefix . $trimmedPath;
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => $this->groupMiddlewares,
        ];
        $this->dispatcher = null;
        return $this;
    }

    /**
     * Match an HTTP method and URI path against registered routes.
     *
     * @throws NotFoundHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function match(string $method, string $path): RouteResult
    {
        $dispatcher = $this->getDispatcher();
        $routeInfo = $dispatcher->dispatch($method, $path);

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => throw new NotFoundHttpException("Route [{$method} {$path}] not found."),
            Dispatcher::METHOD_NOT_ALLOWED => throw new MethodNotAllowedHttpException(
                $routeInfo[1],
                "Method [{$method}] not allowed for [{$path}].",
            ),
            Dispatcher::FOUND => new RouteResult(
                status: Dispatcher::FOUND,
                handler: $routeInfo[1]['handler'],
                params: $routeInfo[2] ?? [],
                middlewares: $routeInfo[1]['middlewares'] ?? [],
            ),
            default => throw new NotFoundHttpException("Could not route [{$method} {$path}]."),
        };
    }

    /**
     * @return list<array{method: string, path: string, handler: mixed, middlewares: list<MiddlewareInterface|class-string<MiddlewareInterface>>}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function getDispatcher(): Dispatcher
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = simpleDispatcher(function (RouteCollector $r): void {
                foreach ($this->routes as $route) {
                    $r->addRoute($route['method'], $route['path'], [
                        'handler' => $route['handler'],
                        'middlewares' => $route['middlewares'],
                    ]);
                }
            });
        }

        return $this->dispatcher;
    }
}
