<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnCallEntry;
use App\Models\OnCallMember;
use App\Models\OnCallOverride;
use App\Models\OnCallSchedule;
use App\Services\OnCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnCallController extends Controller
{
    protected OnCallService $onCall;

    public function __construct(OnCallService $onCall)
    {
        $this->onCall = $onCall;
    }

    // ── 总览 ──

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->onCall->getDashboard(),
        ]);
    }

    // ── 排班管理 ──

    public function index(): JsonResponse
    {
        $schedules = OnCallSchedule::with(['members.user', 'entries' => function ($q) {
            $q->active()->with('user:id,name,email');
        }])->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'rotation_type' => 'required|in:daily,weekly,biweekly,monthly,custom',
            'rotation_length' => 'sometimes|integer|min:1|max:90',
            'channels' => 'sometimes|array',
            'channels.*' => 'string|in:database,email,sms,slack,dingtalk,phone',
            'color' => 'sometimes|string|size:7',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['tenant_id'] = $request->user()->tenant_id;

        $schedule = OnCallSchedule::create($validated);

        return response()->json(['success' => true, 'data' => $schedule, 'message' => __('app.controller_compat.on_call_msg_61')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $schedule = OnCallSchedule::with([
            'members.user:id,name,email,avatar',
            'entries' => fn($q) => $q->orderBy('starts_at', 'desc')->with('user:id,name'),
            'overrides' => fn($q) => $q->orderBy('starts_at', 'desc')->with('originalUser:id,name', 'replacementUser:id,name'),
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $schedule]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = OnCallSchedule::findOrFail($id);
        $schedule->update($request->only([
            'name', 'description', 'rotation_type', 'rotation_length',
            'channels', 'color', 'status', 'time_restriction', 'escalation_rules',
        ]));

        return response()->json(['success' => true, 'data' => $schedule, 'message' => __('app.controller_compat.on_call_msg_83')]);
    }

    public function destroy(int $id): JsonResponse
    {
        OnCallSchedule::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => __('app.controller_compat.on_call_msg_89')]);
    }

    // ── 成员管理 ──

    public function addMember(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'sort_order' => 'sometimes|integer|min:0',
            'weight' => 'sometimes|integer|min:1|max:10',
        ]);

        $member = OnCallMember::create([
            'schedule_id' => $scheduleId,
            'user_id' => $validated['user_id'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'weight' => $validated['weight'] ?? 1,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $member->load('user:id,name,email'), 'message' => __('app.controller_compat.on_call_msg_110')], 201);
    }

    public function removeMember(int $scheduleId, int $memberId): JsonResponse
    {
        OnCallMember::where('schedule_id', $scheduleId)->findOrFail($memberId)->delete();
        return response()->json(['success' => true, 'message' => __('app.controller_compat.on_call_msg_116')]);
    }

    // ── 排班生成 ──

    public function generate(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = OnCallSchedule::findOrFail($scheduleId);
        $days = (int) $request->input('days', 90);
        $generated = $this->onCall->autoGenerate($schedule, $days);

        return response()->json([
            'success' => true,
            'data' => ['generated' => $generated],
            'message' => "已生成 {$generated} 条值班安排",
        ]);
    }

    // ── 临时替换 ──

    public function createOverride(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:on_call_schedules,id',
            'original_user_id' => 'required|exists:users,id',
            'replacement_user_id' => 'required|exists:users,id|different:original_user_id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:200',
        ]);

        $override = OnCallOverride::create($validated + ['status' => 'approved']);

        return response()->json(['success' => true, 'data' => $override, 'message' => __('app.controller_compat.on_call_msg_149')], 201);
    }

    // ── 值班日志 ──

    public function logs(Request $request): JsonResponse
    {
        $query = \App\Models\OnCallLog::with('user:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->schedule_id) {
            $query->whereHas('onCallEntry', fn($q) => $q->where('schedule_id', $request->schedule_id));
        }

        $perPage = (int) $request->input('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
