<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable()->comment('激活IP');
            $table->string('fingerprint')->nullable()->comment('设备指纹');
            $table->string('action')->default('activate')->comment('操作类型: activate/deactivate/verify');
            $table->json('payload')->nullable()->comment('请求数据');
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
