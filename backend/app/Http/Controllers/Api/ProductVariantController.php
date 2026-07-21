<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'variant_type' => ['required', Rule::in(['size', 'color', 'other'])],
            'variant_value' => ['required', 'string', 'max:100'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $variant = $product->variants()->create($data + ['store_id' => $product->store_id]);

        Inventory::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => $data['stock_qty'] ?? 0,
        ]);

        if (! $product->has_variants) {
            // The old base inventory row (variant_id=null) no longer represents
            // anything sellable once the product tracks stock per variant instead.
            $product->inventory()->whereNull('variant_id')->delete();
            $product->update(['has_variants' => true]);
        }

        return response()->json(['data' => $variant], 201);
    }

    /**
     * stock_qty is intentionally not editable here — ongoing stock changes go
     * through InventoryController, which is the source of truth for stock levels.
     */
    public function update(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'variant_type' => ['required', Rule::in(['size', 'color', 'other'])],
            'variant_value' => ['required', 'string', 'max:100'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $variant->update($data);

        return response()->json(['data' => $variant]);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json(null, 204);
    }
}
