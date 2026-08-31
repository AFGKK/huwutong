<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseDomainWhitelist;
use App\Models\LicenseDomainValidationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 域名白名单验证服务 (M2-71)
 *
 * License 绑定域名 → 激活/验证时校验请求来源域名
 * 支持精确域名 + 通配符 (*.example.com)
 */
class DomainWhitelistService
{
    /**
     * 获取 License 的白名单列表
     */
    public function getWhitelist(int $licenseId): array
    {
        return LicenseDomainWhitelist::where('license_id', $licenseId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 添加白名单域名
     */
    public function addDomain(int $licenseId, string $domain, array $options = [], ?int $userId = null): LicenseDomainWhitelist
    {
        $domain = strtolower(trim($domain));
        $isWildcard = str_starts_with($domain, '*.');

        // 检查重复
        $existing = LicenseDomainWhitelist::where('license_id', $licenseId)
            ->where('domain', $domain)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update([
                    'status' => $options['status'] ?? 'active',
                    'scope' => $options['scope'] ?? config('domain-whitelist.default_scope', 'both'),
                    'notes' => $options['notes'] ?? null,
                    'created_by' => $userId,
                ]);
                return $existing->fresh();
            }
            throw new \RuntimeException(__("app.domain_whitelist.msg_029ad2e3"));
        }

        // 检查数量上限
        $count = LicenseDomainWhitelist::where('license_id', $licenseId)->count();
        $max = config('domain-whitelist.max_domains_per_license', 20);
        if ($count >= $max) {
            throw new \RuntimeException(__("app.domain_whitelist.msg_dd18d238"));
        }

        // 是否需要审批
        $requireApproval = config('domain-whitelist.approval.require_for_add', true);
        $isAdmin = $options['is_admin'] ?? false;
        $autoApprove = $requireApproval && $isAdmin && config('domain-whitelist.approval.auto_approve_admin', true);

        $status = $autoApprove ? 'active' : ($requireApproval ? 'pending' : 'active');

        $record = LicenseDomainWhitelist::create([
            'license_id' => $licenseId,
            'domain' => $domain,
            'is_wildcard' => $isWildcard,
            'scope' => $options['scope'] ?? config('domain-whitelist.default_scope', 'both'),
            'status' => $status,
            'notes' => $options['notes'] ?? null,
            'created_by' => $userId,
            'approved_by' => $autoApprove ? $userId : null,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        return $record->fresh();
    }

    /**
     * 批量添加域名
     */
    public function batchAddDomains(int $licenseId, array $domains, array $options = [], ?int $userId = null): array
    {
        $results = [];
        foreach ($domains as $domain) {
            try {
                $record = $this->addDomain($licenseId, $domain, $options, $userId);
                $results[] = ['domain' => $domain, 'success' => true, 'id' => $record->id, 'status' => $record->status];
            } catch (\Exception $e) {
                $results[] = ['domain' => $domain, 'success' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * 删除白名单域名
     */
    public function removeDomain(int $id, ?int $userId = null): bool
    {
        $record = LicenseDomainWhitelist::findOrFail($id);

        $requireApproval = config('domain-whitelist.approval.require_for_remove', false);
        $isAdmin = request()->input('is_admin', false);

        if ($requireApproval && !$isAdmin) {
            $record->update(['status' => 'pending_remove']);
            return true;
        }

        return $record->delete() ? true : false;
    }

    /**
     * 审批白名单域名
     */
    public function approveDomain(int $id, ?int $adminId = null): LicenseDomainWhitelist
    {
        $record = LicenseDomainWhitelist::findOrFail($id);

        if ($record->status === 'pending_remove') {
            $record->delete();
            $record->status = 'removed';
            return $record;
        }

        $record->update([
            'status' => 'active',
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);

        return $record->fresh();
    }

    /**
     * 拒绝审批
     */
    public function rejectDomain(int $id, ?int $adminId = null): LicenseDomainWhitelist
    {
        $record = LicenseDomainWhitelist::findOrFail($id);
        $record->update(['status' => 'rejected', 'approved_by' => $adminId]);
        return $record->fresh();
    }

    /**
     * 验证域名是否在白名单中 (核心方法)
     *
     * @param int $licenseId License ID
     * @param string $requestDomain 请求来源域名
     * @param string $scope 验证范围 (activation/validation)
     * @return array{passed: bool, reason: ?string, matched: ?string}
     */
    public function verify(int $licenseId, string $requestDomain, string $scope = 'validation'): array
    {
        if (!config('domain-whitelist.enabled', true)) {
            return ['passed' => true, 'reason' => '域名白名单未启用', 'matched' => null];
        }

        $requestDomain = strtolower(trim($requestDomain));

        $whitelists = LicenseDomainWhitelist::active()
            ->byScope($scope)
            ->where('license_id', $licenseId)
            ->get();

        // 如果没有配置白名单，默认通过
        if ($whitelists->isEmpty()) {
            return ['passed' => true, 'reason' => '未配置域名白名单', 'matched' => null];
        }

        foreach ($whitelists as $entry) {
            if ($this->domainMatches($requestDomain, $entry->domain, $entry->is_wildcard)) {
                $this->logValidation($licenseId, $requestDomain, 'passed', "匹配域名: {$entry->domain}");
                return ['passed' => true, 'reason' => '域名匹配通过', 'matched' => $entry->domain];
            }
        }

        // 未匹配到任何白名单
        $onFailure = config('domain-whitelist.on_failure', 'block');
        $reason = "域名 {$requestDomain} 不在白名单中";

        if ($onFailure === 'warn') {
            $this->logValidation($licenseId, $requestDomain, 'passed', $reason . ' (仅警告)');
            return ['passed' => true, 'reason' => $reason . ' (仅警告)', 'matched' => null];
        }

        $this->logValidation($licenseId, $requestDomain, 'blocked', $reason);
        return ['passed' => false, 'reason' => $reason, 'matched' => null];
    }

    /**
     * 域名匹配检查
     */
    protected function domainMatches(string $requestDomain, string $whitelistDomain, bool $isWildcard): bool
    {
        if ($isWildcard) {
            // *.example.com → 匹配 sub.example.com, any.sub.example.com
            $suffix = substr($whitelistDomain, 1); // .example.com
            return str_ends_with($requestDomain, $suffix);
        }

        // 精确匹配
        return $requestDomain === $whitelistDomain;
    }

    /**
     * 记录验证日志
     */
    protected function logValidation(int $licenseId, string $domain, string $result, string $reason): void
    {
        try {
            LicenseDomainValidationLog::create([
                'license_id' => $licenseId,
                'domain' => $domain,
                'result' => $result,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log domain validation: ' . $e->getMessage());
        }
    }

    /**
     * 获取验证日志
     */
    public function getLogs(int $licenseId, ?string $result = null, int $limit = 50): array
    {
        $query = LicenseDomainValidationLog::where('license_id', $licenseId);
        if ($result) {
            $query->where('result', $result);
        }
        return $query->orderBy('created_at', 'desc')->limit($limit)->get()->toArray();
    }

    /**
     * 获取 License 的白名单统计
     */
    public function getStats(int $licenseId): array
    {
        $total = LicenseDomainWhitelist::where('license_id', $licenseId)->count();
        $active = LicenseDomainWhitelist::where('license_id', $licenseId)->active()->count();
        $pending = LicenseDomainWhitelist::where('license_id', $licenseId)->pending()->count();
        $wildcard = LicenseDomainWhitelist::where('license_id', $licenseId)->active()->where('is_wildcard', true)->count();

        $recentLogs = LicenseDomainValidationLog::where('license_id', $licenseId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("result, COUNT(*) as count")
            ->groupBy('result')
            ->pluck('count', 'result')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'pending' => $pending,
            'wildcard' => $wildcard,
            'recent_passed' => $recentLogs['passed'] ?? 0,
            'recent_blocked' => $recentLogs['blocked'] ?? 0,
            'max_domains' => config('domain-whitelist.max_domains_per_license', 20),
        ];
    }

    /**
     * 获取所有待审批项
     */
    public function getPendingApprovals(): array
    {
        return LicenseDomainWhitelist::with('license')
            ->whereIn('status', ['pending', 'pending_remove'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
