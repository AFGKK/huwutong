<?php

namespace App\Providers;

use App\Contracts\CloudStorage;
use App\Events\LicenseStatusChanged;
use App\Listeners\LogLicenseStatusChanged;
use App\Services\CloudStorageService;
use App\Services\LlmService;
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
    }

    public function boot(): void
    {
        //
    }

    protected $listen = [
        LicenseStatusChanged::class => [
            LogLicenseStatusChanged::class,
        ],
    ];
}
