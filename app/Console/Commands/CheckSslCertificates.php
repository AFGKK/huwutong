<?php

namespace App\Console\Commands;

use App\Services\CnameService;
use Illuminate\Console\Command;

class CheckSslCertificates extends Command
{
    protected $signature = 'ssl:check
        {--renew : 自动续期到期的证书}
        {--alert-only : 仅检查到期告警，不执行续期}';

    protected $description = '检查 SSL 证书状态，自动续期即将到期的证书并发送告警';

    public function handle(CnameService $cnameService): int
    {
        $this->info('正在检查 SSL 证书状态...');

        if ($this->option('alert-only')) {
            $alerts = $cnameService->checkExpiringCertificates();

            if (empty($alerts)) {
                $this->info('没有即将到期的证书需要告警');
            } else {
                $this->table(
                    ['域名', '到期时间', '剩余天数'],
                    collect($alerts)->map(fn($a) => [
                        'domain' => $a['domain'],
                        'expires_at' => $a['expires_at']->format('Y-m-d H:i:s'),
                        'days_left' => $a['days_left'],
                    ])->toArray(),
                );
            }

            return Command::SUCCESS;
        }

        $results = $cnameService->checkAndRenewCertificates();

        $this->info("检查完成: {$results['checked']} 个证书");
        $this->info("已续期: {$results['renewed']} 个");
        $this->info("失败: {$results['failed']} 个");

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('续期失败的证书:');
            foreach ($results['errors'] as $error) {
                $this->line("  Domain #{$error['domain_id']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
