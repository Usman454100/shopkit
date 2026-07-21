<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use BelongsToStore, HasFactory, HasUuid;

    protected $fillable = [
        'store_id',
        'product_id',
        'variant_type',
        'variant_value',
        'price_override',
        'stock_qty',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'stock_qty' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'variant_id');
    }
}
