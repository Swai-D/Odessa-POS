# Odessa POS

Laravel 12 multi-tenant point-of-sale foundation targeting PHP 8.3 and PostgreSQL 16. The repository contains project setup only; catalog, sales, inventory, purchasing, people, finance, and settings workflows are not implemented.

## Requirements

- PHP 8.3 with PostgreSQL, SQLite, XML, mbstring, ZIP, and GD extensions
- Composer 2
- Node.js 20+ and npm
- Docker Compose for the local PostgreSQL, Redis, and Mailpit services

## Setup

```sh
cp .env.example .env
docker compose up -d postgres redis mailpit
composer install
php artisan key:generate
```

Set `SUPER_ADMIN_PASSWORD` and `DEMO_OWNER_PASSWORD` in `.env` before seeding. Then run:

For local Compose only, set `DB_PASSWORD=odessa_dev` in `.env`; choose a strong secret outside local development.

```sh
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The demo tenant is available at `demo.localhost`; configure local DNS/hosts as needed. On local requests, `X-Tenant: demo` or the `tenant_slug` session value can select a tenant. The super-admin context is the bare `APP_DOMAIN` host.

## Checks

```sh
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse --level=5
npm run build
composer audit
```

Tests use SQLite in memory and do not need PostgreSQL. Queue, cache, and session use their database drivers outside the PHPUnit environment.

## Purchased template

The supplied 265 Dreams POS HTML pages and bundled asset tree are preserved under `resources/template-original/`; the runtime asset copy in `public/assets/` is byte-for-byte identical. The dashboard, POS, sign-in, and error views use template-derived markup. The application sidebar intentionally limits navigation to the permission-filtered entries in `config/menu.php`. Never edit, rename, or reformat the reference pages or static assets.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for tenancy and code conventions.