<?php

namespace Tests\Unit\Support;

use App\Support\PricingMatrixBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingMatrixBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_matrix_cells_from_plan_limits_and_metadata(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'limits' => [
                    'max_products' => 1,
                    'max_activations' => 100,
                    'api_rate_limit' => 60,
                    'max_api_keys' => 2,
                    'team_members' => 1,
                ],
                'metadata' => [
                    'comparison' => [
                        'live_chat' => true,
                        'trial_management' => '7',
                    ],
                ],
            ],
            [
                'slug' => 'pro',
                'limits' => [
                    'max_products' => 10,
                    'max_activations' => 10000,
                    'api_rate_limit' => 1000,
                    'max_api_keys' => 20,
                    'team_members' => 5,
                ],
                'metadata' => [
                    'comparison' => [
                        'rbac' => true,
                        'webhook_retry_filter' => 'retry_filter',
                        'data_export' => 'csv',
                        'trial_management' => '30',
                    ],
                ],
            ],
        ];

        app()->setLocale('zh_CN');
        $rows = app(PricingMatrixBuilder::class)->build($plans);

        $this->assertNotEmpty($rows);
        $this->assertSame('产品数量', $rows[0]['label']);
        $this->assertSame(['1 个', '10 个'], $rows[0]['cells']);

        $rbacRow = collect($rows)->firstWhere('label', 'RBAC 权限管理');
        $this->assertNotNull($rbacRow);
        $this->assertSame(['—', '✓'], $rbacRow['cells']);

        $exportRow = collect($rows)->firstWhere('label', '数据导出');
        $this->assertNotNull($exportRow);
        $this->assertSame(['—', 'CSV'], $exportRow['cells']);
    }

    #[Test]
    public function it_formats_unlimited_limits(): void
    {
        $plans = [[
            'slug' => 'enterprise',
            'limits' => [
                'max_products' => -1,
                'max_activations' => -1,
                'api_rate_limit' => 5000,
                'max_api_keys' => 100,
                'team_members' => -1,
            ],
            'metadata' => [
                'comparison' => [
                    'agent_groups' => -1,
                ],
            ],
        ]];

        app()->setLocale('en');
        $rows = app(PricingMatrixBuilder::class)->build($plans);

        $productsRow = collect($rows)->firstWhere('label', 'Products');
        $this->assertSame(['Unlimited'], $productsRow['cells']);

        $groupsRow = collect($rows)->firstWhere('label', 'Agent groups');
        $this->assertSame(['Unlimited'], $groupsRow['cells']);
    }
}
