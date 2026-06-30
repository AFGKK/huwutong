<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('license_transfer_requests')) {
            Schema::create('license_transfer_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique()->comment('转移编号 TX-xxx');
                $table->string('type', 20)->comment('device_transfer|customer_transfer|user_transfer');
                $table->string('status', 30)->default('pending')->comment('pending|approved|rejected|cancelled|completed|expired');
                $table->foreignId('license_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users');

                // 来源信息
                $table->json('source_info')->nullable()->comment('来源: {customer_id, user_id, device_id, device_name}');
                // 目标信息（根据 type 不同）
                $table->foreignId('target_customer_id')->nullable()->constrained('customers');
                $table->foreignId('target_user_id')->nullable()->constrained('users');
                $table->foreignId('target_device_id')->nullable()->constrained('devices');
                $table->string('target_device_fingerprint', 64)->nullable()->comment('目标设备指纹');
                $table->string('target_device_name')->nullable();
                $table->text('reason')->nullable()->comment('转移原因');
                $table->text('admin_notes')->nullable();

                // 安全和审计
                $table->string('verification_token', 64)->nullable()->comment('验证令牌（用于二次确认）');
                $table->timestamp('verification_expires_at')->nullable();
                $table->ipAddress('request_ip')->nullable();
                $table->json('audit_log')->nullable()->comment('操作审计日志');

                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index(['license_id', 'type']);
                $table->index(['target_customer_id', 'target_device_id'], 'tx_req_cust_dev_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_transfer_requests');
    }
};
