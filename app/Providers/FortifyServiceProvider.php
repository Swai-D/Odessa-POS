<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()
                ->withoutGlobalScope('tenant')
                ->where('email', strtolower((string) $request->input(Fortify::username())))
                ->first();

            if (! $user || ! Hash::check((string) $request->password, $user->password)) {
                return null;
            }

            // On a shop's own address (subdomain, domain or the local override) only that shop's users may sign in.
            // On the shared address the shop shown is only a remembered guess (the session may be new or old), so
            // any valid account may sign in and is then taken to its own shop (see the Login listener below).
            $tenant = app(TenantContext::class)->get();

            if ($tenant !== null && $request->attributes->get('tenant_strict') === true && $user->tenant_id !== $tenant->getKey()) {
                return null;
            }

            return $user;
        });

        // Remember the signed-in user's own shop for the rest of the session; a platform admin has none, so a
        // shop left over from an earlier visit is forgotten.
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User || request()->attributes->get('tenant_strict') === true || ! request()->hasSession()) {
                return;
            }

            $slug = $user->tenant_id ? Tenant::query()->whereKey($user->tenant_id)->value('slug') : null;
            if ($slug) {
                request()->session()->put('tenant_slug', $slug);
            } else {
                request()->session()->forget('tenant_slug');
            }
        });

        // After signing out, go back to that shop's own sign-in page (it shows the shop's name), not the platform one.
        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;

            if ($user instanceof User && $user->tenant_id && request()->attributes->get('tenant_strict') !== true) {
                request()->attributes->set('logout_shop', Tenant::query()->whereKey($user->tenant_id)->value('slug'));
            }
        });

        $this->app->singleton(LogoutResponse::class, fn () => new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                if ($request->wantsJson()) {
                    return new JsonResponse('', 204);
                }

                $slug = $request->attributes->get('logout_shop');

                return redirect($slug ? route('tenant.entry', $slug) : '/');
            }
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input(Fortify::username())).'|'.$request->ip()
        ));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            (string) $request->session()->get('login.id')
        ));
    }
}
