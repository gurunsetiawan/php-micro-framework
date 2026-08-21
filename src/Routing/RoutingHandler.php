<?php

declare(strict_types=1);

namespace Micro\Routing;

use Closure;
use Micro\Exception\FrameworkException;
use Micro\Http\JsonResponse;
use Micro\Middleware\MiddlewarePipeline;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RoutingHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private ?ContainerInterface $container = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = rawurldecode($request->getUri()->getPath());

        $routeResult = $this->router->match($method, $path);

        if ($routeResult->middlewares === []) {
            return $this->invokeHandler($routeResult->handler, $request, $routeResult->params);
        }

        // Execute Group Middlewares in order before reaching the handler
        $actionHandler = new class ($this, $routeResult->handler, $routeResult->params) implements RequestHandlerInterface {
            public function __construct(
                private readonly RoutingHandler $routingHandler,
                private readonly mixed $handler,
                private readonly array $params,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->routingHandler->invokeHandler($this->handler, $request, $this->params);
            }
        };

        $pipeline = new MiddlewarePipeline($actionHandler, $this->container);
        foreach ($routeResult->middlewares as $middleware) {
            $pipeline->add($middleware);
        }

        return $pipeline->handle($request);
    }

    /**
     * @param array<string, string> $params
     */
    public function invokeHandler(mixed $handler, ServerRequestInterface $request, array $params): ResponseInterface
    {
        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        if ($handler instanceof Closure || is_callable($handler)) {
            $result = $handler($request, $params);
            return $this->toResponse($result);
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            $controller = $this->container !== null && $this->container->has($class)
                ? $this->container->get($class)
                : new $class();

            if (!method_exists($controller, $method)) {
                throw new FrameworkException("Method [{$method}] not found on controller [{$class}].");
            }

            $result = $controller->$method($request, $params);
            return $this->toResponse($result);
        }

        if (is_string($handler) && class_exists($handler)) {
            $instance = $this->container !== null && $this->container->has($handler)
                ? $this->container->get($handler)
                : new $handler();

            if ($instance instanceof RequestHandlerInterface) {
                return $instance->handle($request);
            }

            if (is_callable($instance)) {
                $result = $instance($request, $params);
                return $this->toResponse($result);
            }
        }

        throw new FrameworkException('Unresolvable route handler type.');
    }

    private function toResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return JsonResponse::create($result);
        }

        if (is_string($result)) {
            return JsonResponse::create(['message' => $result]);
        }

        return JsonResponse::create($result);
    }
}
