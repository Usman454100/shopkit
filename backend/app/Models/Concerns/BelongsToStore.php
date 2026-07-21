<?php

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every tenant-scoped model (products, orders, inventory, etc.).
 * Any query on such a model that bypasses this scope is a bug, not a
 * shortcut — see docs/02-ARCHITECTURE.md §3.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model) {
            if (! $model->store_id && $storeId = CurrentStore::id()) {
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
