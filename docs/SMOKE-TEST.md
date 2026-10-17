# Manual smoke test

Automated tests (Pest) cover logic and permissions, but nobody has run the screens in a browser yet.
Run this list once on a fresh `php artisan migrate --seed` database before showing the product to a customer,
and again after big changes. Tick each line; write down anything that looks or behaves wrong.

Use two browser profiles (or a private window) so a platform admin and a shop user can be signed in at once.

## 1. Platform admin
- [ ] Sign in with the seeded super admin. The sidebar shows only **Platform → Shops**; the dashboard shows a greeting and a "Manage shops" link.
- [ ] Open a shop URL such as `/pos` as the super admin: a clear "needs a shop" page, not a database error.
- [ ] **Shops → New shop**: create `test-basic` (plan Basic, status Active, paid until a month from now) with an owner.
- [ ] Create a second shop `test-medium` (plan Medium). Edit it: change plan, status, paid-until, add an extra feature and a user limit; save.
- [ ] The shop list shows plan, status and paid-until for both.

## 2. Basic shop (sign in as the `test-basic` owner)
- [ ] Sidebar has Dashboard, POS, Products, Categories, Units, Warehouses, Stock, Sales, Customers, Settings, Users. **No** Brands, Purchases, Suppliers, Reports, Integrations.
- [ ] Typing `/brands`, `/purchases`, `/suppliers`, `/reports`, `/settings/integrations` in the address bar shows the "not in your plan" page.
- [ ] Navbar: no fake search, no store switcher, no email icon. "Add" lists only items the plan allows. Language switch changes EN ⇄ SW and the choice stays after reload.
- [ ] Product form has no Brand field.
- [ ] Create a category, a unit, and a product (with cost, price, alert quantity). Add opening stock with **Stock → Adjustments** using the product search box (type a few letters, pick from the list).
- [ ] Dashboard shows zeros, not demo numbers.

## 3. Till (POS)
- [ ] `/pos` loads without a fake "Freshmart" store switcher; the clock ticks; the Dashboard button goes to the real dashboard.
- [ ] Search and add products by name, SKU and barcode; change quantities; apply a discount.
- [ ] Cash sale with change: receipt number appears, stock goes down, dashboard "Sales today" goes up.
- [ ] Add a customer from the till, then find them again with the customer search box.
- [ ] Credit sale: choose a customer, pay less than the total; the sale is partial/unpaid and the customer owes the balance. Over the customer's credit limit is refused.
- [ ] Hold an order, resume it (customer is restored), then complete it.
- [ ] Same sale twice by double-clicking Pay: only one sale is created.
- [ ] Selling more than the stock on hand is refused with a clear message.
- [ ] Open the sale: print the A4 receipt and the thermal receipt layout. Record a return on it; stock and balance update.

## 4. Lists
- [ ] Products, Sales, Stock, Adjustments, Customers: with more than 25 rows they show pages, the search box finds rows on later pages, and filters (e.g. unpaid sales) survive paging.

## 5. Users and limits
- [ ] **Users**: add a cashier; sign in as them. They see only what their role allows and cannot open Settings or Users.
- [ ] On Basic, adding a 4th user and a 2nd warehouse is refused with an upgrade message.
- [ ] You cannot delete yourself or demote the last Owner.

## 6. Medium shop (`test-medium`)
- [ ] Brands, Purchases, Suppliers, Reports, Integrations appear.
- [ ] Create a supplier and a purchase (pick products with the search box; unit cost fills in). Stock goes up; record a supplier payment.
- [ ] **Reports**: change the dates; figures match the sales you made; net sales are after returns; best sellers show revenue and profit; each **CSV** button downloads a file that opens in Excel with correct Swahili text.
- [ ] Integrations: the receipt printer settings can be saved.

## 7. Subscription
- [ ] Set paid-until to yesterday: warning banner and bell notice, everything still works (grace).
- [ ] Set it 8+ days back: the shop becomes read-only. Pages open, but saving, selling and adjusting are refused with the "subscription expired" message.
- [ ] Set status Suspended: the shop is switched off. Set it back to Active with a future date: everything works again and no data was lost.

- [ ] As super admin, open Shops > a shop > "Renew subscription": record 1 month with an amount; paid-until moves forward and the payment shows in the history below.
- [ ] Renew a shop that lapsed a month ago: the new period starts from the payment day, and the shop is writable again.
- [ ] Press the button twice quickly (or refresh after submitting): only one payment is recorded.

