<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Conditional rules per docs/03-DATABASE-SCHEMA.md §3: weight_based products
 * are restricted to kg/g units, perishables require an expiry date, and
 * variants (when has_variants=true) are validated as a nested array.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('store_id', CurrentStore::id()))
                    ->ignore($productId),
            ],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'pricing_type' => ['required', Rule::in(['fixed', 'weight_based'])],
            'base_price' => ['required', 'numeric', 'min:0'],
            'unit' => [
                'required',
                $this->input('pricing_type') === 'weight_based'
                    ? Rule::in(['kg', 'g'])
                    : Rule::in(['pcs', 'kg', 'g', 'litre', 'dozen', 'other']),
            ],
            'has_variants' => ['boolean'],
            'is_perishable' => ['boolean'],
            'expiry_date' => ['required_if:is_perishable,true', 'nullable', 'date'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'variants' => ['required_if:has_variants,true', 'array'],
            'variants.*.variant_type' => ['required_with:variants', Rule::in(['size', 'color', 'other'])],
            'variants.*.variant_value' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
