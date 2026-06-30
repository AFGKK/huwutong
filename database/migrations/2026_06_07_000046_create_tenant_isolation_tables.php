<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 租户资源配额方案 ───
        Schema::create('quota_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->json('limits')->comment('配额限制 {licenses_max, devices_max, users_max, api_keys_max, storage_mb, bandwidth_gb, monthly_api_calls, seats_total}');
            $table->json('features')->nullable()->comment('功能限制 {whitelabel, sso, audit_log, api_access, custom_domain}');
            $table->string('tier', 30)->default('free')->comment('free/starter/business/enterprise/custom');
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // ─── 为已有 `tenants` 表添加配额和隔离字段 ───
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'quota_plan_id')) {
                $table->foreignId('quota_plan_id')->nullable()->constrained('quota_plans')->nullOnDelete();
            }
            if (!Schema::hasColumn('tenants', 'quota_overrides')) {
                $table->json('quota_overrides')->nullable()->comment('自定义配额覆盖');
            }
            if (!Schema::hasColumn('tenants', 'isolation_level')) {
                $table->string('isolation_level', 30)->default('strict')->comment('数据隔离等级: strict/logical/shared');
            }
            if (!Schema::hasColumn('tenants', 'allowed_origins')) {
                $table->json('allowed_origins')->nullable()->comment('CORS 允许域名');
            }
            if (!Schema::hasColumn('tenants', 'feature_flags')) {
                $table->json('feature_flags')->nullable()->comment('租户特征开关');
            }
            if (!Schema::hasColumn('tenants', 'usage_metrics')) {
                $table->json('usage_metrics')->nullable()->comment('实时用量快照');
            }
            if (!Schema::hasColumn('tenants', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'max_licenses')) {
                $table->unsignedInteger('max_licenses')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'max_devices')) {
                $table->unsignedInteger('max_devices')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'max_api_keys')) {
                $table->unsignedInteger('max_api_keys')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'storage_limit_mb')) {
                $table->unsignedBigInteger('storage_limit_mb')->nullable()->default(1024);
            }
            if (!Schema::hasColumn('tenants', 'monthly_api_limit')) {
                $table->unsignedBigInteger('monthly_api_limit')->nullable()->default(100000);
            }
            if (!Schema::hasColumn('tenants', 'data_retention_days')) {
                $table->unsignedInteger('data_retention_days')->nullable()->default(365);
            }
            if (!Schema::hasColumn('tenants', 'notify_quota_at')) {
                $table->unsignedInteger('notify_quota_at')->nullable()->default(80)->comment('配额使用率达到 % 时通知');
            }
            if (!Schema::hasColumn('tenants', 'quota_last_notified_at')) {
                $table->timestamp('quota_last_notified_at')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'quota_check_enabled')) {
                $table->boolean('quota_check_enabled')->default(true);
            }
            if (!Schema::hasColumn('tenants', 'over_quota_since')) {
                $table->timestamp('over_quota_since')->nullable()->comment('超出配额起始时间');
            }
            if (!Schema::hasColumn('tenants', 'over_quota_action')) {
                $table->string('over_quota_action', 30)->default('block')->comment('超出操: block/warn/log');
            }
        });

        // ─── 租户数据隔离审计日志 ───
        Schema::create('isolation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->comment('quota_breach/quota_notify/data_access/isolation_change/config_change');
            $table->string('severity', 20)->default('info')->comment('info/warning/critical');
            $table->string('resource_type', 50)->nullable()->comment('licenses/users/devices/api_keys/storage/api');
            $table->json('details')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'created_at']);
            $table->index(['tenant_id', 'severity', 'is_resolved']);
        });

        // ─── 跨租户共享设置 ───
        Schema::create('cross_tenant_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('target_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('resource_type', 50)->comment('licenses/products/templates/knowledge');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('资源 ID, null=全部');
            $table->string('permission', 30)->default('read')->comment('read/write/admin');
            $table->string('status', 30)->default('active')->comment('active/pending/revoked');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['source_tenant_id', 'target_tenant_id', 'resource_type', 'resource_id'], 'cross_share_unique');
            $table->index(['target_tenant_id', 'resource_type', 'status']);
        });

        // ─── 租户用量统计（实时快照） ───
        Schema::create('tenant_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->unsignedBigInteger('current_usage')->default(0);
            $table->unsignedBigInteger('quota_limit')->default(0);
            $table->decimal('usage_percent', 5, 2)->default(0);
            $table->string('period', 20)->default('current')->comment('current/daily/monthly');
            $table->timestamp('snapshot_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key', 'snapshot_at']);
            $table->unique(['tenant_id', 'metric_key', 'period'], 'tus_tenant_metric_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_snapshots');
        Schema::dropIfExists('cross_tenant_shares');
        Schema::dropIfExists('isolation_audit_logs');

        Schema::table('tenants', function (Blueprint $table) {
            $drop = ['quota_plan_id', 'quota_overrides', 'isolation_level', 'allowed_origins', 'feature_flags', 'usage_metrics', 'max_users', 'max_licenses', 'max_devices', 'max_api_keys', 'storage_limit_mb', 'monthly_api_limit', 'data_retention_days', 'notify_quota_at', 'quota_last_notified_at', 'quota_check_enabled', 'over_quota_since', 'over_quota_action'];
            foreach ($drop as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('quota_plans');
    }
};
