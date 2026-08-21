<?php

declare(strict_types=1);

namespace Micro\Application;

use Micro\Container\Container;
use Micro\Middleware\MiddlewarePipeline;
use Micro\Routing\Router;
use Micro\Routing\RoutingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class App implements RequestHandlerInterface
{
    private readonly Router $router;
    private readonly MiddlewarePipeline $pipeline;
    private readonly ContainerInterface $container;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
        $this->router = new Router();
        
        $routingHandler = new RoutingHandler($this->router, $this->container);
        $this->pipeline = new MiddlewarePipeline($routingHandler, $this->container);
    }

    public static function create(?ContainerInterface $container = null): self
    {
        return new self($container);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Register a global PSR-15 middleware into the pipeline.
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->pipeline->add($middleware);
        return $this;
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
        $this->router->group($prefix, $callback, $middlewares);
        return $this;
    }

    public function get(string $path, mixed $handler): self
    {
        $this->router->get($path, $handler);
        return $this;
    }

    public function post(string $path, mixed $handler): self
    {
        $this->router->post($path, $handler);
        return $this;
    }

    public function put(string $path, mixed $handler): self
    {
        $this->router->put($path, $handler);
        return $this;
    }

    public function patch(string $path, mixed $handler): self
    {
        $this->router->patch($path, $handler);
        return $this;
    }

    public function delete(string $path, mixed $handler): self
    {
        $this->router->delete($path, $handler);
        return $this;
    }

    /**
     * Handle a PSR-7 ServerRequest and return a PSR-7 Response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $executionPipeline = $this->pipeline->withNewExecution();
        return $executionPipeline->handle($request);
    }

    /**
     * Run the application under standard SAPI, FrankenPHP, or RoadRunner.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        if ($request === null) {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, // ServerRequestFactory
                $psr17Factory, // UriFactory
                $psr17Factory, // UploadedFileFactory
                $psr17Factory  // StreamFactory
            );
            $request = $creator->fromGlobals();
        }

        $response = $this->handle($request);
        $this->emit($response);
    }

    /**
     * Emit headers, status code, and stream response body to output buffer.
     */
    private function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        echo $body->getContents();
    }
}
