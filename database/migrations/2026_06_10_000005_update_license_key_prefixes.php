<?php

use App\Models\License;
use App\Services\KeyGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 迁移已有 License Key 到可读前缀格式
        // 注意：此迁移仅在生产环境中运行，测试环境使用 RefreshDatabase 不会触发

        if (app()->environment('testing')) {
            return;
        }

        $generator = app(KeyGenerator::class);
        $updated = 0;
        $total = 0;

        License::chunk(100, function ($licenses) use ($generator, &$updated, &$total) {
            foreach ($licenses as $license) {
                $total++;
                $type = $license->type;
                $prefix = KeyGenerator::PREFIX_MAP[$type] ?? null;

                if (!$prefix) {
                    continue;
                }

                $key = $license->license_key;

                // 已经是正确前缀，跳过
                if (str_starts_with($key, $prefix)) {
                    continue;
                }

                // 尝试提取后缀
                $suffix = null;
                $oldKey = $key;

                if (preg_match('/^HWT-[A-Z]+-([A-F0-9]{16}-[A-F0-9]{4})$/', $key, $m)) {
                    $suffix = $m[1];
                } elseif (preg_match('/^([A-F0-9]{16}-[A-F0-9]{4})$/', $key, $m)) {
                    $suffix = $m[1];
                }

                if (!$suffix) {
                    $newKey = $generator->generate($type);
                } else {
                    $newKey = "{$prefix}-{$suffix}";
                }

                // 更新 license
                DB::table('licenses')->where('id', $license->id)->update(['license_key' => $newKey]);

                // 更新关联表
                foreach (['license_verification_logs', 'tamper_events', 'audit_logs'] as $table) {
                    try {
                        DB::table($table)->where('license_key', $oldKey)->update(['license_key' => $newKey]);
                    } catch (\Exception $e) {
                        // 表可能不存在
                    }
                }

                $updated++;
            }
        });

        echo "Migrated {$updated}/{$total} license keys to readable prefix format.\n";
    }

    public function down(): void
    {
        // 无回滚操作 - 前缀格式化不可逆
    }
};
