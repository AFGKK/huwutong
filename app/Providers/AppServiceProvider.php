<?php

namespace App\Providers;

use App\Contracts\CloudStorage;
use App\Contracts\PaymentGateway;
use App\Events\LicenseStatusChanged;
use App\Listeners\LogLicenseStatusChanged;
use App\Services\CloudStorageService;
use App\Services\LlmService;
use App\Services\MemoryService;
use App\Services\SensitiveWordService;
use App\Services\PromptFirewallService;
use App\Services\HallucinationDetector;
use App\Services\RagEngineService;
use App\Services\ContentSignatureService;
use App\Services\AiFriendOrchestrator;
use App\Services\Payment\AlipayPaymentGateway;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\StripePaymentGateway;
use App\Services\PaymentManager;
use App\Models\Log;
use App\Observers\LogObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmService::class, function () {
            $service = new LlmService();
            $service->registerAdapter('deepseek', \App\Services\Llm\DeepSeekAdapter::class);
            $service->registerAdapter('ollama', \App\Services\Llm\OllamaAdapter::class);
            $service->registerAdapter('vllm', \App\Services\Llm\VllmAdapter::class);
            return $service;
        });

        // 云存储统一适配层
        $this->app->singleton(CloudStorage::class, function () {
            return new CloudStorageService();
        });

        // 支付网关管理器（单例）
        $this->app->singleton(PaymentManager::class, function () {
            return new PaymentManager();
        });

        // 支付网关具体实现注册
        $this->app->bind(MockPaymentGateway::class, function () {
            return new MockPaymentGateway();
        });
        $this->app->bind(AlipayPaymentGateway::class, function () {
            return new AlipayPaymentGateway();
        });
        $this->app->bind(StripePaymentGateway::class, function () {
            return new StripePaymentGateway();
        });

        // AI 安全防火墙（单例，复用规则缓存）
        $this->app->singleton(PromptFirewallService::class, function ($app) {
            return new PromptFirewallService($app->make(SensitiveWordService::class));
        });

        // AI 幻觉检测器（单例）
        $this->app->singleton(HallucinationDetector::class, function ($app) {
            return new HallucinationDetector(
                $app->make(LlmService::class),
                $app->make(RagEngineService::class),
            );
        });

        // AI 好友编排器（单例，复用 LLM 连接池）
        $this->app->singleton(AiFriendOrchestrator::class, function ($app) {
            return new AiFriendOrchestrator(
                $app->make(LlmService::class),
                $app->make(MemoryService::class),
                $app->make(PromptFirewallService::class),
                $app->make(HallucinationDetector::class),
                $app->make(ContentSignatureService::class),
            );
        });
    }

    public function boot(): void
    {
        // 自动为审计日志计算 Merkle 哈希
        Log::observe(LogObserver::class);

        // M2-133: CDN 静态资源 Blade 指令
        Blade::directive('cdnAssets', function () {
            $cdnDomain = config('cloud-storage.cdn_domain');
            if (! $cdnDomain) {
                return "<?php echo '@vite'; ?>";
            }

            $version = \Illuminate\Support\Facades\Cache::get('static_asset:version', '1');

            return "<?php
                \$cdnBase = 'https://{$cdnDomain}/assets/{$version}';
                \$manifestPath = base_path('public/build/manifest.json');
                if (file_exists(\$manifestPath)) {
                    \$manifest = json_decode(file_get_contents(\$manifestPath), true);
                    foreach (\$manifest as \$entry) {
                        if (isset(\$entry['file'])) {
                            echo '<link rel=\"stylesheet\" href=\"' . \$cdnBase . '/' . \$entry['file'] . '\">';
                        }
                        if (isset(\$entry['css'])) {
                            foreach (\$entry['css'] as \$css) {
                                echo '<link rel=\"stylesheet\" href=\"' . \$cdnBase . '/' . \$css . '\">';
                            }
                        }
                    }
                }
            ?>";
        });
    }

    protected $listen = [
        LicenseStatusChanged::class => [
            LogLicenseStatusChanged::class,
        ],
    ];
}
