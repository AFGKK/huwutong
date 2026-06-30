<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('license_snapshots')) {
            return;
        }
        // ─── M2-12 License 快照表 ───
        Schema::create('license_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('license_id')->index();
            $table->string('action', 50)->comment('触发快照的操作: upgrade/downgrade/transfer/seat_change/type_change/admin_edit');
            $table->string('status_before', 30)->nullable()->comment('变更前的状态');
            $table->string('status_after', 30)->nullable()->comment('变更后的状态');
            $table->json('license_data')->comment('License 全字段快照 JSON');
            $table->json('diff')->nullable()->comment('变更差异摘要');
            $table->unsignedInteger('created_by')->nullable()->comment('操作人 user_id');
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        // ─── M2-13 License 删除者追踪（回收入口扩展） ───
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('deleted_by')->nullable()->after('deleted_at')->comment('删除人 user_id');
        });

        // ─── M2-11 License 通用变更审批表 ───
        Schema::create('license_change_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('license_id')->index();
            $table->string('action', 50)->comment('变更类型: upgrade/downgrade/transfer/seat_change/type_change/early_renewal');
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/cancelled/expired');
            $table->json('request_data')->comment('变更请求数据');
            $table->json('current_snapshot')->nullable()->comment('发起时的 License 快照');
            $table->text('reason')->nullable()->comment('变更原因');
            $table->unsignedInteger('requested_by')->comment('申请人 user_id');
            $table->unsignedInteger('approved_by')->nullable()->comment('审批人 user_id');
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('expires_at')->comment('审批超时自动过期时间');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['license_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_change_approvals');
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('deleted_by');
        });
        Schema::dropIfExists('license_snapshots');
    }
};
