<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges stancl/tenancy's subdomain-resolved tenant into App\Support\Tenancy\CurrentStore,
 * which is what BelongsToStore's global scope actually reads (see docs/02-ARCHITECTURE.md §3).
 * Must run after InitializeTenancyByDomain, which resolves tenancy()->tenant.
 */
class SyncCurrentStoreWithTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        CurrentStore::set(tenancy()->tenant?->getTenantKey());

        try {
            return $next($request);
        } finally {
            CurrentStore::clear();
        }
    }
}
