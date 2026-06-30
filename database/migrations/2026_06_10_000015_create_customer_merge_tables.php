<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 客户合并记录表
        Schema::create('customer_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_customer_id')->comment('源客户ID（将被合并掉的）');
            $table->unsignedBigInteger('target_customer_id')->comment('目标客户ID（合并后的主账号）');
            $table->string('status', 20)->default('pending')->comment('状态: pending/completed/failed/reversed');
            $table->json('conflict_resolution')->nullable()->comment('冲突解决策略记录');
            $table->json('summary')->nullable()->comment('合并汇总（迁移了多少License/发票等）');
            $table->json('errors')->nullable()->comment('合并过程中的错误记录');
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete()->comment('操作人');
            $table->timestamp('merged_at')->nullable()->comment('合并完成时间');
            $table->timestamp('reversed_at')->nullable()->comment('回滚时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('source_customer_id');
            $table->index('target_customer_id');
            $table->index(['tenant_id', 'status']);
        });

        // 为 customers 表添加合并相关字段
        try {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'merged_into_customer_id')) {
                    $table->unsignedBigInteger('merged_into_customer_id')
                        ->nullable()
                        ->after('status')
                        ->comment('被合并到哪个客户ID（null表示是主账号）');
                }
            });
        } catch (\Exception $e) {
            // 如果已经存在则忽略
        }

        try {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'merge_count')) {
                    $table->integer('merge_count')->default(0)->after('merged_into_customer_id')
                        ->comment('已合并的账号数量');
                }
            });
        } catch (\Exception $e) {
            // 如果已经存在则忽略
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['merged_into_customer_id', 'merge_count']);
        });
        Schema::dropIfExists('customer_merge_logs');
    }
};
