<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * store_id is denormalized here (not in the original schema doc, which only
     * has product_id) so BelongsToStore can scope variants directly — otherwise
     * a direct ProductVariant query has no isolation without joining through products.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('variant_type', ['size', 'color', 'other'])->default('other');
            $table->string('variant_value');
            $table->decimal('price_override', 10, 2)->nullable();
            $table->unsignedInteger('stock_qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
