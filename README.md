# lpup

A Laravel 13 API scaffold with OAuth2 (Passport), role-based access control
(spatie/laravel-permission), and a products resource exposed as JSON:API.

This is **phase 1** of the build described in [`SPEC.md`](SPEC.md): authentication,
RBAC, the products schema, and basic CRUD. The bulk Excel **import pipeline is
intentionally not implemented yet** — see [Out of scope](#out-of-scope-next-phase).

## Stack (verified against Laravel 13)

| Component | Version | L13 compatible |
|-----------|---------|----------------|
| laravel/framework | `^13.8` (running 13.15) | — |
| laravel/passport | `^13` (`^11.35\|^12.0\|^13.0`) | ✅ |
| spatie/laravel-permission | `^8` (`^12.0\|^13.0`) | ✅ |
| pestphp/pest | `^4` | ✅ |
| filament/filament | `^5` (`illuminate ^11.28\|^12.0\|^13.0`) | ✅ |
| livewire/livewire | `^4` (filament dep) | ✅ |
| PHP | 8.3+ (built on 8.5) | ✅ |

> **filament-shield was evaluated and rejected:** the latest release (4.2) requires
> `spatie/laravel-permission ^6\|^7` and targets Filament v3 — incompatible with our
> spatie 8 + Filament 5. We bridge spatie RBAC into the panel directly instead (see
> [Admin panel](#admin-panel-filament)).

All three packages were confirmed to declare Laravel 13 support before install,
so no version fallback/workaround was needed (the SPEC asked us to fall back and
document if Passport 13 wasn't tagged — it is).

## Laravel 13 features used

These are genuine first-party L13 APIs (verified against the `13.x` source), not
constructor-middleware or third-party equivalents:

- **`#[Middleware]` controller attribute** — `Illuminate\Routing\Attributes\Controllers\Middleware`.
  `ProductController` is annotated `#[Middleware('auth:api')]`; `AuthController::logout/me`
  use it per-method. Replaces the constructor `$this->middleware()` calls removed in L11.
- **`#[Authorize]` controller attribute** — `Illuminate\Routing\Attributes\Controllers\Authorize`,
  e.g. `#[Authorize('update', 'product')]`. It builds the authorize middleware and runs the
  matching `ProductPolicy` ability, which checks a spatie permission.
- **`JsonApiResource`** — `Illuminate\Http\Resources\JsonApi\JsonApiResource`.
  `ProductResource` declares `$attributes` and emits
  `{"data":{"type":"products","id":"1","attributes":{…}}}` with sparse-fieldset support.
- **`#[UsePolicy]` model attribute** — `Product` binds `ProductPolicy` via
  `#[UsePolicy(ProductPolicy::class)]` instead of registering it in a provider.
- **`#[Fillable]` / `#[Hidden]` model attributes** — used on `User` (shipped by the skeleton).
- **Attribute-based casts** via the `casts()` method on models.

> `PreventRequestForgery` (L13's enhanced CSRF middleware, mentioned in the SPEC) is real
> and guards the `web` group. This API is token-authenticated and stateless, so CSRF does
> not apply to it; it is intentionally not wired onto the `api` routes.

## RBAC model

Roles and permissions are created on the **`api` guard** (matching Passport), and the
`User` model pins `protected string $guard_name = 'api'`.

| Role | Permissions |
|------|-------------|
| **SuperAdmin** | everything (also short-circuited by `ProductPolicy::before()`) |
| **Admin** | `products.{viewAny,view,create,update,delete}` + `users.manage` |
| **User** | `products.viewAny`, `products.view` (read only) |

Permissions are mapped to policy abilities in `app/Policies/ProductPolicy.php`.

## Setup

```bash
composer install
cp .env.example .env        # already present; defaults to SQLite
php artisan key:generate

# Database (SQLite by default — file lives at database/database.sqlite)
touch database/database.sqlite
php artisan migrate

# Passport: generate signing keys + a personal access client
php artisan passport:keys
php artisan passport:client --personal --name="lpup Personal Access Client"

# Seed roles, permissions, demo users (superadmin@/admin@/user@example.com), products
php artisan db:seed
```

To use MySQL instead, set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`,
or run the app with Docker (below), which provides MySQL automatically.

## Running with Docker (MySQL)

A Docker stack runs the app on **PHP 8.3-FPM + nginx + MySQL 8 + Redis 7**.

```bash
# 1. Start Docker Desktop, then build + start the stack
docker compose up -d --build

# 2. One-time app setup (inside the app container)
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan passport:keys --force
docker compose exec app php artisan passport:client --personal \
    --name="lpup Personal Access Client" --provider=users --no-interaction
```

Then open:

- API: <http://localhost:8000/api> (e.g. `POST /api/login`)
- Admin panel: <http://localhost:8000/admin> (`superadmin@example.com` / `password`)
- MySQL from the host: `127.0.0.1:3307`, db `lpup`, user `lpup` / `secret`

### How the database is selected

`.env` keeps `DB_CONNECTION=sqlite`, so **host tooling and the Pest suite stay on
SQLite** with zero setup. The `app` service in `docker-compose.yml` sets real
environment variables (`DB_CONNECTION=mysql`, `DB_HOST=mysql`, …). Laravel's
immutable dotenv does **not** overwrite existing environment variables, so inside
the containers MySQL wins — no `.env` juggling required.

### Common commands

```bash
docker compose exec app php artisan migrate:fresh --seed   # reset DB
docker compose exec app php artisan tinker
docker compose exec app composer install                   # if vendor/ is empty
docker compose logs -f web app                             # tail logs
docker compose down                                        # stop (keep data)
docker compose down -v                                     # stop + drop MySQL volume
```

Redis is running and reachable at `redis:6379`; the app defaults to the database
cache/queue drivers — set `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis` on the
`app` service to switch.

> The `app` container's entrypoint runs `composer install` automatically if
> `vendor/` is missing (e.g. a fresh clone) and makes `storage/` writable.

## API

| Method | Path | Auth | Ability |
|--------|------|------|---------|
| POST | `/api/login` | public | — issues a personal access token |
| POST | `/api/logout` | `auth:api` | revokes current token |
| GET  | `/api/me` | `auth:api` | current user + roles/permissions |
| GET  | `/api/products` | `auth:api` | `viewAny` |
| POST | `/api/products` | `auth:api` | `create` |
| GET  | `/api/products/{product}` | `auth:api` | `view` |
| PUT/PATCH | `/api/products/{product}` | `auth:api` | `update` |
| DELETE | `/api/products/{product}` | `auth:api` | `delete` (soft delete) |

`POST /api/login` returns a Passport **personal access token**. The OAuth2
**password grant** (first-party) and **client credentials** (server-to-server)
flows remain available through Passport's standard `POST /oauth/token` endpoint —
create the corresponding clients with `php artisan passport:client --password` and
`php artisan passport:client --client`.

## Admin panel (Filament)

A Filament v5 panel is mounted at **`/admin`** for managing products.

```bash
php artisan serve
# visit http://127.0.0.1:8000/admin
```

**Login** with a seeded admin account (password is `password`):

- `superadmin@example.com` / `password`
- `admin@example.com` / `password`

**Access control without Shield.** `User` implements `Filament\Models\Contracts\FilamentUser`,
and `canAccessPanel()` allows only the `SuperAdmin` / `Admin` roles. Because the `User`
model pins `protected string $guard_name = 'api'`, those role checks resolve against the
**same `api`-guard roles** the API uses — so there's no duplicate web-guard RBAC to
maintain. The read-only `User` role is intentionally denied panel access.

The Filament `ProductResource` (`app/Filament/Resources/Products/`) was generated from the
products schema: it edits `name`, `sku`, `quantity`, and the `status` enum, with a
soft-delete trash filter and restore/force-delete bulk actions.

> Pre-built Filament assets are published to `public/` by `filament:install`, so no
> `npm` build is required to run the panel.

## Tests

Pest feature tests cover the auth flows and the full RBAC matrix:

```bash
./vendor/bin/pest
```

- `tests/Feature/AuthTest.php` — token issuance, invalid credentials, payload
  validation, `/me` auth gating, logout.
- `tests/Feature/ProductRbacTest.php` — per-role allow/deny for index/show/create/
  update/delete (User read-only, Admin manage, SuperAdmin full), plus validation
  and soft-delete assertions.

Tests run against an in-memory SQLite database (`phpunit.xml`).

## Out of scope (next phase)

Deliberately **not** built yet, per the "stop before import logic" instruction:

- `POST /api/products/import` (xlsx upload → `Bus::batch()` of chunked jobs)
- `ProductImport` model + `GET /api/products/import/{id}/progress`
- `ProcessProductImport` job using the (verified-real) L13 queue attributes
  `#[Tries]`, `#[Backoff]`, `#[Timeout]`, `#[FailOnTimeout]`, queue pinning, and
  idempotent `DB::table()->upsert()` chunk processing.
- Horizon, Redis queue wiring, and the `docker-compose.yml` services.

The products schema (`sku` unique, soft deletes), RBAC, and resource layer are
already shaped to support that import work when it lands.
