<?php

declare(strict_types=1);

namespace Micro\Middleware;

use Micro\Exception\FrameworkException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @var list<MiddlewareInterface|class-string<MiddlewareInterface>>
     */
    private array $queue = [];

    private int $index = 0;

    public function __construct(
        private readonly RequestHandlerInterface $fallbackHandler,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    /**
     * Append a middleware instance or class name to the pipeline.
     *
     * @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->queue[] = $middleware;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->queue)) {
            return $this->fallbackHandler->handle($request);
        }

        $entry = $this->queue[$this->index];
        $this->index++;

        $middleware = $this->resolveMiddleware($entry);

        return $middleware->process($request, $this);
    }

    /**
     * Creates a fresh cloned pipeline instance for handling a request so the index resets.
     */
    public function withNewExecution(): self
    {
        $clone = clone $this;
        $clone->index = 0;
        return $clone;
    }

    private function resolveMiddleware(MiddlewareInterface|string $entry): MiddlewareInterface
    {
        if ($entry instanceof MiddlewareInterface) {
            return $entry;
        }

        if (is_string($entry)) {
            $instance = $this->container !== null && $this->container->has($entry)
                ? $this->container->get($entry)
                : new $entry();

            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }

            throw new FrameworkException("Class [{$entry}] must implement " . MiddlewareInterface::class);
        }

        throw new FrameworkException('Invalid middleware entry in pipeline.');
    }
}
