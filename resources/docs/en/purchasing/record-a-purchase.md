---
title: Record a purchase and receive stock
summary: Enter the goods a supplier delivered so stock goes up, with an optional payment.
keywords: purchase, buy stock, receive goods, delivery, grn, supplier invoice, restock, unit cost, cost price, ununuzi
plan: medium
order: 2
---
When a supplier delivers goods, record a purchase. It adds the quantities to your stock and keeps the cost. This is part of the Medium plan and above, and needs the permission to manage purchases (Owner, Manager and Storekeeper have it).

## Steps

1. Open **Purchases** in the side menu and click **New Purchase**.
2. Choose the **Supplier** (add one first if needed: [Suppliers](help:purchasing/suppliers)).
3. Choose **Receive into**: the warehouse the goods go to.
4. Type the **Supplier invoice no.** if you have one.
5. Click **Add product**, search for the product, and fill **Qty** and **Unit cost** (what you paid for one). **Line total** and the **Total** are worked out for you. Repeat for each product.
6. Tick **Update product cost prices to these costs** if you want each product's cost price to become this unit cost. This changes the profit shown in reports from now on.
7. If you paid the supplier already, fill the box **Paid now**: **Amount**, **Method** and **Payment reference**. Leave it empty if you will pay later.
8. Add a **Note** if you like, then click **Receive goods**.

The stock of each product goes up in the warehouse you chose, and the purchase gets a number (**Purchase No.**).

## Rules

- The payment cannot be more than the total: **The payment is more than the purchase total.**
- If you type an amount, choose the method, or you get **Choose how the payment was made.**
- The same product on two lines must have the same cost, otherwise: **The same product appears twice with different costs.**
- Products counted in whole units must have whole quantities.
- Clicking **Receive goods** twice by mistake does not create two purchases.

## Afterwards

Open **Purchases** to see all of them. The filter **All purchases** / **Unpaid only** and the **Status** (**Paid**, **Partial**, **Unpaid**) show what you still owe. Open a purchase to see the lines and payments. There is no editing: if a quantity was wrong, correct the stock with [Adjust stock](help:stock/adjust-stock).

> **Note:** The system adds the stock at the moment you click **Receive goods**, so record the purchase when the goods are really in your shop.
