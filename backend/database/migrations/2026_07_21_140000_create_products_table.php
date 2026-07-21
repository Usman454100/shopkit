<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('sku')->nullable();
            $table->string('image_url')->nullable();
            $table->enum('pricing_type', ['fixed', 'weight_based'])->default('fixed');
            $table->decimal('base_price', 10, 2);
            $table->enum('unit', ['pcs', 'kg', 'g', 'litre', 'dozen', 'other'])->default('pcs');
            $table->boolean('has_variants')->default(false);
            $table->boolean('is_perishable')->default(false);
            $table->date('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
