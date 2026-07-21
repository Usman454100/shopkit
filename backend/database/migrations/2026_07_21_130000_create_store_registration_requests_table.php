<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_registration_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('business_name');
            $table->enum('category', ['grocery', 'vegetable', 'shoe', 'other'])->default('other');
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone');
            $table->string('address');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_registration_requests');
    }
};
