<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refund_no')->unique()->comment('退款单号');
            $table->decimal('amount', 12, 2)->default(0)->comment('退款金额');
            $table->string('currency', 3)->default('CNY');
            $table->string('reason', 500)->nullable()->comment('退款原因');
            $table->string('status')->default('pending')->comment('pending, completed, failed, cancelled');
            $table->string('payment_refund_id')->nullable()->comment('支付网关退款 ID');
            $table->string('payment_method')->nullable()->comment('退款方式: original, balance, other');
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('license_id');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
