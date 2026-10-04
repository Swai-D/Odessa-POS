<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $identifier = null;
        $tenant = Tenant::query()->where('domain', $host)->first();
        $configuredDomain = (string) config('app.domain');
        $domainUrl = str_contains($configuredDomain, '://') ? $configuredDomain : 'http://'.$configuredDomain;
        $appDomain = strtolower((string) parse_url($domainUrl, PHP_URL_HOST));
        $suffix = '.'.$appDomain;

        if (! $tenant && $appDomain !== '' && str_ends_with($host, $suffix)) {
            $identifier = substr($host, 0, -strlen($suffix));
            $tenant = Tenant::query()->where('slug', $identifier)->first();
        }

        $isLocal = app()->environment('local') || in_array($host, ['localhost', '127.0.0.1'], true);

        if (! $tenant && $isLocal) {
            $identifier = $request->header('X-Tenant') ?: $request->session()->get('tenant_slug');

            if ($identifier) {
                $tenant = Tenant::query()->where('slug', $identifier)->first();
            }
        }

        if ($identifier && ! $tenant) {
            abort(404);
        }

        if (! $tenant && ! $isLocal && $host !== $appDomain) {
            abort(404);
        }

        if ($tenant && ! in_array($tenant->status, ['active', 'trial'], true)) {
            abort(403);
        }

        $this->tenantContext->set($tenant);
        // Spatie roles/permissions run in "teams" mode: scope them to the resolved tenant.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant?->getKey());

        try {
            return $next($request);
        } finally {
            $this->tenantContext->forget();
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }
    }
}
