# Error tracking (Sentry)

Off by default. Set one variable and every unexpected error (server and browser) is sent to Sentry, tagged with the
shop and the deployed version, so you hear about problems before a shopkeeper calls.

## Switch it on (Railway)
1. Create a project at sentry.io (platform: PHP / Laravel) and copy its **DSN**.
2. In Railway add `SENTRY_DSN=<the DSN>` and, optionally, `SENTRY_ENVIRONMENT=production`.
3. Redeploy, then run once: `php artisan errors:test` (it prints `HTTP 200` when connected). A test event appears in Sentry.
4. In Sentry create an alert: "notify me by email on every new issue".

Works with any Sentry-compatible server (for example self-hosted GlitchTip): only the DSN changes.

## What is sent
Exception class, message, stack trace, shop slug, shop and user **id**, request method and URL **without** query string,
release (Railway commit) and environment.

## What is never sent
Request bodies, cookies, headers, query strings, emails, passwords, query bindings. Emails in messages and values in
database messages (`Key (email)=(...)`) are masked. Customer, sale and payment data never leave the server this way.

## How it behaves
- No SDK and no Composer package: `App\Support\ErrorTracking\SentryReporter` posts to Sentry's envelope API after the response is sent. A failing error server never breaks a page.
- The same error is sent once per minute (`SENTRY_DEDUPE_SECONDS`). `SENTRY_SAMPLE_RATE` (0 to 1) can thin it out.
- Normal logging is unchanged. Validation, 404 and authorisation errors are not reported (Laravel ignores them).
- Browser errors (till, offline mode) come in through `/client-errors`: at most 5 per page load, nothing stored. Turn off with `SENTRY_BROWSER=false`.
