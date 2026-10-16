---
title: Use your own web address
summary: Open your shop at your own domain, for example pos.yourshop.co.tz, instead of the shared link.
keywords: domain, custom domain, web address, DNS, CNAME, TXT, website, link
order: 8
---
Your shop always works through its shared link. If you own a domain you can open the shop there instead, so staff and customers see your name.

## What you need
- A domain you own (from any registrar). A **subdomain** such as `pos.yourshop.co.tz` is the easiest.
- Access to its **DNS settings** at the company where you bought it.

## Set it up
1. Open **Settings → Domain**, type your domain and press **Continue**.
2. Add the two records shown, exactly as written, at your DNS provider:
   - a **TXT** record that proves the domain is yours;
   - a **CNAME** record that sends visitors to your shop.
3. Press **Check records**. DNS changes can take from minutes to a few hours, so try again later if it is not found.
4. Once verified, our team adds the domain to the hosting (usually within a working day). The page then shows **Working** and the shared link forwards to your domain.

## Good to know
- Until it shows **Working**, keep using the shared link. Nothing breaks while you wait.
- Only people of your shop can sign in on your domain.
- **Remove domain** takes it off again; the shared link keeps working.
- Plain names such as `yourshop.co.tz` cannot always use a CNAME. Use a subdomain like `pos.yourshop.co.tz`.
