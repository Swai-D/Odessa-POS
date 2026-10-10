<?php

namespace App\Http\Middleware;

use App\Support\Plans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware `plan:<feature>[,<feature>...]`: refuses the request unless the shop's plan includes at least one of them. */
class RequirePlanFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        if (Plans::current()->allowsAny(...$features)) {
            return $next($request);
        }

        $feature = $features[0];

        if ($request->expectsJson()) {
            return response()->json(['message' => __('plans.upgrade_message', ['feature' => __('plans.features.'.$feature)])], 403);
        }

        return response()->view('errors.plan-upgrade', ['feature' => $feature], 403);
    }
}
