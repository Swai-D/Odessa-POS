# TRA fiscal receipts (VFD) — design notes

Source: the community TRA VFD API documentation (https://tra-docs.netlify.app/guide/api/, repo alphaolomi/tra-docs). It is not official; each shop's TRA registration and certification decide what is finally accepted.

## Plan (one PR each)
1. **Core (this PR):** `fiscal_receipts` ledger, gap-free GC and daily DC counters, frozen receipt XML, SHA-1/RSA signer. No network, not hooked into checkout.
2. **Sending:** registration, token cache, ordered one-at-a-time queue with retry of the *original* XML until `ACKCODE 0`, Z report, QR + verification code on receipts, hook into checkout.
3. **Connector UI:** each shop saves its own TIN, certificate (.pfx + password) and registration data under Settings → Integrations (`fiscal` channel); secrets are stored encrypted.

## Rules built into the core
- GC = receipt number, +1 per receipt, no gaps, never reused (row-locked sequence inside the issuing transaction).
- DC restarts at 1 each Tanzanian day; ZNUM = date as YYYYMMDD (Africa/Dar_es_Salaam, not server time).
- XML is frozen at issue; a resend must use the same content, date and time.
- Text is XML-escaped; control characters are dropped.

## Assumptions to confirm during TRA certification
- A 0% item is reported under the shop's chosen code (special, zero-rated, relief or exempt); rates other than the standard 18% and 0% are refused.
- Mobile money and bank transfer are reported as EMONEY, card as CCARD.
- The unpaid part of a credit sale is reported as an `INVOICE` amount next to the paid parts.
- Item `AMT` is the line total after discount, tax included; `DISCOUNT` is the sale's discount total.
- Customer ID type is always 6 (none) until customers get a TIN field.
- Returns / credit notes are not described in the community docs; they need the official TRA document.
