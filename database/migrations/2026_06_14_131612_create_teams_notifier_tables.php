<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teams_webhooks')) {
            return;
        }
        // Teams Webhook 配置表
        Schema::create('teams_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('name', 100)->comment('频道名称');
            $table->string('webhook_url', 500)->comment('Teams Incoming Webhook URL');
            $table->string('notification_type', 50)->default('all')->comment('通知类型: all/activation/alert/expiry');
            $table->boolean('is_active')->default(true);
            $table->json('filters')->nullable()->comment('过滤条件（severity/产品等）');
            $table->string('description', 500)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        // 通知发送日志表
        Schema::create('teams_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->foreignId('teams_webhook_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 50)->comment('activation/alert/expiry');
            $table->string('title', 300);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/success/failed');
            $table->integer('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->string('card_id', 100)->nullable()->comment('Teams 消息 ID');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'notification_type', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_notification_logs');
        Schema::dropIfExists('teams_webhooks');
    }
};
