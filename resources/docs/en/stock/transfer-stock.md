---
title: Transfer stock between warehouses
summary: Move products from one warehouse to another and keep a numbered record of the move.
keywords: transfer, move stock, stock transfer, warehouse, branch, store, send stock, TR number, between warehouses
order: 3
---
A transfer takes stock out of one warehouse and puts the same quantity into another. You need at least two active warehouses, so this works on the Medium plan (up to 3 warehouses) and Enterprise. On Basic you have only one warehouse. See [Warehouses](help:stock/warehouses).

You need the inventory manage permission to make a transfer. Anyone who can see **Stock Transfers** can read past transfers.

## Move stock

1. Open **Stock > Stock Transfers** and click **New transfer**.
2. Choose **From warehouse** and **To warehouse**. They must be different. If you have fewer than two active warehouses the page tells you to add one under **Warehouses**.
3. Optionally write a **Note**.
4. Click **Add product**, search for the product and enter the **Quantity**. Add as many products as you need (up to 200 lines).
5. Click **Move stock**.

The transfer gets a number like TR-0001 and the stock changes at once in both warehouses.

## Rules

- Everything happens together. If one product does not have enough stock in the source warehouse, you see "Not enough ... in ..." and nothing moves.
- Only products with **Track stock** on and **Active** on can be moved.
- Items that are not sold by weight or in decimal units must be moved in whole numbers.
- If you add the same product twice, the quantities are added together.
- Clicking the button twice by mistake does not create two transfers.
- A transfer cannot be edited or cancelled. To undo it, make a new transfer in the opposite direction.

## View past transfers

The **Stock Transfers** list shows the **Number**, **Date**, **From warehouse**, **To warehouse**, how many **Products** and who moved it (**Moved by**). Click a transfer to see its products and quantities.

> **Tip:** Check the result on **Stock > Stock Levels**. Each side of the transfer also appears in the history on **Stock > Stock Adjustments** as **Transfer out** and **Transfer in**.

> **Note:** A transfer is instant. There is no "in transit" step, so do it when the goods are handed over.
