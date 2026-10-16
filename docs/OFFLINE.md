# Offline till

How the till keeps selling without internet, and where the limits are.

## Pieces
- `public/sw.js` (service worker): caches the till page (`/pos`) and its static files (`/assets`, `/js`, `/pwa`). Nothing else is cached, and a redirected response (sign-in page) is never stored. Sign-out clears these caches (`public/js/pwa-register.js`).
- `public/manifest.webmanifest` + `public/pwa/*`: makes the till installable.
- `public/js/pos-offline.js`: IndexedDB. `kv` holds the saved product/customer list per warehouse (`GET /pos/snapshot`); `queue` holds sales made offline.
- `public/js/pos.js`: falls back to the saved list for search, saves a sale to the queue when the network is down or the request fails, sends the queue (one sale at a time, oldest first) when online, shows the status in the till header.

## Sync rules (server)
The queue calls the normal `POST /pos/checkout` with an extra `offline: {sold_at, expected_total}` block. The idempotency key makes a resend return the same sale, so nothing is recorded twice, even when a response was lost.
For a sale with the `offline` block, `CheckoutAction`:
- never refuses for stock: the movement may take stock below zero (`StockService::move(..., allowNegative: true)`); flag `stock`;
- uses the **current server prices**; if the total differs from what the customer was shown: flag `price`;
- trims a non-cash payment above the new total (flag `payment`), and accepts an unpaid balance that credit rules would refuse (flag `credit`);
- keeps the device time as `sold_at`, within 30 days and not in the future (`config/pos.php`).
Flags are stored in `sales.review_reason`; an owner clears them with `sales.reviewed_at` (Sales → Offline sales to check).
New stock arriving into a deficit first covers it; `StockBatchService::settleDeficit()` keeps cost batches from exceeding the real stock.

## Not available offline
Mobile money prompts, held orders, new customers, returns, reports, and anything not in the till.

## Not done yet
- Fiscal (TRA) receipts for offline sales: they will be issued when the sale is synced (part of the TRA work).
- A PIN to unlock the cached till on a shared computer.
- Background Sync when the browser is closed; sending happens while the till page is open.
