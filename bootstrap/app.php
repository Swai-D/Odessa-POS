<?php

use App\Http\Middleware\EnforceSubscription;
use App\Http\Middleware\RequirePlanFeature;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway's proxy is the only way in, so trust it for the client address, port and scheme (HTTPS asset URLs).
        // The forwarded Host header is NOT trusted: shops are chosen by host name, so it must be the real Host.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            ResolveTenant::class,
            SetLocale::class,
        ]);

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
        //
    })->create();
