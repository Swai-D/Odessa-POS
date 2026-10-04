<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?: data_get(app(TenantContext::class)->get()?->settings, 'locale')
            ?: $request->session()->get('locale')
            ?: 'en';

        app()->setLocale(in_array($locale, ['en', 'sw'], true) ? $locale : 'en');

        return $next($request);
    }
}
