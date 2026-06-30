<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 数据留存策略表
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('策略键名，如 audit_logs');
            $table->string('name', 200)->comment('策略名称');
            $table->string('category', 50)->default('audit')->comment('分类: audit|security|operation|notification|performance');
            $table->string('table_name', 200)->nullable()->comment('关联数据表名');
            $table->integer('retention_days')->default(365)->comment('保留天数');
            $table->string('action', 30)->default('delete')->comment('到期动作: archive|delete|anonymize');
            $table->boolean('archive_enabled')->default(false)->comment('是否启用归档');
            $table->integer('archive_after_days')->nullable()->comment('归档阈值(天)');
            $table->string('archive_storage_tier', 30)->default('cold')->comment('归档存储层: hot|warm|cold|frozen|deep_frozen');
            $table->string('archive_disk', 50)->nullable()->comment('归档存储磁盘');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index(['is_active', 'action']);
        });

        // 数据留存执行记录表
        Schema::create('data_retention_executions', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key', 100)->index()->comment('关联策略键名');
            $table->string('table_name', 200)->nullable();
            $table->string('action', 30)->comment('执行动作');
            $table->integer('total_records')->default(0)->comment('处理记录数');
            $table->integer('affected_records')->default(0)->comment('受影响记录数');
            $table->integer('batch_count')->default(0)->comment('批次数');
            $table->boolean('is_dry_run')->default(false);
            $table->string('status', 20)->default('pending')->comment('pending|running|completed|failed');
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // 归档记录表（与 audit_archive_records 互补）
        Schema::create('data_retention_archives', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key', 100)->index();
            $table->string('table_name', 200);
            $table->string('storage_path', 500)->comment('归档文件路径');
            $table->string('storage_disk', 50)->default('s3');
            $table->string('storage_tier', 30)->default('cold');
            $table->bigInteger('file_size_bytes')->default(0);
            $table->integer('record_count')->default(0);
            $table->date('data_from')->nullable()->comment('数据起始日期');
            $table->date('data_to')->nullable()->comment('数据截止日期');
            $table->string('checksum', 64)->nullable()->comment('SHA256');
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_compressed')->default(false);
            $table->string('status', 20)->default('active')->comment('active|restoring|expired|deleted');
            $table->timestamp('restore_available_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_archives');
        Schema::dropIfExists('data_retention_executions');
        Schema::dropIfExists('data_retention_policies');
    }
};
