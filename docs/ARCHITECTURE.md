# Micro PHP Framework — Architecture Specification (v0.1)

> A small, type-safe, PSR-compatible PHP micro-framework kernel designed for modern REST and JSON APIs.

---

## 1. Architectural Philosophy

Micro is designed from empirical requirements extracted from real-world application refactoring. It adheres to these fundamental engineering principles:

- **Explicit Over Magic:** No annotation scanners, no reflection magic during request dispatching, no hidden container proxy generators.
- **Type Safety & Strictness:** Built on PHP 8.4+ with `declare(strict_types=1);`, typed properties, union types, and readonly DTOs.
- **PSR Interoperability:** Uses standard PSR interfaces (PSR-7, PSR-11, PSR-15, PSR-17, PSR-3) allowing drop-in compatibility with any compliant component.
- **Runtime Neutrality:** `App::handle(ServerRequestInterface $request): ResponseInterface` is decoupled from PHP superglobals, making it directly compatible with persistent runtimes like FrankenPHP, RoadRunner, and Swoole as well as traditional PHP-FPM / SAPI.
- **Stateless Pipeline:** Every incoming request executes against a cloned pipeline state (`withNewExecution()`), preventing mutable pipeline state leakage across requests.
- **Zero Bloat / No Monolithic Framework:** No ORM, no Active Record, no template engine, no form helpers, no event bus.

---

## 2. Request & Execution Pipeline

```
+-----------------------------------------------------------------------------------+
|                         CLIENT / HTTP CONSUMER / SAPI                             |
|                                                                                   |
|      HTTP Request (PHP-FPM, SAPI, FrankenPHP, RoadRunner, or Test Harness)        |
+----------------------------------------┬------------------------------------------+
                                         │ PSR-7 ServerRequestInterface
                                         ▼
+-----------------------------------------------------------------------------------+
|                           MICRO-FRAMEWORK CORE (v0.1)                             |
|                                                                                   |
|  [App Kernel (App.php)] ── implements RequestHandlerInterface                     |
|       │                                                                           |
|       ├─▶ [PSR-15 Middleware Pipeline (MiddlewarePipeline.php)]                   |
|       │         ├── ErrorHandlerMiddleware (JSON error envelope + PSR-3 logging)  |
|       │         ├── CorsMiddleware (Preflight 204 + Access-Control headers)       |
|       │         └── Custom Middleware (Global & Route-Group Level)                |
|       │                                                                           |
|       ├─▶ [Routing Engine (Router.php + RoutingHandler.php)]                      |
|       │         ├── FastRoute Route Collector & Group Prefixing                   |
|       │         ├── Strict Method Resolution (404 Not Found, 405 Method Not Allow)|
|       │         └── Parameter Extraction (Passed explicitly as array $params)     |
|       │                                                                           |
|       └─▶ [PSR-11 Autowiring Container (Container.php)]                           |
|                 ├── Constructor Reflection Autowiring                             |
|                 ├── Explicit Interface Bindings & Factory Callbacks               |
|                 └── Singleton Caching & Service Providers                         |
+----------------------------------------┬------------------------------------------+
                                         │ Dispatches to Controller / Closure
                                         ▼
+-----------------------------------------------------------------------------------+
|                      APPLICATION DOMAIN / HANDLER LAYER                           |
|                                                                                   |
|  Signature:  function (ServerRequestInterface $request, array $params): Response  |
|                                                                                   |
|  [Optional Database Module (Micro\Module\Database)]                               |
|       ├── DatabaseConfig (Typed DTO from environment array)                       |
|       ├── ConnectionFactory (Strict PDO Configuration)                            |
|       ├── TransactionManager (Atomic transaction callback execution)              |
|       └── DatabaseProvider (Container registration)                               |
+-----------------------------------------------------------------------------------+
```

---

## 3. Core Component Responsibilities

### 3.1 Kernel (`Micro\Application\App`)
- Implements `Psr\Http\Server\RequestHandlerInterface`.
- Assembles the root `MiddlewarePipeline` and `RoutingHandler`.
- Provides expressive route and group registration methods (`get`, `post`, `put`, `patch`, `delete`, `group`).
- Provides `handle()` for runtime-neutral request processing and `run()` for SAPI emission.

### 3.2 Container (`Micro\Container\Container`)
- Implements `Psr\Container\ContainerInterface`.
- Performs constructor reflection autowiring for concrete classes.
- Requires explicit bindings for interfaces (no ambiguous guessing).
- Detects circular dependencies deterministically via resolution call-stack tracking.
- Caches singleton instances and handles custom callable execution (`call()`).

### 3.3 Router & Routing Handler (`Micro\Routing\*`)
- **`Router`:** Aggregates route definitions and route-group middlewares into FastRoute route collections.
- **`RoutingHandler`:** Resolves the incoming request URI and HTTP method. If matched, extracts route parameters and executes route-group middlewares before invoking the target controller/closure.
- **Explicit Parameter Invariant:** Route parameters are passed explicitly as the second argument (`$params`) to handlers. Route parameters are **not** written into `$request->withAttribute()`.

### 3.4 Error Handling (`Micro\Exception\*` and `Micro\Middleware\ErrorHandlerMiddleware`)
- **`HttpException` Hierarchy:** `BadRequestHttpException` (400), `UnauthorizedHttpException` (401), `ForbiddenHttpException` (403), `NotFoundHttpException` (404), `MethodNotAllowedHttpException` (405), `ConflictHttpException` (409).
- **`ErrorHandlerMiddleware`:** Converts any `HttpException` into a standardized JSON response:
  ```json
  {
    "error": {
      "code": 404,
      "message": "Resource not found"
    }
  }
  ```
  In production, unexpected `Throwable` errors return a sanitized 500 error. In debug mode (`debug: true`), file, line, and stack trace arrays are included.

### 3.5 Optional Database Module (`Micro\Module\Database\*`)
- Self-contained, zero-ORM raw PDO database support.
- `DatabaseConfig`: Typed readonly configuration DTO with environment fallback and aliases.
- `ConnectionFactory`: Static builder generating configured `PDO` instances with strict error modes, utf8mb4, and associative fetching.
- `TransactionManager`: Atomic transaction execution wrapper (`transaction(callable $callback)`) with rollback on exception and nested transaction safety.
- `DatabaseProvider`: Service provider registering database singletons into `Container`.
