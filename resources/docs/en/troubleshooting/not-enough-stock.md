---
title: "Not enough stock" and wrong stock quantities
summary: Understand why the till or a transfer refuses, and how to correct the numbers.
keywords: not enough stock, out of stock, insufficient, negative, stock wrong, cannot sell, quantity, count, zero, warehouse
order: 4
---
The system will not let stock go below zero, so it stops a sale or transfer that needs more than you have.

## What you may see

- At the till, a product tile shows **Out of stock**, and you cannot add more than **in stock** in the selected warehouse.
- On completing a sale: **Not enough stock for "product".**
- In a transfer: **Not enough product in warehouse.**

## Why it happens

1. **The real stock is there, but the system does not know.** You received goods and did not record them, or the opening stock was left at zero.
2. **You are in the wrong warehouse.** With several warehouses, the till shows the stock of the warehouse chosen above the products.
3. **Someone sold it already** from another till.

## Fix it

1. Check the real count on the shelf.
2. Open **Stock > Stock Levels** to see what the system thinks for each warehouse.
3. If goods arrived from a supplier, record them. See [Record a purchase](help:purchasing/record-a-purchase) (Medium plan).
4. Otherwise correct the number with **Stock > Stock Adjustments**. See [Adjust stock](help:stock/adjust-stock). Every adjustment is kept in the history with who made it.
5. If the stock is in another warehouse, [transfer it](help:stock/transfer-stock).

> **Note:** Services, and products where stock tracking is switched off, can always be sold. See [Add a product](help:products/add-a-product).

> **Tip:** Count a few fast-moving products every week and adjust them, rather than counting everything once a year.
