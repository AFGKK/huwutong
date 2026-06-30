<?php

namespace App\Services;

use App\Models\RegionalComplianceConfig;
use App\Models\RegionalSalesRestriction;
use App\Models\RegionalComplianceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M3-18 多区域合规服务
 *
 * 统一管理区域合规配置、税务要求、销售限制
 * 整合: GDPR/PIPL/VAT/数据本地化/Cookie同意/区域销售限制
 */
class RegionalComplianceService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $configs = RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $byRegion = [];
        $totalRequirements = 0;
        $metRequirements = 0;

        foreach ($configs as $config) {
            $requirements = $config->getActiveRequirements();
            $totalRequirements += count($requirements);

            // 检查各合规领域的完成状态
            $checks = $this->checkComplianceStatus($tenantId, $config->region_key);
            $metRequirements += $checks['met_count'];

            $byRegion[$config->region_key] = [
                'config' => $config->toArray(),
                'requirements' => $requirements,
                'checks' => $checks,
            ];
        }

        $activeRestrictions = RegionalSalesRestriction::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $recentLogs = RegionalComplianceLog::where('tenant_id', $tenantId)
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->toArray();

        $stats = [
            'total_regions' => $configs->count(),
            'total_requirements' => $totalRequirements,
            'met_requirements' => $metRequirements,
            'compliance_percentage' => $totalRequirements > 0
                ? round($metRequirements / $totalRequirements * 100, 1)
                : 100,
            'active_restrictions' => $activeRestrictions,
            'recent_logs' => $recentLogs,
        ];

        return compact('byRegion', 'stats', 'recentLogs');
    }

    /**
     * 检查指定区域的合规状态
     */
    public function checkComplianceStatus(int $tenantId, string $regionKey): array
    {
        $config = RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->where('region_key', $regionKey)
            ->first();

        if (!$config) {
            return ['met_count' => 0, 'details' => []];
        }

        $checks = [];
        $metCount = 0;

        // GDPR 检查
        if ($config->gdpr_enabled) {
            $gdprReady = $this->checkGdprReadiness($tenantId);
            $checks['gdpr'] = ['label' => 'GDPR', 'status' => $gdprReady ? 'met' : 'unmet'];
            if ($gdprReady) $metCount++;
        }

        // PIPL 检查
        if ($config->pipl_enabled) {
            $piplReady = $this->checkPiplReadiness($tenantId);
            $checks['pipl'] = ['label' => 'PIPL', 'status' => $piplReady ? 'met' : 'unmet'];
            if ($piplReady) $metCount++;
        }

        // VAT/税务检查
        if ($config->vat_enabled || $config->tax_reporting_enabled) {
            $taxReady = $this->checkTaxReadiness($tenantId, $regionKey);
            $checks['tax'] = ['label' => '税务合规', 'status' => $taxReady ? 'met' : 'unmet'];
            if ($taxReady) $metCount++;
        }

        // 数据本地化检查
        if ($config->data_residency_enabled) {
            $residencyReady = $this->checkDataResidency($tenantId);
            $checks['data_residency'] = ['label' => '数据本地化', 'status' => $residencyReady ? 'met' : 'unmet'];
            if ($residencyReady) $metCount++;
        }

        // Cookie 同意检查
        if ($config->cookie_consent_enabled) {
            $cookieReady = $this->checkCookieConsent($tenantId);
            $checks['cookie_consent'] = ['label' => 'Cookie 同意', 'status' => $cookieReady ? 'met' : 'unmet'];
            if ($cookieReady) $metCount++;
        }

        return [
            'met_count' => $metCount,
            'total_count' => count($checks),
            'details' => $checks,
        ];
    }

    /**
     * 检查 GDPR 就绪状态
     */
    protected function checkGdprReadiness(int $tenantId): bool
    {
        $hasDpa = \App\Models\DataProcessingAgreement::where('tenant_id', $tenantId)
            ->where('is_active', true)->exists();
        $hasDsrProcess = \App\Models\GdprDataRequest::where('tenant_id', $tenantId)->exists();
        $hasConsent = \App\Models\CookieConsentLog::where('tenant_id', $tenantId)->exists();

        return $hasDpa || ($hasDsrProcess && $hasConsent);
    }

    /**
     * 检查 PIPL 就绪状态
     */
    protected function checkPiplReadiness(int $tenantId): bool
    {
        $hasInventory = \App\Models\PersonalDataInventory::where('tenant_id', $tenantId)->exists();
        $hasDpia = \App\Models\Dpia::where('tenant_id', $tenantId)->exists();

        return $hasInventory && $hasDpia;
    }

    /**
     * 检查税务合规就绪状态
     */
    protected function checkTaxReadiness(int $tenantId, string $regionKey): bool
    {
        $hasRates = \App\Models\TaxRate::where('tenant_id', $tenantId)->exists();
        $hasReports = \App\Models\TaxComplianceReport::where('tenant_id', $tenantId)->exists();

        return $hasRates || $hasReports;
    }

    /**
     * 检查数据本地化配置
     */
    protected function checkDataResidency(int $tenantId): bool
    {
        return \App\Models\DataResidencyRecord::where('tenant_id', $tenantId)->exists();
    }

    /**
     * 检查 Cookie 同意配置
     */
    protected function checkCookieConsent(int $tenantId): bool
    {
        return \App\Models\CookieConsentConfig::where('tenant_id', $tenantId)
            ->where('is_active', true)->exists();
    }

    /**
     * 初始化租户的区域合规配置
     */
    public function initializeTenant(int $tenantId): void
    {
        $regions = config('compliance-regional.regions', []);

        foreach ($regions as $key => $region) {
            RegionalComplianceConfig::firstOrCreate(
                ['tenant_id' => $tenantId, 'region_key' => $key],
                [
                    'region_name' => $region['name'],
                    'gdpr_enabled' => $region['compliance']['gdpr'] ?? false,
                    'pipl_enabled' => $region['compliance']['pipl'] ?? false,
                    'vat_enabled' => $region['compliance']['vat'] ?? false,
                    'data_residency_enabled' => $region['compliance']['data_residency'] ?? false,
                    'cookie_consent_enabled' => $region['compliance']['cookie_consent'] ?? true,
                    'tax_reporting_enabled' => $region['compliance']['tax_reporting'] ?? true,
                    'tax_type' => $region['tax']['type'] ?? null,
                    'tax_rate' => $region['tax']['rate'] ?? 0,
                    'tax_reporting_frequency' => $region['tax']['reporting_frequency'] ?? 'quarterly',
                    'digital_service_tax' => $region['tax']['digital_service_tax'] ?? false,
                    'oss_enabled' => $region['tax']['oss_enabled'] ?? false,
                    'oss_threshold' => $region['tax']['oss_threshold'] ?? null,
                    'currency' => $region['currency'] ?? null,
                    'timezone' => $region['timezone'] ?? null,
                    'languages' => $region['languages'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * 更新区域合规配置
     */
    public function updateConfig(int $tenantId, string $regionKey, array $data): RegionalComplianceConfig
    {
        $config = RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->where('region_key', $regionKey)
            ->firstOrFail();

        $config->update($data);

        $this->logAction($tenantId, $regionKey, 'config_updated', 'success', '合规配置已更新');

        return $config->fresh();
    }

    /**
     * 列出所有区域合规配置
     */
    public function listConfigs(int $tenantId): array
    {
        return RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->orderBy('region_key')
            ->get()
            ->toArray();
    }

    /**
     * 获取区域销售限制列表
     */
    public function listRestrictions(int $tenantId, ?string $regionKey = null): array
    {
        $query = RegionalSalesRestriction::where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($regionKey) {
            $query->where('region_key', $regionKey);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * 添加销售限制
     */
    public function addRestriction(int $tenantId, array $data): RegionalSalesRestriction
    {
        $restriction = RegionalSalesRestriction::create(array_merge(
            $data,
            ['tenant_id' => $tenantId]
        ));

        $this->logAction(
            $tenantId,
            $data['region_key'] ?? 'unknown',
            $data['is_allowed'] ? 'sales_unblocked' : 'sales_blocked',
            'success',
            $data['reason'] ?? '销售限制已添加'
        );

        return $restriction->fresh();
    }

    /**
     * 删除销售限制
     */
    public function removeRestriction(int $id, int $tenantId): bool
    {
        $restriction = RegionalSalesRestriction::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $regionKey = $restriction->region_key;
        $restriction->update(['is_active' => false]);

        $this->logAction($tenantId, $regionKey, 'sales_unblocked', 'success', '销售限制已移除');

        return true;
    }

    /**
     * 检查产品在指定区域是否可销售
     */
    public function checkProductSalesEligibility(int $tenantId, int $productId, string $regionKey): array
    {
        $config = RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->where('region_key', $regionKey)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return ['eligible' => false, 'reason' => "区域 {$regionKey} 未启用"];
        }

        // 检查是否有明确限制
        $restriction = RegionalSalesRestriction::where('tenant_id', $tenantId)
            ->where('region_key', $regionKey)
            ->where('restrictable_type', 'product')
            ->where('restrictable_id', $productId)
            ->where('is_active', true)
            ->first();

        if ($restriction && $restriction->isEffective()) {
            return [
                'eligible' => $restriction->is_allowed,
                'reason' => $restriction->reason,
                'restriction' => $restriction->toArray(),
            ];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * 生成合规报告摘要
     */
    public function generateComplianceSummary(int $tenantId): array
    {
        $configs = RegionalComplianceConfig::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $summary = [];
        foreach ($configs as $config) {
            $checks = $this->checkComplianceStatus($tenantId, $config->region_key);
            $summary[] = [
                'region' => $config->region_key,
                'region_name' => $config->region_name ?? $config->region_key,
                'compliance_score' => $checks['total_count'] > 0
                    ? round($checks['met_count'] / $checks['total_count'] * 100, 1)
                    : 100,
                'requirements' => $config->getActiveRequirements(),
                'checks' => $checks['details'],
            ];
        }

        // 记录日志
        $this->logAction($tenantId, 'all', 'report_generated', 'success', '合规报告摘要已生成');

        return $summary;
    }

    /**
     * 记录合规操作日志
     */
    protected function logAction(int $tenantId, string $regionKey, string $action, string $status, ?string $description = null): void
    {
        try {
            RegionalComplianceLog::create([
                'tenant_id' => $tenantId,
                'region_key' => $regionKey,
                'action_type' => $action,
                'status' => $status,
                'description' => $description,
                'performed_by' => request()->user()?->email ?? 'system',
                'occurred_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log compliance action', [
                'tenant_id' => $tenantId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取合规操作日志
     */
    public function getLogs(int $tenantId, array $filters = []): array
    {
        $query = RegionalComplianceLog::where('tenant_id', $tenantId);

        if (!empty($filters['region_key'])) {
            $query->where('region_key', $filters['region_key']);
        }
        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to']);
        }

        return $query->latest('occurred_at')
            ->paginate($filters['per_page'] ?? 20)
            ->toArray();
    }

    /**
     * 清理过期日志
     */
    public function pruneLogs(int $tenantId, int $retentionDays = 365): int
    {
        return RegionalComplianceLog::where('tenant_id', $tenantId)
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
