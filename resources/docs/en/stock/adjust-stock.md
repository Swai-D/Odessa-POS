---
title: Adjust stock
summary: Add or remove stock by hand after a count, a loss, damage or a mistake, and keep a reason on record.
keywords: adjust stock, adjustment, stock take, count, correct stock, damage, loss, theft, expired, add stock, remove stock, reduce, increase, reason
order: 2
---
Use an adjustment when the stock in the system does not match what is on your shelf, for example after a stock count, or when goods are damaged, expired or lost. You need the inventory manage permission (Owner, Manager and Storekeeper by default).

## Make an adjustment

1. Open **Stock > Stock Adjustments** and click **New Adjustment**.
2. Search for the **Product** by name, SKU or barcode and pick it. Only products with **Track stock** switched on can be chosen.
3. Choose the **Warehouse**.
4. Choose the **Direction**: **Add stock** or **Remove stock**.
5. Enter the **Quantity**. This is the amount to add or remove, not the new total. It must be more than 0.
6. Write a **Reason**, for example "Stock count 12 Oct" or "Damaged in storage". It is required.
7. Click **Save**.

> **Example:** The system says 20 but you count 17. Choose **Remove stock** and type 3.

## Rules to know

- Stock cannot go below zero. If you try to remove more than you have, you see "Not enough stock" and nothing changes.
- The reason is saved with the adjustment and shown in the list, together with who made it.
- An adjustment cannot be edited or deleted. If you made a mistake, make a second adjustment the other way.
- Decimal quantities such as 1.5 are accepted by this form. Use them only for products sold by weight or volume.

## Read the history

The table under the button lists every stock change, newest first: **Date**, **Product**, **Warehouse**, **Type**, **Quantity**, **Balance** (the stock after the change), **Reason** and **By**. It includes sales, purchases and transfers too, not only adjustments. Use the search box to find one product.

> **Tip:** Do not use adjustments for stock you bought. Record those with [Record a purchase](help:purchasing/record-a-purchase) so your supplier balance is correct. To move stock between warehouses use [Transfer stock](help:stock/transfer-stock).

If people with the Cashier role cannot see this page, that is normal. See [I cannot see a menu](help:troubleshooting/cannot-see-a-menu).
