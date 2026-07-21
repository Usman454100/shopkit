<?php

namespace App\Http\Controllers\Api\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Customers who've ordered from this store (docs/06-UX-FLOWS.md §2).
     * whereHas/withCount on `orders` are automatically scoped to the current
     * store — Order carries BelongsToStore, so its global scope applies even
     * inside these subqueries.
     */
    public function index(): JsonResponse
    {
        $customers = User::query()
            ->where('role', 'customer')
            ->whereHas('orders')
            ->withCount('orders')
            ->paginate(25);

        return response()->json($customers);
    }

    public function show(User $customer): JsonResponse
    {
        abort_unless($customer->role === 'customer' && $customer->orders()->exists(), 404);

        return response()->json([
            'data' => $customer,
            'orders' => $customer->orders()->with('items')->latest()->paginate(25),
        ]);
    }
}
