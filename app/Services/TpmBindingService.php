<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\TpmBinding;
use App\Models\TpmVerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TPM/安全芯片硬件安全绑定服务 (M2-116)
 *
 * 提供 TPM 2.0/SGX 安全芯片级别的设备指纹、硬件认证链验证。
 */
class TpmBindingService
{
    /**
     * 看板总览
     */
    public function dashboard(): array
    {
        return [
            'total_bindings' => TpmBinding::count(),
            'active_bindings' => TpmBinding::where('status', 'active')->count(),
            'revoked_bindings' => TpmBinding::where('status', 'revoked')->count(),
            'locked_bindings' => TpmBinding::where('status', 'locked')->count(),
            'tpm2_bindings' => TpmBinding::where('binding_type', 'tpm2')->count(),
            'sgx_bindings' => TpmBinding::where('binding_type', 'sgx')->count(),
            'hybrid_bindings' => TpmBinding::where('binding_type', 'hybrid')->count(),
            'today_verifications' => TpmVerificationLog::where('verified_at', '>=', now()->startOfDay())->count(),
            'failed_today' => TpmVerificationLog::where('verified_at', '>=', now()->startOfDay())
                ->where('result', 'failed')->count(),
            'tpm_available_devices' => Device::where('tpm_available', true)->count(),
            'hardware_bound_devices' => Device::where('hardware_bound', '!=', 'none')->count(),
        ];
    }

