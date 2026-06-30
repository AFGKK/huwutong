<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sdk_cache_records')) {
            return;
        }
        // SDK 缓存记录（服务端追踪各 SDK 实例的缓存状态）
        Schema::create('sdk_cache_records', function (Blueprint $table) {
            $table->id();
            $table->string('sdk_instance_id', 64)->comment('SDK实例唯一标识');
            $table->string('language', 20)->nullable()->comment('php/node/python/go/java');
            $table->string('sdk_version', 20)->nullable()->comment('SDK版本号');
            $table->string('machine_id', 64)->nullable()->comment('机器标识');
            $table->string('license_key', 64)->nullable()->comment('缓存的License Key');
            $table->string('cache_key_hash', 64)->comment('缓存键的SHA256哈希');
            $table->string('status', 20)->default('active')->comment('active/expired/invalidated/tampered');
            $table->timestamp('cached_at')->nullable()->comment('首次缓存时间');
            $table->timestamp('expires_at')->nullable()->comment('缓存过期时间');
            $table->timestamp('grace_expires_at')->nullable()->comment('宽限期到期时间');
            $table->timestamp('last_access_at')->nullable()->comment('最后访问时间');
            $table->integer('access_count')->default(0)->comment('缓存访问次数');
            $table->string('last_verification_result', 20)->nullable()->comment('最近验证结果');
            $table->boolean('is_offline')->default(false)->comment('是否离线模式');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['sdk_instance_id', 'cache_key_hash'], 'uk_sdk_cache');
            $table->index('sdk_instance_id');
            $table->index('license_key');
            $table->index('status');
            $table->index('expires_at');
            $table->index('grace_expires_at');
        });

        // SDK 缓存失效事件日志
        Schema::create('sdk_cache_invalidation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sdk_instance_id', 64)->nullable()->comment('目标SDK实例');
            $table->string('license_key', 64)->nullable()->comment('关联License Key');
            $table->string('trigger_type', 40)->comment('license_change/device_change/feature_change/manual');
            $table->string('reason', 200)->nullable();
            $table->json('affected_cache_keys')->nullable()->comment('受影响的缓存键列表');
            $table->string('source', 40)->default('system')->comment('system/admin/webhook');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sdk_instance_id');
            $table->index('trigger_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdk_cache_invalidation_logs');
        Schema::dropIfExists('sdk_cache_records');
    }
};
