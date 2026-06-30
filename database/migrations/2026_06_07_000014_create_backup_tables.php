<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 备份记录
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('备份名称');
            $table->string('type', 30)->default('database')->comment('database/files/full');
            $table->string('status', 30)->default('pending')
                ->comment('pending/running/completed/failed/expired');
            $table->string('file_path', 500)->nullable()->comment('备份文件路径');
            $table->string('file_name', 200)->nullable()->comment('备份文件名');
            $table->unsignedBigInteger('file_size')->nullable()->comment('文件大小(bytes)');
            $table->string('disk', 50)->default('local')->comment('存储磁盘');
            $table->string('checksum', 64)->nullable()->comment('SHA256 校验');
            $table->string('database', 100)->nullable()->comment('数据库名称');
            $table->json('included_tables')->nullable()->comment('包含的表列表');
            $table->json('excluded_tables')->nullable()->comment('排除的表列表');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable()->comment('耗时');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('保留到期时间');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('type');
            $table->index('expires_at');
        });

        // 文件备份包含目录
        Schema::create('backup_file_includes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_record_id')->constrained('backup_records')->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedBigInteger('file_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_file_includes');
        Schema::dropIfExists('backup_records');
    }
};
