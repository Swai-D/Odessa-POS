---
title: Printers and barcode scanners
summary: Fix common problems with receipt printing and barcode scanning at the till.
keywords: printer, printing, receipt, thermal, escpos, usb, bluetooth, scanner, barcode, not working, chrome, edge, paper, 80mm, 58mm
order: 5
---
Most printing problems come from the browser or the printer settings, not from the system.

## Receipts: the basics

- Open **Sales**, click the sale, choose **80mm receipt** or **A4 invoice**, then **Print**. This uses your browser's normal print window and works with any printer.
- **Download PDF** always works if the printer does not.

## Thermal printer

Direct printing to a thermal (ESC/POS) printer is set up under **Settings > Integrations**. See [Integrations](help:settings/integrations).

If it does not print:

1. Use **Chrome or Edge** on a computer. Other browsers show a message that they are not supported.
2. Check the printer is on, has paper, and is connected by USB or paired by Bluetooth.
3. In **Integrations**, check **Enabled** is on, the **Connection** matches how it is plugged in, and the **Paper width** is right (58 mm or 80 mm).
4. When the browser asks you to choose the printer the first time, pick your receipt printer.
5. If it still fails, use **Print** or **Download PDF** to finish the customer's receipt and fix the printer later.

## Barcode scanners

A scanner works like a keyboard. There is nothing to set up in the system.

1. Click once in the search box at the top of the **POS** screen.
2. Scan. The scanner types the code and presses Enter, and the product is added.
3. If nothing is added, check the product has the same **Barcode** (see [Add a product](help:products/add-a-product)), and that the scanner is not set to add extra characters.
4. If it types in the wrong place, click the search box again.

> **Note:** Phone-paid receipts and fiscal (TRA) receipts are not available yet. The printed receipt is your shop receipt.
