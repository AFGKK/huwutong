<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 本地代理节点注册表
        Schema::create('local_proxy_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('代理节点名称');
            $table->string('node_id', 64)->unique()->comment('唯一节点标识(UUID)');
            $table->string('register_token', 128)->nullable()->comment('注册令牌(首次注册用)');
            $table->string('api_key', 128)->nullable()->comment('API密钥(后续通信用)');
            $table->string('base_url', 255)->nullable()->comment('代理服务内网地址');
            $table->string('version', 20)->nullable()->comment('代理软件版本');
            $table->string('os', 50)->nullable()->comment('操作系统');
            $table->string('architecture', 20)->nullable()->comment('CPU架构');
            $table->json('capabilities')->nullable()->comment('能力: [offline_auth, heartbeat, crl_sync, cache]');
            $table->enum('status', ['pending', 'active', 'paused', 'decommissioned'])->default('pending');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 2. 心跳记录
        Schema::create('local_proxy_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('local_proxy_nodes')->cascadeOnDelete();
            $table->timestamp('heartbeat_at');
            $table->json('metrics')->nullable()->comment('运行时指标: cpu/mem/disk/uptime');
            $table->json('cache_stats')->nullable()->comment('缓存统计: cached_licenses/validated_count/failed_count');
            $table->string('status', 20)->default('healthy')->comment('healthy/degraded/offline');
            $table->string('error_message')->nullable();
            $table->timestamps();
        });

        // 3. 代理缓存License记录（代理从云端拉取license文件缓存在本地）
        Schema::create('local_proxy_cached_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('local_proxy_nodes')->cascadeOnDelete();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('license_key', 64);
            $table->string('cache_key', 128)->comment('缓存唯一标识');
            $table->text('cached_payload')->comment('缓存License文件内容(base64)');
            $table->timestamp('cached_at');
            $table->timestamp('expires_at')->nullable()->comment('缓存过期时间');
            $table->timestamp('last_verified_at')->nullable();
            $table->bigInteger('verify_count')->default(0);
            $table->timestamps();

            $table->unique(['node_id', 'license_id']);
            $table->index(['node_id', 'expires_at']);
        });

        // 4. 离线激活请求（内网设备通过代理验证时，代理记录请求）
        Schema::create('local_proxy_activation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('local_proxy_nodes')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key', 64);
            $table->string('fingerprint', 255)->nullable();
            $table->enum('action', ['validate', 'activate', 'deactivate', 'offline_check']);
            $table->enum('result', ['allowed', 'denied', 'pending_sync'])->default('allowed');
            $table->string('reason', 100)->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable()->comment('同步到云端的时间');
            $table->timestamps();
        });

        // 5. 代理配置/策略（云端对代理的设置）
        Schema::create('local_proxy_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('local_proxy_nodes')->cascadeOnDelete();
            $table->enum('sync_mode', ['poll', 'push', 'hybrid'])->default('poll')->comment('同步模式');
            $table->integer('sync_interval_seconds')->default(300)->comment('轮询间隔');
            $table->integer('heartbeat_interval_seconds')->default(60)->comment('心跳间隔');
            $table->integer('cache_ttl_seconds')->default(86400)->comment('缓存License有效期(默认24小时)');
            $table->integer('max_cached_licenses')->default(1000)->comment('最大缓存License数');
            $table->boolean('allow_offline_activation')->default(true)->comment('允许离线激活');
            $table->boolean('require_cloud_validation')->default(false)->comment('强制云端验证');
            $table->json('allowed_actions')->nullable()->comment('允许的动作: validate/activate/deactivate');
            $table->json('ip_whitelist')->nullable()->comment('内网IP白名单');
            $table->json('extra_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_proxy_configs');
        Schema::dropIfExists('local_proxy_activation_logs');
        Schema::dropIfExists('local_proxy_cached_licenses');
        Schema::dropIfExists('local_proxy_heartbeats');
        Schema::dropIfExists('local_proxy_nodes');
    }
};
