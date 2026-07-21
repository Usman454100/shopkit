<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\GeneratesIds;
use Stancl\Tenancy\Database\Concerns\HasDataColumn;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\InvalidatesResolverCache;
use Stancl\Tenancy\Database\Concerns\TenantRun;

/**
 * Store is the tenant model — there is no separate generic `tenants` table.
 * A store's own `id` is the tenant key used by subdomain identification.
 */
class Store extends Model implements TenantContract
{
    use CentralConnection,
        GeneratesIds,
        HasDataColumn,
        HasDomains,
        HasInternalKeys,
        InvalidatesResolverCache,
        TenantRun;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'category',
        'address',
        'latitude',
        'longitude',
        'logo_url',
        'status',
        'isolation_tier',
        'tenant_db_name',
    ];

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Real columns on `stores` — anything not listed here would otherwise be
     * silently redirected into the `data` JSON blob by VirtualColumn (it defaults
     * to only ['id'], since the package's generic tenants table has no other columns).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'organization_id',
            'name',
            'slug',
            'category',
            'address',
            'latitude',
            'longitude',
            'logo_url',
            'status',
            'isolation_tier',
            'tenant_db_name',
            'created_at',
            'updated_at',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
