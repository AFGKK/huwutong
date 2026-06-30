<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // License 签名验证记录
        Schema::create('license_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key')->index()->comment('License Key');
            $table->string('verifier_ip', 45)->nullable()->comment('验证者 IP');
            $table->string('verifier_fingerprint', 128)->nullable()->comment('设备指纹');
            $table->string('result', 50)->comment('验证结果: pass/fail/tamper/expired/revoked');
            $table->text('detail')->nullable()->comment('验证详情');
            $table->string('signature_algorithm', 20)->nullable()->comment('签名算法');
            $table->json('verification_data')->nullable()->comment('验证请求数据');
            $table->boolean('is_sdk_verified')->default(false)->comment('是否 SDK 验证');
            $table->string('sdk_version', 30)->nullable()->comment('SDK 版本');
            $table->timestamps();

            $table->index(['license_key', 'result']);
            $table->index(['license_key', 'created_at']);
        });

        // License 水印记录
        Schema::create('license_watermarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('watermark_key', 64)->unique()->comment('水印标识');
            $table->string('algorithm', 30)->default('stealth')->comment('水印算法: stealth/hmac/bloom');
            $table->json('watermark_data')->nullable()->comment('水印数据（嵌入的溯源信息）');
            $table->string('embed_location', 50)->default('metadata')->comment('嵌入位置');
            $table->string('status', 30)->default('active')->comment('active/revoked/expired');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'status']);
        });

        // 防篡改策略配置
        Schema::create('tamper_protection_configs', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 100)->unique()->comment('规则名称');
            $table->string('rule_type', 50)->comment('规则类型: signature/watermark/activation/device');
            $table->json('conditions')->nullable()->comment('触发条件');
            $table->json('actions')->nullable()->comment('触发动作');
            $table->string('severity', 20)->default('medium')->comment('严重级别');
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_seconds')->default(300)->comment('冷却时间');
            $table->integer('threshold')->default(5)->comment('触发阈值');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 防篡改事件记录
        Schema::create('tamper_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key')->nullable()->index();
            $table->string('event_type', 50)->comment('事件类型');
            $table->string('severity', 20)->default('medium');
            $table->json('event_data')->nullable()->comment('事件详情');
            $table->string('source_ip', 45)->nullable();
            $table->string('source_fingerprint', 128)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 200)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['license_id', 'event_type']);
        });

        // 添加水印字段到 licenses 表
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('watermark_key', 64)->nullable()->unique()->after('metadata')->comment('水印标识');
            $table->string('signature_version', 10)->default('1')->after('watermark_key')->comment('签名版本');
            $table->text('integrity_hash')->nullable()->after('signature_version')->comment('完整性哈希');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['watermark_key', 'signature_version', 'integrity_hash']);
        });
        Schema::dropIfExists('tamper_events');
        Schema::dropIfExists('tamper_protection_configs');
        Schema::dropIfExists('license_watermarks');
        Schema::dropIfExists('license_verification_logs');
    }
};
