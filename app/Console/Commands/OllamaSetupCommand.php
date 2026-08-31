<?php

namespace App\Console\Commands;

use App\Services\OllamaSetupService;
use Illuminate\Console\Command;

/**
 * D-37: Ollama 启动验证与模型拉取
 *
 * php artisan ollama:setup --status
 * php artisan ollama:setup --pull
 * php artisan ollama:setup --pull --model=qwen2.5:7b
 */
class OllamaSetupCommand extends Command
{
    protected $signature = 'ollama:setup
        {--status : 检查 Ollama 健康状态与已安装模型}
        {--pull : 拉取推荐模型（或配合 --model）}
        {--model= : 拉取指定模型}';

    protected $description = 'Ollama 运行时检查与模型拉取（D-37）';

    public function handle(OllamaSetupService $service): int
    {
        $this->info('=== Ollama 设置 (D-37) ===');

        if ($this->option('status')) {
            return $this->showStatus($service);
        }

        if ($model = $this->option('model')) {
            return $this->pullSingle($service, $model);
        }

        if ($this->option('pull')) {
            return $this->pullRecommended($service);
        }

        return $this->showStatus($service);
    }

    protected function showStatus(OllamaSetupService $service): int
    {
        $health = $service->health();
        $this->line('API: ' . $health['api_base']);
        $this->line('状态: ' . $health['status']);

        if ($health['status'] !== 'available') {
            $this->line('');
            $this->warn('Ollama 未运行，请先启动：');
            $this->line('  Docker:  bash deploy/llm/setup.sh ollama');
            $this->line('  Windows: powershell -File scripts/start-ollama.ps1');
            $this->line('  或安装:  https://ollama.com/download');

            return self::FAILURE;
        }

        $this->line('已安装模型: ' . $health['count']);
        foreach ($health['models'] as $m) {
            $this->line('  - ' . ($m['name'] ?? ''));
        }

        if ($health['count'] === 0) {
            $this->line('');
            $this->comment('尚无模型，运行: php artisan ollama:setup --pull');
        }

        return self::SUCCESS;
    }

    protected function pullSingle(OllamaSetupService $service, string $model): int
    {
        if (! $service->isAvailable()) {
            $this->error('Ollama 不可用');

            return self::FAILURE;
        }

        $this->info("拉取模型 {$model}...");
        $result = $service->pullModel($model);

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }

    protected function pullRecommended(OllamaSetupService $service): int
    {
        if (! $service->isAvailable()) {
            $this->error('Ollama 不可用');

            return self::FAILURE;
        }

        $models = $service->recommendedModels();
        $this->info('拉取推荐模型: ' . implode(', ', $models));

        $result = $service->pullRecommendedModels($models);

        foreach ($result['pulled'] as $item) {
            $this->line('  ✅ ' . $item['model']);
        }
        foreach ($result['failed'] as $item) {
            $this->line('  ❌ ' . $item['model'] . ': ' . $item['message']);
        }

        $this->line('');
        $this->showStatus($service);

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
