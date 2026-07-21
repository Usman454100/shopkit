<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToStore, HasFactory, HasUuid;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'category',
        'sku',
        'image_url',
        'pricing_type',
        'base_price',
        'unit',
        'has_variants',
        'is_perishable',
        'expiry_date',
        'batch_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'has_variants' => 'boolean',
            'is_perishable' => 'boolean',
            'is_active' => 'boolean',
            'expiry_date' => 'date',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * True if any sellable unit (the product itself, or any variant) has stock.
     * Used to derive the customer-facing "in stock" flag without exposing
     * exact backroom quantities (see docs/06-UX-FLOWS.md's out-of-stock edge case).
     */
    public function isInStock(): bool
    {
        return $this->inventory->sum('quantity_on_hand') > 0;
    }

    public function isExpired(): bool
    {
        return $this->is_perishable && $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
