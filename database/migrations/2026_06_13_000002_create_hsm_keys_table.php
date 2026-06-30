<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsm_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_label')->unique()->comment('密钥标签（如 license-v1）');
            $table->string('key_handle')->unique()->comment('HSM 密钥句柄');
            $table->text('public_key')->comment('公钥（hex/base64）');
            $table->string('algorithm', 20)->default('Ed25519')->comment('算法: Ed25519/RSA');
            $table->string('provider', 50)->default('software')->comment('HSM 提供者');
            $table->boolean('is_active')->default(true);
            $table->bigInteger('sign_count')->default(0)->comment('签名次数');
            $table->timestamp('rotated_at')->nullable()->comment('轮换时间');
            $table->timestamps();

            $table->index('is_active');
            $table->index('algorithm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsm_keys');
    }
};
