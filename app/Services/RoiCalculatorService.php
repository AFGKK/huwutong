<?php

namespace App\Services;

class RoiCalculatorService
{
    // 自建方案成本估算参数（每年）
    const BUILD_COST_PARAMS = [
        'developer_salary' => [
            'label' => '开发人员年薪',
            'default_cny' => 300000,
            'default_usd' => 120000,
            'description' => '包含社保等综合成本',
        ],
        'developer_count' => [
            'label' => '所需开发人数',
            'default' => 2,
            'min' => 1,
            'max' => 20,
        ],
        'devops_cost' => [
            'label' => '运维/DevOps年成本',
            'default_cny' => 50000,
            'default_usd' => 12000,
            'description' => '服务器、域名、CDN等',
        ],
        'infrastructure_cost' => [
            'label' => '基础设施年费用',
            'default_cny' => 80000,
            'default_usd' => 24000,
            'description' => '云服务器、数据库、带宽',
        ],
        'maintenance_yearly' => [
            'label' => '年维护成本（%开发成本）',
            'default' => 20,
            'min' => 5,
            'max' => 50,
            'description' => 'Bug修复、更新迭代',
        ],
        'compliance_cost' => [
            'label' => '合规/安全年费用',
            'default_cny' => 30000,
            'default_usd' => 8000,
            'description' => 'GDPR、SOC2等合规认证',
        ],
        'opportunity_cost' => [
            'label' => '机会成本（月延迟损失）',
            'default_cny' => 60000,
            'default_usd' => 15000,
            'description' => '上线延迟导致的潜在收入损失',
        ],
        'development_months' => [
            'label' => '开发周期（月）',
            'default' => 6,
            'min' => 1,
            'max' => 36,
        ],
    ];

    // 本平台方案参数
    const PLATFORM_COST_PARAMS = [
        'license_fee' => [
            'label' => 'License年费（每Seat）',
            'default_cny' => 1200,
            'default_usd' => 199,
        ],
        'seat_count' => [
            'label' => 'Seat数量',
            'default' => 50,
            'min' => 1,
            'max' => 10000,
        ],
        'support_fee' => [
            'label' => '技术支持年费',
            'default_cny' => 20000,
            'default_usd' => 5000,
        ],
        'setup_fee' => [
            'label' => '一次性部署费用',
            'default_cny' => 10000,
            'default_usd' => 299,
        ],
    ];