## 7b. Expenses (Medium and Enterprise)
- [ ] On a Medium shop: Finance > Expenses appears in the sidebar; on a Basic shop it does not and `/expenses` shows the upgrade page.
- [ ] Add a category (Expense categories), then add an expense with a decimal amount; edit it and delete it from the list.
- [ ] Filter by dates and category, search by note, and download the CSV; the total above the table matches the rows.
- [ ] Reports shows "Profit and loss" with expenses by category; net profit = gross profit - expenses.
- [ ] A Cashier (no expenses permission) sees neither the menu items nor the profit and loss card.
- [ ] A shop that existed before this release: Owner, Manager and Accountant can open Expenses without re-creating roles.

## 7c. Search, sorting and upgrade prompts
- [ ] Navbar search: type a product name, a customer, a sale number; results are grouped and each link opens the right page. A Cashier does not see groups they have no permission for.
- [ ] Click the column headers (Products: SKU, name, price; Sales and Purchases: number, date, total, balance; Expenses: date, amount): the arrow shows the direction, a second click reverses it, and your search and filters stay.
- [ ] On a Basic shop, Brands, Purchases, Suppliers, Reports, Expenses show dimmed with a lock. Clicking one opens the "not in your plan" page naming the plan that includes it, with your contact details (set `PLAN_CONTACT_PHONE` / `PLAN_CONTACT_EMAIL` in `.env`).

## 7d. Product import and export
- [ ] Products > import icon > download the template, add a few rows in Excel (save as CSV, comma or semicolon both work) and upload: the summary shows created/updated counts and the products appear with prices and opening stock.
- [ ] Upload a file with a deliberate mistake (negative price, repeated SKU): nothing is imported and each problem names its line.
- [ ] Export the products, change a price in the file, upload it again: only that price changes and stock stays as it was.
- [ ] Open the exported file in Excel with Swahili text: letters display correctly.

## 7e. Stock transfers
- [ ] On a shop with two warehouses: Stock > Stock Transfers > New transfer. Move a few units of a product; the transfer page lists it, Stock Levels shows less in the source and more in the destination, and Stock Adjustments shows a "Transfer out" and a "Transfer in" row.
- [ ] Try to move more than the source holds: you get a clear message and nothing moves (also not for the other lines).
- [ ] On a Basic shop (one warehouse) the form explains that two warehouses are needed.

## 7f. Till closing
- [ ] As a cashier: make a few cash sales, then Sales > Till closing > Close till. The expected cash equals the cash you took (plus the float from last time). Enter the counted cash and the float to leave, and close; the report page prints.
- [ ] Close again after more sales: the new report starts where the last one ended and the opening float is the float you left.
- [ ] Count less or more than expected: the difference shows red or amber. Try leaving more float than counted: you get a clear message.
- [ ] A cashier sees only their own closings; the owner/manager/accountant sees everyone's with the cashier name.

## 7g. Plans: promotions and custom domain
- [ ] Basic shop: **Promotions** is dimmed with a lock; `/promotions` shows the upgrade page naming Medium. The till has no coupon box.
- [ ] Medium shop: Promotions opens; custom domain (Settings > Domain) shows the upgrade page naming Enterprise. Enterprise opens both.
- [ ] Create a 10% promotion on a Medium shop, then switch that shop to Basic: the promotion stays in the list data but no longer applies at the till or on a synced offline sale, and no sale is refused.
- [ ] At **Platform > Plans** give Basic the Promotions feature: the Basic shop gets it at once, with no code change.

## 7h. Promotions and coupons (Medium and up)
- [ ] Create an automatic promotion (percent, all products): the till shows the discount on the line and the receipt/sale page records the promotion name.
- [ ] Create a coupon promotion: type the code in the till coupon box; a wrong code gives a clear message; a valid code is used once only when it has a limit.
- [ ] A promotion outside its dates or hours does not apply.

## 7i. Offline till
Use Chrome with DevTools > Network > Offline (or switch off Wi-Fi).
- [ ] Open `/pos` online once, then go offline and reload: the till still loads and search finds products and customers from the saved list.
- [ ] Make two sales offline: each is saved, the header shows how many are waiting, and receipts can be printed.
- [ ] Go back online: the queue sends by itself, oldest first; stock and the dashboard update; refreshing or sending again never creates a duplicate.
- [ ] Sell more than the stock offline: the sale is accepted on sync, stock may go negative, and the sale appears in **Sales > Offline sales to check** with a reason. Mark it reviewed.
- [ ] Sign out: the cached till is cleared (the next visit needs a sign-in).

