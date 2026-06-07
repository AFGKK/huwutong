<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('api_keys', 'tier')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->string('tier', 20)->default('standard')->after('permissions')
                    ->comment('密钥等级: free, standard, enterprise, custom');
                $table->string('allowed_methods', 100)->nullable()->after('tier')
                    ->comment('允许的HTTP方法列表(逗号分隔), null=全部');
                $table->unsignedInteger('daily_quota')->nullable()->after('usage_quota')
                    ->comment('每日请求配额');
                $table->unsignedInteger('daily_usage')->default(0)->after('daily_quota')
                    ->comment('每日已使用请求数');
                $table->timestamp('daily_reset_at')->nullable()->after('daily_usage')
                    ->comment('每日配额重置时间');
                $table->json('allowed_ips')->nullable()->after('allowed_ip')
                    ->comment('IP白名单(多个IP)');
                $table->json('allowed_referrers')->nullable()->after('allowed_ips')
                    ->comment('Referer白名单');
                $table->json('tags')->nullable()->after('allowed_referrers')
                    ->comment('密钥标签');
                $table->json('metadata')->nullable()->after('tags')
                    ->comment('自定义元数据 (JSON)');
                $table->string('description', 500)->nullable()->after('metadata')
                    ->comment('密钥描述');
                $table->unsignedBigInteger('created_by')->nullable()->after('description')
                    ->comment('创建者用户ID');
                $table->timestamp('rotated_at')->nullable()->after('last_used_at')
                    ->comment('上次密钥轮换时间');
                $table->softDeletes()->after('updated_at');
            });
        }

        // 创建 API 密钥审计日志表
        if (! Schema::hasTable('api_key_audit_logs')) {
            Schema::create('api_key_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('api_key_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('action', 50)->comment('操作: create/update/delete/regenerate/toggle/usage');
                $table->string('actor_type', 30)->nullable()->comment('操作者类型: user/api_key/system');
                $table->unsignedBigInteger('actor_id')->nullable()->comment('操作者ID');
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('remark')->nullable();
                $table->timestamps();

                $table->index(['api_key_id', 'created_at']);
                $table->index(['tenant_id', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_audit_logs');

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn([
                'tier', 'allowed_methods', 'daily_quota', 'daily_usage',
                'daily_reset_at', 'allowed_ips', 'allowed_referrers',
                'tags', 'metadata', 'description', 'created_by',
                'rotated_at', 'deleted_at',
            ]);
        });
    }
};
