<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Micro\Container\Container;
use Micro\Http\JsonResponse;
use Micro\Middleware\MiddlewarePipeline;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OrderMiddleware implements MiddlewareInterface
{
    public function __construct(private string $tag)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $log = $request->getAttribute('order_log', []);
        $log[] = "in:{$this->tag}";
        $request = $request->withAttribute('order_log', $log);

        $response = $handler->handle($request);

        $header = $response->getHeaderLine('X-Order');
        $header = $header !== '' ? "{$header},out:{$this->tag}" : "out:{$this->tag}";

        return $response->withHeader('X-Order', $header);
    }
}

class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new Response(418, ['X-Short-Circuit' => 'true']);
    }
}

final class MiddlewarePipelineTest extends TestCase
{
    public function testFifoOnionExecutionOrder(): void
    {
        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $log = $request->getAttribute('order_log', []);
                $log[] = 'core';
                return JsonResponse::ok(['log' => $log]);
            }
        };

        $pipeline = new MiddlewarePipeline($fallback);
        $pipeline->add(new OrderMiddleware('A'));
        $pipeline->add(new OrderMiddleware('B'));

        $request = new ServerRequest('GET', '/');
        $response = $pipeline->handle($request);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['in:A', 'in:B', 'core'], $payload['log']);
        $this->assertSame('out:B,out:A', $response->getHeaderLine('X-Order'));
    }

    public function testShortCircuitMiddleware(): void
    {
        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return JsonResponse::ok(['should_not_reach' => true]);
            }
        };

        $pipeline = new MiddlewarePipeline($fallback);
        $pipeline->add(new ShortCircuitMiddleware());
        $pipeline->add(new OrderMiddleware('A'));

        $request = new ServerRequest('GET', '/');
        $response = $pipeline->handle($request);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaderLine('X-Short-Circuit'));
    }

    public function testStatelessClonedExecutionWithNewExecution(): void
    {
        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($fallback);
        $pipeline->add(new OrderMiddleware('1'));

        // Handle request 1
        $req1 = new ServerRequest('GET', '/1');
        $exec1 = $pipeline->withNewExecution();
        $res1 = $exec1->handle($req1);
        $this->assertSame(200, $res1->getStatusCode());

        // Handle request 2 (index must not be exhausted)
        $req2 = new ServerRequest('GET', '/2');
        $exec2 = $pipeline->withNewExecution();
        $res2 = $exec2->handle($req2);
        $this->assertSame(200, $res2->getStatusCode());
    }
}
