<?php

namespace App\Console\Commands;

use App\Models\CustomDomain;
use App\Models\Notification;
use App\Models\SslCertificate;
use Illuminate\Console\Command;

class CheckDomainHealth extends Command
{
    protected $signature = 'domain:check-health';
    protected $description = '检查域名健康状态：SSL 到期提醒、DNS 解析异常';

    public function handle(): int
    {
        $this->info('开始检查域名健康状态...');

        // 1. SSL 证书即将到期提醒（30天内）
        $expiringSsls = SslCertificate::where('status', 'issued')
            ->where('expires_at', '<', now()->addDays(30))
            ->where(function ($q) {
                $q->whereNull('renewal_alert_sent_at')
                  ->orWhere('renewal_alert_sent_at', '<', now()->subDays(1));
            })
            ->with('customDomain.tenant')
            ->get();

        foreach ($expiringSsls as $ssl) {
            $daysLeft = now()->diffInDays($ssl->expires_at);
            $domain = $ssl->customDomain;

            Notification::create([
                'tenant_id' => $domain?->tenant_id,
                'type' => 'ssl_expiring',
                'title' => "SSL 证书即将到期",
                'content' => "域名 {$domain?->domain} 的 SSL 证书将于 {$daysLeft} 天后到期（{$ssl->expires_at->toDateString()}），请及时续期。",
                'payload' => [
                    'custom_domain_id' => $domain?->id,
                    'domain' => $domain?->domain,
                    'expires_at' => $ssl->expires_at->toDateString(),
                    'days_left' => $daysLeft,
                ],
                'is_read' => false,
            ]);

            $ssl->renewal_alert_sent_at = now();
            $ssl->save();

            $this->warn("  [SSL到期] {$domain?->domain} - 剩余 {$daysLeft} 天");
        }

        // 2. DNS 解析异常检测
        $activeDomains = CustomDomain::where('is_active', true)
            ->where('verified', true)
            ->get();

        $dnsFailed = 0;
        foreach ($activeDomains as $domain) {
            $dnsOk = $this->checkDns($domain->domain, $domain->cname_target);
            if (!$dnsOk) {
                $dnsFailed++;
                $this->error("  [DNS异常] {$domain->domain} - 解析失败");
            }
        }

        $this->info("检查完成。SSL到期提醒: {$expiringSsls->count()} 个，DNS异常: {$dnsFailed} 个");
        return Command::SUCCESS;
    }

    private function checkDns(string $domain, ?string $expectedCname): bool
    {
        if (!$expectedCname) return false;
        try {
            $records = dns_get_record($domain, DNS_CNAME);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (isset($r['target']) && rtrim($r['target'], '.') === rtrim($expectedCname, '.')) {
                        return true;
                    }
                }
            }
            $aRecords = dns_get_record($domain, DNS_A);
            return is_array($aRecords) && !empty($aRecords);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
