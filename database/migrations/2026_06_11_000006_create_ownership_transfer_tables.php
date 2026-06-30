<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique()->comment('编号: OT-YYYYMMDD-XXXXXX');
            $table->foreignId('tenant_id')->constrained();
            $table->string('transferable_type', 50)->comment('转移对象类型: license / product');
            $table->unsignedBigInteger('transferable_id')->comment('转移对象ID');
            $table->unsignedBigInteger('source_customer_id')->comment('源客户');
            $table->unsignedBigInteger('target_customer_id')->comment('目标客户');
            $table->string('status', 30)->default('pending_source')->comment('状态: pending_source/pending_target/approved/rejected/cancelled/completed');
            $table->decimal('transfer_fee', 12, 2)->nullable()->comment('转移费用');
            $table->string('fee_currency', 3)->default('CNY');
            $table->json('source_info')->nullable()->comment('源数据快照');
            $table->json('migration_summary')->nullable()->comment('数据迁移总结');
            $table->json('audit_log')->nullable();
            $table->text('source_notes')->nullable()->comment('源客户备注');
            $table->text('target_notes')->nullable()->comment('目标客户备注');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('source_confirmed_by')->nullable();
            $table->timestamp('source_confirmed_at')->nullable();
            $table->unsignedBigInteger('target_confirmed_by')->nullable();
            $table->timestamp('target_confirmed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['transferable_type', 'transferable_id'], 'otr_transferable_idx');
            $table->index('source_customer_id');
            $table->index('target_customer_id');

            $table->foreign('source_customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('target_customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('source_confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('target_confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('ownership_transfer_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_request_id');
            $table->string('entity_type', 50)->comment('迁移数据类型: license/subscription/invoice/device/custom_field/tag');
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 20)->default('migrated')->comment('migrated/skipped/failed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transfer_request_id');
            $table->index(['entity_type', 'entity_id']);
            $table->foreign('transfer_request_id')->references('id')->on('ownership_transfer_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_transfer_records');
        Schema::dropIfExists('ownership_transfer_requests');
    }
};
