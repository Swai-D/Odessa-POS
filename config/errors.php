<?php

/*
 | Error tracking with Sentry (or any Sentry-compatible server, such as GlitchTip). Off until a DSN is set.
 | There is no Sentry SDK: App\Support\ErrorTracking\SentryReporter sends the events itself, so nothing
 | else has to be installed. Only error details and technical context are sent, never request data.
 */
return [
    'dsn' => env('SENTRY_DSN', env('SENTRY_LARAVEL_DSN')),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    // Railway sets this to the deployed commit, so each error shows which version it came from.
    'release' => env('SENTRY_RELEASE', env('RAILWAY_GIT_COMMIT_SHA')),
    // The same error is reported at most once per this many seconds.
    'dedupe_seconds' => (int) env('SENTRY_DEDUPE_SECONDS', 60),
    // Fraction of errors sent, 0 to 1.
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),
    // Report JavaScript errors from the browser too (till, offline mode).
    'browser' => (bool) env('SENTRY_BROWSER', true),
];
