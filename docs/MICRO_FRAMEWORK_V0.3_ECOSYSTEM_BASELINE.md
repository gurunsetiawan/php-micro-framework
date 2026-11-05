# micro/framework v0.3 Ecosystem Baseline Specification

**Document Identifier:** `DOC-ARCH-ECOSYSTEM-V0.3`  
**Framework Version:** `micro/framework v0.3.0`  
**Starter Version:** `php-micro-starter v0.3.0`  
**Status:** **Authoritative Stable Baseline**  
**Classification:** Technical Architecture & Ecosystem Specification

---

## 1. Purpose

`micro/framework v0.3.0` is a small, type-safe, PSR-compatible PHP micro-framework kernel designed to provide generic application infrastructure without imposing a business domain, Object-Relational Mapper (ORM), authentication architecture, frontend stack, or heavy runtime ecosystem.

The framework is intentionally domain-agnostic. It does not embody any specific business model or vertical workflow. Instead, it provides the fundamental plumbing required to build modern, deterministic, observable, and high-performance HTTP microservices and modular web applications.

---

## 2. Version and Status

- **Framework Kernel:** `micro/framework v0.3.0`
- **Application Starter:** `php-micro-starter v0.3.0`
- **Release Status:** **Authoritative stable baseline for the validated ecosystem.**
- **Scope Boundary:** This specification defines the stable capabilities, cross-layer boundaries, observability contracts, and governance policies established across all verified consumer migrations.

---

## 3. Architecture Overview

The framework provides eleven core capabilities, each implemented with zero unnecessary abstractions:

```
+-----------------------------------------------------------------------------------+
|                            HTTP CONSUMER / SAPI RUNTIME                           |
|       (PHP-FPM, SAPI CLI Server, FrankenPHP, RoadRunner, or Test Harness)         |
+-----------------------------------------┬-----------------------------------------+
                                          │ PSR-7 ServerRequestInterface
                                          ▼
+-----------------------------------------------------------------------------------+
|                           MICRO FRAMEWORK KERNEL (v0.3.0)                         |
|                                                                                   |
|  [App Kernel (App.php)] ── implements RequestHandlerInterface                     |
|       │                                                                           |
|       ├─▶ [PSR-15 Middleware Pipeline (MiddlewarePipeline.php)]                   |
|       │         ├── RequestIdMiddleware (X-Request-ID correlation header)         |
|       │         ├── AccessLogMiddleware (Structured NDJSON / PSR-3 telemetry)     |
|       │         ├── CorsMiddleware (Configurable origin/method headers)           |
|       │         ├── ErrorHandlerMiddleware (Centralized exception handling)       |
|       │         └── JsonBodyParserMiddleware (Strict JSON payload decoding)       |
|       │                                                                           |
|       ├─▶ [Routing Engine (Router.php + RoutingHandler.php)]                      |
|       │         ├── FastRoute Regex Dispatcher & Group Prefixing                  |
|       │         ├── Method Matching & HTTP Error Mapping (404 / 405)              |
|       │         └── Route Parameter Extraction (Explicit array $params)           |
|       │                                                                           |
|       └─▶ [PSR-11 Autowiring Container (Container.php)]                           |
|                 ├── Type-Hinted Constructor Autowiring                            |
|                 ├── Interface Bindings & Factory Registrations                    |
|                 └── Singleton Lifecycle Caching & Service Providers               |
+-----------------------------------------┬-----------------------------------------+
                                          │ Dispatches to Controller / Handler
                                          ▼
+-----------------------------------------------------------------------------------+
|                        OPTIONAL DATABASE MODULE (v0.3.0)                          |
|                                                                                   |
|  [Database Module (Micro\Module\Database)]                                        |
|       ├── DatabaseConfig (Strict immutable connection configuration)              |
|       ├── ConnectionFactory (PDO instantiation with strict error modes)           |
|       └── TransactionManager (ACID callback execution & rollback management)      |
+-----------------------------------------------------------------------------------+
```

