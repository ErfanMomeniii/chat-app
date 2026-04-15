# Chat Application Backend

RESTful JSON API for group chat. PHP 8.3, Slim 4, SQLite.

## Quick Start

```bash
make build && make run
```

Or without Docker (requires PHP 8.1+ with pdo_sqlite):

```bash
make install
make start   # http://localhost:8080
```

Run tests:

```bash
make test                # All (221 tests)
make test-unit           # Repository + service tests
make test-integration    # HTTP action tests
make lint                # PSR-12 code style check
```

## Makefile Targets

| Target | Description |
|--------|-------------|
| `make install` | Install Composer dependencies |
| `make test` | Run all tests |
| `make test-unit` | Run unit tests only |
| `make test-integration` | Run integration tests only |
| `make start` | Start local dev server on port 8080 |
| `make lint` | Check PSR-12 code style |
| `make build` | Build Docker image |
| `make run` | Run Docker container on port 8080 |

## API

Every request (except `/health`) requires an `X-User-Id` header (alphanumeric, 1-64 chars, e.g. `alice`, `user-42`).

| Method | Endpoint | Description | Body |
|--------|----------|-------------|------|
| `GET` | `/health` | Health check (no auth required) | -- |
| `POST` | `/groups` | Create a group | `{"name": "...", "description": "..."}` |
| `GET` | `/groups` | List groups (paginated) | -- |
| `GET` | `/groups/{id}` | Get a group | -- |
| `PATCH` | `/groups/{id}` | Update a group (creator only) | `{"name": "...", "description": "..."}` |
| `DELETE` | `/groups/{id}` | Delete a group (creator only) | -- |
| `POST` | `/groups/{id}/members` | Join a group | -- |
| `DELETE` | `/groups/{id}/members` | Leave a group | -- |
| `GET` | `/groups/{id}/members` | List members (paginated) | -- |
| `POST` | `/groups/{id}/messages` | Send a message (members only) | `{"content": "..."}` |
| `GET` | `/groups/{id}/messages` | List messages (paginated, members only) | -- |

**Pagination** is supported on groups, members, and messages via cursor parameters: `?limit=50&before_id=123`. Returns newest first. Default limit is 50, max 100. Response includes a `meta` object with `count` and `has_more` fields.

**Group creation** automatically adds the creator as a member (atomic transaction).

**Group update/delete** is restricted to the group creator. `PATCH` updates name and description; `DELETE` cascades to members and messages via foreign key constraints.

**Health endpoint** returns `{"status":"ok"}` (200) when the database is reachable, or `{"status":"error"}` (503) otherwise. No authentication required.

### Error Format

```json
{"error": {"type": "validation_error", "message": "Field 'name' must be 1-100 characters"}}
```

| Status | Type | When |
|--------|------|------|
| 400 | `bad_request` | Invalid `X-User-Id` format |
| 401 | `unauthorized` | Missing `X-User-Id` header |
| 403 | `forbidden` | Not a group member |
| 404 | `not_found` | Group does not exist |
| 405 | `method_not_allowed` | Wrong HTTP method |
| 422 | `validation_error` | Invalid input |

### Example Session

```bash
# Health check
curl localhost:8080/health

# Alice creates a group
curl -X POST localhost:8080/groups \
  -H "X-User-Id: alice" -H "Content-Type: application/json" \
  -d '{"name": "general", "description": "General chat"}'

# Bob joins
curl -X POST localhost:8080/groups/1/members -H "X-User-Id: bob"

# Alice sends a message
curl -X POST localhost:8080/groups/1/messages \
  -H "X-User-Id: alice" -H "Content-Type: application/json" \
  -d '{"content": "Hello everyone"}'

# Bob reads messages
curl localhost:8080/groups/1/messages -H "X-User-Id: bob"

# List groups with pagination
curl -H "X-User-Id: alice" "localhost:8080/groups?limit=10"

# Alice updates the group
curl -X PATCH localhost:8080/groups/1 \
  -H "X-User-Id: alice" -H "Content-Type: application/json" \
  -d '{"name": "general-chat", "description": "Updated description"}'

# Bob leaves
curl -X DELETE localhost:8080/groups/1/members -H "X-User-Id: bob"

# Alice deletes the group
curl -X DELETE localhost:8080/groups/1 -H "X-User-Id: alice"
```

## Architecture

The project follows the **ADR (Action-Domain-Responder)** pattern with a layered domain:

```
src/
  Action/                          Handles HTTP requests, delegates to Domain
    HealthAction.php               Database health check (no auth)
    Group/                         CreateGroup, GetGroup, ListGroups, UpdateGroup, DeleteGroup
    Member/                        JoinGroup, LeaveGroup, ListMembers
    Message/                       SendMessage, ListMessages
  Domain/                          Core business logic, no HTTP awareness
    Entity/                        Immutable readonly value objects (Group, Member, Message)
    Repository/                    Data access via PDO prepared statements
    Service/                       Business rules, transactions, orchestration
    Exception/                     Domain exceptions mapped to HTTP status codes
  Responder/                       Builds HTTP responses (JSON encoding, status codes)
  Infrastructure/                  Cross-cutting technical concerns
    Http/                          Error handler, request validation, auth + security middleware
    Persistence/                   Database factory, migration runner (sequential SQL migrations)
```

