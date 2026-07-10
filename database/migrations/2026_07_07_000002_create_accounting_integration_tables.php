<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 会计系统集成配置 ───
        if (Schema::hasTable('accounting_integrations')) { return; }
        Schema::create('accounting_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');              // quickbooks | xero | yonyou | kingdee
            $table->string('name')->nullable();      // 可读名称
            $table->boolean('is_active')->default(false);
            $table->string('environment')->default('sandbox'); // sandbox | production

            // OAuth2 / API 凭据（加密存储）
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // 中国财务软件特有字段
            $table->string('api_endpoint')->nullable();
            $table->string('company_id')->nullable();   // 账套ID/公司ID
            $table->string('username')->nullable();      // 用友/金蝶 登录名
            $table->text('password')->nullable();        // 加密存储

            // 同步配置
            $table->json('sync_config')->nullable();     // 同步规则配置
            $table->integer('sync_interval_minutes')->default(60);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->unique(['tenant_id', 'provider'], 'acct_int_uniq');
        });

        // ─── 映射关系：本地单据 ↔ 会计系统单据 ───
        if (Schema::hasTable('accounting_sync_mappings')) { return; }
        Schema::create('accounting_sync_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('accounting_integrations')->cascadeOnDelete();
            $table->string('local_type');              // invoice | payment | refund | customer
            $table->unsignedBigInteger('local_id');    // 本地单据ID
            $table->string('remote_id')->nullable();   // 会计系统单据ID
            $table->string('remote_number')->nullable();// 会计系统单据号
            $table->string('status')->default('pending'); // pending | synced | failed
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'local_type', 'local_id'], 'acct_map_uniq');
            $table->index(['integration_id', 'status'], 'acct_map_status_idx');
        });

        // ─── 同步日志 ───
        if (Schema::hasTable('accounting_sync_logs')) { return; }
        Schema::create('accounting_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('accounting_integrations')->cascadeOnDelete();
            $table->string('sync_type');               // auto | manual
            $table->string('direction');               // push | pull
            $table->string('entity_type');             // invoice | payment | refund | customer
            $table->integer('total_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_sync_logs');
        Schema::dropIfExists('accounting_sync_mappings');
        Schema::dropIfExists('accounting_integrations');
    }
};
