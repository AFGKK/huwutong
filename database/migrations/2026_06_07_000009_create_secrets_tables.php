<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 加密主密钥版本管理
        Schema::create('master_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_id', 64)->unique()->comment('密钥标识，如 kek-v1');
            $table->string('label', 100)->nullable()->comment('友好名称');
            $table->text('encrypted_key')->comment('加密存储的主密钥材料');
            $table->string('algorithm', 30)->default('aes-256-gcm')->comment('加密算法');
            $table->string('status', 20)->default('active')->comment('active/deprecated/revoked');
            $table->boolean('is_current')->default(false)->comment('是否是当前活跃密钥');
            $table->timestamp('rotated_at')->nullable()->comment('最后轮换时间');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 租户加密凭据（API Key / 密钥对 / 连接串等）
        Schema::create('tenant_secrets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 100)->comment('凭据名称');
            $table->string('slug', 100)->comment('唯一标识: stripe_key/smtp_pass/vault_token');
            $table->string('type', 30)->default('api_key')->comment('api_key/password/certificate/token/connection');
            $table->text('encrypted_value')->comment('加密后的凭据值');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->comment('active/expired/revoked');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('last_rotated_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // 凭据访问审计日志
        Schema::create('secret_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('secret_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('action', 30)->comment('access/rotate/revoke/restore');
            $table->string('accessed_by', 100)->nullable()->comment('访问来源: user:{id}/system:webhook');
            $table->string('ip_address', 45)->nullable();
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index(['secret_id', 'created_at']);
            $table->foreign('secret_id')->references('id')->on('tenant_secrets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_access_logs');
        Schema::dropIfExists('tenant_secrets');
        Schema::dropIfExists('master_keys');
    }
};