### Core Capabilities:
1. **Application Lifecycle (`App`):** Direct implementation of PSR-15 `RequestHandlerInterface`, enabling stateless execution and runtime neutrality across SAPI, PHP-FPM, FrankenPHP, and RoadRunner.
2. **PSR-11 Dependency Injection (`Container`):** Reflection-based constructor autowiring, explicit interface-to-implementation binding, factory closures, and singleton lifecycle caching.
3. **Routing Engine (`Router`):** High-performance FastRoute regex parameter routing, route grouping, prefixing, and explicit method resolution.
4. **PSR-7 Request & Response Handling:** Native compatibility with PSR-7 message interfaces (`ServerRequestInterface`, `ResponseInterface`).
5. **PSR-15 Middleware Pipeline (`MiddlewarePipeline`):** Stateless, clone-on-execution pipeline preventing cross-request state leakage in persistent workers.
6. **CORS Middleware (`CorsMiddleware`):** Preflight `OPTIONS` short-circuiting (HTTP 204) and configurable `Access-Control-*` header management.
7. **Centralized Error Handling (`ErrorHandlerMiddleware`):** Production-safe exception capture, debug-mode diagnostics, and standard JSON error formatting.
8. **JSON Response Factory (`JsonResponse`):** Type-safe helper for standard JSON payloads (`ok`, `created`, `error`, `noContent`).
9. **Request Correlation (`RequestIdMiddleware`):** Generates or propagates standard `X-Request-ID` headers across all incoming requests and outgoing responses.
10. **Structured Access Logging (`AccessLogMiddleware`):** Emits structured NDJSON access logs via any PSR-3 logger containing request correlation, duration, and status.
11. **Optional Database & Transactions (`Micro\Module\Database`):** Strict PDO configuration, immutable database DTOs, and closure-based transactional execution via `TransactionManager`.

---

## 4. Core, Starter, and Application Boundaries

The ecosystem enforces a strict three-tier boundary separation:

```
+-------------------------------------------------------------------------+
|                         FRAMEWORK CORE (micro/framework)                |
|  - Kernel & Request Lifecycle       - FastRoute Dispatcher              |
|  - PSR-11 DI Container              - PSR-15 Middleware Pipeline        |
|  - RequestId / AccessLog / CORS     - JsonResponse & ErrorHandler       |
|  - Optional Database & Transaction Infrastructure                       |
+-------------------------------------------------------------------------+
                                    │
                                    ▼
+-------------------------------------------------------------------------+
|                        APPLICATION STARTER (php-micro-starter)          |
|  - Canonical index.php Entry Point  - AppProvider Service Registration  |
|  - Health Probe Controller          - Concrete JsonLogger (PSR-3)       |
|  - Canonical .env Loader            - Disposable Baseline Templates     |
+-------------------------------------------------------------------------+
                                    │
                                    ▼
+-------------------------------------------------------------------------+
|                        CONSUMER APPLICATION                             |
|  - Domain Entities & Value Objects  - Business Calculation Engines      |
|  - Authentication & JWT Logic       - Role-Based Access Control (RBAC)  |
|  - Domain-Specific Storage / PDO    - External Third-Party APIs         |
|  - Export Generators (Excel / CSV)  - Domain Security & Privacy Rules   |
+-------------------------------------------------------------------------+
```

### 1. Framework Core (`micro/framework`)
Owns generic infrastructure only. It is completely decoupled from any business logic, concrete logging implementations, authentication mechanisms, or database schema structures.

### 2. Application Starter (`php-micro-starter`)
Provides an opinionated but disposable baseline for new applications. It includes default service providers, a standard `.env` parser, a PSR-3 NDJSON stdout logger, and a canonical middleware pipeline assembly. Applications are free to customize or replace starter components without framework friction.

### 3. Consumer Application
Owns all domain-specific behavior:
- Domain entities, aggregates, and value objects
- Business rules, workflows, and calculation algorithms
- Authentication and session handling (JWT, Cookies, Tokens)
- Authorization and RBAC policies
- Database repositories and specialized SQL schemas
- External integrations (payment gateways, webhooks, third-party APIs)
- Specialized file exports (Spreadsheets, CSVs, PDFs)
- Application-level data redaction and security policies

---

## 5. Observability Model

`micro/framework v0.3.0` establishes a standardized, cloud-native observability model:

