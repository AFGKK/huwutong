<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_smtp_configs')) {
            return;
        }
        // 客户 SMTP 配置表
        if (!Schema::hasTable('customer_smtp_configs')) {
            Schema::create('customer_smtp_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('provider', 50)->default('custom')->comment('smtp提供商: qq/163/gmail/outlook/qq_exmail/aliyun/custom');
                $table->string('name', 100)->nullable()->comment('配置名称');
                $table->string('host', 200);
                $table->unsignedInteger('port')->default(587);
                $table->string('encryption', 20)->nullable()->comment('tls/ssl/null');
                $table->string('auth', 30)->default('login');
                $table->string('username', 200)->nullable();
                $table->text('password')->nullable()->comment('加密存储');
                $table->string('from_address', 200)->nullable()->comment('发件人地址');
                $table->string('from_name', 100)->nullable()->comment('发件人名称');
                $table->string('status', 30)->default('active')->comment('active/inactive/failed');
                $table->boolean('is_primary')->default(false)->comment('是否为主SMTP');
                $table->unsignedInteger('priority')->default(0)->comment('优先级，越高越优先');
                $table->unsignedInteger('failure_count')->default(0)->comment('连续失败次数');
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->timestamp('recovered_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_primary']);
                $table->index(['customer_id', 'is_primary']);
                $table->index('status');
            });
        }

        // SMTP 发送日志 & 降级事件表
        if (!Schema::hasTable('smtp_delivery_logs')) {
            Schema::create('smtp_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('smtp_config_id')->nullable()->constrained('customer_smtp_configs')->nullOnDelete();
                $table->string('event_type', 30)->comment('send/test/failover/recovery/alert');
                $table->string('status', 30)->comment('success/failed');
                $table->string('from_address', 200)->nullable();
                $table->string('to_address', 200)->nullable();
                $table->string('subject', 500)->nullable();
                $table->text('error_message')->nullable();
                $table->text('stack_trace')->nullable();
                $table->unsignedInteger('failure_count')->default(0);
                $table->string('fallback_action', 50)->nullable()->comment('switch_to_backup/use_system_default/alert_sent');
                $table->timestamps();

                $table->index(['smtp_config_id', 'created_at']);
                $table->index('event_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_delivery_logs');
        Schema::dropIfExists('customer_smtp_configs');
    }
};
