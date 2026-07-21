<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('variants')
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate(25);

        return response()->json($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('variants'));

            if ($product->has_variants) {
                foreach ($request->input('variants', []) as $variantData) {
                    $variant = $product->variants()->create([
                        'store_id' => $product->store_id,
                        'variant_type' => $variantData['variant_type'],
                        'variant_value' => $variantData['variant_value'],
                        'price_override' => $variantData['price_override'] ?? null,
                        'stock_qty' => $variantData['stock_qty'] ?? 0,
                    ]);

                    Inventory::create([
                        'store_id' => $product->store_id,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'quantity_on_hand' => $variantData['stock_qty'] ?? 0,
                    ]);
                }
            } else {
                Inventory::create([
                    'store_id' => $product->store_id,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity_on_hand' => 0,
                ]);
            }

            return $product;
        });

        return response()->json(['data' => $product->load('variants', 'inventory')], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->load('variants', 'inventory')]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->safe()->except('variants'));

        return response()->json(['data' => $product->load('variants', 'inventory')]);
    }

    /**
     * No hard delete — docs/06-UX-FLOWS.md's Products screen only has add/edit.
     * "Removing" a product means taking it off the catalog, not destroying its history.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return response()->json(['data' => $product]);
    }
}
