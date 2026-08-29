<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 手机号注册/登录完整化：
 * - users.email 允许为空（纯手机号用户）
 * - 系统设置补齐阿里云短信模板 Code
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }

        $rows = [
            [
                'group' => 'sms',
                'key' => 'sms_aliyun_template',
                'value' => '',
                'type' => 'text',
                'description' => '阿里云验证码短信模板 Code（如 SMS_xxx）',
                'is_public' => false,
            ],
            [
                'group' => 'sms',
                'key' => 'sms_aliyun_notify_template',
                'value' => '',
                'type' => 'text',
                'description' => '阿里云通知类短信模板 Code（可选）',
                'is_public' => false,
            ],
            [
                'group' => 'sms',
                'key' => 'sms_phone_auth_enabled',
                'value' => '1',
                'type' => 'switch',
                'description' => '启用手机号验证码登录/注册',
                'is_public' => true,
            ],
        ];

        foreach ($rows as $row) {
            $existing = SiteSetting::where('key', $row['key'])->first();
            if ($existing) {
                $existing->update([
                    'group' => $row['group'],
                    'type' => $row['type'],
                    'description' => $row['description'],
                    'is_public' => $row['is_public'],
                ]);
            } else {
                SiteSetting::create($row);
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("UPDATE users SET email = CONCAT('user_', id, '@placeholder.local') WHERE email IS NULL");
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE users SET email = CONCAT('user_', id, '@placeholder.local') WHERE email IS NULL");
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
