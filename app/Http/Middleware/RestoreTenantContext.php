<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;

class RestoreTenantContext
{
    public function handle($job, Closure $next): mixed
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant
            ? app(TenantContext::class)->run($tenant, fn () => $next($job))
            : $next($job);
    }
}
