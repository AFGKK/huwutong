<?php

namespace App\Services\CloudMarketplace;

class CloudMarketplaceServiceFactory
{
    /**
     * 创建云市场服务实例
     */
    public static function make(string $marketplace): BaseCloudMarketplaceService
    {
        return match ($marketplace) {
            'aws'   => app(AwsMarketplaceService::class),
            default => throw new \InvalidArgumentException("Unsupported marketplace: {$marketplace}"),
        };
    }

    /**
     * 获取所有已启用的云市场服务
     * @return array<string, BaseCloudMarketplaceService>
     */
    public static function allEnabled(): array
    {
        $services = [];
        $marketplaces = ['aws'];

        foreach ($marketplaces as $mp) {
            if (config("cloud-marketplace.{$mp}.enabled", false)) {
                $services[$mp] = self::make($mp);
            }
        }

        return $services;
    }

    /**
     * 获取支持的云市场列表
     * @return array<int, array{key: string, name: string, enabled: bool}>
     */
    public static function marketplaceList(): array
    {
        return [
            ['key' => 'aws',   'name' => 'AWS Marketplace',      'icon' => 'fab fa-aws',      'enabled' => config('cloud-marketplace.aws.enabled', false)],
        ];
    }
}