## 7j. Settings switches (Settings > Optional features)
- [ ] Barcode scanner **off**: pressing Enter in the till search does not add an item. **On**: scanning (or typing a code + Enter) adds the product; an unknown code gives a message.
- [ ] Cash drawer **off**: nothing happens after a cash sale. **On** with a printer connected: the drawer opens after a cash sale (see section 8).
- [ ] Batch / lot number and expiry date: switching them on shows the lot and expiry fields on purchases and adjustments; switching them off hides them and does not delete old data.

## 7k. Batches and expiry
- [ ] Receive a purchase with a lot number and expiry date for a product that tracks expiry. Stock > expiry list shows the date and the days left.
- [ ] Receive a second lot with an earlier expiry, then sell one unit: the earlier lot is used first (FEFO).
- [ ] A transfer to another warehouse carries the lot and expiry with it.
- [ ] A product that expires soon shows an expiry badge in the till.
- [ ] Run `php artisan stock:expiry-alerts`: the owner gets one email/SMS digest; running it again with the same list of products sends nothing more until the repeat period has passed.

## 7l. Barcode labels
- [ ] Products > select products > print labels: the preview shows the barcode, name and price; EAN-13, UPC-A, EAN-8 and Code 128 values draw correctly.
- [ ] Print to a real label sheet or label printer: size and margins are right and a phone/scanner reads the code.

## 7m. Notifications (email and SMS)
Needs `SMS_DRIVER=nextsms`, `NEXTSMS_*`, `SMS_SENDER_ID`, the Resend SMTP settings and `NOTIFY_ADMIN_EMAIL/PHONE`.
- [ ] A new shop registration reaches the platform admin by email and SMS.
- [ ] A low-stock digest and a subscription reminder arrive at the shop owner (email and, where a phone is set, SMS).
- [ ] Wrong SMS credentials do not break the page that triggered the message; the failure is only logged.

## 7n. Custom domain (Enterprise)
- [ ] Settings > Domain: enter `pos.example.co.tz`; the page shows the TXT record `_odessa-verify.<domain>` and the CNAME target (`CUSTOM_DOMAIN_TARGET`).
- [ ] Before the DNS exists, **Verify** says what is missing and the pending name does not open the shop.
- [ ] After adding both records: Verify succeeds, the owner is notified, and the platform admin adds the domain in Railway > Settings > Networking > Custom Domain.
- [ ] The shop opens on the new domain and its sign-in works; the old `slug.your-domain` link keeps working.
- [ ] A domain already used by another shop is refused.

## 7o. Error tracking (Sentry)
- [ ] Set `SENTRY_DSN`, run `php artisan errors:test`: one event appears in Sentry; the same error twice in a short time is reported once.
- [ ] Cause a JavaScript error in the till (e.g. DevTools console `setTimeout(()=>{throw new Error("smoke")})`): it appears in Sentry as a browser error.
- [ ] The event contains no email addresses, passwords or database values.

## 8. Hardware and integrations (needs the real device)
- [ ] ESC/POS thermal printer through the browser (Web Serial/WebUSB): print a receipt; the cash drawer pulse (ESC p) opens a drawer wired to the printer.
- [ ] Barcode scanner (keyboard wedge) in the till search box, with the scanner switch on.
- [ ] Mobile money (AzamPay): each shop enters its keys in Integrations and registers its own callback URL with AzamPay; a test payment confirms the sale from the callback; keys are never shown again after saving.
- [ ] TRA fiscal receipts: not finished; waiting for TRA test credentials (TIN, .pfx certificate, CERTKEY).

## 9. Production checklist
- [ ] `.env` uses PostgreSQL, `APP_DEBUG=false`, a real `APP_KEY`, and the tenant header/session overrides are **off**.
- [ ] Wildcard DNS and TLS for `*.your-domain`; a shop opens on `slug.your-domain`.
- [ ] Queue worker and scheduler are running (`subscriptions:remind`, `stock:low-alerts`, `stock:expiry-alerts` and `domains:check` hourly); backups of the database are tested by restoring one.
- [ ] `POS_TIMEZONE`, `BILLING_*`, `SENTRY_DSN`, `CUSTOM_DOMAIN_TARGET` and the notification variables are set in Railway (see `docs/RAILWAY.md`).
