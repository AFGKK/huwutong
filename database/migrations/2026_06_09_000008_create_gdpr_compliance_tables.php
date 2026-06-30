<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GDPR 数据主体请求（DSR）表
        Schema::create('gdpr_data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('access/export/rectification/erasure/restrict/portability/object');
            $table->string('status', 20)->default('pending')->comment('pending/processing/completed/approved/rejected/failed');
            $table->json('request_data')->nullable()->comment('请求详情');
            $table->text('reason')->nullable()->comment('请求原因');
            $table->string('output_file')->nullable()->comment('导出文件路径');
            $table->bigInteger('file_size')->default(0);
            $table->timestamp('expires_at')->nullable()->comment('文件过期时间');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('type');
            $table->index('created_at');
        });

        // DPA（数据处理协议）表
        Schema::create('data_processing_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('version', 20);
            $table->text('content')->comment('协议全文 Markdown');
            $table->string('status', 20)->default('draft')->comment('draft/published/archived');
            $table->json('data_categories')->nullable()->comment('处理的数据类别');
            $table->json('processing_purposes')->nullable()->comment('处理目的');
            $table->json('sub_processors')->nullable()->comment('子处理者列表');
            $table->json('security_measures')->nullable()->comment('安全措施');
            $table->string('jurisdiction')->nullable()->comment('管辖法律');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'version']);
            $table->index('status');
        });

        // DPA 签署记录表
        Schema::create('dpa_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpa_id')->constrained('data_processing_agreements')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signed_by')->constrained('users')->cascadeOnDelete();
            $table->string('signer_name');
            $table->string('signer_title')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->unique(['dpa_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dpa_signatures');
        Schema::dropIfExists('data_processing_agreements');
        Schema::dropIfExists('gdpr_data_requests');
    }
};
