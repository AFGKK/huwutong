<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 发货日志表（记录每次发货尝试）
        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->comment('email/webhook/api/auto_license');
            $table->string('status', 20)->default('pending')->comment('pending/sent/failed');
            $table->text('payload')->nullable()->comment('发送内容');
            $table->text('response')->nullable()->comment('外部系统响应');
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempt')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 为 Delivery 表添加 webhook/email 推送状态
        if (Schema::hasTable('deliveries') && !Schema::hasColumn('deliveries', 'webhook_pushed')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->boolean('webhook_pushed')->default(false)->after('meta');
                $table->boolean('email_sent')->default(false)->after('webhook_pushed');
                $table->timestamp('webhook_pushed_at')->nullable()->after('email_sent');
                $table->timestamp('email_sent_at')->nullable()->after('webhook_pushed_at');
                $table->string('auto_license_id', 64)->nullable()->after('email_sent_at')->comment('自动创建的 License ID');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
    }
};
