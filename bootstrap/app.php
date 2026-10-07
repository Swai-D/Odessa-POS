<?php

use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ResolveTenant::class,
            SetLocale::class,
        ]);

        $middleware->alias(['tenant.required' => RequireTenant::class]);

        // The tenant must be known before the session user is loaded (Authenticate) and before route
        // model binding (SubstituteBindings): both query tenant-scoped models. Laravel sorts those two
        // by priority, so an unlisted ResolveTenant appended to the group would run after them.
        // It needs the session (local tenant fallback), so it goes right after ShareErrorsFromSession.
        $middleware->appendToPriorityList(after: ShareErrorsFromSession::class, append: ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
