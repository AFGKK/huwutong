<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offline_activations')) {
            return;
        }
        // 给 licenses 表添加离线激活相关字段
        Schema::table('licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('licenses', 'offline_activated_at')) {
                $table->timestamp('offline_activated_at')->nullable()->after('activated_at')
                    ->comment('最近一次离线文件生成/激活时间');
            }
            if (! Schema::hasColumn('licenses', 'offline_expires_at')) {
                $table->timestamp('offline_expires_at')->nullable()->after('offline_activated_at')
                    ->comment('离线文件有效期截止时间');
            }
        });

        // 离线激活记录表（审计追踪）
        Schema::create('offline_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->string('license_key', 64);
            $table->string('client_ip', 45)->nullable();
            $table->string('result', 20)->default('pending')->comment('pending/valid/expired/revoked');
            $table->json('payload_snapshot')->nullable()->comment('离线文件载荷快照');
            $table->timestamp('expires_at')->nullable()->comment('离线文件到期时间');
            $table->timestamps();

            $table->index('license_key');
            $table->index('result');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['offline_activated_at', 'offline_expires_at']);
        });
        Schema::dropIfExists('offline_activations');
    }
};
