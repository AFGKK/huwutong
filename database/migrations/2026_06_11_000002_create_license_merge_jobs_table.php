<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_merge_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('target_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // pending, previewed, completed, failed, rolled_back
            $table->unsignedInteger('total_licenses')->default(0);
            $table->unsignedInteger('merged_licenses')->default(0);
            $table->unsignedInteger('skipped_licenses')->default(0);
            $table->unsignedInteger('failed_licenses')->default(0);
            $table->unsignedInteger('total_devices')->default(0);
            $table->unsignedInteger('migrated_devices')->default(0);
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->json('conflict_resolution')->nullable(); // 冲突解决策略
            $table->json('merge_audit')->nullable();          // 完整的合并审计链
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['source_customer_id']);
            $table->index(['target_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_merge_jobs');
    }
};
