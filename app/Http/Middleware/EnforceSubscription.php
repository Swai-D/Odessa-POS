<?php

namespace App\Http\Middleware;

use App\Support\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the shop's subscription state: a suspended shop is switched off, and once the grace period is over the
 * shop becomes read-only (reading and printing still work, anything that changes data is refused).
 */
class EnforceSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = Subscription::current();
        $state = $subscription->state();

        if ($state === Subscription::SUSPENDED) {
            return $this->refuse($request, 'subscription.suspended_message', 'suspended');
        }

        if ($state === Subscription::READONLY && ! $request->isMethodSafe()) {
            return $this->refuse($request, 'subscription.readonly_message', 'readonly');
        }

        return $next($request);
    }

    private function refuse(Request $request, string $message, string $reason): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __($message)], 403);
        }

        return response()->view('errors.subscription', ['reason' => $reason, 'message' => __($message)], 403);
    }
}
