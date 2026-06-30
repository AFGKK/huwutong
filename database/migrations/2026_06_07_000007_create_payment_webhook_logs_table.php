<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 支付 Webhook 日志（记录所有 incoming webhook 事件）
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20)->comment('stripe/alipay');
            $table->string('event_type', 100)->comment('如 payment_intent.succeeded');
            $table->string('event_id', 100)->nullable()->unique()->comment('网关事件ID，用于幂等');
            $table->string('status', 20)->default('received')->comment('received/processing/completed/failed');
            $table->json('payload')->comment('原始请求体');
            $table->text('response')->nullable()->comment('处理结果摘要');
            $table->string('error_message')->nullable();
            $table->nullableMorphs('processable'); // 关联的 invoice / subscription
            $table->timestamps();

            $table->index(['gateway', 'event_type']);
            $table->index('created_at');
        });

        // 向 invoices 表补充支付渠道元数据（已有字段的基础上补充）
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'gateway_charge_id')) {
                $table->string('gateway_charge_id')->nullable()->after('paid_at')->comment('网关支付ID');
            }
            if (!Schema::hasColumn('invoices', 'gateway_refund_id')) {
                $table->string('gateway_refund_id')->nullable()->after('gateway_charge_id')->comment('网关退款ID');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
