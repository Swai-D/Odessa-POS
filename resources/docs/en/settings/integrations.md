---
title: Set up receipt printing (Integrations)
summary: Choose how receipts are printed, and see which optional connections are and are not available.
keywords: integrations, printer, thermal printer, ESC/POS, receipt printer, browser print, paper width, USB, Bluetooth, mobile money, TRA, fiscal, EFD, VFD
order: 3
plan: medium
---
The **Integrations** page lets you plug in optional connections. Everything here is off until you choose a provider and switch it on. It needs the Medium plan and the settings permission (the Owner by default).

## What is available today

| Connection | Status |
|---|---|
| **Receipt printer** | Available: browser print or a thermal (ESC/POS) printer. |
| **TRA fiscal receipts** | Not available yet. |
| **Mobile money** | Not available yet. |

For **TRA fiscal receipts** and **Mobile money** the page says no providers are available. The system does not send sales to TRA or collect money from a customer's phone. You can still choose **Mobile Money** as the payment method when you record a sale, but you record it yourself.

## Set up a receipt printer

1. In the sidebar, under **Settings**, open **Integrations**.
2. In the **Receipt printer** box, choose a **Provider**:
   - **Browser print (any printer)** uses the normal print window of your browser. It needs no extra settings.
   - **Thermal printer (ESC/POS)** sends the receipt straight to a receipt printer. Choose the **Connection** (**USB serial or paired Bluetooth**, or **USB (WebUSB)**), the **Paper width** (58 mm or 80 mm), the number of **Copies**, and whether to **Cut paper after printing**.
3. Turn on the **Enabled** switch.
4. Press **Save**.

## Print a receipt

1. Open **Sales** and click a sale.
2. Press **Print** to use the browser print window. A **PDF** button is also there.
3. If you chose the thermal printer, press **Print to thermal printer**. Your browser asks you to pick the printer the first time.

> **Note:** Thermal printing needs Chrome or Edge. In other browsers you will see a message to use Chrome or Edge, or to use browser print.

## Barcode scanners

A barcode scanner needs no setup here. It works like a keyboard: click the search box on the **POS** screen, scan, and the product is added. See [Printers and barcode scanners](help:troubleshooting/printing-and-scanners).

> **Tip:** If you cannot see **Integrations**, your plan may be Basic or your role may not allow it. See [Plans compared](help:plans/plans-compared) and [Users and roles](help:settings/users-and-roles).
