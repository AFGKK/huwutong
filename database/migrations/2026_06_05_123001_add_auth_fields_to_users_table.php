<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('password_history')->nullable()->after('password')
                ->comment('最近密码哈希历史');
            $table->timestamp('password_changed_at')->nullable()->after('password_history')
                ->comment('上次密码修改时间');
            $table->integer('login_attempts')->default(0)->after('status')
                ->comment('连续登录失败次数');
            $table->timestamp('locked_until')->nullable()->after('login_attempts')
                ->comment('账号锁定截止时间');
            $table->timestamp('phone_verified_at')->nullable()->after('phone')
                ->comment('手机号验证时间');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_history',
                'password_changed_at',
                'login_attempts',
                'locked_until',
                'phone_verified_at',
            ]);
        });
    }
};
