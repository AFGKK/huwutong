<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('update_verification_logs')) {
            return;
        }
        // 更新签名验证日志
        Schema::create('update_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_package_id')->constrained()->cascadeOnDelete();
            $table->string('sdk_instance_id', 64)->nullable()->comment('SDK实例标识');
            $table->string('tenant_id', 64)->nullable();
            $table->string('algorithm', 20)->comment('ed25519/rsa-sha256');
            $table->boolean('verified')->default(false)->comment('验证是否通过');
            $table->string('file_hash', 128)->nullable()->comment('客户端计算的文件哈希');
            $table->string('expected_hash', 128)->nullable()->comment('服务端期望的哈希');
            $table->string('signature', 256)->nullable()->comment('客户端提交的签名');
            $table->text('error_message')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index('verified');
            $table->index('created_at');
        });

        // 更新回滚记录
        Schema::create('update_rollbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_package_id')->constrained()->cascadeOnDelete();
            $table->string('from_version', 20)->comment('当前版本');
            $table->string('to_version', 20)->comment('回滚目标版本');
            $table->string('trigger_type', 40)->default('manual')->comment('manual/auto_crash/auto_failure/auto_timeout');
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/executed/failed/rolled_forward');
            $table->text('reason')->nullable();
            $table->json('rollback_metrics')->nullable()->comment('回滚前的指标快照');
            $table->json('rollback_result')->nullable()->comment('回滚执行结果');
            $table->integer('affected_instances')->default(0)->comment('受影响实例数');
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('trigger_type');
            $table->index('from_version');
        });

        // 灰度发布规则
        Schema::create('update_gray_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_package_id')->constrained()->cascadeOnDelete();
            $table->string('strategy', 20)->default('region')->comment('region/percentage/whitelist/tenant_tag');
            $table->string('current_stage', 20)->default('canary')->comment('canary/beta/wide/full');
            $table->integer('current_percentage')->default(5)->comment('当前灰度百分比');
            $table->json('target_regions')->nullable()->comment('目标区域列表');
            $table->json('excluded_regions')->nullable()->comment('排除区域列表');
            $table->json('whitelist_tenants')->nullable()->comment('白名单租户ID列表');
            $table->json('blacklist_tenants')->nullable()->comment('黑名单租户ID列表');
            $table->json('tenant_tags')->nullable()->comment('租户标签筛选');
            $table->string('status', 20)->default('pending')->comment('pending/running/paused/completed/rolled_back');
            $table->timestamp('stage_started_at')->nullable();
            $table->timestamp('stage_ends_at')->nullable();
            $table->json('stage_metrics')->nullable()->comment('各阶段指标快照');
            $table->json('config')->nullable()->comment('扩展配置');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('current_stage');
            $table->index('strategy');
        });

        // 更新包增加灰度相关字段
        Schema::table('update_packages', function (Blueprint $table) {
            $table->string('sign_algorithm', 20)->nullable()->after('signature')->comment('签名算法');
            $table->string('public_key_version', 10)->nullable()->after('sign_algorithm')->comment('签名公钥版本');
            $table->string('rollback_version', 20)->nullable()->after('public_key_version')->comment('可回滚到的版本');
            $table->boolean('is_rollback')->default(false)->after('rollback_version')->comment('是否为回滚包');
            $table->json('rollback_info')->nullable()->after('is_rollback')->comment('回滚信息');
        });
    }

    public function down(): void
    {
        Schema::table('update_packages', function (Blueprint $table) {
            $table->dropColumn(['sign_algorithm', 'public_key_version', 'rollback_version', 'is_rollback', 'rollback_info']);
        });
        Schema::dropIfExists('update_gray_releases');
        Schema::dropIfExists('update_rollbacks');
        Schema::dropIfExists('update_verification_logs');
    }
};
