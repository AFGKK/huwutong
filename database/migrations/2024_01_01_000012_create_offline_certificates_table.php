<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offline_certificates')) {
            return;
        }

        // 离线验证公钥证书表
        Schema::create('offline_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->comment('租户 ID');
            $table->integer('key_version')->unique()->comment('密钥版本号');
            $table->string('algorithm', 16)->default('Ed25519')->comment('签名算法');
            $table->text('public_key')->comment('Base64 公钥');
            $table->text('seed_encrypted')->nullable()->comment('加密的 seed（用于恢复私钥）');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 离线吊销列表
        Schema::create('offline_crl_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offline_certificate_id')->index()->comment('证书 ID');
            $table->string('license_key', 64)->index()->comment('被吊销的 License Key');
            $table->string('reason', 64)->nullable()->comment('吊销原因');
            $table->timestamp('revoked_at');
            $table->timestamps();

            $table->unique(['offline_certificate_id', 'license_key']);
        });

        // 离线验证历史记录
        Schema::create('offline_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id')->nullable()->index()->comment('License ID');
            $table->string('license_key', 64)->index();
            $table->string('result', 32)->comment('valid/invalid/expired/revoked/tampered');
            $table->text('detail')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('client_version', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_verifications');
        Schema::dropIfExists('offline_crl_entries');
        Schema::dropIfExists('offline_certificates');
    }
};
