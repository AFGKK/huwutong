<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 结算周期定义
        if (!Schema::hasTable('settlement_cycles')) {
            Schema::create('settlement_cycles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('period_type');
                $table->date('period_start');
                $table->date('period_end');
                $table->date('settlement_date');
                $table->date('payout_date')->nullable();
                $table->string('status');
                $table->decimal('total_commission', 14, 2)->default(0);
                $table->decimal('total_fee', 14, 2)->default(0);
                $table->decimal('total_payout', 14, 2)->default(0);
                $table->integer('agent_count')->default(0);
                $table->integer('settlement_count')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
                $table->index(['period_start', 'period_end']);
            });
        }

        // 结算批次
        if (!Schema::hasTable('settlement_batches')) {
            Schema::create('settlement_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_cycle_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('batch_no')->unique();
                $table->string('channel');
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('total_fee', 14, 2)->default(0);
                $table->decimal('net_amount', 14, 2)->default(0);
                $table->integer('item_count')->default(0);
                $table->string('status');
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
                $table->index('batch_no');
            });
        }

        // 结算批次明细
        if (!Schema::hasTable('settlement_batch_items')) {
            Schema::create('settlement_batch_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_batch_id')->constrained()->cascadeOnDelete();
                $table->morphs('settleable');
                $table->decimal('amount', 14, 2);
                $table->decimal('fee', 14, 2)->default(0);
                $table->decimal('net_amount', 14, 2);
                $table->string('status');
                $table->timestamps();
                // PostgreSQL 的 morphs() 已自动创建此索引
                if (Schema::getConnection()->getDriverName() !== 'pgsql') {
                    $table->index(['settleable_type', 'settleable_id']);
                }
                $table->index('settlement_batch_id');
            });
        }

        // 平台费用记录
        if (!Schema::hasTable('platform_fees')) {
            Schema::create('platform_fees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('feeable');
                $table->string('fee_type');
                $table->string('name');
                $table->decimal('amount', 14, 2);
                $table->decimal('rate', 6, 4)->nullable();
                $table->string('currency', 3)->default('CNY');
                $table->string('status')->default('collected');
                $table->json('metadata')->nullable();
                $table->timestamp('collected_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'fee_type']);
                // $table->index(['feeable_type', 'feeable_id']); // 由 nullableMorphs 自动创建
            });
        }

        // commission_settlements 追加字段
        if (Schema::hasTable('commission_settlements')) {
            Schema::table('commission_settlements', function (Blueprint $table) {
                if (!Schema::hasColumn('commission_settlements', 'settlement_batch_id')) {
                    $table->foreignId('settlement_batch_id')->nullable()->after('notes')
                          ->constrained('settlement_batches')->nullOnDelete();
                }
                if (!Schema::hasColumn('commission_settlements', 'settlement_cycle_id')) {
                    $table->foreignId('settlement_cycle_id')->nullable()->after('settlement_batch_id')
                          ->constrained('settlement_cycles')->nullOnDelete();
                }
                if (!Schema::hasColumn('commission_settlements', 'fee')) {
                    $table->decimal('fee', 14, 2)->default(0)->after('commission_amount');
                }
                if (!Schema::hasColumn('commission_settlements', 'net_amount')) {
                    $table->decimal('net_amount', 14, 2)->default(0)->after('fee');
                }
                if (!Schema::hasColumn('commission_settlements', 'payout_method')) {
                    $table->string('payout_method')->nullable()->after('settlement_type');
                }
            });
        }

        // earnings_accounts 追加字段
        if (Schema::hasTable('earnings_accounts')) {
            Schema::table('earnings_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('earnings_accounts', 'last_settlement_at')) {
                    $table->timestamp('last_settlement_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('earnings_accounts', 'next_settlement_at')) {
                    $table->timestamp('next_settlement_at')->nullable()->after('last_settlement_at');
                }
                if (!Schema::hasColumn('earnings_accounts', 'lifetime_settled')) {
                    $table->decimal('lifetime_settled', 14, 2)->default(0)->after('total_withdrawn');
                }
            });
        }
    }

    public function down(): void
    {
        // 先移除关联字段
        if (Schema::hasTable('earnings_accounts')) {
            Schema::table('earnings_accounts', function (Blueprint $table) {
                $table->dropColumn(['last_settlement_at', 'next_settlement_at', 'lifetime_settled']);
            });
        }
        if (Schema::hasTable('commission_settlements')) {
            Schema::table('commission_settlements', function (Blueprint $table) {
                $table->dropColumn(['settlement_batch_id', 'settlement_cycle_id', 'fee', 'net_amount', 'payout_method']);
            });
        }
        Schema::dropIfExists('platform_fees');
        Schema::dropIfExists('settlement_batch_items');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('settlement_cycles');
    }
};
