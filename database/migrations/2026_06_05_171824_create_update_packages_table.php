<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('version', 32);                        // 语义版本号，如 2.1.0
            $table->string('prev_version', 32)->nullable();        // 前一版本（增量补丁的基版本）
            $table->enum('type', ['full', 'incremental', 'hotfix'])->default('full');
            $table->string('file_path', 512);                     // 云存储路径
            $table->string('file_name', 255);                     // 原始文件名
            $table->bigInteger('file_size')->unsigned();           // 文件大小（字节）
            $table->string('file_hash', 128);                     // SHA-256 哈希
            $table->string('signature', 512)->nullable();          // Ed25519 签名
            $table->json('checksums')->nullable();                 // 各分块校验和
            $table->json('release_notes')->nullable();             // 发布说明（多语言）
            $table->json('metadata')->nullable();                  // 额外元数据（最低要求版本、兼容性等）
            $table->enum('status', ['draft', 'published', 'deprecated', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // 同一产品下版本号唯一
            $table->unique(['product_id', 'version']);

            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'version']);
        });

        Schema::create('update_package_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_package_id')->constrained('update_packages')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('client_ip', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('cdn_edge_ip', 45)->nullable();        // CDN 边缘节点 IP
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['update_package_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_package_downloads');
        Schema::dropIfExists('update_packages');
    }
};
