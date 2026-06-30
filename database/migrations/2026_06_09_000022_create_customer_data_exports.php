<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_data_exports')) {
            Schema::create('customer_data_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30)->comment('导出类型: licenses/invoices/activations/customers');
                $table->string('format', 10)->default('csv')->comment('csv/pdf');
                $table->json('filters')->nullable()->comment('筛选条件快照');
                $table->string('status', 20)->default('pending')
                    ->comment('pending/processing/completed/failed');
                $table->string('file_path', 500)->nullable()->comment('生成的文件路径');
                $table->string('file_name', 255)->nullable()->comment('原始文件名');
                $table->unsignedInteger('file_size')->default(0)->comment('文件大小(bytes)');
                $table->unsignedInteger('record_count')->default(0)->comment('导出的记录数');
                $table->text('error_message')->nullable();
                $table->timestamp('expires_at')->nullable()->comment('文件过期时间');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index(['tenant_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_data_exports');
    }
};
