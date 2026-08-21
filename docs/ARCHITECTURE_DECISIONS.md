# Architecture Decision Records (ADRs) — Micro Framework

This document records the foundational architectural decisions for the Micro PHP Framework (v0.1).

---

## Index

- [ADR-F01: PHP 8.4+ Type Safety & Strict Types Baseline](#adr-f01-php-84-type-safety--strict-types-baseline)
- [ADR-F02: Minimal Standard PSR Surface Area](#adr-f02-minimal-standard-psr-surface-area)
- [ADR-F03: Zero Monolithic Dependencies / FastRoute & Nyholm Core](#adr-f03-zero-monolithic-dependencies--fastroute--nyholm-core)
- [ADR-F04: PSR-15 Onion Middleware Pipeline with Stateless Execution](#adr-f04-psr-15-onion-middleware-pipeline-with-stateless-execution)
- [ADR-F05: Explicit Route Parameter Handler Signature](#adr-f05-explicit-route-parameter-handler-signature)
- [ADR-F06: Hybrid PSR-11 Container with Constructor Autowiring](#adr-f06-hybrid-psr-11-container-with-constructor-autowiring)
- [ADR-F07: Centralized HttpException Hierarchy & JSON Error Normalization](#adr-f07-centralized-httpexception-hierarchy--json-error-normalization)
- [ADR-F08: Standalone Configurable CORS Preflight Middleware](#adr-f08-standalone-configurable-cors-preflight-middleware)
- [ADR-F09: Optional Raw PDO Database Module with Atomic Transaction Manager](#adr-f09-optional-raw-pdo-database-module-with-atomic-transaction-manager)
- [ADR-F10: Runtime Neutrality (SAPI, FrankenPHP, RoadRunner, Test Harness)](#adr-f10-runtime-neutrality-sapi-frankenphp-roadrunner-test-harness)

---

### ADR-F01: PHP 8.4+ Type Safety & Strict Types Baseline

- **Status:** Accepted / Frozen
- **Context:** The framework needs modern type safety, performance, and maintainability without legacy polyfills.
- **Decision:** Target PHP `>= 8.4` with mandatory `declare(strict_types=1);` in all files. Leverage readonly classes, constructor property promotion, union types, and named arguments.
- **Consequences:** Maximum code clarity and type safety; older PHP versions (<8.4) are unsupported.

---

### ADR-F02: Minimal Standard PSR Surface Area

- **Status:** Accepted / Frozen
- **Context:** Deciding which PSR standards are appropriate for a micro-framework kernel.
- **Decision:** Adopt PSR-4 (autoloading), PSR-7 (HTTP messages), PSR-11 (container), PSR-15 (HTTP middleware), PSR-17 (HTTP factories), and optional PSR-3 (logging).
- **Consequences:** Provides complete interoperability with the broader PHP ecosystem while avoiding unnecessary PSR bloat (e.g. PSR-6, PSR-16 cache).

---

### ADR-F03: Zero Monolithic Dependencies / FastRoute & Nyholm Core

- **Status:** Accepted / Frozen
- **Context:** Choosing lightweight runtime libraries for HTTP routing and message creation.
- **Decision:** Bind directly to `nikic/fast-route` for regex routing, and `nyholm/psr7` + `nyholm/psr7-server` for PSR-7 message creation.
- **Consequences:** Extremely small footprint (< 100 KB total third-party code), zero reliance on heavyweight frameworks.

---

### ADR-F04: PSR-15 Onion Middleware Pipeline with Stateless Execution

- **Status:** Accepted / Frozen
- **Context:** Middleware pipelines in long-running runtimes (FrankenPHP, RoadRunner) can leak execution index state across concurrent requests if mutable.
- **Decision:** Implement `MiddlewarePipeline` implementing `RequestHandlerInterface` with a `withNewExecution()` cloning method that resets the pipeline index for every request.
- **Consequences:** Concurrency-safe, stateless execution with FIFO order and short-circuit capability.

---

### ADR-F05: Explicit Route Parameter Handler Signature

- **Status:** Accepted / Frozen
- **Context:** How matched URL route parameters (e.g. `{id}`) are passed to controllers and route handlers.
- **Decision:** Route parameters are passed explicitly as the second argument: `handler(ServerRequestInterface $request, array $params): ResponseInterface`.
- **Consequences:** Request attributes are not polluted with routing variables; parameter access is explicit, simple, and clean.

---

### ADR-F06: Hybrid PSR-11 Container with Constructor Autowiring

- **Status:** Accepted / Frozen
- **Context:** Balancing developer ergonomics with predictable dependency injection.
- **Decision:** Provide reflection-based constructor autowiring for concrete classes, require explicit bindings for interfaces, cache singletons, and detect circular dependencies via resolution call-stack tracking.
- **Consequences:** Rapid development without massive configuration boilerplate; strict predictability when resolving interfaces.

---

### ADR-F07: Centralized HttpException Hierarchy & JSON Error Normalization

- **Status:** Accepted / Frozen
- **Context:** Error and exception normalization across JSON APIs.
- **Decision:** Standardize HTTP status codes using a typed `HttpException` hierarchy (400, 401, 403, 404, 405, 409). Catch all exceptions in `ErrorHandlerMiddleware` to return standard JSON payloads `{"error": {"code": 4xx/500, "message": "..."}}`.
- **Consequences:** Uniform error responses across all routes; automatic stack trace sanitization in production.

---

### ADR-F08: Standalone Configurable CORS Preflight Middleware

- **Status:** Accepted / Frozen
- **Context:** Modern SPAs require CORS preflight (`OPTIONS`) handling and header decoration.
- **Decision:** Include a dedicated, configurable `CorsMiddleware` that intercepts `OPTIONS` requests with HTTP 204 and decorates downstream responses with CORS headers.
- **Consequences:** Zero CORS errors when integrating with Single Page Applications (React, Vue, etc.).

---

### ADR-F09: Optional Raw PDO Database Module with Atomic Transaction Manager

- **Status:** Accepted / Frozen
- **Context:** Database integration without introducing ORM or query builder complexity.
- **Decision:** Provide an optional `Micro\Module\Database` module containing `DatabaseConfig`, `ConnectionFactory`, and `TransactionManager` (`transaction(callable $callback)` with rollback on Throwable and nested transaction safety).
- **Consequences:** Full transactional database capability without sacrificing the micro-kernel's purity.

---

### ADR-F10: Runtime Neutrality (SAPI, FrankenPHP, RoadRunner, Test Harness)

- **Status:** Accepted / Frozen
- **Context:** Supporting both traditional web servers and modern asynchronous/persistent PHP runtimes.
- **Decision:** `App::handle()` operates purely on PSR-7 request and response objects without invoking `echo` or `header()`. `App::run()` handles SAPI emissions when executing under standard web servers.
- **Consequences:** Direct testability in unit/integration test harnesses and seamless deployment to FrankenPHP or RoadRunner.
