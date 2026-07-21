<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for order status changes (04-FEATURES-BY-PHASE.md's
     * cross-cutting requirement: "who/when") — also backs the customer app's
     * order-tracking timeline, which Milestone 3 left without history.
     * Written only on actual transitions, not at order creation.
     */
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('from_status', ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled']);
            $table->enum('to_status', ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled']);
            $table->foreignUuid('changed_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
