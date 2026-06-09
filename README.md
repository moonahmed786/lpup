# LPUP

LPUP is a Laravel application for product management, API access, and bulk product imports. It includes Passport authentication, role-based permissions, a Filament admin panel, and a queued CSV/XLSX import workflow with progress tracking.

## Tech Stack

- PHP 8.3+
- Laravel 13
- MySQL 8 for Docker/local app runtime
- SQLite for simple host-side development and tests
- Laravel Passport for API tokens
- spatie/laravel-permission for roles and permissions
- Filament 5 for the admin panel
- Maatwebsite Excel for CSV/XLSX imports
- Pest for tests

## Features

- Product CRUD API with JSON:API responses
- Passport login, logout, and `/api/me`
- Product policies backed by roles and permissions
- Filament admin panel for products, users, roles, permissions, and imports
- Bulk product import from `.csv` or `.xlsx`
- Import progress tracking with processed/failed row counts
- Failure CSV download for invalid rows
- Start/stop controls for import jobs
- Docker setup with PHP-FPM, Nginx, MySQL, Redis, and a queue worker

## Local Setup

Install dependencies and create the local environment file:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

The default `.env.example` uses SQLite. Create the local database and run migrations:

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan passport:keys
php artisan passport:client --personal --name="LPUP Personal Access Client"
```

Start the local server:

```bash
php artisan serve
```

The app will be available at `http://127.0.0.1:8000`.

## Docker Setup

Docker is the easiest way to run the full stack with MySQL, Redis, Nginx, and a queue worker:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan passport:keys --force
docker compose exec app php artisan passport:client --personal \
    --name="LPUP Personal Access Client" --provider=users --no-interaction
```

Open the app at:

- Admin panel: `http://localhost:8000/admin`
- API base URL: `http://localhost:8000/api`
- MySQL host connection: `127.0.0.1:3307`

Default database credentials in Docker:

- Database: `lpup`
- Username: `lpup`
- Password: `secret`

Useful Docker commands:

```bash
docker compose logs -f app worker web
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan queue:restart
docker compose restart worker
docker compose down
docker compose down -v
```

## Admin Panel

Filament is mounted at:

```text
/admin
```

Seeded admin users use `password` as the password:

- `superadmin@example.com`
- `admin@example.com`

Panel access is limited to the `SuperAdmin` and `Admin` roles.

## Product Imports

Imports are available from the Products page and Product Imports page in Filament.

Accepted file types:

- `.csv`
- `.xlsx`

Supported columns:

| Column | Required | Notes |
| --- | --- | --- |
| `name` | Yes | Product name |
| `sku` | Yes | Unique product SKU |
| `quantity` | Yes, unless `stock` exists | Stored as product quantity |
| `stock` | Yes, unless `quantity` exists | Accepted as a quantity alias |
| `price` | No | Numeric value |
| `description` | No | Product description |
| `status` | No | `active`, `inactive`, or `draft`; defaults to `draft` |

Extra columns such as `category` are ignored.

The import table shows:

- Current status
- Progress percentage
- Processed and total row counts
- Failed row count
- Failure CSV download, when invalid rows exist
- Start and Stop actions for background imports

Stop is cooperative: a running import stops after the current chunk finishes. Pending imports stop before they start.

## API

Authentication:

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/login` | Issue a personal access token |
| `POST` | `/api/logout` | Revoke the current token |
| `GET` | `/api/me` | Return the current user, roles, and permissions |

Products:

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/api/products` | `products.viewAny` |
| `POST` | `/api/products` | `products.create` |
| `GET` | `/api/products/{product}` | `products.view` |
| `PUT/PATCH` | `/api/products/{product}` | `products.update` |
| `DELETE` | `/api/products/{product}` | `products.delete` |

Use the token from `/api/login` as a bearer token:

```bash
curl -H "Authorization: Bearer <token>" http://localhost:8000/api/products
```

## Roles and Permissions

Seeded roles:

| Role | Access |
| --- | --- |
| `SuperAdmin` | Full access |
| `Admin` | Manage products and users |
| `User` | Read-only product API access |

Roles and permissions are managed from the Filament admin panel.

## Testing

Run the full test suite:

```bash
php artisan test
```

Run the main feature suites:

```bash
php artisan test tests/Feature/ProductImportTest.php tests/Feature/ProductRbacTest.php
```

Format code:

```bash
./vendor/bin/pint
```

## Notes for Local Development

- Local `.env`, SQLite databases, Passport keys, logs, generated import files, and editor/tool folders are ignored by Git.
- Docker uses MySQL through environment variables in `docker-compose.yml`.
- Host-side tests can stay on SQLite for a faster feedback loop.
- After changing queue job code, restart the worker with `docker compose restart worker` or `php artisan queue:restart`.
