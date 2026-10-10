# Platform Admin Handoff

## Completed

- Added the super-admin overview at `/platform`; the route is protected by `auth` and `can:platform`.
- The overview shows shop counts by account status, subscription deadlines needing attention, monthly payments grouped by currency, recently registered shops, and recent subscription payments.
- Added the dashboard link to the platform-only sidebar. Shop management, plan configuration, tenant domain fields, and payment renewal flows remain on their existing implementations.
- Added `/platform/payments` for searchable-by-filter subscription transaction review: date range, shop, currency, and method filters; per-currency totals; pagination; and a CSV export using the same filter set. The dashboard's recent-payment panel links to the full report.
- Added database-backed plan CRUD at `/platform/plans` for stable plan codes, TZS monthly/annual prices, features, limits, sorting, and activation. Plans in use by shops or payment history cannot be deleted; inactive plans remain available to their current shops.
- Migrated current plan feature/limit definitions from `config/plans.php` into the catalog, preserving existing behavior and the `demo` alias.
- Added a one-time migration for the configured initial TZS prices: Basic 50,000/month and 590,000/year; Medium 70,000/month and 820,000/year; Enterprise 90,000/month and 1,050,000/year.
- Renewal now offers only monthly (1 month) and annual (12 months), calculates the charge server-side, ignores client-submitted amounts, and applies edited prices only when the next renewal is recorded. Discounts require a reason and cannot exceed the selected price.
- Payment records snapshot plan code/name, list price, discount, reason, final amount, and TZS currency. Existing payment history stays readable; old rows without a snapshot use their recorded amount as the display fallback.
- Added English/Swahili translations and `tests/Feature/Platform/PlatformDashboardTest.php` coverage for authorization, aggregates, renewals, and currency-specific revenue.
- Added `tests/Feature/Platform/PlatformPaymentTest.php` coverage for report filtering, CSV content, date validation, and access control.
- Added `tests/Feature/Plans/SubscriptionPlanAdminTest.php` coverage for plan CRUD, assignment, feature/limit behavior, deletion guards, and authorization; `RenewalTest.php` covers pricing, discounts, idempotency, and renewal dates.
- Feature commit `2e3526c` (`Add platform admin overview dashboard`) was pushed to `origin/main`.
- Plan/payment implementation is in the current worktree and has not been committed or pushed.
- Applied only migrations `2026_10_12_000001_create_subscription_plans_table` and `2026_10_12_000002_add_plan_price_snapshots_to_tenant_payments` to the local PostgreSQL database. The unrelated pending till-closing migrations were left pending.

## Where Work Stopped

The CRUD seeds Basic, Medium, and Enterprise with their current feature/limit definitions; the one-time pricing migration supplies the configured rates above. Subsequent changes are managed through `/platform/plans` and apply on the next renewal.

The template's other super-admin areas are not implemented as new workflows. Consider these separately before starting them:

- Custom-domain verification and lifecycle: tenants have a domain field, but no verification workflow exists.
- Coupons or promotional billing: no platform coupon model/workflow exists.
- Cross-shop operational reporting: keep tenant data isolated and design any reviewed super-admin access explicitly.

The platform overview is at `/platform`, plans at `/platform/plans`, and payment review at `/platform/payments`. Focused verification passed: 63 tests / 329 assertions, Pint, and Larastan level 5. Re-run the plan/payment bundle with `vendor/bin/pest tests/Feature/Plans tests/Feature/Platform tests/Feature/Sales/CheckoutTest.php tests/Feature/SidebarTest.php`.

## Worktree Caution

At handoff time, unrelated user work was present in the worktree and intentionally excluded from commit `2e3526c`. Check `git status` before editing or committing, and preserve those changes unless asked otherwise.
