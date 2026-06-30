<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_assistant_jobs')) {
            return;
        }
        Schema::create('migration_assistant_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->comment('cryptlex/localazy');
            $table->string('status', 30)->default('draft')->comment('draft/validating/importing/completed/failed/rolled_back');
            $table->json('config')->nullable()->comment('API配置/字段映射/选项');
            $table->json('field_mapping')->nullable()->comment('用户自定义字段映射');
            $table->json('summary')->nullable()->comment('导入摘要');
            $table->json('validation_results')->nullable()->comment('AI验证结果');
            $table->json('ai_suggestions')->nullable()->comment('AI建议');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('valid_items')->default(0);
            $table->unsignedInteger('imported_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('skipped_items')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_assistant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_assistant_job_id')->constrained('migration_assistant_jobs')->cascadeOnDelete();
            $table->unsignedInteger('item_index');
            $table->json('original_data')->comment('原始数据');
            $table->json('mapped_data')->nullable()->comment('映射后数据');
            $table->json('cleaned_data')->nullable()->comment('清洗后数据');
            $table->json('validation_errors')->nullable()->comment('验证错误');
            $table->json('ai_suggestions')->nullable()->comment('AI修复建议');
            $table->string('status', 30)->default('pending')->comment('pending/valid/error/fixed/imported/skipped');
            $table->unsignedBigInteger('created_license_id')->nullable();
            $table->unsignedBigInteger('created_customer_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_assistant_items');
        Schema::dropIfExists('migration_assistant_jobs');
    }
};
