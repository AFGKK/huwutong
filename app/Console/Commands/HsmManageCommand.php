<?php

namespace App\Console\Commands;

use App\Services\Hsm\HsmService;
use App\Models\HsmKey;
use Illuminate\Console\Command;

class HsmManageCommand extends Command
{
    protected $signature = 'hsm
        {action : status|init|rotate|sign|verify|list}
        {--label=license-v1 : 密钥标签}
        {--key= : 密钥 ID}
        {--license= : License Key}
        {--signature= : 签名值}
        {--algorithm=Ed25519 : 算法}';

    protected $description = 'HSM 硬件安全模块管理（M3-79）';

    public function handle(HsmService $hsm): int
    {
        return match ($this->argument('action')) {
            'status' => $this->showStatus($hsm),
            'init' => $this->initKey($hsm),
            'rotate' => $this->rotateKey($hsm),
            'sign' => $this->signLicense($hsm),
            'verify' => $this->verifySignature($hsm),
            'list' => $this->listKeys(),
            default => $this->error('未知操作'),
        };
    }

    private function showStatus(HsmService $hsm): int
    {
        $health = $hsm->health();
        $stats = $hsm->stats();

        $this->info('HSM 硬件安全模块状态');
        $this->newLine();
        $this->table(
            ['指标', '值'],
            [
                ['启用状态', $stats['enabled'] ? '✅ 已启用' : '❌ 未启用'],
                ['HSM 提供者', $stats['provider']],
                ['健康状态', $health['healthy'] ? '✅ 正常' : '❌ 异常'],
                ['健康消息', $health['message']],
                ['密钥总数', $stats['total_keys']],
                ['活跃密钥', $stats['active_keys']],
                ['总签名次数', $stats['total_signatures']],
            ]
        );
        return 0;
    }

    private function initKey(HsmService $hsm): int
    {
        $label = $this->option('label');
        $algorithm = $this->option('algorithm');

        $this->info("初始化 HSM 密钥: {$label} ({$algorithm})");
        $key = $hsm->createKey($label, $algorithm);

        $this->info('✅ 密钥创建成功');
        $this->table(
            ['属性', '值'],
            [
                ['ID', $key->id],
                ['标签', $key->key_label],
                ['句柄', $key->key_handle],
                ['算法', $key->algorithm],
                ['公钥', substr($key->public_key, 0, 32) . '...'],
            ]
        );
        return 0;
    }

    private function rotateKey(HsmService $hsm): int
    {
        $label = $this->option('label');
        $algorithm = $this->option('algorithm');

        if (!$this->confirm("确认轮换密钥「{$label}」？旧密钥将被停用。")) {
            return 0;
        }

        $key = $hsm->rotateKey($label, $algorithm);
        $this->info("✅ 密钥轮换完成，新密钥 ID: {$key->id}");
        return 0;
    }

    private function signLicense(HsmService $hsm): int
    {
        $license = $this->option('license');
        if (!$license) {
            $this->error('请提供 --license 参数');
            return 1;
        }

        $result = $hsm->signLicenseKey($license, $this->option('label'));
        $this->info("签名结果:");
        $this->line("  Signature: {$result['signature']}");
        $this->line("  Key ID:    {$result['key_id']}");
        $this->line("  Algorithm: {$result['algorithm']}");
        return 0;
    }

    private function verifySignature(HsmService $hsm): int
    {
        $license = $this->option('license');
        $signature = $this->option('signature');
        $keyId = (int)$this->option('key');

        if (!$license || !$signature || !$keyId) {
            $this->error('请提供 --license --signature --key 参数');
            return 1;
        }

        $valid = $hsm->verifyLicenseKey($license, $signature, $keyId);
        $this->info($valid ? '✅ 签名验证通过' : '❌ 签名验证失败');
        return $valid ? 0 : 1;
    }

    private function listKeys(): int
    {
        $keys = HsmKey::orderByDesc('id')->get();

        if ($keys->isEmpty()) {
            $this->warn('没有 HSM 密钥记录');
            return 0;
        }

        $this->table(
            ['ID', '标签', '算法', '提供者', '活跃', '签名次数', '轮换时间'],
            $keys->map(fn($k) => [
                $k->id,
                $k->key_label,
                $k->algorithm,
                $k->provider,
                $k->is_active ? '✅' : '❌',
                number_format($k->sign_count),
                $k->rotated_at?->diffForHumans() ?? '-',
            ])
        );
        return 0;
    }
}
