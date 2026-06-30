<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('key', 64)->unique();
            $table->string('prefix', 10);
            $table->json('abilities')->nullable();
            $table->string('ip_whitelist')->nullable()->comment('逗号分隔IP白名单');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('prefix');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_api_keys');
    }
};
