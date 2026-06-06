<?php

namespace App\Providers;

use App\Contracts\CloudStorage;
use App\Contracts\PaymentGateway;
use App\Events\LicenseStatusChanged;
use App\Listeners\LogLicenseStatusChanged;
use App\Services\CloudStorageService;
use App\Services\LlmService;
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
    }

    public function boot(): void
    {
        // 自动为审计日志计算 Merkle 哈希
        Log::observe(LogObserver::class);
    }

    protected $listen = [
        LicenseStatusChanged::class => [
            LogLicenseStatusChanged::class,
        ],
    ];
}
