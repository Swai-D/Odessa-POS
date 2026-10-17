---
title: Barcode scanner and cash drawer
summary: What the two hardware switches in Settings do at the till, and what you need for each.
keywords: barcode scanner, scan, cash drawer, till, ESC/POS, hardware, beep
order: 9
---
Both are switches under **Settings → Optional features**. They are off until you switch them on.

## Barcode scanner
Any USB or Bluetooth scanner that types the code and presses Enter works. With the switch on, the till:
- keeps the cursor in the search box, also after you close a window;
- beeps when a scanned item is added;
- sounds a low tone and shows "Not found" when the code is not in your products.

With the switch off, Enter in the search box only searches.

Print labels for products without a barcode from **Products → barcode icon**.

## Cash drawer
The drawer is wired to your **ESC/POS receipt printer** (the RJ11 socket on the printer), so first set that printer up in **Settings → Integrations → Printer** with the **ESC/POS** driver. With the switch on, the till shows:
- an **Open drawer** button, for when you give change without a sale;
- an automatic drawer opening after every sale that includes **cash**.

It works in Chrome or Edge on https. The first time, the browser asks you to choose the printer; allow it once. If the switch is on but the printer is not set up, the page tells you.
