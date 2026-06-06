<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with('user')
            ->where('tenant_id', $request->user()->tenant_id);

        // 搜索
        if ($search = $request->input('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($type = $request->input('filter.type')) {
            $query->where('type', $type);
        }
        if ($level = $request->input('filter.level')) {
            $query->where('level', $level);
        }
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedSorts = ['created_at', 'updated_at', 'level', 'type', 'status'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        } else {
            $query->latest();
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $customer = Customer::with('user', 'licenses')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $customer->loadCount('licenses as licenses_count');

        // 关联的设备
        $licenseIds = $customer->licenses()->pluck('id');
        $devices = \App\Models\Device::whereIn('license_id', $licenseIds)
            ->where('tenant_id', $request->user()->tenant_id)
            ->select('id', 'fingerprint', 'platform', 'os_version', 'trust_score', 'last_seen_at', 'license_id')
            ->orderByDesc('last_seen_at')
            ->get();

        return ApiResponse::success([
            'customer' => $customer,
            'devices' => $devices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'type' => 'required|in:individual,enterprise',
            'level' => 'required|in:free,pro,enterprise',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $customer = Customer::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$validated,
        ]);

        return ApiResponse::created($customer->load('user'), '客户创建成功');
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $customer = Customer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'type' => 'sometimes|in:individual,enterprise',
            'level' => 'sometimes|in:free,pro,enterprise',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        $customer->update($validated);

        return ApiResponse::success($customer->load('user'), '客户更新成功');
    }

    /**
     * 获取客户统计
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $total = Customer::where('tenant_id', $tenantId)->count();
        $enterprise = Customer::where('tenant_id', $tenantId)->where('type', 'enterprise')->count();
        $byLevel = Customer::where('tenant_id', $tenantId)
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        return ApiResponse::success([
            'total' => $total,
            'enterprise' => $enterprise,
            'individual' => $total - $enterprise,
            'by_level' => $byLevel,
        ]);
    }

    /**
     * 获取客户的 License
     */
    public function licenses(int $id, Request $request): JsonResponse
    {
        $customer = Customer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $perPage = min((int) $request->input('per_page', 20), 100);

        $licenses = License::with('product')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated($licenses);
    }
}
