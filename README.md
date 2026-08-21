# Micro PHP Framework (v0.1)

> A small, fast, type-safe, PSR-compatible PHP micro-framework kernel designed for modern REST and JSON APIs.

---

## 📌 What is Micro?

Micro is an intentionally minimal, zero-bloat PHP micro-framework kernel. It provides only the core plumbing needed to build clean, testable, and robust HTTP APIs on PHP 8.4+:

- **PSR Standards Compliant:** PSR-7 (HTTP Messages), PSR-11 (Container), PSR-15 (HTTP Handlers & Middleware), PSR-17 (HTTP Factories), and optional PSR-3 (Logger).
- **FastRoute Engine:** Ultra-fast regex-compiled routing with nested prefix groups and group-level middleware.
- **Explicit Parameter Contract:** Route parameters (`$params`) are passed explicitly to handlers, leaving PSR-7 request attributes unpolluted.
- **Constructor Autowiring Container:** Zero-configuration dependency injection for concrete classes with circular dependency detection.
- **Stateless Pipeline:** Safe for persistent/concurrent runtimes (FrankenPHP, RoadRunner, Swoole) as well as traditional PHP-FPM / SAPI.
- **Normalized Error Handling:** Standard JSON error envelopes with typed HTTP exceptions and production-safe error sanitization.
- **Optional Database Module:** Lightweight, raw PDO connection builder and atomic transaction manager without ORM overhead.

---

## 🚫 Non-Goals (What Micro is NOT)

Micro deliberately avoids heavyweight conventions:
- ❌ **No ORM / Query Builder / Active Record:** Write clean SQL with raw PDO and prepared statements.
- ❌ **No Event Bus / CQRS Engine:** Explicit procedural and domain service calls over magic dispatching.
- ❌ **No State Machine / Workflow Framework:** Domain state logic belongs explicitly in domain models.
- ❌ **No Template / View Engine:** Optimized strictly for REST/JSON APIs and Single Page Application (SPA) backends.
- ❌ **No Session / Cookie State:** Designed for stateless token-based architectures (Bearer / JWT).
- ❌ **No Annotation / Attribute Magic:** Explicit code over reflection runtime scanning.

---

## 🚀 Quickstart

### 1. Minimal Application Example

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Micro\Application\App;
use Micro\Http\JsonResponse;
use Micro\Middleware\CorsMiddleware;
use Micro\Middleware\ErrorHandlerMiddleware;
use Psr\Http\Message\ServerRequestInterface;

// 1. Create Kernel
$app = App::create();

// 2. Global Middleware Pipeline
$app->add(new ErrorHandlerMiddleware(debug: true));
$app->add(new CorsMiddleware(allowedOrigins: ['*']));

// 3. Define Routes
$app->get('/health', function (ServerRequestInterface $request, array $params) {
    return JsonResponse::ok(['status' => 'healthy', 'timestamp' => time()]);
});

$app->group('/api/v1', function ($router) {
    $router->get('/items', [ItemController::class, 'index']);
    $router->get('/items/{id}', [ItemController::class, 'show']);
    $router->post('/items', [ItemController::class, 'store']);
});

// 4. Run Application (SAPI, FrankenPHP, or RoadRunner)
$app->run();
```

---

## 🛠️ Core Capabilities

### Explicit Route Parameter Handling

Route parameters are passed explicitly as the second argument `$params`:

```php
namespace App\Controller;

use Micro\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ItemController
{
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = $params['id']; // Explicit, typed, no request mutation
        return JsonResponse::ok(['id' => $id, 'name' => 'Sample Item']);
    }
}
```

### Dependency Injection & Autowiring

```php
use Micro\Container\Container;

$container = new Container();

// Explicit Interface Binding
$container->bind(UserRepositoryInterface::class, SqlUserRepository::class);

// Singleton Registration
$container->singleton(HttpClient::class);

// Concrete Autowiring (Constructor parameters are automatically resolved)
$userService = $container->get(UserService::class);
```

### Typed HTTP Exceptions & Error Handling

Throw typed HTTP exceptions anywhere in your domain or controllers:

```php
use Micro\Exception\BadRequestHttpException;
use Micro\Exception\NotFoundHttpException;
use Micro\Exception\ForbiddenHttpException;

if (!$user) {
    throw new NotFoundHttpException('User not found.');
}

if (!$user->canEdit()) {
    throw new ForbiddenHttpException('Insufficient permissions.');
}
```

Standard JSON Error Envelope:
```json
{
  "error": {
    "code": 404,
    "message": "User not found."
  }
}
```

---

## 🗄️ Optional Database Module

Micro includes an optional, zero-overhead raw PDO database module under `Micro\Module\Database`:

```php
use Micro\Module\Database\DatabaseConfig;
use Micro\Module\Database\DatabaseProvider;
use Micro\Module\Database\TransactionManager;

$provider = new DatabaseProvider();
$provider->register($app->getContainer(), DatabaseConfig::fromEnv());

// Execute atomic transactions with automatic rollback on error:
$tx = $app->getContainer()->get(TransactionManager::class);

$tx->transaction(function () use ($pdo) {
    $pdo->exec("UPDATE accounts SET balance = balance - 100 WHERE id = 1");
    $pdo->exec("UPDATE accounts SET balance = balance + 100 WHERE id = 2");
});
```

---

## 🧪 Testing

Run the test suite:

```bash
composer install
vendor/bin/phpunit
```

---

## 📄 License

Open-sourced software licensed under the [MIT License](LICENSE).
