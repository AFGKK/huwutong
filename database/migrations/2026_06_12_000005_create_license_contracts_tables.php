<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 授权合约模板
        Schema::create('license_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('合约名称');
            $table->string('slug', 100)->unique()->comment('合约标识');
            $table->text('description')->nullable();

            // 合约类型
            $table->string('contract_type', 50)->default('license')
                ->comment('合约类型: license, feature, api, device, time, geo, role, custom');

            // 合约条件定义 (JSON)
            $table->json('conditions')->comment('授权条件列表');
            // 合约动作定义 (JSON) - 满足/不满足时执行
            $table->json('actions')->nullable()->comment('授权动作');

            // 合约配置
            $table->string('evaluation_mode', 20)->default('all')
                ->comment('评估模式: all(全部满足), any(任一满足), custom(自定义表达式)');
            $table->string('custom_expression', 500)->nullable()
                ->comment('自定义条件表达式 (evaluation_mode=custom时使用)');

            // 授权结果模板
            $table->json('grant_template')->nullable()
                ->comment('授权结果模板(动态授权的权限范围)');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('version')->default(1);
            $table->integer('priority')->default(100);

            $table->timestamps();
            $table->softDeletes();
        });

        // 合约分配（绑定到具体实体）
        Schema::create('license_contract_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('license_contracts')->cascadeOnDelete();
            $table->morphs('assignable'); // License, User, Product, etc.

            // 覆盖配置
            $table->json('override_conditions')->nullable()->comment('覆盖条件');
            $table->json('override_actions')->nullable()->comment('覆盖动作');
            $table->json('override_grant')->nullable()->comment('覆盖授权模板');

            // 合约配置参数
            $table->json('parameters')->nullable()->comment('合约参数(键值对)');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(0);

            $table->timestamps();
            $table->unique(['contract_id', 'assignable_type', 'assignable_id'], 'contract_assignable_unique');
        });

        // 合约评估日志
        Schema::create('license_contract_evaluation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('license_contracts')->nullOnDelete();
            $table->string('evaluatable_type', 50)->nullable()->index('lec_evaluatable_type_idx');
            $table->unsignedBigInteger('evaluatable_id')->nullable();
            $table->string('context_type', 50)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            $table->string('contract_slug')->nullable();
            $table->string('contract_name')->nullable();
            $table->string('evaluation_mode', 20);

            $table->string('result', 20)->comment('granted, denied, error');
            $table->json('conditions_results')->nullable()->comment('各条件评估结果');
            $table->json('matched_conditions')->nullable();
            $table->json('failed_conditions')->nullable();
            $table->string('reason', 500)->nullable();

            $table->json('context_data')->nullable();
            $table->ipAddress('source_ip')->nullable();

            $table->float('evaluation_time_ms')->default(0);
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['contract_id', 'result']);
            $table->index(['evaluatable_type', 'evaluatable_id'], 'lec_evaluatable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_contract_evaluation_logs');
        Schema::dropIfExists('license_contract_assignments');
        Schema::dropIfExists('license_contracts');
    }
};
