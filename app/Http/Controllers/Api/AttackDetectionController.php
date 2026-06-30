<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AttackEvent;
use App\Models\AttackIpBlock;
use App\Services\AttackDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AttackDetectionController extends Controller
{
    public function __construct(protected AttackDetectionService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard());
    }

    /**
     * 攻击事件列表
     */
    public function events(Request $request): JsonResponse
    {
        $query = AttackEvent::orderByDesc('detected_at');

        if ($request->filled('attack_type')) {
            $query->where('attack_type', $request->attack_type);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::paginated($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 事件详情
     */
    public function show(AttackEvent $attackEvent): JsonResponse
    {
        return ApiResponse::success($attackEvent);
    }

    /**
     * 更新事件状态
     */
    public function updateStatus(Request $request, AttackEvent $attackEvent): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,investigating,blocked,resolved,false_positive',
        ]);

        $attackEvent->update([
            'status' => $validated['status'],
            'resolved_at' => in_array($validated['status'], ['resolved', 'false_positive']) ? now() : null,
        ]);

        return ApiResponse::success($attackEvent, '状态已更新');
    }

    /**
     * IP封禁列表
     */
    public function ipBlocks(Request $request): JsonResponse
    {
        $query = AttackIpBlock::orderByDesc('blocked_at');

        if ($request->filled('ip')) {
            $query->where('ip', 'like', "%{$request->ip}%");
        }

        return ApiResponse::paginated($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 手动封禁IP
     */
    public function blockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'reason' => 'required|string|max:500',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_permanent' => 'nullable|boolean',
        ]);

        AttackIpBlock::updateOrCreate(
            ['ip' => $validated['ip']],
            [
                'reason' => $validated['reason'],
                'attack_type' => 'manual',
                'confidence' => 1.0,
                'expires_at' => $validated['is_permanent'] ? now()->addYears(100) : now()->addMinutes($validated['duration_minutes'] ?? 60),
                'is_permanent' => $validated['is_permanent'] ?? false,
            ]
        );

        Cache::put("banned:ip:{$validated['ip']}", true, $validated['duration_minutes'] ?? 60 * 60);

        return ApiResponse::success(null, 'IP已封禁');
    }

    /**
     * 解封IP
     */
    public function unblockIp(string $ip): JsonResponse
    {
        AttackIpBlock::where('ip', $ip)->delete();
        Cache::forget("banned:ip:{$ip}");
        return ApiResponse::success(null, 'IP已解封');
    }

    /**
     * 执行分析（手动触发）
     */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'method' => 'nullable|string',
            'path' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $event = $this->service->analyzeRequest(
            $validated['ip'],
            $validated['method'] ?? 'GET',
            $validated['path'] ?? '/',
            $validated['context'] ?? []
        );

        return $event
            ? ApiResponse::success($event, '已检测到攻击')
            : ApiResponse::success(null, '未检测到异常');
    }
}
