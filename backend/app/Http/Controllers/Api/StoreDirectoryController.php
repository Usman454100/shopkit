<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerStoreJoin;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central, public store discovery for the join-store onboarding flow (QR/link
 * resolution, manual search) — see docs/06-UX-FLOWS.md §1 Onboarding.
 */
class StoreDirectoryController extends Controller
{
    private const PUBLIC_FIELDS = ['id', 'name', 'slug', 'category', 'address', 'logo_url'];

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'string']]);

        $store = Store::query()
            ->where('slug', $data['slug'])
            ->where('status', 'approved')
            ->first(self::PUBLIC_FIELDS);

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        return response()->json(['data' => $store]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['nullable', 'string', 'max:255']]);

        $stores = Store::query()
            ->where('status', 'approved')
            ->when($data['query'] ?? null, fn ($q, $query) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->paginate(25, self::PUBLIC_FIELDS);

        return response()->json($stores);
    }

    /**
     * Idempotent — joining is a convenience record for "switch store" (Profile
     * screen), not an authorization boundary. Any authenticated customer can
     * already reach any approved store's subdomain directly.
     */
    public function join(Request $request, Store $store): JsonResponse
    {
        if ($store->status !== 'approved') {
            return response()->json(['message' => 'This store is not available.'], 404);
        }

        $join = CustomerStoreJoin::query()->firstOrCreate(
            ['customer_id' => $request->user()->id, 'store_id' => $store->id],
            ['joined_at' => now()],
        );

        return response()->json(['data' => $join], 201);
    }
}
