<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lark_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->default('飞书集成');
            $table->boolean('is_enabled')->default(false);

            // 自建应用配置
            $table->string('app_id', 100)->nullable();
            $table->text('app_secret')->nullable();
            $table->string('encrypt_key', 100)->nullable();
            $table->string('verification_token', 100)->nullable();

            // 机器人配置
            $table->text('bot_webhook_url')->nullable();       // 群机器人 Webhook URL
            $table->boolean('notify_enabled')->default(true);   // 启用通知推送

            // 应用商店应用配置
            $table->string('store_app_id', 100)->nullable();

            // 自动缓存
            $table->text('tenant_token')->nullable();
            $table->timestamp('tenant_token_expires_at')->nullable();

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lark_integrations');
    }
};
