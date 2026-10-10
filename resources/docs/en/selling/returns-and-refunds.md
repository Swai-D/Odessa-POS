---
title: Returns and refunds
summary: Take goods back from a customer, put them back in stock and pay out or reduce the debt.
keywords: return, refund, give back, money back, restock, wrong item, damaged, exchange, credit note, reverse sale
order: 6
---
When a customer brings goods back, record a return on the original sale. The goods go back into stock and the money is settled in one step. This is part of every plan. You need the permission to manage sales, which Owner, Manager and Accountant have by default (the Cashier role does not).

## Record a return

1. Open **Sales** and click the receipt number of the sale. See [Receipts and the sales list](help:selling/receipts-and-sales-list).
2. In the **Return items** box, type a number in **Return qty** next to each item coming back. Leave the others empty. The column **Returned** shows what was already returned before.
3. Choose **Refund paid by** (for example **Cash**). You need this only when money is paid out.
4. Type a **Reason** if you want to keep a note.
5. Click **Record return**. The message **Return recorded.** appears.

## What happens to the money

The refund value is worked out from the line total of each item on the receipt, in proportion to the quantity returned.

1. First it **reduces any balance the customer still owes** on this sale (**Reduced balance**).
2. Only the rest is **Paid out**, using the method you chose.

So if a customer bought on credit and has not paid, returning goods just lowers the debt and no cash leaves the till.

## Rules

- You cannot return more than was sold, and you cannot return the same item twice beyond its quantity. The error says **More of ... is being returned than was sold.**
- Items sold by piece can only be returned in whole numbers.
- Enter a quantity for at least one item, otherwise you get **Enter a quantity for at least one item.**
- Returned items are added back to the stock of the warehouse the sale came from (only for products whose stock is tracked). You can see this as a movement in **Stock Adjustments**.

## Afterwards

The sale page now has a **Returns** list with the **Return No.** (it starts with RT-), the amounts and the reason, and shows **Net after returns**. The sale, the stock and the money stay linked, so reports show the correct net sales.

> **Note:** A cash refund you record is subtracted from the cash your till should hold when you close it. See [Close the till](help:money/close-the-till).
