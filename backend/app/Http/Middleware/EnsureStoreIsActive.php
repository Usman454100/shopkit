<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks customer browsing/ordering on a suspended store or lapsed subscription
 * with a clear response, rather than a confusing 404/500 — see the "Store
 * temporarily unavailable" edge case in docs/06-UX-FLOWS.md §1.
 */
class EnsureStoreIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = tenancy()->tenant;

        if (! $store || $store->status !== 'approved') {
            return response()->json(['message' => 'This store is temporarily unavailable.'], 503);
        }

        $subscription = $store->organization?->subscriptions()->latest()->first();

        if ($subscription && in_array($subscription->status, ['past_due', 'cancelled'], true)) {
            return response()->json(['message' => 'This store is temporarily unavailable.'], 503);
        }

        return $next($request);
    }
}
