# Promotions

Rules live in `promotions` (tenant-owned). The arithmetic is in `PromotionEngine` (PHP) and `public/js/pos-promo.js` (the till),
both run against `tests/fixtures/promotion-cases.json` (`tests/Feature/Sales/PromotionEngineTest.php`, `node tests/js/promo.test.mjs`).
Keep them in step.

- Kinds: `percent` (value = hundredths of a percent), `fixed` (value = minor units off each unit), `bogo` (buy_qty paid, get_qty free,
  whole groups only), `bulk` (from min_qty, percent or fixed per unit).
- One promotion per line: the biggest discount wins, lowest id on a tie. Never stacked.
- Schedule: first/last day, ISO weekdays, time window (may pass midnight), judged in the shop's time zone
  (`pos.timezone`, or the shop's `timezone` setting) at the sale's own time (the device time for an offline sale).
- Coupon = a promotion with a code; applies only when the code is sent as `coupon_code`. `uses_count` goes up only when the coupon
  gave a discount to at least one line; `max_uses` is checked under a row lock, so two tills cannot both take the last use.
  Coupons are not in the offline snapshot and are ignored for offline sales.
- Calculation: promotion discount per line first (`SaleCalculator` line `promo`), then the cashier's order discount on what is left.
  `sale_items.promo_discount` is the promotion part of `discount`; `sales.promo_total` the sum.
- The server recomputes everything at checkout; the till only previews it.
- Permissions: `sales.view` to see, `sales.manage` to create/change (PromotionPolicy).
