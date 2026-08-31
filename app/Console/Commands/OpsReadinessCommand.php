<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 上线运维就绪检查（品牌 / 备案 / OAuth / 小程序 / 支付短信邮件 / PWA）
 *
 * php artisan ops:readiness
 * php artisan ops:readiness --strict
 */
class OpsReadinessCommand extends Command
{
    protected $signature = 'ops:readiness {--strict : 生产门禁，警告与可选能力缺失均计为失败}';

    protected $description = '上线运维就绪检查：Logo、备案、OAuth、小程序、支付/短信/邮件、PWA';

    public function handle(): int
    {
        $script = base_path('scripts/verify-ops-readiness.php');
        if (! is_file($script)) {
            $this->error('缺少 scripts/verify-ops-readiness.php');

            return self::FAILURE;
        }

        $cmd = PHP_BINARY.' '.escapeshellarg($script);
        if ($this->option('strict')) {
            $cmd .= ' --strict';
        }

        passthru($cmd, $code);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
