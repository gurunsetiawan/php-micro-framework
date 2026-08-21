<?php

declare(strict_types=1);

namespace Micro\Tests\Integration;

use Micro\Application\App;
use Micro\Exception\BadRequestHttpException;
use Micro\Http\JsonResponse;
use Micro\Middleware\CorsMiddleware;
use Micro\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('Authorization')) {
            return JsonResponse::error('Missing token', 401);
        }

        return $handler->handle($request);
    }
}

class ProductController
{
    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return JsonResponse::ok([
            ['id' => '1', 'name' => 'Laptop'],
            ['id' => '2', 'name' => 'Mouse'],
        ]);
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return JsonResponse::ok([
            'id' => $params['id'],
            'name' => 'Product ' . $params['id'],
        ]);
    }

    public function store(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        if (empty($body['name'])) {
            throw new BadRequestHttpException('Name is required.');
        }

        return JsonResponse::created([
            'id' => '100',
            'name' => $body['name'],
        ]);
    }
}

final class AppHttpIntegrationTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = App::create();

        // Middleware
        $this->app->add(new ErrorHandlerMiddleware(debug: true));
        $this->app->add(new CorsMiddleware(allowedOrigins: ['*']));

        // Health check route
        $this->app->get('/health', fn() => JsonResponse::ok(['status' => 'healthy']));

        // Resource route group
        $this->app->group('/api/v1', function (\Micro\Routing\Router $router): void {
            $router->get('/products', [ProductController::class, 'index']);
            $router->get('/products/{id}', [ProductController::class, 'show']);
            $router->post('/products', [ProductController::class, 'store']);
        });

        // Protected route group
        $this->app->group('/admin', function (\Micro\Routing\Router $router): void {
            $router->get('/stats', fn() => JsonResponse::ok(['users_count' => 50]));
        }, [new AuthHeaderMiddleware()]);
    }

    public function testGetHealthRoute(): void
    {
        $request = new ServerRequest('GET', '/health');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('healthy', $payload['status']);
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testGetProductDetailWithParameter(): void
    {
        $request = new ServerRequest('GET', '/api/v1/products/42');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('42', $payload['id']);
        $this->assertSame('Product 42', $payload['name']);
    }

    public function testPostCreateProductSuccess(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/products'))
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode(['name' => 'Mechanical Keyboard']));

        $response = $this->app->handle($request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('100', $payload['id']);
        $this->assertSame('Mechanical Keyboard', $payload['name']);
    }

    public function testPostCreateProductValidationError(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/products'))
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([])); // Empty body

        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['error']['code']);
        $this->assertSame('Name is required.', $payload['error']['message']);
    }

    public function testProtectedGroupRejection(): void
    {
        $request = new ServerRequest('GET', '/admin/stats');
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testProtectedGroupSuccessWithHeader(): void
    {
        $request = (new ServerRequest('GET', '/admin/stats'))
            ->withHeader('Authorization', 'Bearer valid-token');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(50, $payload['users_count']);
    }

    public function testNotFoundRoute(): void
    {
        $request = new ServerRequest('GET', '/unknown/route');
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(404, $payload['error']['code']);
    }

    public function testMethodNotAllowedRoute(): void
    {
        $request = new ServerRequest('DELETE', '/health');
        $response = $this->app->handle($request);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->getHeaderLine('Allow'));
    }

    public function testCorsPreflightOptions(): void
    {
        $request = (new ServerRequest('OPTIONS', '/api/v1/products'))
            ->withHeader('Origin', 'http://localhost:3000');
        $response = $this->app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
