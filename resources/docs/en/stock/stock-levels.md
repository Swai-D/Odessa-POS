---
title: See your stock levels
summary: Check how much of each product you have in every warehouse and spot items running low.
keywords: stock, stock levels, quantity, on hand, inventory, low stock, running out, alert, warehouse, how many, history, ledger
order: 1
---
**Stock Levels** shows the quantity you hold now, for each product in each warehouse. You need the inventory view permission (Owner, Manager, Storekeeper and Accountant have it).

## Check the quantities

1. Open **Stock > Stock Levels**.
2. Each row shows the **SKU**, **Product**, **Warehouse**, **Unit** and **Qty**.
3. Type in the search box to find a product by name or SKU.

A product appears once for each warehouse that has ever held it. A product with no stock movement yet does not appear at all.

## Low-stock warnings

When you [add a product](help:products/add-a-product) you can set a **Low-stock alert quantity**. When the quantity is at or below that number, the row shows a yellow **Low stock** badge. If the alert quantity is 0, there is no warning.

> **Note:** On **Stock Levels** the check is done per warehouse. The **Dashboard**, the bell icon and the **Running low** list on [Reports](help:money/reports) compare the total across all warehouses. If you use more than one warehouse, the two can differ.

Services and products with **Track stock** switched off have no stock and are not counted.

## How stock changes

Stock goes up or down only through these actions, and each one is written to a permanent history:

- A sale takes stock out and a return puts it back (see [Make a sale](help:selling/make-a-sale)).
- A purchase puts stock in (see [Record a purchase](help:purchasing/record-a-purchase)).
- A transfer moves it between warehouses (see [Transfer stock](help:stock/transfer-stock)).
- An adjustment corrects it by hand (see [Adjust stock](help:stock/adjust-stock)).
- Opening stock is added when a product is created.

To see the history of changes, open **Stock > Stock Adjustments**. Despite the name, that page lists every movement with its date, type, quantity, the balance after it, the reason and who did it.

> **Tip:** If a number looks wrong, read the history first to see what happened, then fix it with an adjustment and a clear reason. See also [Not enough stock](help:troubleshooting/not-enough-stock).
