<?php

namespace App\Services;

use App\Models\HoneypotLicense;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * M2-03 主动蜜罐防御
 *
 * 蜜罐 License 管理 + 触发检测 + 告警集成。
 * 蜜罐 License 是假密钥，被激活尝试时触发实时告警，
 * 用于发现未授权使用系统的行为。
 */
class HoneypotService
{
    public function __construct(
        protected AlertEngineService $alertEngine,
    ) {}

    // ═══════════════════════════════════════
    //  蜜罐 License 管理
    // ═══════════════════════════════════════

    /**
     * 生成新的蜜罐 License
     */
    public function generate(array $data): HoneypotLicense
    {
        return HoneypotLicense::create([
            'license_key' => HoneypotLicense::generateKey(),
            'label' => $data['label'] ?? null,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * 批量生成蜜罐 License
     */
    public function generateBatch(int $count, ?string $labelPrefix = null): array
    {
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            $generated[] = $this->generate([
                'label' => $labelPrefix ? "{$labelPrefix} #{$i}" : null,
            ]);
        }
        return $generated;
    }

    /**
     * 分页列表
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = HoneypotLicense::orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query->paginate($request->input('per_page', 20));
    }

    /**
     * 仪表盘统计
     */
    public function dashboard(): array
    {
        return [
            'total' => HoneypotLicense::count(),
            'active' => HoneypotLicense::where('status', 'active')->count(),
            'triggered' => HoneypotLicense::where('status', 'triggered')->count(),
            'disabled' => HoneypotLicense::where('status', 'disabled')->count(),
            'total_triggers' => HoneypotLicense::sum('trigger_count'),
            'recent_triggered' => HoneypotLicense::where('status', 'triggered')
                ->where('triggered_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * 禁用蜜罐 License
     */
    public function disable(HoneypotLicense $honeypot): HoneypotLicense
    {
        $honeypot->update(['status' => 'disabled']);
        return $honeypot;
    }

    /**
     * 重新激活蜜罐 License
     */
    public function reactivate(HoneypotLicense $honeypot): HoneypotLicense
    {
        $honeypot->update(['status' => 'active']);
        return $honeypot;
    }

    /**
     * 删除蜜罐 License
     */
    public function delete(HoneypotLicense $honeypot): bool
    {
        return $honeypot->delete();
    }

    // ═══════════════════════════════════════
    //  蜜罐触发检测 & 告警
    // ═══════════════════════════════════════

    /**
     * 检测 License Key 是否为蜜罐
     * 供 ActivateController::activate() 调用
     *
     * @return array|null ['honeypot' => HoneypotLicense, 'is_honeypot' => true] 或 null
     */
    public function detect(string $licenseKey): ?HoneypotLicense
    {
        return HoneypotLicense::where('license_key', $licenseKey)
            ->where('status', 'active')
            ->first();
    }

    /**
     * 处理蜜罐触发：记录触发 + 发送告警
     */
    public function handleTrigger(HoneypotLicense $honeypot, string $ip, array $context = []): void
    {
        // 记录触发
        $honeypot->recordTrigger($ip, $context);

        // 日志记录
        Log::warning('Honeypot License triggered', [
            'honeypot_id' => $honeypot->id,
            'license_key' => $honeypot->license_key,
            'label' => $honeypot->label,
            'ip' => $ip,
            'context' => $context,
        ]);

        // 通过告警引擎发送实时告警
        $this->alertEngine->fireManual(
            title: '🚨 蜜罐 License 被触发！疑似非法访问',
            message: "蜜罐 License「{$honeypot->label}」({$honeypot->license_key}) 在 IP {$ip} 被激活尝试",
            severity: 'critical',
            eventType: 'honeypot_triggered',
            context: [
                'honeypot_id' => $honeypot->id,
                'license_key' => $honeypot->license_key,
                'label' => $honeypot->label,
                'ip' => $ip,
                'trigger_info' => $context,
            ],
            sourceType: 'honeypot_license',
            sourceId: $honeypot->id,
        );
    }
}
