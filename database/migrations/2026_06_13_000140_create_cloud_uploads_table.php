<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cloud_uploads')) {
            return;
        }
        Schema::create('cloud_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->comment('logo/brand_asset/document/screenshot/other');
            $table->string('original_name', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->comment('bytes');
            $table->string('path', 1000)->comment('存储路径');
            $table->string('url', 1000)->nullable()->comment('CDN/公开URL');
            $table->string('thumbnail_url', 1000)->nullable()->comment('缩略图URL');
            $table->string('disk', 30)->default('s3');
            $table->string('hash', 64)->comment('文件SHA256');
            $table->json('metadata')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('status', 30)->default('active')->comment('active/deleted');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_uploads');
    }
};
