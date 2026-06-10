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
- Passport login with an HTTP-only API cookie, logout, and `/api/me`
- Product policies backed by roles and permissions
- Filament admin panel for products, users, roles, permissions, and imports
- Swagger UI and OpenAPI JSON documentation
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
php artisan lpup:install-passport
```

Start the local server:

```bash
php artisan serve
```

The app will be available at `http://127.0.0.1:8000`.

## Local Docker Setup

Docker is the easiest way to run the full stack with MySQL, Redis, Nginx, and a queue worker:

```bash
cp .env.example .env
# Set DB_PASSWORD and MYSQL_ROOT_PASSWORD in .env before starting Docker.
docker compose up -d --build
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan lpup:install-passport
```

Open the app at:

- Admin panel: `http://localhost:8000/admin`
- API base URL: `http://localhost:8000/api`
- Swagger docs: `http://localhost:8000/docs/api`
- MySQL host connection: `127.0.0.1:3307`

Default database credentials in Docker:

- Database: `lpup`
- Username: `lpup`
- Password: value of `DB_PASSWORD` in `.env`

Useful Docker commands:

```bash
docker compose logs -f app worker web
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan queue:restart
docker compose restart worker
docker compose down
docker compose down -v
```

## Production Docker Setup

Use `docker-compose.production.yml` for a deployable container layout. It builds self-contained app and web images, does not bind-mount the project source, keeps Laravel storage in a named Docker volume, does not publish MySQL to the host, runs Redis-backed cache/session/queue settings, and disables demo users by default.

The production compose file is intended to sit behind an HTTPS reverse proxy or load balancer. It sets secure session cookies and trusts forwarded proxy headers so Laravel generates HTTPS-aware URLs when the proxy sends `X-Forwarded-Proto: https`.

Required environment variables:

```bash
export APP_URL=https://your-domain.example
export APP_KEY='base64:paste-key-from-php-artisan-key-generate-show'
export DB_DATABASE=lpup
export DB_USERNAME=lpup
export DB_PASSWORD='use-a-long-random-password'
export MYSQL_ROOT_PASSWORD='use-a-different-long-random-password'
export TRUSTED_PROXIES='*'
export APP_IMAGE_TAG=latest
```

Start the stack:

```bash
docker compose -f docker-compose.production.yml up -d --build
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan lpup:install-passport
docker compose -f docker-compose.production.yml exec app php artisan config:cache
docker compose -f docker-compose.production.yml exec app php artisan route:cache
docker compose -f docker-compose.production.yml exec app php artisan view:cache
```

Back up and restore MySQL:

```bash
scripts/backup-mysql.sh
scripts/restore-mysql.sh backups/mysql/lpup-YYYYMMDDTHHMMSSZ.sql.gz
```

Production notes:

- Set `APP_KEY` with `php artisan key:generate --show` and provide it through your environment or secret manager.
- Keep `APP_DEBUG=false`.
- Keep `SEED_DEMO_USERS=false` in production.
- Terminate TLS before traffic reaches the app. Use a reverse proxy, load balancer, or platform ingress that forwards `X-Forwarded-*` headers.
- Keep `SESSION_SECURE_COOKIE=true` in production. The production compose file sets this for app and worker containers.
- Use shared storage, such as S3 or a mounted shared volume, when running multiple web/worker replicas. The production Docker compose file mounts the Laravel `storage` directory into app and worker containers. Set `PRODUCT_IMPORT_DISK` accordingly if you move imports to S3.
- Keep `DB_QUEUE_RETRY_AFTER` and `REDIS_QUEUE_RETRY_AFTER` above the import job timeout. The default is `1200`, while the worker timeout is `900`.
- Passport key files must be owned by the app user and use `600` or `660` permissions.
- Schedule `scripts/backup-mysql.sh` from cron or your deployment platform and copy the generated files to durable off-server storage.

## Admin Panel

Filament is mounted at:

```text
/admin
```

Seeded admin users use `password` as the password:

- `superadmin@example.com`
- `admin@example.com`

Panel access is limited to the `SuperAdmin` and `Admin` roles.
The seeded `user@example.com` account also uses `password`, but it is intentionally limited to read-only product API access and cannot sign in to the Filament admin panel.

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

### Duplicate handling

Imports only **insert new products**; they never overwrite existing data:

- A row whose `sku` already exists in the database is skipped and recorded in the failures CSV as `duplicate sku (already exists)`.
- A `sku` that repeats within the same uploaded file keeps the first occurrence and records the later ones as `duplicate sku in file`.

Both count toward the failed-row total, so re-uploading the same file results in every row being reported as a duplicate rather than changing any products.

### Upload size limits

The maximum upload size is controlled by `PRODUCT_IMPORT_MAX_UPLOAD_KB` (default `102400`, i.e. 100 MB), which feeds both the Filament `FileUpload` validation and the Livewire temporary-upload rules. The production Docker image already sets matching PHP and nginx limits (`docker/php/php.ini`, `docker/nginx/default.conf`).

For local development, PHP's own limits must be at least as large, otherwise larger files are silently rejected by PHP before validation runs. Ensure your local `php.ini` has:

```ini
upload_max_filesize = 100M
post_max_size = 100M
```

Verify with `php -i | grep -E "upload_max_filesize|post_max_size"` and restart your PHP server after changing them.

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
| `POST` | `/api/login` | Issue a personal access token in an HTTP-only cookie |
| `POST` | `/api/logout` | Revoke the current token and clear the cookie |
| `GET` | `/api/me` | Return the current user, roles, and permissions |

Products:

| Method | Path | Ability |
| --- | --- | --- |
| `GET` | `/api/products` | `products.viewAny` |
| `POST` | `/api/products` | `products.create` |
| `GET` | `/api/products/{product}` | `products.view` |
| `PUT/PATCH` | `/api/products/{product}` | `products.update` |
| `DELETE` | `/api/products/{product}` | `products.delete` |

`/api/login` does not return the access token in the JSON body. It sets the token in the `lpup_token` HTTP-only cookie so browser clients can authenticate without storing tokens in JavaScript-readable storage.

Example login and authenticated request with cookies:

```bash
curl -i -c cookies.txt \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{"email":"user@example.com","password":"password"}' \
    http://localhost:8000/api/login

curl -b cookies.txt \
    -H "Accept: application/json" \
    http://localhost:8000/api/products
```

Swagger UI is available at `/docs/api`, and the OpenAPI JSON document is available at `/docs/openapi.json`.

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
