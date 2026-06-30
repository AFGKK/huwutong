<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 租户邀请记录表（区别于通用 invite_codes）
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 30)->default('member')
                ->comment('邀请角色: admin/finance/developer/readonly');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique()->comment('邀请令牌');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamp('accepted_at')->nullable()->comment('接受时间');
            $table->timestamp('declined_at')->nullable()->comment('拒绝时间');
            $table->string('status', 20)->default('pending')
                ->comment('pending/accepted/declined/expired/cancelled');
            $table->text('message')->nullable()->comment('邀请附言');
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index(['token', 'status']);
            $table->index('expires_at');
        });

        // 2. 增强 tenant_members 表：添加 invited_via 和 permissions 字段
        Schema::table('tenant_members', function (Blueprint $table) {
            $table->string('invited_via', 30)->nullable()->after('invited_by')
                ->comment('加入方式: invitation/direct_add/sso/signup');
            $table->json('permissions')->nullable()->after('role')
                ->comment('角色额外权限覆盖（JSON）');
            $table->timestamp('joined_at')->nullable()->after('permissions')
                ->comment('加入时间');
            $table->index('status');
        });

        // 3. 更新 tenant_members 中现有的 joined_at 为 created_at
        DB::table('tenant_members')->whereNull('joined_at')->update([
            'joined_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');

        Schema::table('tenant_members', function (Blueprint $table) {
            $table->dropColumn(['invited_via', 'permissions', 'joined_at']);
        });
    }
};
