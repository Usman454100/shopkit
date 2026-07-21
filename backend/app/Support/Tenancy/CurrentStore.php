<?php

namespace App\Support\Tenancy;

/**
 * Holds the store_id that tenant-scoped queries (via App\Models\Concerns\BelongsToStore)
 * are filtered by for the current request/command. Set by tenant-identification
 * middleware once a store is resolved from the subdomain.
 */
class CurrentStore
{
    protected static ?string $id = null;

    protected static bool $bypassed = false;

    public static function set(?string $storeId): void
    {
        static::$id = $storeId;
    }

    public static function id(): ?string
    {
        return static::$id;
    }

    public static function clear(): void
    {
        static::$id = null;
    }

    /**
     * Explicitly run a callback without store scoping (e.g. Super Admin
     * cross-tenant views). This is the only sanctioned way to see past the
     * global scope — anything else bypassing it is a bug, not a shortcut.
     */
    public static function bypass(callable $callback): mixed
    {
        $previous = static::$bypassed;
        static::$bypassed = true;

        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }

    public static function isBypassed(): bool
    {
        return static::$bypassed;
    }
}