    /**
     * 计算ROI
     */
    public function calculate(array $params, string $currency = 'CNY'): array
    {
        $isCny = $currency === 'CNY';

        // ─── 自建成本 ───
        $devSalary = $params['developer_salary'] ?? ($isCny ? self::BUILD_COST_PARAMS['developer_salary']['default_cny'] : self::BUILD_COST_PARAMS['developer_salary']['default_usd']);
        $devCount = max(1, (int)($params['developer_count'] ?? self::BUILD_COST_PARAMS['developer_count']['default']));
        $devopsCost = $params['devops_cost'] ?? ($isCny ? self::BUILD_COST_PARAMS['devops_cost']['default_cny'] : self::BUILD_COST_PARAMS['devops_cost']['default_usd']);
        $infraCost = $params['infrastructure_cost'] ?? ($isCny ? self::BUILD_COST_PARAMS['infrastructure_cost']['default_cny'] : self::BUILD_COST_PARAMS['infrastructure_cost']['default_usd']);
        $maintenancePct = max(5, min(50, (float)($params['maintenance_yearly'] ?? self::BUILD_COST_PARAMS['maintenance_yearly']['default']))) / 100;
        $complianceCost = $params['compliance_cost'] ?? ($isCny ? self::BUILD_COST_PARAMS['compliance_cost']['default_cny'] : self::BUILD_COST_PARAMS['compliance_cost']['default_usd']);
        $oppCostMonthly = $params['opportunity_cost'] ?? ($isCny ? self::BUILD_COST_PARAMS['opportunity_cost']['default_cny'] : self::BUILD_COST_PARAMS['opportunity_cost']['default_usd']);
        $devMonths = max(1, min(36, (int)($params['development_months'] ?? self::BUILD_COST_PARAMS['development_months']['default'])));

        // 一次性开发成本（包含开发周期内的机会成本）
        $yearlyDevCost = $devSalary * $devCount;
        $devCostOneTime = ($yearlyDevCost * $devMonths / 12) + ($devopsCost * $devMonths / 12);
        $opportunityCost = $oppCostMonthly * $devMonths;
        $totalBuildYear1 = $devCostOneTime + $infraCost + $complianceCost + $opportunityCost;

        // 年维护成本
        $yearlyMaintenance = $yearlyDevCost * $maintenancePct;
        $totalBuildYearly = $yearlyMaintenance + $infraCost + $devopsCost + $complianceCost;

        // ─── 本平台成本 ───
        $licenseFee = $params['license_fee'] ?? ($isCny ? self::PLATFORM_COST_PARAMS['license_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['license_fee']['default_usd']);
        $seatCount = max(1, (int)($params['seat_count'] ?? self::PLATFORM_COST_PARAMS['seat_count']['default']));
        $supportFee = $params['support_fee'] ?? ($isCny ? self::PLATFORM_COST_PARAMS['support_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['support_fee']['default_usd']);
        $setupFee = $params['setup_fee'] ?? ($isCny ? self::PLATFORM_COST_PARAMS['setup_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['setup_fee']['default_usd']);

        $platformYear1 = $licenseFee * $seatCount + $supportFee + $setupFee;
        $platformYearly = $licenseFee * $seatCount + $supportFee;

        // ─── 计算 ───
        $savingsYear1 = $totalBuildYear1 - $platformYear1;
        $savingsYearly = $totalBuildYearly - $platformYearly;
        $fiveYearBuild = $totalBuildYear1 + $totalBuildYearly * 4;
        $fiveYearPlatform = $platformYear1 + $platformYearly * 4;
        $fiveYearSaving = $fiveYearBuild - $fiveYearPlatform;

        // ROI百分比 = (节省 / 平台成本) * 100
        $roiYear1 = $platformYear1 > 0 ? round(($savingsYear1 / $platformYear1) * 100, 1) : 0;
        $roiYearly = $platformYearly > 0 ? round(($savingsYearly / $platformYearly) * 100, 1) : 0;
        $roi5Year = $fiveYearPlatform > 0 ? round(($fiveYearSaving / $fiveYearPlatform) * 100, 1) : 0;

        // 回本周期（月）= 一次性成本 / 每月节省
        $monthlySaving = $savingsYearly / 12;
        $monthsToBreakEven = $monthlySaving > 0
            ? ceil(($setupFee + ($devMonths > 0 ? $devCostOneTime * ($devMonths / 12) : 0)) / max($monthlySaving, 1))
            : 999;

        // 逐年对比（5年）
        $yearlyComparison = [];
        for ($y = 1; $y <= 5; $y++) {
            $buildCost = $y === 1 ? $totalBuildYear1 : $totalBuildYearly;
            $platCost = $y === 1 ? $platformYear1 : $platformYearly;
            $cumBuild = 0;
            $cumPlatform = 0;
            for ($py = 1; $py <= $y; $py++) {
                $cumBuild += $py === 1 ? $totalBuildYear1 : $totalBuildYearly;
                $cumPlatform += $py === 1 ? $platformYear1 : $platformYearly;
            }
            $yearlyComparison[] = [
                'year' => $y,
                'build_cost' => round($buildCost, 2),
                'platform_cost' => round($platCost, 2),
                'cumulative_build' => round($cumBuild, 2),
                'cumulative_platform' => round($cumPlatform, 2),
                'savings' => round($buildCost - $platCost, 2),
            ];
        }

        return [
            'currency' => $currency,
            'currency_symbol' => $isCny ? '¥' : '$',

            // 自建成本
            'build' => [
                'year1' => round($totalBuildYear1, 2),
                'yearly' => round($totalBuildYearly, 2),
                'breakdown' => [
                    'development' => round($devCostOneTime, 2),
                    'infrastructure' => round($infraCost, 2),
                    'devops' => round($devopsCost, 2),
                    'compliance' => round($complianceCost, 2),
                    'maintenance' => round($yearlyMaintenance, 2),
                    'opportunity_cost' => round($opportunityCost, 2),
                ],
            ],

            // 本平台成本
            'platform' => [
                'year1' => round($platformYear1, 2),
                'yearly' => round($platformYearly, 2),
                'breakdown' => [
                    'license_fee' => round($licenseFee * $seatCount, 2),
                    'support_fee' => round($supportFee, 2),
                    'setup_fee' => round($setupFee, 2),
                ],
            ],

            // ROI指标
            'roi' => [
                'year1' => $roiYear1,
                'yearly' => $roiYearly,
                'five_year' => $roi5Year,
            ],

            // 节省
            'savings' => [
                'year1' => round($savingsYear1, 2),
                'yearly' => round($savingsYearly, 2),
                'five_year' => round($fiveYearSaving, 2),
                'break_even_months' => $monthsToBreakEven,
                'break_even_label' => $monthsToBreakEven >= 999 ? '超过5年' : "{$monthsToBreakEven}个月",
            ],

            // 逐年对比
            'yearly_comparison' => $yearlyComparison,

            // 输入参数
            'params' => [
                'developer_salary' => $devSalary,
                'developer_count' => $devCount,
                'devops_cost' => $devopsCost,
                'infrastructure_cost' => $infraCost,
                'maintenance_pct' => $maintenancePct * 100,
                'compliance_cost' => $complianceCost,
                'opportunity_cost' => $oppCostMonthly,
                'development_months' => $devMonths,
                'license_fee' => $licenseFee,
                'seat_count' => $seatCount,
                'support_fee' => $supportFee,
                'setup_fee' => $setupFee,
            ],
        ];
    }

    /**
     * 获取默认参数
     */
    public function getDefaults(string $currency = 'CNY'): array
    {
        $isCny = $currency === 'CNY';

        return [
            'developer_salary' => $isCny ? self::BUILD_COST_PARAMS['developer_salary']['default_cny'] : self::BUILD_COST_PARAMS['developer_salary']['default_usd'],
            'developer_count' => self::BUILD_COST_PARAMS['developer_count']['default'],
            'devops_cost' => $isCny ? self::BUILD_COST_PARAMS['devops_cost']['default_cny'] : self::BUILD_COST_PARAMS['devops_cost']['default_usd'],
            'infrastructure_cost' => $isCny ? self::BUILD_COST_PARAMS['infrastructure_cost']['default_cny'] : self::BUILD_COST_PARAMS['infrastructure_cost']['default_usd'],
            'maintenance_yearly' => self::BUILD_COST_PARAMS['maintenance_yearly']['default'],
            'compliance_cost' => $isCny ? self::BUILD_COST_PARAMS['compliance_cost']['default_cny'] : self::BUILD_COST_PARAMS['compliance_cost']['default_usd'],
            'opportunity_cost' => $isCny ? self::BUILD_COST_PARAMS['opportunity_cost']['default_cny'] : self::BUILD_COST_PARAMS['opportunity_cost']['default_usd'],
            'development_months' => self::BUILD_COST_PARAMS['development_months']['default'],
            'license_fee' => $isCny ? self::PLATFORM_COST_PARAMS['license_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['license_fee']['default_usd'],
            'seat_count' => self::PLATFORM_COST_PARAMS['seat_count']['default'],
            'support_fee' => $isCny ? self::PLATFORM_COST_PARAMS['support_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['support_fee']['default_usd'],
            'setup_fee' => $isCny ? self::PLATFORM_COST_PARAMS['setup_fee']['default_cny'] : self::PLATFORM_COST_PARAMS['setup_fee']['default_usd'],
        ];
    }

    /**
     * 获取参数定义
     */
    public function getParamDefinitions(): array
    {
        return [
            'build' => self::BUILD_COST_PARAMS,
            'platform' => self::PLATFORM_COST_PARAMS,
        ];
    }
}
