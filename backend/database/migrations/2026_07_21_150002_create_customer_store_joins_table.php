<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Not tenant-scoped (no BelongsToStore) — like wishlists/reviews in
     * 03-DATABASE-SCHEMA.md, this is a customer-centric record queried across
     * stores ("which stores have I joined"), not a per-store admin view.
     * A convenience list for the Profile "switch store" screen — not an
     * authorization boundary (see docs/07-ROADMAP.md Milestone 3 notes).
     */
    public function up(): void
    {
        Schema::create('customer_store_joins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['customer_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_store_joins');
    }
};
