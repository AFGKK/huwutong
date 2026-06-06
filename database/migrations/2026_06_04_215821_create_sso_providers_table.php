<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('提供商名称');
            $table->string('provider_type')->comment('类型: saml2, oidc, oauth2');
            $table->boolean('is_active')->default(false)->comment('是否启用');

            // SAML 2.0 配置
            $table->text('idp_entity_id')->nullable()->comment('IdP Entity ID');
            $table->text('idp_login_url')->nullable()->comment('IdP SSO URL');
            $table->text('idp_logout_url')->nullable()->comment('IdP SLO URL');
            $table->text('idp_x509_certificate')->nullable()->comment('IdP 公钥证书');
            $table->text('sp_entity_id')->nullable()->comment('SP Entity ID');
            $table->text('sp_acs_url')->nullable()->comment('SP Assertion Consumer Service URL');

            // OIDC / OAuth2 配置
            $table->text('client_id')->nullable()->comment('Client ID');
            $table->text('client_secret')->nullable()->comment('Client Secret');
            $table->text('authorization_url')->nullable()->comment('OAuth 授权 URL');
            $table->text('token_url')->nullable()->comment('OAuth Token URL');
            $table->text('userinfo_url')->nullable()->comment('OAuth 用户信息 URL');
            $table->text('jwks_url')->nullable()->comment('JWKS URL');
            $table->text('scopes')->nullable()->comment('请求权限范围 逗号分隔');

            // 属性映射
            $table->json('attribute_mapping')->nullable()->comment('IdP 属性→系统字段映射: {email, name, phone, tenant_id}');
            $table->json('metadata')->nullable()->comment('扩展元数据');

            $table->timestamps();

            $table->unique(['tenant_id', 'provider_type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_providers');
    }
};
