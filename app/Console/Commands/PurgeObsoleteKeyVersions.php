<?php

namespace App\Console\Commands;

use App\Services\PublicKeyVersionService;
use Illuminate\Console\Command;

class PurgeObsoleteKeyVersions extends Command
{
    protected $signature = 'key-version:purge-obsolete
                            {--dry-run : 仅显示将要清理的版本}';

    protected $description = '清理已废弃的公钥版本（已过期 + 过兼容窗口期）';

    public function handle(PublicKeyVersionService $keyVersionService): int
    {
        $this->info('扫描废弃的公钥版本...');

        $cutoff = now()->subDays(PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS);

        $obsolete = \App\Models\PublicKeyVersion::where('is_active', false)
            ->where('is_revoked', true)
            ->where('expires_at', '<=', $cutoff)
            ->get();

        if ($obsolete->isEmpty()) {
            $this->info('没有需要清理的废弃版本');
            return Command::SUCCESS;
        }

        $this->table(
            ['版本', '算法', '过期时间', '吊销原因'],
            $obsolete->map(fn ($v) => [
                "v{$v->key_version}",
                $v->algorithm,
                $v->expires_at?->toDateString() ?? 'N/A',
                $v->revoke_reason ?? 'N/A',
            ])->toArray()
        );

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] 将清理 {$obsolete->count()} 个废弃版本");
            return Command::SUCCESS;
        }

        $count = $keyVersionService->purgeObsoleteVersions();
        $this->info("已清理 {$count} 个废弃公钥版本");

        return Command::SUCCESS;
    }
}
