<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 库存变更日志 (M2-150)
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained('product_skus')->cascadeOnDelete();
            $table->string('type', 30)->comment('initial/deduct/rollback/reserve/unreserve/adjust');
            $table->integer('quantity')->comment('正数=增加, 负数=减少');
            $table->integer('stock_before')->nullable();
            $table->integer('stock_after')->nullable();
            $table->string('reference_type', 60)->nullable()->comment('order/refund/adjustment');
            $table->string('reference_id', 64)->nullable();
            $table->string('remark', 500)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sku_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // 支付安全审计日志 (M2-153)
        Schema::create('payment_security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('check_type', 60)->comment('duplicate_payment/amount_tamper/signature_verify/refund_abuse/ip_check');
            $table->boolean('passed')->default(false);
            $table->json('details')->nullable();
            $table->string('risk_level', 20)->default('low')->comment('low/medium/high/critical');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'check_type']);
            $table->index(['risk_level', 'created_at']);
        });

        // 退款附件 (M2-155)
        if (Schema::hasTable('refunds') && !Schema::hasColumn('refunds', 'customer_notes')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->text('customer_notes')->nullable()->after('reason')->comment('客户补充说明');
                $table->json('attachments')->nullable()->after('customer_notes')->comment('截图等附件URL');
                $table->timestamp('customer_requested_at')->nullable()->after('attachments')->comment('客户发起退款时间');
                $table->string('reject_reason', 500)->nullable()->after('failure_reason')->comment('拒绝退款的理由');
                $table->foreignId('order_id')->nullable()->after('license_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_security_logs');
        Schema::dropIfExists('inventory_logs');
    }
};
