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
}
