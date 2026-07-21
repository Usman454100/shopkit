<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer-facing, read-only catalog browsing — anonymous (no auth required),
 * per docs/01-PRD.md §7.2 and the updated Milestone 3 decision to allow
 * browsing before login (auth is only required at checkout).
 */
class CatalogController extends Controller
{
    private function visibleQuery()
    {
        return Product::query()
            ->with(['variants.inventory', 'inventory'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('is_perishable', false)
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->visibleQuery()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->latest()
            ->paginate(25);

        return CustomerProductResource::collection($products);
    }

    public function show(string $product)
    {
        $product = $this->visibleQuery()->findOrFail($product);

        return new CustomerProductResource($product);
    }
}
