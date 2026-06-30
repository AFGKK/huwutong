<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tpm_bindings')) {
            return;
        }
        // 1. TPM 绑定记录表
        Schema::create('tpm_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();

            // TPM 身份
            $table->string('tpm_manufacturer', 100)->nullable()->comment('TPM 制造商');
            $table->string('tpm_version', 20)->nullable()->comment('TPM 版本 (2.0)');
            $table->string('ek_public_key', 1024)->nullable()->comment('Endorsement Key 公钥(PEM)');
            $table->text('ek_certificate')->nullable()->comment('EK 证书(PEM)');
            $table->text('ak_public_key')->nullable()->comment('Attestation Key 公钥(PEM)');
            $table->string('ak_name', 512)->nullable()->comment('AK Name (SHA256)');
            $table->json('pcr_values')->nullable()->comment('PCR 选择及哈希值');

            // 绑定状态
            $table->string('binding_type', 20)->default('tpm2')->comment('tpm2/sgx/hybrid');
            $table->string('status', 20)->default('active')->comment('active/revoked/expired/locked');
            $table->integer('failed_attempts')->default(0)->comment('连续验证失败次数');
            $table->timestamp('locked_until')->nullable()->comment('锁定截止时间');
            $table->timestamp('last_verified_at')->nullable()->comment('上次验证时间');
            $table->timestamp('last_attestation_at')->nullable()->comment('上次完整认证时间');

            // SGX 特定
            $table->text('sgx_quote')->nullable()->comment('SGX Quote (base64)');
            $table->string('sgx_tcb_level', 50)->nullable()->comment('SGX TCB 级别');

            // 元数据
            $table->json('metadata')->nullable()->comment('扩展元数据');
            $table->ipAddress('bound_ip')->nullable()->comment('绑定时的IP');
            $table->string('bound_user_agent', 500)->nullable();
            $table->timestamp('bound_at')->comment('绑定时间');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'status']);
            $table->index('ak_name', 'idx_ak_name');
        });

        // 2. TPM 验证历史
        Schema::create('tpm_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tpm_binding_id')->constrained('tpm_bindings')->cascadeOnDelete();
            $table->string('result', 20)->comment('passed/failed/error');
            $table->text('quote_data')->nullable()->comment('Quote 数据(JSON)');
            $table->text('error_message')->nullable();
            $table->float('duration_ms', 10, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('verified_at')->index();
            $table->timestamps();
        });

        // 3. 设备表增加 TPM 相关字段
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('tpm_available')->default(false)->after('is_virtual')->comment('是否支持TPM');
            $table->string('tpm_manufacturer', 100)->nullable()->after('tpm_available')->comment('TPM 制造商');
            $table->string('tpm_spec_version', 20)->nullable()->after('tpm_manufacturer')->comment('TPM 规范版本');
            $table->string('hardware_bound', 20)->default('none')->after('tpm_spec_version')->comment('none/software/tpm/sgx/hybrid');
            $table->timestamp('hardware_bound_at')->nullable()->after('hardware_bound');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['tpm_available', 'tpm_manufacturer', 'tpm_spec_version', 'hardware_bound', 'hardware_bound_at']);
        });
        Schema::dropIfExists('tpm_verification_logs');
        Schema::dropIfExists('tpm_bindings');
    }
};
