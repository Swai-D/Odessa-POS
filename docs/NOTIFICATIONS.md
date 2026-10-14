# Notifications (email + SMS)

Shop owners get an email and an SMS for: subscription reminders (7, 3 days before, on the day, and the last day of grace), a payment receipt when a renewal is recorded. The platform team can get an alert when a shop sends "I have paid".

- Messages are queued (database queue). The queue worker and scheduler start with the container (`RUN_WORKERS=1`, see `docker/entrypoint.sh`).
- `subscriptions:remind` runs daily at 08:00 Africa/Dar_es_Salaam and records each reminder in `subscription_reminders`, so it never sends twice.
- A failed send is retried 3 times (1 min, 5 min) and never blocks a payment or renewal.
- Notifications carry only plain text values, never models.

## Email (Resend over SMTP)
Verify your sending domain in Resend (SPF/DKIM), then set `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.resend.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME=resend`, `MAIL_PASSWORD=<API key>`, `MAIL_FROM_ADDRESS`.

## SMS (NextSMS)
Set `SMS_DRIVER=nextsms`, `NEXTSMS_USERNAME`, `NEXTSMS_PASSWORD` and `SMS_SENDER_ID` (a sender ID registered with NextSMS). `NEXTSMS_SEND_PATH` defaults to `/api/sms/v1/text/single` with body `{from, to, text, reference}`; confirm it against the "Send SMS" section of the NextSMS documentation and change it if it differs. With `SMS_DRIVER=log` messages are only written to the log.

## Low-stock digest
`stock:low-alerts` runs daily at 08:30 (Africa/Dar_es_Salaam). It sends the owner one message listing products at or below their alert quantity, only when that list changed since the last digest or after 7 days (`low_stock_alerts`). It works shop by shop inside that shop's tenant context.
