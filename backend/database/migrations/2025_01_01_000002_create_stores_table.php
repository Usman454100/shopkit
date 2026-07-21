<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store is the tenant model (see App\Models\Store) — there is no separate
     * generic `tenants` table. `data` is required by stancl/tenancy's HasDataColumn concern.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('category', ['grocery', 'vegetable', 'shoe', 'other'])->default('other');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->enum('isolation_tier', ['shared', 'dedicated'])->default('shared');
            $table->string('tenant_db_name')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
