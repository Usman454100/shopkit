<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer-facing view of a product — deliberately narrower than the admin
 * serialization in ProductController: no sku, no exact stock counts, just an
 * in_stock flag (see docs/06-UX-FLOWS.md's out-of-stock/perishable edge cases).
 */
class CustomerProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'image_url' => $this->image_url,
            'pricing_type' => $this->pricing_type,
            'base_price' => $this->base_price,
            'unit' => $this->unit,
            'has_variants' => $this->has_variants,
            'is_perishable' => $this->is_perishable,
            'in_stock' => $this->isInStock(),
            'variants' => $this->when($this->has_variants, fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'variant_type' => $variant->variant_type,
                'variant_value' => $variant->variant_value,
                'price_override' => $variant->price_override,
                'in_stock' => $variant->isInStock(),
            ])),
        ];
    }
}
