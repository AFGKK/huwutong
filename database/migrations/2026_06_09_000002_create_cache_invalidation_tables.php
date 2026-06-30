<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cache_invalidations')) {
            return;
        }

        Schema::create('cache_invalidations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index()->comment('租户 ID');
            $table->string('invalidation_key', 100)->comment('失效键，如 license.status.123, featureflag.premium_ai');
            $table->string('type', 50)->comment('类型: license_status, feature_flag, product_config, heartbeat');
            $table->json('context')->nullable()->comment('上下文（旧值/新值/变更描述）');
            $table->string('status', 20)->default('pending')->comment('状态: pending, published, failed, merged');
            $table->unsignedSmallInteger('attempts')->default(0)->comment('推送尝试次数');
            $table->timestamp('published_at')->nullable()->comment('推送成功时间');
            $table->timestamp('last_attempt_at')->nullable()->comment('最后尝试时间');
            $table->text('last_error')->nullable()->comment('最后一次错误信息');
            $table->string('channel', 30)->default('reverb')->comment('推送通道: reverb, webhook, sse');
            $table->string('group_hash', 64)->nullable()->index()->comment('合并组哈希（相同hash合并推送）');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'idx_tenant_status_created');
            $table->index(['tenant_id', 'type'], 'idx_tenant_type');
        });

        // 已存在的配置变更日志
        if (! Schema::hasTable('cache_invalidation_webhooks')) {
            Schema::create('cache_invalidation_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index()->comment('租户 ID');
                $table->string('url')->comment('SDK 回调 Webhook URL');
                $table->string('secret')->nullable()->comment('签名密钥');
                $table->json('subscribed_types')->nullable()->comment('订阅的失效类型');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_invalidation_webhooks');
        Schema::dropIfExists('cache_invalidations');
    }
};
