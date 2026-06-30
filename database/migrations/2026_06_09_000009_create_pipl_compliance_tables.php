<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 个人信息分类分级
        Schema::create('personal_data_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_name')->comment('字段名');
            $table->string('table_name')->comment('数据表');
            $table->string('category', 30)->comment('person/general/sensitive/private');
            $table->string('classification', 30)->comment('L1/L2/L3/L4 — 一级~四级');
            $table->string('purpose')->nullable()->comment('收集使用目的');
            $table->string('retention_days')->default('365')->comment('保留天数');
            $table->boolean('is_required')->default(false)->comment('是否必填');
            $table->boolean('is_exportable')->default(true)->comment('是否可导出');
            $table->boolean('is_deletable')->default(true)->comment('是否可删除');
            $table->string('status', 20)->default('active')->comment('active/archived');
            $table->timestamps();

            $table->unique(['tenant_id', 'table_name', 'field_name']);
            $table->index('classification');
            $table->index('category');
        });

        // 跨境数据传输评估
        Schema::create('cross_border_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('data_category', 50)->comment('传输的数据类别');
            $table->string('recipient_country', 100)->comment('接收方所在国家/地区');
            $table->string('recipient_name')->comment('接收方名称');
            $table->string('recipient_purpose')->comment('传输目的');
            $table->string('transfer_method', 30)->comment('api/sdk/manual/cloud');
            $table->string('legal_basis', 30)->comment('consent/standard_clauses/adequacy/safe_harbor/other');
            $table->text('security_measures')->nullable()->comment('安全保护措施');
            $table->text('impact_assessment')->nullable()->comment('影响评估结论');
            $table->string('status', 20)->default('active')->comment('active/expired/revoked');
            $table->timestamp('reviewed_at')->nullable()->comment('最近评估日期');
            $table->timestamp('next_review_at')->nullable()->comment('下次评估日期');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('recipient_country');
        });

        // DPIA 数据保护影响评估
        Schema::create('dpias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->comment('评估标题');
            $table->string('status', 20)->default('draft')->comment('draft/in_progress/completed/archived');
            $table->text('description')->nullable()->comment('处理活动描述');
            $table->text('necessity_assessment')->nullable()->comment('必要性评估');
            $table->text('risk_assessment')->nullable()->comment('风险评估');
            $table->text('mitigation_measures')->nullable()->comment('缓解措施');
            $table->text('conclusion')->nullable()->comment('评估结论');
            $table->json('involved_data_categories')->nullable()->comment('涉及的数据类别');
            $table->json('stakeholders')->nullable()->comment('相关方');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dpias');
        Schema::dropIfExists('cross_border_transfers');
        Schema::dropIfExists('personal_data_inventories');
    }
};