    /**
     * TPM 绑定列表
     */
    public function listBindings(Request $request): array
    {
        $query = TpmBinding::with(['license:id,license_key,name', 'device:id,fingerprint'])
            ->withCount('verificationLogs');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('binding_type')) $query->where('binding_type', $request->binding_type);
        if ($request->filled('license_id')) $query->where('license_id', $request->license_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('tpm_manufacturer', 'like', "%{$s}%")
                  ->orWhere('ak_name', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $items = $query->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * 注册 TPM 绑定
     *
     * SDK 端在激活时调用，提交 TPM 认证信息。
     */
    public function registerBinding(array $data): TpmBinding
    {
        $license = License::findOrFail($data['license_id']);

        // 检查绑定数量限制
        $maxBindings = config('tpm.binding.max_bindings_per_license', 3);
        $activeCount = TpmBinding::where('license_id', $license->id)
            ->where('status', 'active')->count();
        if ($activeCount >= $maxBindings) {
            throw new \RuntimeException("已达到最大绑定数限制({$maxBindings})");
        }

        // 验证 EK 证书（生产环境应验证证书链）
        if (config('tpm.tpm.require_ek_cert', true) && empty($data['ek_certificate'])) {
            throw new \RuntimeException('缺少 Endorsement Key 证书');
        }

        // 验证 AK
        if (config('tpm.tpm.require_ak', true) && empty($data['ak_public_key'])) {
            throw new \RuntimeException('缺少 Attestation Key');
        }

        $binding = TpmBinding::create([
            'license_id' => $license->id,
            'device_id' => $data['device_id'] ?? null,
            'tpm_manufacturer' => $data['tpm_manufacturer'] ?? null,
            'tpm_version' => $data['tpm_version'] ?? '2.0',
            'ek_public_key' => $data['ek_public_key'] ?? null,
            'ek_certificate' => $data['ek_certificate'] ?? null,
            'ak_public_key' => $data['ak_public_key'] ?? null,
            'ak_name' => $data['ak_name'] ?? null,
            'pcr_values' => $data['pcr_values'] ?? null,
            'binding_type' => $data['binding_type'] ?? 'tpm2',
            'status' => 'active',
            'metadata' => $data['metadata'] ?? null,
            'bound_ip' => request()->ip(),
            'bound_user_agent' => request()->userAgent(),
            'bound_at' => now(),
        ]);

        // 更新设备 TPM 状态
        if ($data['device_id'] ?? null) {
            Device::where('id', $data['device_id'])->update([
                'tpm_available' => true,
                'tpm_manufacturer' => $data['tpm_manufacturer'] ?? null,
                'tpm_spec_version' => $data['tpm_version'] ?? '2.0',
                'hardware_bound' => $data['binding_type'] ?? 'tpm',
                'hardware_bound_at' => now(),
            ]);
        }

        return $binding->fresh()->load('license:id,license_key,name');
    }

    /**
     * 验证 TPM 绑定
     *
     * 验证 TPM Quote 和硬件状态。
     */
    public function verifyBinding(int $id, array $quoteData): array
    {
        $binding = TpmBinding::findOrFail($id);

        if ($binding->status !== 'active') {
            throw new \RuntimeException("绑定状态不可用: {$binding->status}");
        }

        if ($binding->isLocked()) {
            throw new \RuntimeException('绑定已被锁定，请等待锁定解除');
        }

        $startTime = microtime(true);
        $result = 'passed';
        $error = null;

        try {
            // 验证 Quote nonce
            $nonce = $quoteData['nonce'] ?? '';
            if (strlen($nonce) < 16) {
                throw new \RuntimeException('Quote nonce 无效');
            }

            // 验证 PCR 值
            $pcrValues = $quoteData['pcr_values'] ?? [];
            if (!empty($binding->pcr_values) && !empty($pcrValues)) {
                foreach ($binding->pcr_values as $key => $expected) {
                    if (isset($pcrValues[$key]) && $pcrValues[$key] !== $expected) {
                        throw new \RuntimeException("PCR {$key} 值不匹配");
                    }
                }
            }

            // 验证时间窗口
            $quoteTime = $quoteData['timestamp'] ?? 0;
            $maxAge = config('tpm.tpm.max_quote_age_seconds', 300);
            if ($quoteTime > 0 && (time() - $quoteTime) > $maxAge) {
                throw new \RuntimeException('Quote 已过期');
            }

            // 记录验证成功
            $binding->resetFailures();
            $binding->update([
                'last_verified_at' => now(),
                'last_attestation_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $result = 'failed';
            $error = $e->getMessage();
            $binding->recordFailure();
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // 记录验证日志
        TpmVerificationLog::create([
            'tpm_binding_id' => $binding->id,
            'result' => $result,
            'quote_data' => $quoteData,
            'error_message' => $error,
            'duration_ms' => $duration,
            'ip_address' => request()->ip(),
            'verified_at' => now(),
        ]);

        return [
            'result' => $result,
            'error' => $error,
            'duration_ms' => $duration,
            'failed_attempts' => $binding->fresh()->failed_attempts,
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * 获取绑定详情
     */
    public function showBinding(int $id): TpmBinding
    {
        return TpmBinding::with([
            'license:id,license_key,name',
            'device:id,fingerprint,platform',
            'verificationLogs' => function ($q) { $q->latest('verified_at')->limit(20); },
        ])->findOrFail($id);
    }

    /**
     * 吊销 TPM 绑定
     */
    public function revokeBinding(int $id, ?string $reason = null): TpmBinding
    {
        $binding = TpmBinding::findOrFail($id);
        $binding->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);

        // 更新设备硬件绑定状态
        if ($binding->device_id) {
            Device::where('id', $binding->device_id)
                ->where('hardware_bound', '!=', 'none')
                ->update(['hardware_bound' => 'none', 'hardware_bound_at' => null]);
        }

        return $binding->fresh();
    }

    /**
     * 解锁绑定
     */
    public function unlockBinding(int $id): TpmBinding
    {
        $binding = TpmBinding::findOrFail($id);
        $binding->update([
            'status' => 'active',
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
        return $binding->fresh();
    }

    /**
     * 检查 License 的 TPM 绑定状态
     */
    public function checkLicenseBinding(int $licenseId): array
    {
        $license = License::findOrFail($licenseId);
        $bindings = TpmBinding::where('license_id', $licenseId)
            ->with('device:id,fingerprint')
            ->orderByDesc('id')
            ->get();

        $activeCount = $bindings->where('status', 'active')->count();

        return [
            'license_id' => $licenseId,
            'license_key' => $license->license_key,
            'total_bindings' => $bindings->count(),
            'active_bindings' => $activeCount,
            'max_bindings' => config('tpm.binding.max_bindings_per_license', 3),
            'bindings' => $bindings,
        ];
    }

    /**
     * 验证历史
     */
    public function verificationHistory(int $bindingId, int $limit = 50): array
    {
        return TpmVerificationLog::where('tpm_binding_id', $bindingId)
            ->latest('verified_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 验证统计
     */
    public function verificationStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $dailyStats = TpmVerificationLog::where('verified_at', '>=', $since)
            ->selectRaw("DATE(verified_at) as date, result, COUNT(*) as count")
            ->groupBy('date', 'result')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($items) {
                $total = $items->sum('count');
                $passed = $items->where('result', 'passed')->sum('count');
                return [
                    'total' => $total,
                    'passed' => $passed,
                    'failed' => $total - $passed,
                    'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
                ];
            });

        return [
            'daily' => $dailyStats,
            'total' => $dailyStats->sum('total'),
            'avg_pass_rate' => $dailyStats->avg('pass_rate') ?? 0,
        ];
    }

    /**
     * TPM 支持的硬件设备列表
     */
    public function tpmCapableDevices(Request $request): array
    {
        $query = Device::where('tpm_available', true)
            ->orWhere('hardware_bound', '!=', 'none');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('fingerprint', 'like', "%{$s}%")
                  ->orWhere('tpm_manufacturer', 'like', "%{$s}%");
            });
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')->paginate(20)->items();
        return ['items' => $items, 'total' => $total];
    }

    /**
     * 清理过期验证日志
     */
    public function pruneLogs(int $days = 90): int
    {
        return TpmVerificationLog::where('verified_at', '<', now()->subDays($days))->delete();
    }
}
