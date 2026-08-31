<?php

namespace App\Services;

use App\Models\LlmProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 本地大模型部署管理服务 (M3-49)
 *
 * 管理私有化部署的大模型实例
 * - Ollama / vLLM 实例监控
 * - GPU 状态监控
 * - 模型下载管理
 * - 部署安装指引
 * - 健康检查与告警
 */
class LocalLLMService
{
    const CACHE_KEY_STATUS = 'local_llm:status';
    const CACHE_KEY_GPU = 'local_llm:gpu';

    /**
     * 获取所有本地 LLM 实例状态
     */
    public function getStatus(): array
    {
        return Cache::remember(self::CACHE_KEY_STATUS, 30, function () {
            $providers = LlmProvider::whereIn('driver', ['ollama', 'vllm'])
                ->where('is_active', true)
                ->get();

            $instances = [];
            foreach ($providers as $provider) {
                $instances[] = $this->checkInstance($provider);
            }

            // 自动检测未注册的本地实例
            if (empty($instances)) {
                $instances = $this->autoDetect();
            }

            return [
                'instances' => $instances,
                'total' => count($instances),
                'healthy' => count(array_filter($instances, fn($i) => $i['healthy'])),
                'gpu_info' => $this->getGpuInfo(),
                'hardware' => $this->getHardwareInfo(),
            ];
        });
    }

