<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migration_imports')) {
            return;
        }
        Schema::create('migration_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->comment('keygen/licensespring/custom');
            $table->string('status', 30)->default('pending')->comment('pending/running/validating/completed/failed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('success')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->json('field_mapping')->nullable()->comment('字段映射配置');
            $table->json('options')->nullable()->comment('导入选项');
            $table->text('error_message')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('file_path', 500)->nullable()->comment('上传文件路径(自定义导入)');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_import_id')->constrained('migration_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('original_data')->comment('原始数据');
            $table->json('mapped_data')->nullable()->comment('映射后数据');
            $table->string('status', 30)->default('pending')->comment('pending/success/skipped/failed');
            $table->string('error_message')->nullable();
            $table->unsignedBigInteger('created_license_id')->nullable();
            $table->unsignedBigInteger('created_customer_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_import_rows');
        Schema::dropIfExists('migration_imports');
    }
};
