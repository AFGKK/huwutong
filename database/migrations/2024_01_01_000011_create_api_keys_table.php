<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 跳过如果表已存在（防止 RefreshDatabase 重跑时冲突）
        if (Schema::hasTable('api_keys')) {
            return;
        }

        // 注意：不使用外键约束，因为测试时 tenants 表可能在之后才创建
        // 业务层保证 tenant_id 的有效性
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index()->comment('租户 ID');
            $table->string('key_id', 64)->unique()->comment('密钥标识（客户可见）');
            $table->string('name')->comment('密钥名称');
            $table->text('secret')->comment('HMAC 密钥（加密存储）');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('key_id');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
