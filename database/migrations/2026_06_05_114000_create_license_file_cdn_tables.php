<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // License 文件分发记录
        Schema::create('license_file_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('file_key')->unique()->comment('存储 key（S3/local 路径）');
            $table->string('original_filename')->comment('原始文件名');
            $table->string('mime_type')->default('application/octet-stream');
            $table->bigInteger('file_size')->default(0)->comment('文件字节数');
            $table->string('file_hash')->comment('文件 SHA-256');
            $table->string('signature')->comment('Ed25519 签名');
            $table->integer('key_version')->comment('签名时使用的密钥版本');
            $table->string('algorithm')->default('Ed25519');
            $table->json('payload_snapshot')->nullable()->comment('生成时的载荷快照');
            $table->string('storage_driver')->default('local')->comment('local/s3/cdn');
            $table->string('cdn_url')->nullable()->comment('CDN 加速 URL');
            $table->string('status')->default('active')->comment('active/expired/revoked');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // CDN 分发统计
        Schema::create('cdn_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_file_record_id')->constrained('license_file_records')->cascadeOnDelete();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->string('country')->nullable();
            $table->integer('response_code')->default(200);
            $table->bigInteger('bytes_served')->default(0);
            $table->timestamp('downloaded_at')->useCurrent();
            $table->index('downloaded_at');
            $table->index('license_file_record_id');
        });

        // 公钥版本管理表（对接 M2-135）
        Schema::create('public_key_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('key_version')->unique()->comment('递增版本号');
            $table->string('algorithm')->default('Ed25519');
            $table->text('public_key')->comment('Base64 公钥');
            $table->text('public_key_pem')->nullable()->comment('PEM 格式公钥');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('过期后旧公钥保留 30 天兼容窗口');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();
        });

        // CRL 条目扩展
        Schema::create('certificate_revocation_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_file_record_id')->nullable()->constrained('license_file_records')->nullOnDelete();
            $table->string('license_key');
            $table->integer('key_version');
            $table->string('reason')->nullable();
            $table->timestamp('revoked_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->index(['license_key', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_revocation_list');
        Schema::dropIfExists('public_key_versions');
        Schema::dropIfExists('cdn_distributions');
        Schema::dropIfExists('license_file_records');
    }
};
