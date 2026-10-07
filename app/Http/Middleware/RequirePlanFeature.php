<?php

namespace App\Http\Middleware;

use App\Support\Plans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware `plan:<feature>`: refuses the request when the shop's plan does not include the feature. */
class RequirePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (Plans::current()->allows($feature)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('plans.upgrade_message', ['feature' => __('plans.features.'.$feature)])], 403);
        }

        return response()->view('errors.plan-upgrade', ['feature' => $feature], 403);
    }
}
