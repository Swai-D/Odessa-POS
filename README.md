# Odessa POS

A multi-tenant point-of-sale and shop-management SaaS for East African retailers, built by Odessa Lab Technologies.
One Laravel codebase and one PostgreSQL database serve every shop; the shop's **plan** decides which features are on.
The interface is the purchased *Dreams POS* HTML template wrapped in Blade, in English and Swahili, with TZS as the default currency.

This file is the developer's entry point. Read it first, then `CLAUDE.md` (the rules that must not be broken) and `docs/` (one file per subsystem).

- [1. What the product does](#1-what-the-product-does)
- [2. Stack](#2-stack)
- [3. Getting started](#3-getting-started)
- [4. Architecture in one page](#4-architecture-in-one-page)
- [5. Non-negotiable rules](#5-non-negotiable-rules)
- [6. Plans and feature gating](#6-plans-and-feature-gating)
- [7. Subsystems](#7-subsystems)
- [8. Configuration reference](#8-configuration-reference)
- [9. Testing and CI](#9-testing-and-ci)
- [10. Deploying (Railway)](#10-deploying-railway)
- [11. Operations](#11-operations)
- [12. Working on the codebase](#12-working-on-the-codebase)
- [13. Status and roadmap](#13-status-and-roadmap)
- [14. Documentation map](#14-documentation-map)

---

## 1. What the product does

**For a shop** (owner, manager, cashier, accountant and custom roles):

| Area | What is there |
| --- | --- |
| Till (POS) | Product search by name/SKU/barcode, discounts, coupons, cash/card/mobile money/credit payments, held orders, receipts (A4 and thermal), returns, **offline mode** that queues sales and syncs them later |
| Catalog | Products, categories, brands, units, CSV import/export, barcode label printing, optional weighed products, batch/lot numbers and expiry dates |
| Inventory | Warehouses, stock levels, adjustments, transfers, stocktakes, FIFO cost batches, FEFO selling for expiring goods |
| Sales | Sales list, quotations, customer credit and payments, offline sales awaiting review, till closing with cash count |
| Purchasing | Suppliers, purchases, supplier payments |
| Finance | Expenses and categories, profit and loss, branch reports, CSV exports |
| People | Customers (credit limits), users, roles and permissions, audit log |
| Settings | Business profile, tax, currency, optional-feature switches, integrations, custom domain, billing page |
| Help | In-app help centre (EN/SW) from `resources/docs/` |

**For the platform operator** (super admin at the bare `APP_DOMAIN`): shops, plans and prices, subscription renewals and payment history, payment notices, platform overview, shop notices.

**Subscription model:** trial -> paid -> grace period (7 days, works normally with a warning) -> read-only. A shop's data is never deleted when it lapses.

## 2. Stack

Laravel 12, PHP 8.3, PostgreSQL 16, Blade + Bootstrap/jQuery (the template; **not** Tailwind), Vite, Pest, Pint, Larastan level 5.
Queue, cache and session drivers are database-backed. Redis and Mailpit exist in `docker-compose.yml` for local use. Laravel Sail is not used.
Packages in play: Fortify (auth), Spatie Permission (teams = tenant), Spatie Activitylog, Spatie Medialibrary, DomPDF, Laravel Excel.

## 3. Getting started

Requirements: PHP 8.3 (extensions: pgsql, sqlite, xml, mbstring, zip, gd, dom), Composer 2, Node 20+, Docker (for PostgreSQL, Redis, Mailpit).

```sh
cp .env.example .env
docker compose up -d postgres redis mailpit
composer install
php artisan key:generate
# In .env: DB_PASSWORD=odessa_dev (local compose only), SUPER_ADMIN_PASSWORD, DEMO_OWNER_PASSWORD
php artisan migrate --seed
npm ci && npm run build
php artisan serve
```

The seeder creates the super admin (`SUPER_ADMIN_EMAIL`) and a demo shop with slug `demo` on the Enterprise plan (owner `DEMO_OWNER_EMAIL`).
Seeding reads `config('pos.seed')`, so it still works with `config:cache`.

**Reaching a shop locally.** Production resolves shops by host (`{slug}.APP_DOMAIN` or a verified custom domain). Locally you can use the `X-Tenant` header, the session fallback, or `/t/{slug}`.
These overrides must never be enabled in production.

**Background work.** Emails/SMS are queued and reminders are scheduled. Run `php artisan queue:work` and `php artisan schedule:work` while developing anything that sends messages.

### Everyday commands

```sh
php artisan migrate --seed          # database
vendor/bin/pest                     # tests (SQLite in memory)
vendor/bin/pint --test              # code style (drop --test to fix)
vendor/bin/phpstan analyse --level=5
node tests/js/calc.test.mjs && node tests/js/promo.test.mjs && node tests/js/barcode.test.mjs
npm run build                       # Vite
composer audit
```

Custom artisan commands: `subscriptions:remind`, `stock:low-alerts`, `domains:check`, `errors:test`.

## 4. Architecture in one page

```
app/
  Domain/{Catalog,Sales,Purchasing,Inventory,People,Finance,Settings,Integrations}/
      Actions/   one class per business operation (CheckoutAction, CreatePurchaseAction, ...)
      Services/  reusable domain logic (StockService, PromotionService, CustomDomainService, ...)
      Models/    tenant-owned Eloquent models
  Http/Controllers/   thin; Platform/ is the super-admin area
  Http/Middleware/    ResolveTenant, RequireTenant, EnforceSubscription, RequirePlanFeature, SetLocale, SecurityHeaders
  Policies/           authorization
  Support/            Money, Plans, Subscription, TenantSettings, Tenancy/TenantContext, ErrorTracking/, Dns/, Sms/, Help/
config/               plans.php, menu.php, pos.php, integrations.php, azampay.php, billing.php, errors.php, notifications.php
resources/
  template-original/  READ-ONLY purchased template
  views/              Blade wrappers
  docs/{en,sw}/       in-app help articles
public/
  assets/             READ-ONLY template assets (byte-for-byte copy)
  js/                 pos.js, pos-calc.js, pos-promo.js, pos-offline.js, barcode.js, labels.js, escpos-print.js, error-report.js ...
  sw.js, manifest.webmanifest, pwa/   the installable, offline-capable till
tests/                Feature/ (by domain), js/ (calculator tests), fixtures/ (shared PHP+JS cases)
docs/                 one document per subsystem
```

### Tenancy

- One database. Tenants have ULID ids. Every tenant-owned model uses `App\Models\Concerns\BelongsToTenant`, which adds a global scope and fills `tenant_id` on create.
- `ResolveTenant` picks the shop from the host: `{slug}.APP_DOMAIN`, or a **verified** custom domain (`tenants.domain`). The bare `APP_DOMAIN` is the super admin. Unknown shop = 404, suspended = 403.
- `TenantContext::run($tenant, fn)` scopes work (jobs, commands, tests) and restores the previous state in `finally`. Never keep context between requests or jobs.
- Queue payloads carry the tenant id; jobs can use the `RestoreTenantContext` middleware.
- Spatie Permission runs in teams mode with the tenant id as the team key.
- Never bypass the scope except in explicit, reviewed super-admin/system operations (for example `domains:check`, platform pages).

### Money

Integer minor units everywhere (`sale_items.price`, `promotions.value`, ...). Format only with `App\Support\Money`.
The till's JavaScript (`pos-calc.js`, `pos-promo.js`) mirrors the PHP calculators and both run against **shared fixtures** in `tests/fixtures/`. Change one side, change the other, and the fixture.

### Stock and costing

All stock changes go through `StockService::move()`, which writes the `stock_movements` ledger and keeps FIFO **cost batches** (`stock_batches`, allocations) in step in the same transaction. Writing `ProductStock` directly breaks costing.
Products flagged for expiry are sold **FEFO** (earliest expiry first); lot numbers are kept only if the product's flags ask for them. Transfers carry cost, age, lot and expiry to the destination warehouse. See `docs/ARCHITECTURE.md`.

### Views

Blade wrappers around the template. Never edit, rename, minify or modernize `public/assets/` or `resources/template-original/`.
The sidebar is generated from `config/menu.php` (label, icon, route, `permission`, optional `feature`).

## 5. Non-negotiable rules

These come from `CLAUDE.md`; breaking them fails review.

1. **Template is read-only.** No edits to `public/assets/` or `resources/template-original/`. Wrap, don't modify. Keep Bootstrap/jQuery.
2. **Tenant scope is sacred.** `BelongsToTenant` on every tenant-owned model; no cross-tenant queries outside reviewed system code.
3. **Thin controllers.** Behaviour in Actions/Services, validation in FormRequests, authorization in Policies.
4. **Money is an integer.** TZS default; `Money` for formatting.
5. **Integrations are off by default** and configured per tenant. Per-shop secrets are stored encrypted and never shown again.
6. **No features that are only decoration.** A switch or setting must change behaviour (decided product rule).
7. **Never delete a shop's data** because a subscription lapsed or a plan changed; gate access instead.
8. **A sale is never refused because of a plan change** (for example a late offline sync after a downgrade).
9. **Do not add business workflows nobody asked for.**
10. Every change ships as its own PR with green CI; do not merge on red.

## 6. Plans and feature gating

Plans live in the database (`subscription_plans`, managed at `/platform/plans`). `config/plans.php` only **seeds** them: changing the config does not change existing plans, so adding a feature needs a **data migration** (see `database/migrations/2026_10_29_000001_add_promotions_and_custom_domain_plan_features.php` for the pattern).

| Feature flag | Basic | Medium | Enterprise |
| --- | :-: | :-: | :-: |
| returns, credit_sales, printer | yes | yes | yes |
| brands, purchasing, reports, expenses, mobile_money, promotions | | yes | yes |
| fiscal, custom_domain, custom_roles, audit_log, branch_reports, priority_support | | | yes |

Limits: Basic 3 users / 1 warehouse, Medium 10 / 3, Enterprise unlimited. Trial shops get the Medium features. Per-shop exceptions go in the tenant's `settings.plan_overrides` (`features`, `limits`) - no code change.

How to gate something:

1. Add the flag to `config/plans.php` `features`, and its names to `lang/{en,sw}/plans.php`.
2. Routes: `->middleware('plan:<flag>')` (shows the upgrade page with HTTP 403).
3. Menu: `'feature' => '<flag>'` on the entry in `config/menu.php` (renders dimmed with a lock).
4. Logic that runs outside a request (till payload, offline sync): ask `Plans::current()->allows('<flag>')` and degrade quietly instead of failing.
5. Write a data migration for existing plan rows, tests for Basic/Medium/Enterprise, and update `resources/docs/{en,sw}/plans/plans-compared.md`.

## 7. Subsystems

| Subsystem | Essentials | Doc |
| --- | --- | --- |
| Offline till | Service worker caches `/pos`; IndexedDB holds the product/customer snapshot and a sale queue; sync replays `POST /pos/checkout` with an `offline` block and an idempotency key; server never refuses for stock, uses current prices, flags rows for review | `docs/OFFLINE.md` |
| Promotions | percent/fixed/bogo/bulk, one promotion per line (biggest wins), schedules in the shop time zone, coupons with row-locked usage counts; PHP and JS engines share fixtures; Medium+ | `docs/PROMOTIONS.md` |
| Mobile money | AzamPay per shop (own keys, own callback URL), request + callback actions, idempotent | `config/azampay.php`, `app/Domain/Sales/Actions/*AzamPay*` |
| Printing and hardware | Browser print via hidden iframe (`@page`); ESC/POS over Web Serial/WebUSB (`escpos-print.js`); cash-drawer pulse; barcode scanner switch; barcode label sheets (EAN-13, UPC-A, EAN-8, Code 128 encoded client-side) | Settings -> Optional features |
| Batch and expiry | Lot/expiry per batch, FEFO, expiry on transfers and in the till | `resources/docs/*/stock/batches-and-expiry.md` |
| Notifications | Email (Resend over SMTP) and SMS (NextSMS) through one notifier; low-stock digest, subscription reminders, payment notices, domain verified | `docs/NOTIFICATIONS.md` |
| Error tracking | Sentry envelope API through Laravel `Http`, no SDK; sent after the response; deduplicated; emails/DB values redacted; browser errors via `/client-errors` | `docs/ERROR-TRACKING.md` |
| Custom domain | Enterprise; TXT proof `_odessa-verify.<domain>` + CNAME to `CUSTOM_DOMAIN_TARGET`; a pending name never resolves; `domains:check` hourly; admin adds the domain in Railway | Settings -> Domain |
| Platform admin | Shops, plans, renewals with price snapshots, payment report/CSV, notices | `docs/PLATFORM-ADMIN-HANDOFF.md` |
| TRA fiscal (VFD) | Core ledger, counters, XML builder and signer exist; sending and the connector UI are **not** built | `docs/FISCAL.md` |
| Backups | `scripts/backup-postgres.sh` plus Railway volume backups; restore must be rehearsed | `docs/BACKUPS.md` |

## 8. Configuration reference

Everything below is read from `.env` (see `.env.example`). Production values live in Railway variables.

| Group | Variables |
| --- | --- |
| App | `APP_KEY` (never rotate after launch), `APP_ENV`, `APP_DEBUG=false`, `APP_URL`, `APP_DOMAIN` (host only), `APP_LOCALE` |
| Database | `DB_URL` (Railway) or `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` |
| Drivers | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `database`; `LOG_CHANNEL=stderr` on Railway |
| Seeding | `SUPER_ADMIN_EMAIL/PASSWORD`, `DEMO_OWNER_EMAIL/PASSWORD` |
| POS | `POS_DEFAULT_CURRENCY`, `POS_TIMEZONE`, `UPLOADS_DISK`, `CUSTOM_DOMAIN_TARGET` |
| Plans and billing | `PLAN_CONTACT_PHONE/EMAIL`, `SELF_SIGNUP_ENABLED`, `BILLING_*` (account name, WhatsApp, M-Pesa/Mixx/Airtel/HaloPesa numbers, bank details) |
| SMS | `SMS_DRIVER` (`log` or `nextsms`), `SMS_SENDER_ID`, `NEXTSMS_BASE_URL/SEND_PATH/USERNAME/PASSWORD` |
| Email | `MAIL_*` (Resend SMTP in production, Mailpit locally) |
| Operator alerts | `NOTIFY_ADMIN_EMAIL`, `NOTIFY_ADMIN_PHONE` |
| Errors | `SENTRY_DSN`, `SENTRY_ENVIRONMENT` |
| Workers | `RUN_WORKERS=1` runs the queue worker and scheduler inside the web container; set `0` when they are separate services |

Per-shop configuration (not env): optional-feature switches, integration credentials (encrypted), timezone, currency, tax, plan overrides - stored in `tenants.settings` and `tenant_integrations`.

## 9. Testing and CI

- **Pest** (SQLite in memory) organised by domain under `tests/Feature/`. Helpers: `createTenant($slug, $plan = 'enterprise')`, `createTenantUser($tenant, [permissions])`, `posFixture()`, `checkout()`. Test shops are Enterprise by default; pass `'basic'`/`'medium'` to test gating.
- **JS** calculator tests (`tests/js/*.test.mjs`) run in CI and share fixtures with PHP.
- **CI** (`.github/workflows/ci.yml`) runs, in order: Pint, PHPStan L5, Pest, JS tests, `composer audit`, `npm run build`. Failures become annotations. Pint runs first and can hide later failures - fix style, then re-read.
- `docs/SMOKE-TEST.md` is the manual browser checklist to run before showing the product to a customer and after large changes.
- Rule of thumb for a new feature: a Pest test per plan tier, per permission, per tenant boundary; idempotency tests for anything a client can resend.

## 10. Deploying (Railway)

Docker service (`Dockerfile`, `railway.json`) + a PostgreSQL service. Migrations run as the pre-deploy command; `/up` is the health check; `docker/entrypoint.sh` starts Apache and, when `RUN_WORKERS=1`, a looping queue worker and scheduler.
First deploy: set variables, deploy, then run `php artisan db:seed --force` once. Full steps in `docs/RAILWAY.md`.

After a shop verifies a custom domain, add that domain in Railway -> Settings -> Networking -> Custom Domain, and point `CUSTOM_DOMAIN_TARGET` at the service hostname.

## 11. Operations

| Task | How |
| --- | --- |
| Give one shop an extra feature or limit | `plan_overrides` in the shop's settings (platform shop edit) |
| Change what a plan includes | `/platform/plans` (existing shops follow at once) |
| Renew a subscription | Platform -> Shops -> shop -> Renew (server computes the price; idempotent) |
| Verify error tracking | set `SENTRY_DSN`, `php artisan errors:test` |
| Check domains now | `php artisan domains:check` |
| Back up / restore | `docs/BACKUPS.md` |
| Review offline sales | Sales -> Offline sales to check |

Scheduled (Africa/Dar_es_Salaam): `subscriptions:remind` 08:00, `stock:low-alerts` 08:30, `domains:check` hourly. Each shop registers its own AzamPay callback URL.

## 12. Working on the codebase

**Branches and PRs.** One feature per branch and PR. Branch from `origin/main`. Wait for green CI before merging - merging early has already forced follow-up fix PRs. Never commit to `main` directly.

**Commit trailers** (used by this project's automation):

```
Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
Claude-Session: <session url>
```

**Adding a feature, checklist**

1. Decide the plan tier and permission. Add the flag/permission (and migrate existing plan and role rows).
2. Route -> thin controller -> FormRequest -> Action/Service -> model with `BelongsToTenant`.
3. Money in integer minor units; stock through `StockService::move()`.
4. EN and SW strings in `lang/`; menu entry in `config/menu.php`; help article in `resources/docs/{en,sw}/`.
5. Tests, including Basic-plan denial and cross-tenant isolation; add JS fixtures if the till maths changed.
6. Pint, PHPStan, Pest, JS tests, build; update `docs/` and `docs/SMOKE-TEST.md`.

**Pitfalls seen before**

- Plan features are in the DB; a config-only change does nothing for existing shops.
- `defer()` callbacks do not run in tests (error reporting posts immediately under tests).
- Importing order and fully-qualified names are enforced by Pint; fix them before reading test output.
- Duplicate Pest helper function names across test files collide; prefix them.
- A branch merged into another feature branch does not reach `main`; check the PR's base before merging.

## 13. Status and roadmap

Built and in `main`: tenancy, plans and subscriptions, till with offline mode, catalog with import/export and labels, inventory with FIFO/FEFO, purchasing, expenses and reports, promotions, mobile money (AzamPay), notifications (email/SMS), error tracking, custom domains, plan gating, help centre, platform admin.

Open items:

- **Expiry digest** (daily email/SMS for products near expiry): PR #92.
- **TRA fiscal receipts**, parts 2-3 (sending, QR, connector UI): blocked on TRA test credentials (TIN, `.pfx` certificate, CERTKEY).
- Manual smoke test on real hardware (thermal printer, drawer, scanner) and a real AzamPay sandbox payment.
- Possibly retire the `/t/{slug}` entry route once every shop uses its subdomain or custom domain.
- Not planned: PIN lock for the cached offline till; Background Sync with the browser closed.

## 14. Documentation map

| File | Topic |
| --- | --- |
| `CLAUDE.md` | Contributor rules (read first) |
| `docs/ARCHITECTURE.md` | Tenancy, views, FIFO costing, conventions |
| `docs/OFFLINE.md` | Offline till and sync rules |
| `docs/PROMOTIONS.md` | Promotion kinds, schedules, coupons |
| `docs/NOTIFICATIONS.md` | Email/SMS setup and digests |
| `docs/ERROR-TRACKING.md` | Sentry setup, what is and is not sent |
| `docs/FISCAL.md` | TRA VFD design notes and plan |
| `docs/RAILWAY.md` | Hosting on Railway |
| `docs/BACKUPS.md` | Backup and restore |
| `docs/PLATFORM-ADMIN-HANDOFF.md` | Platform admin features |
| `docs/SMOKE-TEST.md` | Manual QA checklist |
| `docs/UI-ENHANCEMENTS.md` | UI notes |
| `resources/docs/{en,sw}/` | In-app help articles (user-facing) |

---

*Odessa Lab Technologies - Dar es Salaam, Tanzania.*
