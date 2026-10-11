# Architecture

## Tenancy

Odessa POS uses one PostgreSQL database and a ULID `tenants.id`. Resolve a tenant from a custom domain or `{slug}.APP_DOMAIN`; in local development only, `X-Tenant` and session fallback are available. Bare `APP_DOMAIN` is reserved for super-admin access. Suspended tenants receive 403 and unknown tenant identifiers receive 404.

Tenant-owned Eloquent models must use `App\Models\Concerns\BelongsToTenant`. The trait scopes reads and fills `tenant_id` at creation through the request/job-scoped `TenantContext`. Do not remove this scope for application queries; privileged cross-tenant work must be explicit and reviewed. `TenantContext::run()` restores prior state in a `finally` block.

Queue payloads include the active tenant ULID. Queue lifecycle hooks restore and clear context for queued jobs and notifications. Jobs may also attach `RestoreTenantContext` through their `middleware()` method.

Spatie Permission Teams is enabled with `tenant_id` as its ULID team key. Activity log, media library, and Fortify migrations/configuration are published into the application.

## Views and assets

`resources/template-original/` is the read-only purchased-template reference, including the original HTML and bundled asset tree. `public/assets/` is a byte-for-byte copy of the static assets. Blade layouts and template-derived views are under `resources/views`. Do not edit, rename, minify, or replace either source or runtime assets.

The sidebar menu is defined once in `config/menu.php`; each entry names its translation key, icon class, route, and permission. The Blade partial filters entries with the current user's permission.

## Inventory costing (FIFO batches)

Stock quantity lives in `product_stocks` and every change goes through `StockService::move()`, which writes the `stock_movements` ledger row. The same call keeps the **cost batches** in step (`StockBatchService`, `stock_batches`, `stock_batch_allocations`), inside the same transaction:

- Stock coming in (purchase, opening stock, adjustment, return, transfer in) creates a batch with its own `unit_cost`. Pass `unitCost:` to `move()`; without it the latest batch cost in that warehouse, then `products.cost_price`, is used.
- Stock going out (sale, write-off, transfer out) is taken from the oldest batch first. The batches used are stored as allocations on the movement; `StockMovement::averageUnitCost()` gives the weighted cost, which `CheckoutAction` snapshots into `sale_items.cost_price`, so profit reports use the real cost.
- A sale return puts units back into the batches the sale took them from; a transfer recreates the batches (cost and age) in the destination warehouse.
- Stock valuation (reports and branch report) sums `quantity_remaining x unit_cost` over batches. `products.cost_price` is only the latest/reference cost: editing it or restocking at a new price never changes the cost of stock already bought.

Always change stock through `StockService::move()`; writing `ProductStock` directly would leave the batches out of step.

## Conventions

- Keep controllers thin; put domain behavior in Actions and Services.
- Validate input with Form Requests and enforce access with Policies.
- Every tenant-owned model uses `BelongsToTenant`.
- Store monetary values as integer minor units; format them with `App\Support\Money`.
- Default currency is TZS; optional integrations are per-tenant feature flags in `config/pos.php` and `TenantSettings`.
- English (`en`) is the fallback locale; supported user/tenant/session locales are `en` and `sw`.

Run tests with `vendor/bin/pest`; Pint, Larastan level 5, Vite build, and Composer audit are required CI checks.