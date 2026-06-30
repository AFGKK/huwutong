<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained();
            $table->string('reservation_token', 64)->unique()->comment('预留令牌（客户端持有，提交时携带）');
            $table->string('fingerprint')->nullable()->comment('设备指纹');
            $table->string('ip_address', 45)->nullable()->comment('预申请IP');
            $table->json('payload')->nullable()->comment('预申请请求数据（机型、组件等）');
            $table->string('status', 20)->default('reserved')
                ->comment('预留状态: reserved/committed/cancelled/expired');
            $table->timestamp('reserved_at')->useCurrent()->comment('预申请时间');
            $table->timestamp('expires_at')->index()->comment('预留过期时间');
            $table->timestamp('committed_at')->nullable()->comment('确认提交时间');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->timestamps();

            $table->index(['license_id', 'status'], 'res_license_status');
            $table->index(['license_id', 'fingerprint', 'status'], 'res_fingerprint_status');
        });

        Schema::create('authorization_reservation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('authorization_reservations')->cascadeOnDelete();
            $table->string('action', 30)->comment('操作: reserve/commit/cancel/expire/timeout');
            $table->json('detail')->nullable()->comment('操作详情');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_reservation_logs');
        Schema::dropIfExists('authorization_reservations');
    }
};
