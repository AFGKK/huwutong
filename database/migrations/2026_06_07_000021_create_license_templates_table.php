<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type')->default('standard')->comment('trial, standard, enterprise, development');
            $table->integer('seats')->default(1);
            $table->integer('max_devices')->default(1);
            $table->integer('expiry_days')->nullable()->comment('过期天数（从创建时算起，null 为永久）');
            $table->json('metadata')->nullable()->comment('默认元数据 JSON');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_templates');
    }
};
