<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255)->comment('原始文件名');
            $table->string('storage_path', 500)->comment('云存储路径');
            $table->string('mime_type', 100)->nullable()->comment('MIME类型');
            $table->unsignedBigInteger('file_size')->comment('文件大小（字节）');
            $table->string('file_extension', 20)->nullable()->comment('文件扩展名');
            $table->string('disk', 30)->default('s3')->comment('存储磁盘');
            $table->string('visibility', 20)->default('private')->comment('可见性: private/public');
            $table->string('category', 50)->nullable()->comment('分类: invoice/receipt/contract/attachment/other');
            $table->text('description')->nullable()->comment('文件描述');
            $table->json('metadata')->nullable()->comment('扩展元数据');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('上传者');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'category']);
            $table->index('uploaded_by');

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('file_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_file_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique()->comment('分享令牌');
            $table->string('password', 255)->nullable()->comment('访问密码（bcrypt）');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->unsignedInteger('max_downloads')->nullable()->comment('最大下载次数');
            $table->unsignedInteger('download_count')->default(0)->comment('已下载次数');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_share_links');
        Schema::dropIfExists('customer_files');
    }
};
