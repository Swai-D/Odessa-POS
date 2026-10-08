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

## 8. Hardware and integrations (needs the real device)
- [ ] ESC/POS thermal printer through the browser (Web Serial/WebUSB): not tested on real hardware yet.
- [ ] Barcode scanner (keyboard wedge) in the till search box.
- [ ] Mobile money and TRA fiscal receipts: not built yet.

## 9. Production checklist
- [ ] `.env` uses PostgreSQL, `APP_DEBUG=false`, a real `APP_KEY`, and the tenant header/session overrides are **off**.
- [ ] Wildcard DNS and TLS for `*.your-domain`; a shop opens on `slug.your-domain`.
- [ ] Queue worker and scheduler are running; backups of the database are tested by restoring one.
