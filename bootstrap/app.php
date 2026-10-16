<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnforceSubscription;
use App\Http\Middleware\RequirePlanFeature;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\ErrorTracking\SentryReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Outside the web group on purpose: no session, shop or database work before the check itself.
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway's proxy is the only way in, so trust it for the client address, port and scheme (HTTPS asset URLs).
        // The forwarded Host header is NOT trusted: shops are chosen by host name, so it must be the real Host.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            SecurityHeaders::class,
            ResolveTenant::class,
            SetLocale::class,
        ]);

        // Provider callbacks cannot carry our CSRF token; they are verified by signature instead.
        // Browser error reports are throttled, stored nowhere and may come from a page whose token went stale.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'client-errors']);

        $middleware->alias([
            'tenant.required' => RequireTenant::class,
            'plan' => RequirePlanFeature::class,
            'subscription' => EnforceSubscription::class,
        ]);

        // The tenant must be known before the session user is loaded (Authenticate) and before route
        // model binding (SubstituteBindings): both query tenant-scoped models. Laravel sorts those two
        // by priority, so an unlisted ResolveTenant appended to the group would run after them.
        // It needs the session (local tenant fallback), so it goes right after ShareErrorsFromSession.
        $middleware->appendToPriorityList(after: ShareErrorsFromSession::class, append: ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Error tracking (Sentry) is off until SENTRY_DSN is set. This adds to normal logging, it does not replace it.
        $exceptions->report(function (Throwable $e): void {
            app(SentryReporter::class)->capture($e);
        });
    })->create();
