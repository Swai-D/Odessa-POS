<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop pages and actions only make sense inside a tenant. Without one (for example a platform super admin,
 * who belongs to no shop) the request is refused with an explanation instead of reaching the database,
 * where a tenant-owned row without a tenant_id would fail with a raw SQL error.
 */
class RequireTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->get() !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('app.tenant_required')], 403);
        }

        return response()->view('errors.tenant-required', [], 403);
    }
}
