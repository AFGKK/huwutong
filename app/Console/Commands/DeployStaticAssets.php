<?php

namespace App\Console\Commands;

use App\Services\StaticAssetCdnService;
use Illuminate\Console\Command;

class DeployStaticAssets extends Command
{
    protected $signature = 'assets:cdn-deploy
        {--version= : 自定义版本号（默认使用时间戳）}
        {--activate : 部署后自动激活版本}
        {--build-dir= : 构建产物目录（默认 public/build）}';

    protected $description = '部署前端构建产物到 CDN (M2-133)';

    public function handle(StaticAssetCdnService $cdnService): int
    {
        $this->info('开始部署静态资源到 CDN...');

        try {
            $result = $cdnService->deploy(
                $this->option('version'),
                $this->option('build-dir')
            );

            $this->newLine();
            $this->info("部署完成！");
            $this->table(
                ['指标', '值'],
                [
                    ['版本', $result['version']],
                    ['文件总数', (string) $result['total']],
                    ['上传失败', (string) $result['failed']],
                    ['CDN Base URL', $result['base_url']],
                ]
            );

            if ($result['failed'] > 0) {
                $this->warn("{$result['failed']} 个文件上传失败，请查看日志确认");
            }

            // 可选激活
            if ($this->option('activate')) {
                $this->call('assets:cdn-activate', ['version' => $result['version']]);
            }

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
