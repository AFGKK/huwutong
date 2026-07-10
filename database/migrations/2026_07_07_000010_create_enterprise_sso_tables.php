<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 企业身份提供商 (IdP) ──
        if (Schema::hasTable('enterprise_idps')) { return; }
        Schema::create('enterprise_idps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sso_provider_id')->nullable()->constrained('sso_providers')->nullOnDelete();
            $table->string('name');                              // Okta / Azure AD / OneLogin
            $table->string('provider_type');                     // okta | azure_ad | onelogin | generic_saml
            $table->boolean('is_active')->default(true);
            // SAML IdP 元数据
            $table->text('idp_metadata_xml')->nullable();        // 原始 IdP Metadata XML
            $table->string('idp_entity_id')->nullable();
            $table->string('idp_sso_url')->nullable();           // IdP SSO Endpoint
            $table->string('idp_slo_url')->nullable();           // IdP SLO Endpoint
            $table->text('idp_x509_certificate')->nullable();    // IdP 签名证书
            // SP 配置
            $table->string('sp_entity_id')->nullable()->comment('默认: {tenant_slug}.huwutong.com');
            $table->string('sp_acs_url')->nullable();            // Assertion Consumer Service
            $table->string('sp_audience_uri')->nullable();
            $table->enum('name_id_format', ['email', 'unspecified', 'persistent', 'transient'])->default('email');
            // 高级配置
            $table->boolean('encrypt_assertion')->default(false);
            $table->boolean('sign_authn_requests')->default(true);
            $table->integer('authn_request_timeout')->default(300); // SAML 请求超时(秒)
            $table->json('metadata')->nullable();                // 额外元数据
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // ── 域名路由 (根据邮箱域名自动匹配 IdP) ──
        if (Schema::hasTable('idp_domain_routes')) { return; }
        Schema::create('idp_domain_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idp_id')->constrained('enterprise_idps')->cascadeOnDelete();
            $table->string('domain');                            // 如 example.com
            $table->boolean('is_primary')->default(false);       // 是否为主域名
            $table->timestamps();

            $table->unique(['tenant_id', 'domain']);
            $table->index('domain');
        });

        // ── IdP 用户组映射 (组→角色) ──
        if (Schema::hasTable('idp_group_mappings')) { return; }
        Schema::create('idp_group_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idp_id')->constrained('enterprise_idps')->cascadeOnDelete();
            $table->string('idp_group_name');                    // IdP 中的组名, 如 Okta: "Engineers"
            $table->string('local_role');                        // 映射到本地的角色名, 如 "admin"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['idp_id', 'idp_group_name']);
        });

        // ── JIT 自动创建用户规则 ──
        if (Schema::hasTable('jit_provisioning_rules')) { return; }
        Schema::create('jit_provisioning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idp_id')->constrained('enterprise_idps')->cascadeOnDelete();
            $table->string('name');                              // 规则名称
            $table->boolean('is_active')->default(true);
            $table->string('default_role')->default('user');     // 新用户默认角色
            $table->boolean('auto_create_users')->default(true); // 首次登录自动创建
            $table->boolean('auto_update_attributes')->default(true); // 每次登录同步属性
            $table->string('email_domain_filter')->nullable();   // 只处理指定域名的邮箱
            $table->json('attribute_mapping')->nullable();       // IdP 属性 → 本地字段映射
            $table->timestamps();
        });

        // ── IdP 健康检查日志 ──
        if (Schema::hasTable('idp_health_logs')) { return; }
        Schema::create('idp_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idp_id')->constrained('enterprise_idps')->cascadeOnDelete();
            $table->string('check_type')->default('connectivity'); // connectivity | cert_expiry | metadata
            $table->boolean('is_healthy');
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_health_logs');
        Schema::dropIfExists('jit_provisioning_rules');
        Schema::dropIfExists('idp_group_mappings');
        Schema::dropIfExists('idp_domain_routes');
        Schema::dropIfExists('enterprise_idps');
    }
};
