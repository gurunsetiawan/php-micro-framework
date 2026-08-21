<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Micro\Container\Container;
use Micro\Http\JsonResponse;
use Micro\Routing\Router;
use Micro\Routing\RoutingHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SampleController
{
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return JsonResponse::ok([
            'id' => $params['id'],
            'has_attr' => $request->getAttribute('id') !== null,
        ]);
    }

    public function rawArray(ServerRequestInterface $request, array $params): array
    {
        return ['status' => 'success', 'slug' => $params['slug']];
    }
}

class HeaderInjectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Custom-Header', 'injected');
    }
}

final class RoutingHandlerTest extends TestCase
{
    private Router $router;
    private Container $container;
    private RoutingHandler $handler;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->container = new Container();
        $this->handler = new RoutingHandler($this->router, $this->container);
    }

    public function testRouteParameterExplicitPassingWithoutRequestAttributeDuplication(): void
    {
        $this->router->get('/items/{id}', [SampleController::class, 'show']);

        $request = new ServerRequest('GET', '/items/99');
        $response = $this->handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame('99', $payload['id']);
        // Verify invariant: parameters are NOT copied to request attributes
        $this->assertFalse($payload['has_attr']);
    }

    public function testAutoJsonResponseForArrayReturns(): void
    {
        $this->router->get('/articles/{slug}', [SampleController::class, 'rawArray']);

        $request = new ServerRequest('GET', '/articles/hello-world');
        $response = $this->handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('hello-world', $payload['slug']);
    }

    public function testClosureHandlerExecution(): void
    {
        $this->router->get('/ping', function (ServerRequestInterface $req, array $params): ResponseInterface {
            return JsonResponse::ok(['pong' => true]);
        });

        $request = new ServerRequest('GET', '/ping');
        $response = $this->handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['pong']);
    }

    public function testRouteGroupMiddlewareExecution(): void
    {
        $this->router->group('/api', function (Router $r): void {
            $r->get('/test', fn() => JsonResponse::ok(['msg' => 'ok']));
        }, [new HeaderInjectMiddleware()]);

        $request = new ServerRequest('GET', '/api/test');
        $response = $this->handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('injected', $response->getHeaderLine('X-Custom-Header'));
    }
}
