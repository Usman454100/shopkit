<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Enforces row-level tenant isolation for shared-DB (Basic/Pro) stores.
 * Fails closed: with no current store and no explicit bypass, the query
 * returns zero rows rather than every tenant's data.
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (CurrentStore::isBypassed()) {
            return;
        }

        $storeId = CurrentStore::id();

        if ($storeId !== null) {
            $builder->where($model->qualifyColumn('store_id'), $storeId);

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