1. **Request Correlation:** `RequestIdMiddleware` inspects incoming requests for an existing `X-Request-ID` header (32–64 characters alphanumeric/dashes) or generates a secure 16-byte random hex identifier. This identifier is bound to the request attributes and injected into all response headers.
2. **Access Telemetry:** `AccessLogMiddleware` measures request execution time with microsecond precision and delegates log emission to a PSR-3 `LoggerInterface`.
3. **Structured NDJSON Output:** Concrete logging (provided by the starter's `JsonLogger`) emits single-line JSON records formatted for container stdout, fluentd, logstash, or CloudWatch:
   ```json
   {"timestamp":"2026-08-24T10:28:39+00:00","level":"INFO","message":"HTTP GET /api/health 200 (0.42ms)","context":{"request_id":"a95d3bb42fb0bc9e346e8ed56722b6c7","method":"GET","path":"/api/health","status":200,"duration_ms":0.42}}
   ```
4. **Sensitive Data Redaction:** Context keys matching sensitive patterns (`authorization`, `cookie`, `set-cookie`, `password`, `token`, `secret`, `api_key`) are automatically redacted to `***REDACTED***` before emission.
5. **Decoupled Architecture:** The framework core depends exclusively on the standard `Psr\Log\LoggerInterface` and `Psr\Log\NullLogger`, ensuring that applications retain full control over concrete logging backends without forcing monolithic logging dependencies into core.

---

## 6. Design Principles

The framework adheres to eight immutable architectural principles:

1. **Keep the framework small:** Zero bloat; core remains lightweight and auditable.
2. **Prefer generic infrastructure over business abstractions:** Provide routing, DI, and middleware; never provide business-specific helpers in core.
3. **Keep domain logic in applications:** Business calculations and domain rules belong strictly in consumer repositories.
4. **Preserve backwards compatibility:** Version evolution must remain predictable, non-breaking within major versions, and contract-stable.
5. **Avoid speculative abstractions:** Features are introduced only when real application requirements demand them.
6. **Prefer standard PSR interfaces:** Rely on PSR-3, PSR-7, PSR-11, PSR-15, and PSR-17 for ecosystem interoperability.
7. **Keep runtime dependencies minimal:** Framework core relies on standard, hardened packages (`nikic/fast-route`, `psr/*`, `nyholm/psr7`).
8. **Evolve through empirical validation:** Framework capabilities must be validated against production-grade consumer workloads before inclusion.

---

## 7. Ecosystem Validation

The stability of `micro/framework v0.3.0` is proven across five independent application domains representing diverse architectural challenges:

| Domain Classification | Primary Architectural Challenge | Core Capabilities Tested | Validation Result |
| :--- | :--- | :--- | :--- |
| **Employee Management** | Multi-table relational queries, search filtering, pagination, CSV exports | Router, DI Container, JsonResponse, PDO, ErrorHandler | **PASS** |
| **Ecommerce** | Multi-item checkout, stock decrement transactions, CSRF, image uploads | PDO Transactions, RequestId, CORS, JSON parsing | **PASS** |
| **Task / Workflow API** | RESTful lifecycle state machines, assignment workflows | FastRoute syntax, ErrorHandler, Status mappings | **PASS** |
| **Webhook Service** | High-throughput stateless ingest, HMAC signature verification, idempotency | RequestId, AccessLog redaction, raw body stream | **PASS** |
| **Financial Processing** | Precision arithmetic (BCMath), MySQL advisory locking, OpenXML XLSX & CSV exports | PDO transactions, binary PSR-7 streaming, RequestId | **PASS** |

---

## 8. Evidence Summary

Empirical evidence collected across the ecosystem:

- **Framework Core Test Suite:** 100% test pass rate across unit, middleware, container, router, and database test suites.
- **Starter Test Suite:** Complete validation of application bootstrap, `.env` parsing, and structured logging.
- **Consumer Parity:**
  - In the Financial Processing domain, 215 historical monthly periods representing **Rp 782,619,787.00** were regenerated with **100.00% exact mathematical match** against production reference records across 9 distinct monetary components.
- **Container & Stateless Verification:** Validated under Docker and stateless CLI execution with zero cross-request memory leaks.
- **Database & Transactional Integrity:** Verified rollback on intentional failure, zero orphan records, and mutual exclusion under concurrent advisory locks.
- **Distribution Verification:** Released under public GitHub tag `v0.3.0` with clean installation via Composer.

---

## 9. Security Baseline

- **Environment Isolation:** Secrets and database credentials are read from environment variables; `.env` files are excluded from version control via `.gitignore`.
- **Request ID Validation:** Incoming request IDs are sanitized to prevent log injection attacks.
- **Automated Log Sanitization:** Authorization headers, tokens, passwords, and sensitive context keys are redacted prior to log output.
- **Application-Owned Security:** Authentication (JWT, sessions) and authorization (RBAC) are owned entirely by the application domain, preventing insecure framework-level assumptions.
- **Safe Binary Streaming:** Binary exports (spreadsheets, CSVs) are streamed directly without logging sensitive financial or personal data.

---

## 10. Dependency Philosophy

- **Minimal Footprint:** Framework core requires only FastRoute, Nyholm PSR-7, and standard PSR interfaces.
- **No Mandatory ORM:** Applications choose their persistence strategy (PDO, Query Builders, or custom repositories).
- **No Mandatory Authentication:** Applications define authentication according to their operational needs.
- **No Mandatory Frontend:** Decoupled backend architecture supports Single Page Applications (SPAs), mobile clients, or microservice consumers.
- **Zero Concrete Loggers in Core:** Relies strictly on `Psr\Log\LoggerInterface`.

---

## 11. Distribution

- **Public Repository:** `https://github.com/gurunsetiawan/php-micro-framework`
- **Release Tag:** `v0.3.0` (commit `f757b9d`)
- **Starter Repository:** `https://github.com/gurunsetiawan/php-micro-starter` (`v0.3.0`)
- **Distribution Method:** Installable directly via Git / Composer without private repository dependencies.

---

## 12. What Stays Out of Framework Core

To preserve framework simplicity and prevent scope bloat, the following categories are strictly excluded from `micro/framework`:

1. **Domain-Specific Calculations:** Currency rounding, tax calculations, attendance formulas, and business algorithms.
2. **Domain State Machines:** Approval workflows, status transitions, and business validation rules.
3. **Banking & Specialized File Formats:** BCA Autocredit CSV formats, MTFS layouts, and proprietary EDI specifications.
4. **Document & Spreadsheet Builders:** POI / OpenXML XLSX generators, PDF renderers, and template engines.
5. **Domain Role / RBAC Models:** Specific permission names (`RECAP_MANAGE`, `EXCEL_DOWNLOAD`) and access graphs.
6. **Third-Party API Clients:** Payment gateway SDKs, messaging APIs, and cloud provider clients.

---

## 13. v0.4 Evidence Policy

Future framework evolution is governed by the **Two-Consumer Evidence Rule**:

> A proposed new capability may only be considered for `micro/framework` or `php-micro-starter` when at least **two independent consumer applications** demonstrate the exact same generic architectural requirement that cannot be cleanly solved in application space.

### Feature Classification Taxonomy:
- **Framework Core:** Universal HTTP, routing, DI, or middleware primitives.
- **Starter / Utility:** Reusable, optional service providers or helpers for common application tasks.
- **Application-Level:** Domain-specific or single-consumer requirements (must remain in application space).
- **Rejected:** Features that violate simplicity, introduce heavy dependencies, or enforce monolithic opinions.

---

## 14. Current Future Candidates (Non-Committed)

| Candidate Feature | Evidence Source | Evaluation & Classification |
| :--- | :--- | :--- |
| **Stream Response Helper** | Financial Processing (1) | **Candidate for Core:** Convenience factory for binary downloads with automatic `Content-Disposition` and `Content-Length`. Insufficient multi-consumer evidence currently. |
| **HMAC Token / Signature Utility** | Webhook Service (1), Financial Processing (2) | **Candidate for Starter:** Reusable HMAC-SHA256 signature generator for webhooks and signed download URLs. Meets the 2-consumer rule for starter consideration. |
| **Idempotency Store Middleware** | Webhook Service (1), Financial Processing (2) | **Candidate for Starter:** Generic `Idempotency-Key` header verification with cache/database backing. Under architectural evaluation. |

---

## 15. Known Non-Blocking Limitations

1. **Native Streaming Helper:** Binary file responses currently require constructing standard PSR-7 `Response` instances with explicit headers in userland code.
2. **Built-in CLI Runner:** The framework focuses purely on HTTP request handling; CLI commands are implemented via native PHP scripts or standalone runners.

---

## 16. Architecture Diagram

```
+-------------------------------------------------------------------------+
|                         micro/framework v0.3.0                          |
|                         Generic Infrastructure                          |
|  - Kernel / Request Handler        - FastRoute Dispatcher               |
|  - PSR-11 DI Container             - PSR-15 Middleware Pipeline         |
|  - RequestId / AccessLog / CORS    - JsonResponse & ErrorHandler        |
|  - PDO Connection & Transactions                                        |
+------------------------------------+------------------------------------+
                                     │
                                     ▼
+-------------------------------------------------------------------------+
|                        php-micro-starter v0.3.0                         |
|                    Canonical Application Bootstrap                      |
|  - Entrypoint (public/index.php)   - AppProvider Module Binding         |
|  - Health Probe Controller         - Concrete JsonLogger (PSR-3)        |
+------------------------------------+------------------------------------+
                                     │
                                     ▼
+-------------------------------------------------------------------------+
|                          Consumer Application                           |
|                 Domain + Business Rules + Integrations                  |
|  - Domain Entities & Calculators   - Repositories & SQL Schemas         |
|  - Authentication & Authorization  - Export Generators & REST DTOs      |
+-------------------------------------------------------------------------+
```

---

## 17. Stable Baseline Declaration

`micro/framework v0.3.0` is the authoritative stable baseline for the validated ecosystem.

Its architecture is intentionally small, generic, PSR-oriented, and domain-agnostic.

Future framework evolution must be driven by repeated, evidence-backed requirements from independent consumer applications.