    /**
     * 检查单个实例状态
     */
    protected function checkInstance(LlmProvider $provider): array
    {
        $healthEndpoint = $provider->driver === 'ollama'
            ? '/api/tags'
            : '/v1/models';

        $apiBase = rtrim($provider->api_base ?: (
            $provider->driver === 'ollama'
                ? config('local-llm.ollama.api_base')
                : config('local-llm.vllm.api_base')
        ), '/');

        $startTime = microtime(true);
        $healthy = false;
        $models = [];
        $error = null;

        try {
            $response = Http::timeout(10)->get("{$apiBase}{$healthEndpoint}");
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $healthy = true;
                $rawModels = $provider->driver === 'ollama'
                    ? ($response->json('models') ?? [])
                    : ($response->json('data') ?? []);

                $models = array_map(fn($m) => [
                    'id' => $m['name'] ?? $m['id'] ?? 'unknown',
                    'size' => $m['size'] ?? 0,
                ], $rawModels);
            } else {
                $error = "HTTP {$response->status()}";
            }
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $error = $e->getMessage();
        }

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'driver' => $provider->driver,
            'api_base' => $apiBase,
            'healthy' => $healthy,
            'latency_ms' => $latencyMs ?? 0,
            'models' => $models,
            'model_count' => count($models),
            'default_model' => $provider->default_model,
            'error' => $error,
            'last_check' => now()->toIso8601String(),
        ];
    }

    /**
     * 自动检测未注册的本地 LLM 实例
     */
    protected function autoDetect(): array
    {
        $instances = [];
        $endpoints = [
            ['driver' => 'ollama', 'url' => config('local-llm.ollama.api_base'), 'health' => '/api/tags', 'name' => 'Ollama (自动检测)'],
            ['driver' => 'vllm', 'url' => config('local-llm.vllm.api_base'), 'health' => '/v1/models', 'name' => 'vLLM (自动检测)'],
        ];

        foreach ($endpoints as $ep) {
            try {
                $response = Http::timeout(5)->get($ep['url'] . $ep['health']);
                if ($response->successful()) {
                    $instances[] = [
                        'id' => null,
                        'name' => $ep['name'],
                        'driver' => $ep['driver'],
                        'api_base' => $ep['url'],
                        'healthy' => true,
                        'latency_ms' => 0,
                        'models' => [],
                        'model_count' => 0,
                        'default_model' => null,
                        'error' => null,
                        'auto_detected' => true,
                        'last_check' => now()->toIso8601String(),
                    ];
                }
            } catch (\Throwable) {
                // 静默忽略
            }
        }

        return $instances;
    }

    /**
     * 获取 GPU 信息
     */
    public function getGpuInfo(): array
    {
        if (!config('local-llm.gpu_monitoring.enabled')) {
            return ['available' => false, 'message' => __('app.common.gpu_monitoring_disabled')];
        }

        // 尝试通过 nvidia-smi 获取
        $smiPath = config('local-llm.gpu_monitoring.nvidia_smi_path');
        $output = [];
        $exitCode = 0;
        exec("{$smiPath} --query-gpu=index,name,memory.total,memory.used,memory.free,temperature.gpu,utilization.gpu --format=csv,noheader,nounits 2>&1", $output, $exitCode);

        if ($exitCode !== 0 || empty($output)) {
            // 尝试通过 Ollama 获取 GPU 信息
            try {
                $response = Http::timeout(5)->get(config('local-llm.ollama.api_base') . '/api/gpu');
                if ($response->successful()) {
                    return [
                        'available' => true,
                        'source' => 'ollama',
                        'gpus' => $response->json(),
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }

            return ['available' => false, 'message' => __('app.common.nvidia_gpu_not_detected')];
        }

        $gpus = [];
        foreach ($output as $line) {
            $parts = str_getcsv($line);
            if (count($parts) >= 7) {
                $gpus[] = [
                    'index' => (int) trim($parts[0]),
                    'name' => trim($parts[1]),
                    'memory_total_mb' => (int) trim($parts[2]),
                    'memory_used_mb' => (int) trim($parts[3]),
                    'memory_free_mb' => (int) trim($parts[4]),
                    'temperature_c' => (int) trim($parts[5]),
                    'utilization_percent' => (int) trim($parts[6]),
                ];
            }
        }

        return [
            'available' => !empty($gpus),
            'source' => 'nvidia-smi',
            'gpus' => $gpus,
            'total_vram_mb' => array_sum(array_column($gpus, 'memory_total_mb')),
            'used_vram_mb' => array_sum(array_column($gpus, 'memory_used_mb')),
        ];
    }

    /**
     * 获取硬件信息
     */
    public function getHardwareInfo(): array
    {
        $info = [];

        // CPU
        $cpuInfo = [];
        if (PHP_OS_FAMILY === 'Linux') {
            $cpuInfo = @file_get_contents('/proc/cpuinfo');
            preg_match('/model name\s+:\s+(.+)/', $cpuInfo, $matches);
            $info['cpu'] = $matches[1] ?? 'unknown';
            $info['cpu_cores'] = (int) `nproc`;
        } else {
            $info['cpu'] = php_uname('m');
            $info['cpu_cores'] = 1;
        }

        // RAM
        if (PHP_OS_FAMILY === 'Linux') {
            $memInfo = @file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $matches);
            $info['ram_total_gb'] = isset($matches[1]) ? round((int)$matches[1] / 1024 / 1024, 1) : 0;
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $matches);
            $info['ram_available_gb'] = isset($matches[1]) ? round((int)$matches[1] / 1024 / 1024, 1) : 0;
        } else {
            $info['ram_total_gb'] = 0;
            $info['ram_available_gb'] = 0;
        }

        // Disk
        $diskPath = config('local-llm.models.download_dir');
        if (!is_dir($diskPath)) {
            $diskPath = storage_path();
        }
        $info['disk_total_gb'] = round(disk_total_space($diskPath) / 1024 / 1024 / 1024, 1);
        $info['disk_free_gb'] = round(disk_free_space($diskPath) / 1024 / 1024 / 1024, 1);

        // Check requirements
        $requirements = config('local-llm.hardware_requirements');
        $info['meets_minimum'] = (
            $info['ram_total_gb'] >= $requirements['minimum_ram_gb']
        );
        $info['recommended_hardware'] = $requirements;

        return $info;
    }

    /**
     * 下载模型（通过 Ollama）
     */
    public function pullModel(string $modelName): array
    {
        $apiBase = config('local-llm.ollama.api_base');

        try {
            $response = Http::timeout(3600)->post("{$apiBase}/api/pull", [
                'name' => $modelName,
                'stream' => false,
            ]);

            if ($response->successful()) {
                Log::info('LocalLLM: Model pulled', ['model' => $modelName]);
                return [
                    'success' => true,
                    'message' => "模型 {$modelName} 下载完成",
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => "下载失败: " . ($response->json('error') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "下载异常: " . $e->getMessage(),
            ];
        }
    }

    /**
     * 删除模型
     */
    public function deleteModel(string $modelName): array
    {
        $apiBase = config('local-llm.ollama.api_base');

        try {
            $response = Http::timeout(30)->delete("{$apiBase}/api/delete", [
                'name' => $modelName,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => __('app.common.model_deleted', ['model' => $modelName])];
            }

            return ['success' => false, 'message' => __('app.common.delete_failed', ['message' => $response->body()])];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => __('app.common.delete_exception', ['message' => $e->getMessage()])];
        }
    }

    /**
     * 获取部署指引
     */
    public function getDeploymentGuide(): array
    {
        return [
            'ollama' => [
                'title' => 'Ollama 部署',
                'description' => '最简单的本地大模型部署方案，支持 macOS/Linux/Windows',
                'steps' => [
                    ['step' => 1, 'action' => '启动 Ollama', 'command' => 'bash deploy/llm/setup.sh ollama'],
                    ['step' => 2, 'action' => '拉取推荐模型', 'command' => 'php artisan ollama:setup --pull'],
                    ['step' => 3, 'action' => '验证服务', 'command' => 'php artisan ollama:setup --status'],
                    ['step' => 4, 'action' => '配置 Provider', 'command' => '在管理后台 LLM Providers 中添加 Ollama'],
                ],
                'docker_compose' => true,
            ],
            'vllm' => [
                'title' => 'vLLM 部署 (GPU 推荐)',
                'description' => '高性能推理引擎，支持 PagedAttention/连续批处理',
                'steps' => [
                    ['step' => 1, 'action' => '部署 vLLM', 'command' => 'docker compose -f deploy/llm/docker-compose.vllm.yml up -d'],
                    ['step' => 2, 'action' => '验证服务', 'command' => 'curl http://localhost:8000/v1/models'],
                    ['step' => 3, 'action' => '配置 Provider', 'command' => '在管理后台 LLM Providers 中添加 vLLM'],
                ],
                'docker_compose' => true,
            ],
            'hardware_requirements' => config('local-llm.hardware_requirements'),
        ];
    }
}
