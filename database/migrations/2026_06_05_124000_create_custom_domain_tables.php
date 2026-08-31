<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 租户自定义域名绑定
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique()->comment('完整域名，如 license.example.com');
            $table->string('cname_target')->nullable()->comment('CNAME 目标值');
            $table->string('verification_method')->default('cname')->comment('cname/http');
            $table->string('verification_value')->nullable()->comment('验证记录值');
            $table->boolean('verified')->default(false)->comment('DNS 验证是否通过');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(false)->comment('是否生效（已验证+SSL 就绪）');
            $table->string('status')->default('pending')
                ->comment('pending/verifying/active/failed/expired');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index('domain');
        });

        // SSL 证书记录
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_domain_id')->constrained()->cascadeOnDelete();
            $table->string('issuer')->default('Lets Encrypt');
            $table->text('certificate')->nullable()->comment('PEM 证书内容（加密存储）');
            $table->text('private_key')->nullable()->comment('私钥（加密存储）');
            $table->text('certificate_chain')->nullable()->comment('证书链');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('pending')
                ->comment('pending/issued/renewing/failed/expired/revoked');
            $table->string('acme_challenge_token')->nullable()->comment('ACME HTTP-01 验证令牌');
            $table->text('acme_challenge_content')->nullable()->comment('ACME 验证文件内容');
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamp('renewal_alert_sent_at')->nullable()->comment('到期告警发送时间');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index('status');
        });

        // 域名分发配置（CDN/反向代理配置）
        Schema::create('domain_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_domain_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('reverse_proxy')
                ->comment('reverse_proxy/redirect/static');
            $table->string('target_url')->nullable()->comment('反代目标 URL');
            $table->json('config')->nullable()->comment('高级配置');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_routes');
        Schema::dropIfExists('ssl_certificates');
        Schema::dropIfExists('custom_domains');
    }
};
