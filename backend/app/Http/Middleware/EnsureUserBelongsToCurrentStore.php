<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Sanctum bearer token is not domain-bound — without this, a valid token
 * from Store A's admin could be pointed at Store B's subdomain and operate
 * within Store B's row-scoped data. Super Admin is exempt (cross-tenant support).
 */
class EnsureUserBelongsToCurrentStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->role === 'super_admin') {
            return CurrentStore::bypass(fn () => $next($request));
        }

        if ($user->store_id !== CurrentStore::id()) {
            abort(403);
        }

        return $next($request);
    }
}
