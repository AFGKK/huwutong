<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_archive_restore_requests')) {
            return;
        }
        // 增强归档策略表
        Schema::table('audit_archive_policies', function (Blueprint $table) {
            $table->string('storage_tier', 20)->default('cold')->after('archive_disk')->comment('hot/warm/cold/frozen');
            $table->string('retrieval_status', 20)->nullable()->after('last_executed_at')->comment('归档状态');
            $table->string('encryption_key_hash', 64)->nullable()->after('retrieval_status');
            $table->bigInteger('archived_size_bytes')->default(0)->after('encryption_key_hash');
            $table->integer('total_archived_count')->default(0)->after('archived_size_bytes');
        });

        // 增强归档记录表
        Schema::table('audit_archive_records', function (Blueprint $table) {
            $table->string('storage_class', 30)->nullable()->after('archive_file')->comment('STANDARD/DEEP_ARCHIVE/GLACIER');
            $table->string('checksum', 64)->nullable()->after('storage_class')->comment('归档文件SHA256');
            $table->boolean('is_encrypted')->default(false)->after('checksum');
            $table->boolean('is_compressed')->default(false)->after('is_encrypted');
            $table->string('original_filename', 255)->nullable()->after('is_compressed');
        });

        // 取回请求表
        Schema::create('audit_archive_restore_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_record_id')->constrained('audit_archive_records')->cascadeOnDelete();
            $table->string('requester_type', 40)->default('admin')->comment('admin/compliance/audit');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/restoring/available/expired/failed/cancelled');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('available_until')->nullable()->comment('取回文件有效期');
            $table->timestamp('expires_at')->nullable();
            $table->string('temp_file_path', 500)->nullable()->comment('临时存储路径');
            $table->string('error_message', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_archive_restore_requests');

        Schema::table('audit_archive_records', function (Blueprint $table) {
            $table->dropColumn(['storage_class', 'checksum', 'is_encrypted', 'is_compressed', 'original_filename']);
        });

        Schema::table('audit_archive_policies', function (Blueprint $table) {
            $table->dropColumn(['storage_tier', 'retrieval_status', 'encryption_key_hash', 'archived_size_bytes', 'total_archived_count']);
        });
    }
};
