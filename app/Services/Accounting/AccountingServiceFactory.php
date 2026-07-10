<?php

namespace App\Services\Accounting;

use App\Models\AccountingIntegration;

class AccountingServiceFactory
{
    /**
     * 创建会计系统服务实例
     */
    public static function make(AccountingIntegration $integration): BaseAccountingService
    {
        return match ($integration->provider) {
            'quickbooks' => new QuickBooksService($integration),
            'xero'       => new XeroService($integration),
            'yonyou'     => new YonyouService($integration),
            'kingdee'    => new KingdeeService($integration),
            default => throw new \InvalidArgumentException("Unsupported accounting provider: {$integration->provider}"),
        };
    }

    /**
     * 获取所有启用的会计集成
     * @return \Illuminate\Support\Collection<int, BaseAccountingService>
     */
    public static function allActive()
    {
        return AccountingIntegration::active()->get()->map(fn($i) => self::make($i));
    }

    /**
     * 支持的会计系统列表
     */
    public static function providers(): array
    {
        return [
            ['key' => 'quickbooks', 'name' => 'QuickBooks Online',  'type' => 'international', 'icon' => '📘'],
            ['key' => 'xero',       'name' => 'Xero',               'type' => 'international', 'icon' => '📗'],
            ['key' => 'yonyou',     'name' => '用友 (U8+/畅捷通)',  'type' => 'china',         'icon' => '🇨🇳'],
            ['key' => 'kingdee',    'name' => '金蝶 (K/3+云星空)',   'type' => 'china',         'icon' => '🇨🇳'],
        ];
    }
}
