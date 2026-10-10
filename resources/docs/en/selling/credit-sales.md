---
title: Sell on credit
summary: Let a customer take goods now and pay later, and see what they still owe.
keywords: credit, debt, pay later, deni, mkopo, owe, balance, on account, partial payment, credit limit, unpaid
order: 4
---
A credit sale leaves a balance that the customer pays later. Every credit sale needs a named customer, and the **Credit sales** feature is part of every plan.

## Make a credit sale

1. Add the products at the till.
2. Choose the customer (not **Walk-in customer**). If they are new, add them first: [Add a customer](help:customers/add-a-customer).
3. Click **Pay Later** to leave the whole amount unpaid. Or choose a payment method and type a smaller amount than the total to take a deposit.
4. The line **Balance due on credit** shows what the customer will owe. Click **Complete sale**.

If you forget the customer, the till says **Select a customer to sell on credit.**

## Credit limit

Each customer can have a **Credit limit** (set in **Customers**). If this sale would make their total debt bigger than the limit, the sale is refused with **This sale would exceed the customer's credit limit.** Debt means the unpaid balance of all their sales. A customer with an empty limit has no limit.

## See and collect what is owed

1. Open **Sales** and use the filter to choose **With balance due**. The **Balance** column shows what each customer owes and **Status** shows **Unpaid** or **Partly paid**.
2. Click the receipt number to open the sale.
3. In **Record payment**, choose the **Method**, type the **Amount** (and a **Reference** if you like) and click **Save**.

You can take the money in several smaller payments. The payment cannot be more than the balance: you get **The payment is more than the balance due.** Once the balance reaches zero the status becomes **Paid**.

> **Note:** **Record payment** needs the permission to manage sales. The Cashier role does not have it by default; Owner, Manager and Accountant do. See [Users and roles](help:settings/users-and-roles).

> **Tip:** To see who owes the most, open **Customers** (the **Outstanding** column) or the *Customers who owe* list in [Reports](help:money/reports). More in [Credit and balances](help:customers/credit-and-balances).
