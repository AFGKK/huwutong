<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('earnings_account_id')->constrained('users');
            $table->index('user_id');

            // multi-channel detail fields
            $table->string('bank_name')->nullable()->after('channel_account')->comment('银行名称');
            $table->string('bank_branch')->nullable()->after('bank_name')->comment('支行名称');
            $table->string('bank_account_name')->nullable()->after('bank_branch')->comment('开户姓名');
            $table->string('bank_account_no')->nullable()->after('bank_account_name')->comment('银行卡号');
            $table->string('alipay_account')->nullable()->after('bank_account_no')->comment('支付宝账号');
            $table->string('wechat_account')->nullable()->after('alipay_account')->comment('微信账号');
            $table->string('paypal_email')->nullable()->after('wechat_account')->comment('PayPal邮箱');

            // batch & review fields
            $table->string('batch_no')->nullable()->after('paypal_email')->comment('批次号');
            $table->decimal('fee', 10, 2)->default(0)->after('amount')->comment('手续费');
            $table->decimal('net_amount', 10, 2)->default(0)->after('fee')->comment('实际到账金额');
            $table->foreignId('reviewed_by')->nullable()->after('remark')->constrained('users');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('failure_reason')->nullable()->after('proof')->comment('打款失败原因');
            $table->string('transaction_id')->nullable()->after('failure_reason')->comment('第三方交易号/流水号');

            // indexes
            $table->index('batch_no');
            $table->index('status');
            $table->index('reviewed_by');
            $table->index('created_at');
        });

        // batch operations table
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique()->comment('批次号');
            $table->string('title')->nullable()->comment('批次名称');
            $table->string('channel')->comment('打款渠道: bank/alipay/wechat/paypal');
            $table->integer('total_count')->default(0)->comment('总笔数');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('总金额');
            $table->decimal('total_fee', 12, 2)->default(0)->comment('总手续费');
            $table->string('status')->default('pending')->comment('状态: pending/processing/completed/partial_failed/failed');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_no',
                'alipay_account', 'wechat_account', 'paypal_email',
                'batch_no', 'fee', 'net_amount', 'reviewed_by', 'reviewed_at',
                'failure_reason', 'transaction_id',
            ]);
            $table->dropIndex(['batch_no']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
