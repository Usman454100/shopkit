<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Notifications\ProductExpired;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Console\Command;

/**
 * Runs across every store, so it must bypass BelongsToStore's scope —
 * there is no "current tenant" in a scheduled command's context.
 * See docs/03-DATABASE-SCHEMA.md §3 and docs/07-ROADMAP.md Milestone 2.
 */
class FlagExpiredProducts extends Command
{
    protected $signature = 'products:flag-expired';

    protected $description = 'Deactivate perishable products past their expiry date and notify the store owner';

    public function handle(): int
    {
        $count = CurrentStore::bypass(function () {
            $expired = Product::query()
                ->where('is_perishable', true)
                ->where('is_active', true)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<', now()->toDateString())
                ->with('store.organization.owner')
                ->get();

            foreach ($expired as $product) {
                $product->update(['is_active' => false]);

                $owner = $product->store?->organization?->owner;
                $owner?->notify(new ProductExpired($product));
            }

            return $expired->count();
        });

        $this->info("Flagged {$count} expired product(s).");

        return self::SUCCESS;
    }
}
