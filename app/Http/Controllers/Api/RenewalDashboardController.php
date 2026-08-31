<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RenewalDashboardController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService,
        protected BillingService $billingService,
    ) {}

    /**
     * 续期面板统计数据
     *
     * GET /api/renewal-dashboard/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // License 即将到期统计
        $licenseQuery = License::query();
        if ($tenantId) {
            $licenseQuery->where('tenant_id', $tenantId);
        }

        $licenseExpiring7d = (clone $licenseQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->count();

        $licenseExpiring14d = (clone $licenseQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(14))
            ->where('expires_at', '>', now())
            ->count();

        $licenseExpiring30d = (clone $licenseQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        $licenseExpired = (clone $licenseQuery)
            ->where(function ($q) {
                $q->where('status', 'expired')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'active')
                         ->whereNotNull('expires_at')
                         ->where('expires_at', '<=', now());
                  });
            })
            ->count();

        // 过去30天内已过期的 License
        $expired30d = (clone $licenseQuery)
            ->where('status', 'expired')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        // Subscription 即将到期统计
        $subQuery = Subscription::query()->whereIn('status', ['active', 'trialing']);
        if ($tenantId) {
            $subQuery->where('tenant_id', $tenantId);
        }

        $subExpiring7d = (clone $subQuery)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays(7))
            ->where('ends_at', '>', now())
            ->count();

        $subExpiring30d = (clone $subQuery)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays(30))
            ->where('ends_at', '>', now())
            ->count();

        // 总续期预估金额（基于即将到期的30天内 License 的售价）
        $expiringLicenses = (clone $licenseQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->with('product:id,name,price')
            ->get();

        $estimatedRenewalAmount = $expiringLicenses->sum(function ($license) {
            return $license->product?->price ?? 0;
        });

        return ApiResponse::success([
            'licenses' => [
                'expiring_7d' => $licenseExpiring7d,
                'expiring_14d' => $licenseExpiring14d,
                'expiring_30d' => $licenseExpiring30d,
                'expired' => $licenseExpired,
                'expired_30d' => $expired30d,
            ],
            'subscriptions' => [
                'expiring_7d' => $subExpiring7d,
                'expiring_30d' => $subExpiring30d,
            ],
            'estimated_renewal_amount' => round($estimatedRenewalAmount, 2),
        ]);
    }

    /**
     * 即将到期的 License 列表
     *
     * GET /api/renewal-dashboard/expiring-licenses
     * ?days=30&search=&status=&sort_field=&sort_order=&per_page=
     */
    public function expiringLicenses(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $days = (int) $request->input('days', 30);
        $search = $request->input('search');
        $sortField = $request->input('sort_field', 'expires_at');
        $sortOrder = $request->input('sort_order', 'asc');

        $query = License::query()
            ->with(['product:id,name', 'customer:id,name,email', 'tenant:id,name'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['license_key', 'expires_at', 'created_at', 'seats'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('expires_at', 'asc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        // 附加天数信息
        $paginator->getCollection()->transform(function ($license) {
            $license->days_until_expiry = $license->expires_at ? now()->diffInDays($license->expires_at, false) : null;
            $license->expiry_status = $this->getExpiryStatus($license->days_until_expiry);
            return $license;
        });

        return ApiResponse::paginated($paginator);
    }

    /**
     * 已过期 License 列表
     *
     * GET /api/renewal-dashboard/expired-licenses
     */
    public function expiredLicenses(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $search = $request->input('search');
        $daysAgo = $request->input('days_ago', 30);
        $sortField = $request->input('sort_field', 'expires_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = License::query()
            ->with(['product:id,name', 'customer:id,name,email', 'tenant:id,name'])
            ->where(function ($q) {
                $q->where('status', 'expired')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'active')
                         ->whereNotNull('expires_at')
                         ->where('expires_at', '<=', now());
                  });
            });

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($daysAgo > 0) {
            $query->where('expires_at', '>=', now()->subDays((int) $daysAgo));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['license_key', 'expires_at', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 批量续期（延长 expires_at）
     *
     * POST /api/renewal-dashboard/batch-renew
     * Body: { license_ids: int[], days: int, notify: bool }
     */
    public function batchRenew(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_ids' => 'required|array|min:1|max:100',
            'license_ids.*' => 'integer|exists:licenses,id',
            'days' => 'required|integer|min:1|max:3650',
            'notify' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $tenantId = $request->user()->tenant_id;
        $data = $validator->validated();

        $licenses = License::whereIn('id', $data['license_ids'])
            ->where('status', 'active')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $renewed = 0;
        $skipped = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($licenses as $license) {
                $newExpiresAt = $license->expires_at
                    ? $license->expires_at->copy()->addDays($data['days'])
                    : now()->addDays($data['days']);

                $license->expires_at = $newExpiresAt;
                $license->save();
                $renewed++;

                // 记录审计日志
                activity()
                    ->performedOn($license)
                    ->causedBy($request->user())
                    ->withProperties([
                        'action' => 'batch_renew',
                        'old_expires_at' => $license->getOriginal('expires_at')?->toDateString(),
                        'new_expires_at' => $newExpiresAt->toDateString(),
                        'days_added' => $data['days'],
                    ])
                    ->log('license:batch_renew');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('RENEWAL_FAILED', __("app.renewal_dashboard.msg_d2191a5e") . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'renewed' => $renewed,
            'skipped' => count($skipped),
            'total' => count($data['license_ids']),
        ], __('app.common.renewed_result', ['count' => $renewed]));
    }

    /**
     * 单个 License 续期
     *
     * POST /api/renewal-dashboard/renew/{license}
     * Body: { days: int, notify: bool }
     */
    public function renew(Request $request, License $license): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        if ($tenantId && $license->tenant_id !== $tenantId) {
            return ApiResponse::error('FORBIDDEN', __("app.renewal_dashboard.msg_6024cd8f"), 403);
        }

        if ($license->status !== 'active') {
            return ApiResponse::error('INVALID_STATUS', __("app.renewal_dashboard.msg_af3ad67f"), 422);
        }

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:3650',
            'notify' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $days = (int) $validator->validated()['days'];
        $oldExpiresAt = $license->expires_at?->copy();

        $newExpiresAt = $license->expires_at
            ? $license->expires_at->copy()->addDays($days)
            : now()->addDays($days);

        $license->expires_at = $newExpiresAt;
        $license->save();

        // 记录审计日志
        activity()
            ->performedOn($license)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'renew',
                'old_expires_at' => $oldExpiresAt?->toDateString(),
                'new_expires_at' => $newExpiresAt->toDateString(),
                'days_added' => $days,
            ])
            ->log('license:renew');

        $license->load(['product:id,name', 'customer:id,name,email']);

        return ApiResponse::success($license, __("app.renewal_dashboard.msg_5b69190a"));
    }

    /**
     * 续期活动日志
     *
     * GET /api/renewal-dashboard/activity-log
     */
    public function activityLog(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = DB::table('activity_log')
            ->whereIn('log_name', ['license:renew', 'license:batch_renew'])
            ->where('causer_type', 'App\Models\User')
            ->orderBy('created_at', 'desc')
            ->limit(100);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $logs = $query->get()->map(function ($log) {
            $properties = json_decode($log->properties ?? '{}', true);
            return [
                'id' => $log->id,
                'action' => $log->log_name === 'license:renew' ? '单个续期' : '批量续期',
                'description' => $log->description,
                'properties' => $properties,
                'created_at' => $log->created_at,
                'causer_id' => $log->causer_id,
            ];
        });

        return ApiResponse::success($logs);
    }

    protected function getExpiryStatus(?int $daysUntilExpiry): string
    {
        if ($daysUntilExpiry === null) return 'unknown';
        if ($daysUntilExpiry <= 0) return 'expired';
        if ($daysUntilExpiry <= 7) return 'critical';
        if ($daysUntilExpiry <= 14) return 'warning';
        if ($daysUntilExpiry <= 30) return 'notice';
        return 'normal';
    }
}
