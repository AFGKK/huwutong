<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MRR变化明细表（记录每月的MRR构成变化）
        if (!Schema::hasTable('mrr_change_details')) {
            Schema::create('mrr_change_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('year_month', 7)->comment('所属月份 YYYY-MM');
                $table->string('change_type', 30)
                    ->comment('new_subscription|upgrade|downgrade|cancellation|reactivation|price_change');
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
                $table->decimal('previous_mrr', 12, 2)->default(0)->comment('变更前MRR');
                $table->decimal('new_mrr', 12, 2)->default(0)->comment('变更后MRR');
                $table->decimal('mrr_impact', 12, 2)->default(0)->comment('MRR影响金额');
                $table->string('currency', 3)->default('CNY');
                $table->string('reason')->nullable()->comment('变更原因');
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['tenant_id', 'year_month']);
                $table->index(['tenant_id', 'change_type', 'year_month']);
                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mrr_change_details');
    }
};
