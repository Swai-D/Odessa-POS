---
title: Import and export products
summary: Add or update many products at once from a CSV file, or download your product list.
keywords: import, export, CSV, excel, spreadsheet, upload, bulk, many products, template, download, opening stock, update prices, semicolon
order: 3
---
Instead of typing products one by one, you can load them from a spreadsheet saved as CSV. You need permission to manage products to import. Anyone who can view products can export.

## Import products

1. Open **Inventory > Products** and click the import icon at the top right, or go straight to the import page.
2. Click **Download template**. Open it in Excel and fill one product per row. Keep the heading row exactly as it is.
3. Save the file as CSV (comma or semicolon separated both work).
4. On the import page choose the file under **CSV file** and click **Upload and import**.

The file must be 2 MB or less and hold at most 2000 products.

## Columns

Only **sku** and **name** are required. The others are optional: barcode, type, category, brand, unit, cost_price, selling_price, tax_rate, tax_inclusive, track_stock, alert_quantity, is_active and opening_stock. Type is `standard` or `service`. For the yes/no columns write `yes` or `no`. Prices are in whole currency units such as 1500 or 1500.50.

## How it works

- Products are matched by **SKU**. A known SKU is updated, and only the cells you fill in change. A new SKU creates a new product.
- **Opening stock** goes into your default warehouse and only for new products. It is ignored for products that already exist, so uploading the same file twice does no harm. To change stock later use [Adjust stock](help:stock/adjust-stock).
- Missing categories, brands and units are created for you. On the Basic plan the brand column is ignored.
- The whole file is checked first. If any row has a problem, nothing is saved and you see a list such as "Line 5: ..." (up to 50 problems) to fix. Then upload again.

> **Tip:** The most common errors are a repeated SKU, a typing mistake in a heading, or a SKU that belongs to a deleted product.

## Export products

Click the export icon on **Inventory > Products**. You get a CSV with all your products and a **stock** column showing the quantity on hand. You can edit this file and upload it again; the **stock** column is simply ignored.

> **Note:** To change prices in bulk, export, edit the price cells, and import the file back.

See also [Add a product](help:products/add-a-product).
