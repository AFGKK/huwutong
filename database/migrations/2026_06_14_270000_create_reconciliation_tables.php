<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reconciliation_imports')) {
            return;
        }
        // CSV 导入记录
        Schema::create('reconciliation_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('channel', 30)->comment('微信/alipay/stripe/paypal');
            $table->string('filename')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('matched_rows')->default(0);
            $table->integer('unmatched_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->string('status', 20)->default('processing')->comment('processing/completed/failed');
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();
        });

        // 对账日历/周期
        Schema::create('reconciliation_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('period_type', 20)->default('daily')->comment('daily/weekly/monthly/quarterly');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('pending')->comment('pending/in_progress/completed/failed');
            $table->integer('total_transactions')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('unmatched_count')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('difference_amount', 12, 2)->default(0);
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 支付渠道账单行（CSV 导入后的原始数据）
        Schema::create('reconciliation_channel_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedBigInteger('reconciliation_id')->nullable();
            $table->string('channel', 30);
            $table->string('transaction_id', 100)->nullable()->comment('渠道交易号');
            $table->string('order_id', 100)->nullable()->comment('商户订单号');
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 30)->nullable()->comment('渠道状态');
            $table->timestamp('transaction_time')->nullable();
            $table->string('payer_account', 100)->nullable();
            $table->string('payee_account', 100)->nullable();
            $table->text('raw_data')->nullable()->comment('原始CSV行JSON');
            $table->string('match_status', 20)->default('pending')->comment('pending/matched/unmatched/ignored');
            $table->unsignedBigInteger('matched_order_id')->nullable();
            $table->string('matched_order_no', 64)->nullable();
            $table->decimal('difference', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('order_id');
            $table->index('match_status');
            $table->index('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_channel_rows');
        Schema::dropIfExists('reconciliation_calendars');
        Schema::dropIfExists('reconciliation_imports');
    }
};
