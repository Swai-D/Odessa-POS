# Odessa POS Contributor Notes

## Stack

Laravel 12, PHP 8.3, PostgreSQL 16, Vite, Pest, Pint, and Larastan level 5. Queue, cache, and session drivers are database-backed. Local supporting services are PostgreSQL, Redis, and Mailpit in `docker-compose.yml`; Laravel Sail is not used.

## Template preservation

The purchased Dreams POS HTML and assets must remain exactly as supplied. Keep the static files in `public/assets/` and the original source in `resources/template-original/`; never edit, rename, minify, or modernize these files. Build Blade wrappers around them. Do not replace Bootstrap/jQuery with Tailwind. The sidebar's permitted navigation comes from `config/menu.php`.

## Tenancy

Every tenant-owned Eloquent model uses `App\Models\Concerns\BelongsToTenant`. Never query across tenants by bypassing that global scope except in explicit, reviewed super-admin/system operations. Resolve tenants by subdomain/custom domain; local-only header/session overrides must not be enabled in production. Use `TenantContext::run()` for scoped work and do not retain context between requests or jobs. Queued work can attach `RestoreTenantContext`; serialized queue payloads carry the tenant ID.

## Code conventions

Keep controllers thin. Place behavior in Actions/Services, request validation in FormRequests, and authorization in Policies. Store money as integer minor units and format with `App\Support\Money`. Default tenant currency is TZS. Optional hardware and payment integrations remain disabled by default and are tenant-configurable.

Do not add business workflows until requested. Keep the domain folders organized under `app/Domain/{Catalog,Sales,Purchasing,Inventory,People,Finance,Settings}`.

## Commands

```sh
php artisan migrate --seed
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse --level=5
npm run build
composer audit
```

PHPUnit/Pest uses SQLite in memory. See `docs/ARCHITECTURE.md` for the tenancy and template boundaries.