Dependency direction: `Action -> Service -> Repository -> PDO`. Actions never touch PDO directly; transaction orchestration lives in `GroupService`, keeping the Action layer purely about HTTP request/response handling.

### Database Schema

Three tables in SQLite with foreign keys and indexes:

- **groups** -- `id`, `name` (UNIQUE), `description`, `created_by`, `created_at`
- **members** -- `id`, `group_id`, `user_id`, UNIQUE `(group_id, user_id)`, `joined_at`, FK -> groups with `ON DELETE CASCADE`, indexed on `(group_id, id)` for cursor pagination
- **messages** -- `id`, `group_id` (FK -> groups), `user_id`, `content`, `created_at`, indexed on `(group_id, created_at)`

Pragmas: `foreign_keys=ON`, `journal_mode=WAL`.

### Observability

Structured logging via **Monolog** (PSR-3 `LoggerInterface`), writing JSON to stderr:

- **All state mutations** are logged with context: group creation (with group_id, name, created_by), member join/leave (group_id, user_id), message send (message_id, group_id, user_id, content_length)
- **Request correlation IDs** via `X-Request-Id` header — generated (UUID v4) or forwarded from upstream. Included in all log entries for distributed tracing.
- **Error handler** logs all exceptions with request ID, HTTP method, path, status code, exception class, and optional stack trace
- **Health check failures** logged at CRITICAL level with exception details
- **Duplicate group name attempts** logged at NOTICE level with the attempted name and user

Log level is configurable: `APP_DEBUG=1` enables DEBUG level, production defaults to INFO.

### Security

**Input protection** (defense-in-depth, multiple layers):

- **SQL injection:** All queries use PDO prepared statements with bound parameters. No string interpolation touches SQL.
- **XSS:** The API exclusively returns `Content-Type: application/json`. Browsers will not render JSON as HTML, preventing reflected XSS. `X-Content-Type-Options: nosniff` prevents MIME-type sniffing that could override this. Clients consuming this API must escape content when rendering to HTML.
- **Content-type enforcement:** Slim's body parsing middleware only parses `application/json` bodies. Other content types are rejected by the `RequestValidator`.
- **User ID validation:** Strict regex `^[a-zA-Z0-9_-]{1,64}$` at the middleware level, before any business logic executes.
- **Input validation:** All string inputs are length-validated and trimmed. Query parameters are type-checked with explicit min/max bounds.

**Security headers** applied to every response via `SecurityHeadersMiddleware`:

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevents MIME-type sniffing |
| `X-Frame-Options` | `DENY` | Prevents clickjacking via iframes |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` | Restricts resource loading |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enforces HTTPS |
| `Cache-Control` | `no-store, no-cache, must-revalidate` | Prevents caching of API responses |

### Tests

221 tests, 1071 assertions across 20 files:

- **Unit tests** (56) -- Repository, Service, Entity, and Migration layer with in-memory SQLite
- **Integration tests** (165) -- Full HTTP stack per Action (including CRUD for groups), middleware tests (auth, security headers, correlation IDs), and an end-to-end user journey test covering create -> join -> message -> read -> leave -> access revoked

## Design Decisions

**ADR over MVC.** Each route is a single invokable class. No bloated controllers, clear call graph: one URL = one file.

**Domain Service layer.** Business rules like "group must exist" live in `GroupService`, not in repositories (data access) or actions (HTTP handling). Repositories stay neutral -- they return `null` for missing records, not exceptions. Transaction orchestration (group creation + auto-join) lives in the Service layer, keeping Actions free of PDO concerns.

**Cursor pagination.** `before_id` with `WHERE id < ?` uses the index directly, O(1) regardless of page depth. `OFFSET` would scan and discard rows, degrading at depth. Applied consistently to groups, members, and messages. The `members` table has a composite index `(group_id, id)` to support efficient cursor queries at scale.

**Structured logging.** PSR-3 `LoggerInterface` (Monolog) is injected into all mutating actions and the error handler. Every state change logs who did what to which resource, enabling audit trails and debugging without attaching a debugger at 3 AM.

**Sequential migrations.** Schema changes live in numbered `.sql` files under `database/migrations/`. A lightweight `MigrationRunner` tracks applied migrations in a `schema_migrations` table, runs pending files in sorted order inside transactions, and rolls back on failure. No heavy ORM dependency needed — just plain SQL files respecting SQLite constraints.

**Production Docker.** The container runs Nginx + PHP-FPM, not `php -S`. Nginx handles static files, connection management, and proxying; PHP-FPM provides process management with configurable workers. Entrypoint starts FPM as a daemon, then Nginx in the foreground.

**Health endpoint.** `GET /health` runs `SELECT 1` against the database and returns 200/503. Excluded from authentication middleware so it can be used by Docker HEALTHCHECK and load balancers without credentials.

**Security headers.** `SecurityHeadersMiddleware` applies defense-in-depth headers to every response. Combined with JSON-only responses and strict input validation, this covers OWASP top 10 vectors relevant to a REST API.
