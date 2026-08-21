<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use FastRoute\Dispatcher;
use Micro\Exception\MethodNotAllowedHttpException;
use Micro\Exception\NotFoundHttpException;
use Micro\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRouteRegistrationForHttpMethods(): void
    {
        $this->router->get('/users', 'UserController@index');
        $this->router->post('/users', 'UserController@store');
        $this->router->put('/users/{id}', 'UserController@update');
        $this->router->patch('/users/{id}', 'UserController@patch');
        $this->router->delete('/users/{id}', 'UserController@destroy');

        $routes = $this->router->getRoutes();

        $this->assertCount(5, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/users', $routes[0]['path']);
        $this->assertSame('POST', $routes[1]['method']);
        $this->assertSame('PUT', $routes[2]['method']);
        $this->assertSame('PATCH', $routes[3]['method']);
        $this->assertSame('DELETE', $routes[4]['method']);
    }

    public function testRouteGroupPrefixNormalization(): void
    {
        $this->router->group('/api/v1', function (Router $router): void {
            $router->get('/items', 'ItemController@index');
            $router->post('/items', 'ItemController@store');
        });

        $routes = $this->router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame('/api/v1/items', $routes[0]['path']);
        $this->assertSame('/api/v1/items', $routes[1]['path']);
    }

    public function testNestedRouteGroups(): void
    {
        $this->router->group('admin', function (Router $router): void {
            $router->group('users', function (Router $inner): void {
                $inner->get('{id}', 'AdminUserController@show');
            });
        });

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/admin/users/{id}', $routes[0]['path']);
    }

    public function testRouteMatchingFound(): void
    {
        $this->router->get('/users/{id}', 'UserController@show');

        $result = $this->router->match('GET', '/users/42');

        $this->assertSame(Dispatcher::FOUND, $result->status);
        $this->assertSame('UserController@show', $result->handler);
        $this->assertSame(['id' => '42'], $result->params);
    }

    public function testRouteMatchingNotFound(): void
    {
        $this->router->get('/users', 'UserController@index');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Route [GET /missing] not found.');

        $this->router->match('GET', '/missing');
    }

    public function testRouteMatchingMethodNotAllowed(): void
    {
        $this->router->get('/users', 'UserController@index');

        try {
            $this->router->match('POST', '/users');
            $this->fail('Expected MethodNotAllowedHttpException was not thrown.');
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertContains('GET', $e->getAllowedMethods());
            $this->assertSame('GET', $e->getHeaders()['Allow']);
        }
    }
}
