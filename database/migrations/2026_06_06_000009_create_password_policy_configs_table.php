<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_policy_configs', function (Blueprint $table) {
            $table->id();
            // 密码强度
            $table->integer('min_length')->default(8);
            $table->integer('max_length')->default(128);
            $table->boolean('require_uppercase')->default(true);
            $table->boolean('require_lowercase')->default(true);
            $table->boolean('require_number')->default(true);
            $table->boolean('require_special')->default(true);
            // 密码历史
            $table->integer('history_count')->default(5)->comment('禁止使用最近 N 次密码');
            $table->integer('expiry_days')->default(90)->comment('密码过期天数，0=永不过期');
            // 账号锁定
            $table->integer('lockout_max_attempts')->default(5);
            $table->integer('lockout_duration_minutes')->default(15);
            // 其他
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 插入默认策略
        DB::table('password_policy_configs')->insert([
            'min_length' => 8,
            'max_length' => 128,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_number' => true,
            'require_special' => true,
            'history_count' => 5,
            'expiry_days' => 90,
            'lockout_max_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('password_policy_configs');
    }
};
