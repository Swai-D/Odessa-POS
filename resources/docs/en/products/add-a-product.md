---
title: Add a product
summary: Create a product with its price, barcode and opening stock so you can sell it at the till.
keywords: add product, new product, create product, item, SKU, barcode, price, cost price, selling price, tax, opening stock, service, edit product, delete product, low stock alert
order: 1
---
Every item you sell must exist as a product first. You need the products permission to add or change products (Owner, Manager and Storekeeper have it by default). A Cashier can only view them.

## Add a product

1. Open **Inventory > Products** and click **Add Product**.
2. Under **Product Information** enter the **Name** and the **SKU**. The SKU is your own unique code for the item; the system does not make one for you. Add the **Barcode** if the item has one.
3. Choose the **Product Type**: **Standard (physical product)** or **Service (no stock)**. A service never holds stock, for example a delivery fee.
4. Pick the **Category**, **Brand** (Medium plan and above) and **Unit** if you want. They are optional. See [Categories, brands and units](help:products/categories-brands-units).
5. Under **Pricing & Tax** type the **Cost Price** (what you pay) and **Selling Price**. Type whole currency units, for example 2500, with no commas. You can use decimals such as 1500.50 if you need them.
6. Enter the **Tax Rate (%)** (0 if you charge no tax) and switch **Price includes tax** on or off.
7. Under **Stock & Options** leave **Track stock** on for physical goods. Set **Low-stock alert quantity** if you want a warning when stock gets low.
8. To record what you already have, fill **Opening Stock** and choose the **Warehouse**. A warehouse is required when the opening stock is more than zero.
9. Click **Save**.

> **Note:** **Opening Stock** appears only when you create a product. Later, change the quantity with [Adjust stock](help:stock/adjust-stock).

## Optional settings

The **Active** switch controls whether the product can be sold. An inactive product is kept but hidden from the till and marked **Inactive** in the list. The extra switches for weighed items, batches and expiry dates appear only if the Owner turned them on in **Settings**.

## Edit or delete a product

- Click the pencil icon on the row to edit. You can change prices and details at any time; old sales keep the price they were sold at.
- Click the bin icon to delete. Deleted products disappear from lists and the till.

> **Tip:** The same SKU cannot be used twice, even after you delete a product. If you get an error that the SKU is taken, choose a different code.

To add many products at once, see [Import and export products](help:products/import-export).
