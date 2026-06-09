claude "Build a production-grade Laravel 13 application. Read SPEC.md first, then produce a plan before writing code. Stop and confirm package compatibility with Laravel 13 before composer require.

Stack: Laravel 13, PHP 8.3+, MySQL 8, Redis (queues + cache), Laravel Passport for OAuth2 (verify L13 compatibility — fall back to latest supported version with a note if not yet released), spatie/laravel-permission for RBAC, maatwebsite/excel for imports, Laravel Horizon for queue monitoring, Pest for tests.

Use Laravel 13 idioms throughout:
- #[Middleware] and #[Authorize] PHP attributes on controllers instead of constructor middleware calls
- #[Tries(3)], #[Backoff([10,30,60])], #[Timeout(600)], #[FailOnTimeout] attributes on the import job class
- Queue::route(ProcessProductImport::class, connection: 'redis', queue: 'imports') in a service provider
- PreventRequestForgery middleware (Laravel 13's enhanced CSRF)
- Cache::touch() where TTL refresh is needed instead of get-then-set patterns
- JSON:API Resources for product list/detail responses (use first-party JsonApiResource, not legacy JsonResource)

Features:
1. RBAC with spatie/laravel-permission. Three roles seeded: SuperAdmin (all permissions), Admin (manage users + products), User (read products only). Use #[Authorize] attributes on controller methods. Policy classes for Product model.

2. OAuth2 via Laravel Passport — password grant for first-party clients, client credentials for server-to-server, personal access tokens. Token revocation endpoint. If Passport 13 is not yet stable, document the workaround clearly in README.

3. Products table: id, name, sku (unique, indexed), quantity (unsignedInteger), status (enum: active/inactive/draft), timestamps, soft deletes. Factory + seeder.

4. Excel import for 200,000 rows:
   - POST /api/products/import accepts .xlsx, stores file, dispatches a Bus::batch() of chunked jobs, returns batch_id immediately
   - maatwebsite/excel with WithChunkReading (1000 rows), ShouldQueue, WithBatchInserts, SkipsOnError, SkipsOnFailure
   - Import job uses Laravel 13 attributes: #[Tries(3)], #[Backoff([10,30,60])], #[Timeout(900)], #[FailOnTimeout]
   - Queue::route() pins it to a dedicated 'imports' queue on Redis
   - ProductImport model: id, user_id, filename, total_rows, processed_rows, failed_rows, status, error_log_path, batch_id, started_at, completed_at
   - GET /api/products/import/{id}/progress returns percentage, processed, failed, status, ETA. Reads from Bus batch + ProductImport model.
   - Per-row validation: sku unique, quantity >= 0, status in enum. Failed rows written to a separate CSV, non-blocking.
   - Idempotent: upsert on sku, so re-imports are safe.
   - Memory-safe: chunked reading, raw upserts via DB::table()->upsert() rather than Eloquent hydration during chunks.

Deliverables:
- Migrations, models, factories, seeders
- Thin controllers, logic in services (ProductImportService, AuthService)
- FormRequest classes for validation
- JSON:API Resources for product responses
- Pest tests: feature tests for auth flows, RBAC enforcement, import endpoint dispatch; unit tests for ProductImportService with a 10k-row fixture verifying chunk processing, progress updates, and idempotent re-imports
- OpenAPI docs (verify l5-swagger Laravel 13 support; if not ready, use scramble or generate manually)
- README with setup, .env.example, queue worker commands, and a note on which Laravel 13 features are used
- docker-compose.yml: php-fpm 8.3, nginx, mysql 8, redis 7, horizon

Plan first, await my approval, then implement in logical commits."