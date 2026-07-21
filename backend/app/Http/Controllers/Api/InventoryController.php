<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inventory = Inventory::query()
            ->with(['product', 'variant'])
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            )
            ->when($request->boolean('expiring_soon'), function ($query) {
                $horizon = now()->addDays(config('shopkit.expiring_soon_days'));

                $query->whereHas('product', function ($productQuery) use ($horizon) {
                    $productQuery->where('is_perishable', true)
                        ->whereNotNull('expiry_date')
                        ->whereDate('expiry_date', '<=', $horizon);
                });
            })
            ->latest()
            ->paginate(25);

        return response()->json($inventory);
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity_on_hand' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $inventory->update($data + ['last_restocked_at' => now()]);

        return response()->json(['data' => $inventory]);
    }
}
