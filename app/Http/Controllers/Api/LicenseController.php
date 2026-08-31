<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateLicenseRequest;
use App\Http\Requests\Api\LicenseStatusRequest;
use App\Http\Requests\Api\UpdateLicenseRequest;
use App\Models\License;
use App\Models\Product;
use App\Models\Customer;
use App\Services\KeyGenerator;
use App\Services\KeyPrefixFormatter;
use App\Services\LicenseService;
use App\Services\SeatPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LicenseController extends Controller
{
    protected SeatPoolService $seatPoolService;

    public function __construct(
        protected LicenseService $licenseService,
        protected KeyGenerator   $keyGenerator,
        protected KeyPrefixFormatter $keyPrefixFormatter,
    ) {
        $this->seatPoolService = app(SeatPoolService::class);
    }

    /**
     * 获取 License 列表（分页+筛选+排序）
     *
     * GET /api/licenses
     * ?filter[status]=active&filter[type]=standard&sort=-created_at
     */
    public function index(Request $request): JsonResponse
    {
        $query = License::query()->with(['product', 'customer', 'tenant', 'tags']);

        // 租户隔离
        if ($tenantId = $request->user()->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        $paginator = (new class {
            use \App\Http\Concerns\QueryBuilder;
        })->buildPaginatedQuery($query, $request);

        return ApiResponse::paginated($paginator);
    }

    /**
     * License 详情
     *
     * GET /api/licenses/{license}
     */
    public function show(int $id): JsonResponse
    {
        $license = License::with([
            'product', 'customer', 'tenant', 'tags',
            'devices' => fn($q) => $q->latest(),
            'activations' => fn($q) => $q->latest()->limit(10),
        ])->findOrFail($id);

        // 安全：检查租户归属
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_view'), 403);
        }

        // 分析当前状态
        $statusInfo = $this->licenseService->getStatusInfo($license);

        // 加载 SKU 交付物
        $sku = $license->getSku();
        $deliverables = $sku?->deliverables ?? [];

        return ApiResponse::success([
            'license' => $license,
            'status_info' => $statusInfo,
            'deliverables' => $deliverables,
        ]);
    }

    /**
     * 查询 License（通过 license_key）
     *
     * POST /api/licenses/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
        ]);

        $license = License::where('license_key', $data['license_key'])
            ->with(['product', 'customer'])
            ->first();

        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', __('app.api.license.key_not_found'), 404);
        }

        return ApiResponse::success($license);
    }

    /**
     * 公开查询 License（无需登录）
     *
     * POST /api/license/public-lookup
     *
     * 只返回公开信息，不暴露客户敏感数据
     */
    public function publicLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string|max:100',
        ]);

        $license = License::where('license_key', $data['license_key'])
            ->with(['product:id,name,description', 'devices'])
            ->first();

        if (! $license) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.license.lookup_not_found'),
                'found' => false,
            ]);
        }

        $deviceCount = $license->devices->count();
        $maxDevices = $license->seat_limit ?? $license->product?->seat_limit ?? 0;

        $devices = $license->devices->map(function ($device) {
            $meta = is_array($device->metadata) ? $device->metadata : [];
            $fp = (string) ($device->fingerprint ?? '');

            return [
                'id' => $device->id,
                'device_name' => $meta['device_name'] ?? ($meta['client']['device_name'] ?? null),
                'platform' => $device->platform,
                'fingerprint_mask' => $fp !== '' ? (substr($fp, 0, 8) . '…') : '—',
                'last_seen_at' => $device->last_seen_at?->toDateTimeString() ?? $device->updated_at?->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'found' => true,
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'status_label' => $license->status_label ?? $license->status,
                'product_name' => $license->product?->name ?? '—',
                'product_description' => $license->product?->description ?? '',
                'product_id' => $license->product_id,
                'license_type' => $license->type,
                'license_type_label' => $license->type_label ?? $license->type,
                'activated' => $license->activated,
                'max_devices' => $maxDevices,
                'activated_devices' => $deviceCount,
                'devices' => $devices,
                'created_at' => $license->created_at?->toDateString(),
                'expires_at' => $license->expires_at?->toDateString(),
                'is_expired' => $license->expires_at ? $license->expires_at->isPast() : false,
            ],
        ]);
    }

    /**
     * 生成并创建 License
     *
     * POST /api/licenses
     */
    public function store(CreateLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $licenseKey = $this->keyGenerator->generate($data['type'] ?? 'standard');

        $license = $this->licenseService->create([
            'tenant_id' => $request->user()->tenant_id ?? $data['tenant_id'],
            'product_id' => $data['product_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'license_key' => $licenseKey,
            'type' => $data['type'] ?? 'standard',
            'expires_at' => $data['expires_at'] ?? null,
            'seats' => $data['seats'] ?? 1,
            'max_devices' => $data['max_devices'] ?? 1,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return ApiResponse::created($license, __('app.api.license.created'));
    }

    /**
     * 批量生成 License
     *
     * POST /api/licenses/batch
     */
    public function batchStore(CreateLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $count = min($data['count'] ?? 10, 100);

        $keys = $this->keyGenerator->generateBatch($data['type'] ?? 'standard', $count);

        $licenses = [];
        foreach ($keys as $key) {
            $licenses[] = $this->licenseService->create([
                'tenant_id' => $request->user()->tenant_id ?? $data['tenant_id'],
                'product_id' => $data['product_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'license_key' => $key,
                'type' => $data['type'] ?? 'standard',
                'expires_at' => $data['expires_at'] ?? null,
                'seats' => $data['seats'] ?? 1,
                'max_devices' => $data['max_devices'] ?? 1,
                'metadata' => $data['metadata'] ?? null,
            ]);
        }

        return ApiResponse::created($licenses, __('app.api.license.created_n', ['count' => $count]));
    }

    /**
     * 更新 License 信息
     *
     * PUT /api/licenses/{license}
     */
    public function update(UpdateLicenseRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }

        $updated = $this->licenseService->update($license, $request->validated());

        return ApiResponse::success($updated, __('app.api.license.updated'));
    }

    /**
     * 软删除 License（放入回收站）
     *
     * DELETE /api/licenses/{license}
     */
    public function destroy(int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }

        $this->licenseService->softDelete($license);

        return ApiResponse::success(null, __('app.api.license.trashed'));
    }

    /**
     * 从回收站恢复 License
     *
     * POST /api/licenses/{license}/restore
     */
    public function restoreFromTrash(int $id): JsonResponse
    {
        $license = License::withTrashed()->findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }

        $restored = $this->licenseService->restoreFromTrash($id);

        return ApiResponse::success($restored, __('app.api.license.restored'));
    }

    /**
     * License 统计
     *
     * GET /api/licenses/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $stats = $this->licenseService->stats($tenantId);

        return ApiResponse::success($stats);
    }

    /**
     * 批量操作 License
     *
     * POST /api/licenses/batch/operation
     * Body: { license_ids: int[], action: string, payload?: object }
     *
     * 支持的 action:
     * - activate: 激活
     * - deactivate: 停用
     * - revoke: 撤销
     * - suspend: 挂起
     * - freeze: 冻结
     * - restore: 恢复
     * - blacklist: 加入黑名单
     * - refund: 退款
     * - delete: 软删除
     * - renew: 续期（需 payload.days）
     * - update_seats: 更新席位（需 payload.seats）
     * - update_metadata: 更新元数据（需 payload.metadata）
     * - transfer: 转移租户（需 payload.tenant_id，仅 super-admin）
     * - add_tags: 添加标签（需 payload.tags）
     * - remove_tags: 移除标签（需 payload.tags）
     */
    public function batchOperation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_ids' => 'required|array|min:1|max:200',
            'license_ids.*' => 'integer|exists:licenses,id',
            'action' => 'required|string|in:activate,deactivate,revoke,suspend,freeze,restore,blacklist,refund,delete,renew,update_seats,update_metadata,transfer,add_tags,remove_tags',
            'payload' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        $licenses = License::whereIn('id', $data['license_ids'])
            ->when(!$isSuperAdmin, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        if ($licenses->isEmpty()) {
            return ApiResponse::error('NOT_FOUND', __('app.api.license.not_found_filter'), 404);
        }

        $action = $data['action'];
        $payload = $data['payload'] ?? [];
        $results = [
            'action' => $action,
            'total' => count($data['license_ids']),
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($licenses as $license) {
                try {
                    $this->executeBatchAction($license, $action, $payload, $user);
                    $results['processed']++;
                    $results['details'][] = [
                        'id' => $license->id,
                        'license_key' => $license->license_key,
                        'success' => true,
                    ];
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $license->id,
                        'license_key' => $license->license_key,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // 记录批量操作审计日志
            activity()
                ->causedBy($user)
                ->withProperties([
                    'action' => "batch_{$action}",
                    'license_ids' => $data['license_ids'],
                    'total' => $results['total'],
                    'processed' => $results['processed'],
                    'failed' => $results['failed'],
                    'payload' => $payload,
                ])
                ->log("license:batch_{$action}");

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('BATCH_FAILED', __('app.api.license.batch_failed', ['error' => $e->getMessage()]), 500);
        }

        return ApiResponse::success($results, __('app.api.license.batch_done', ['ok' => $results['processed'], 'fail' => $results['failed']]));
    }

    /**
     * 执行单个 License 的批量操作
     */
    protected function executeBatchAction(License $license, string $action, array $payload, $user): void
    {
        switch ($action) {
            case 'activate':
                $this->licenseService->activate($license);
                break;
            case 'deactivate':
                $license->status = 'inactive';
                $license->activated_at = null;
                $license->save();
                break;
            case 'revoke':
                $this->licenseService->revoke($license, $payload['reason'] ?? null);
                break;
            case 'suspend':
                $this->licenseService->suspend($license, $payload['reason'] ?? null);
                break;
            case 'freeze':
                $this->licenseService->freeze($license, $payload['reason'] ?? null);
                break;
            case 'restore':
                $this->licenseService->restore($license, $payload['reason'] ?? null);
                break;
            case 'blacklist':
                $this->licenseService->blacklist($license, $payload['reason'] ?? null);
                break;
            case 'refund':
                $this->licenseService->refund($license, $payload['reason'] ?? null);
                break;
            case 'delete':
                $this->licenseService->softDelete($license);
                break;
            case 'renew':
                $days = (int) ($payload['days'] ?? 365);
                $newExpiresAt = $license->expires_at
                    ? $license->expires_at->copy()->addDays($days)
                    : now()->addDays($days);
                $license->expires_at = $newExpiresAt;
                $license->save();
                break;
            case 'update_seats':
                $seats = (int) ($payload['seats'] ?? 1);
                if ($seats < 1) $seats = 1;
                $license->seats = $seats;
                $license->save();
                break;
            case 'update_metadata':
                $metadata = $payload['metadata'] ?? [];
                $existing = $license->metadata ?? [];
                $license->metadata = array_merge($existing, $metadata);
                $license->save();
                break;
            case 'transfer':
                $newTenantId = (int) ($payload['tenant_id'] ?? 0);
                if (!$newTenantId) {
                    throw new \Exception(__('app.api.license.tenant_required'));
                }
                if (!$user->hasRole('super-admin')) {
                    throw new \Exception(__('app.api.license.tenant_transfer_admin'));
                }
                $license->tenant_id = $newTenantId;
                $license->save();
                break;
            case 'add_tags':
                $tags = $payload['tags'] ?? [];
                if (!empty($tags)) {
                    $license->tag($tags);
                }
                break;
            case 'remove_tags':
                $tags = $payload['tags'] ?? [];
                if (!empty($tags)) {
                    $license->untag($tags);
                }
                break;
            default:
                throw new \Exception(__('app.api.license.unsupported_action', ['action' => $action]));
        }
    }

    // ─── 状态管理 ───

    /**
     * 撤销 License
     *
     * POST /api/licenses/{license}/revoke
     */
    public function revoke(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->revoke($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.revoked'));
    }

    /**
     * 挂起 License
     *
     * POST /api/licenses/{license}/suspend
     */
    public function suspend(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->suspend($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.suspended'));
    }

    /**
     * 冻结 License
     *
     * POST /api/licenses/{license}/freeze
     */
    public function freeze(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->freeze($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.frozen'));
    }

    /**
     * 解冻/恢复 License
     *
     * POST /api/licenses/{license}/restore
     */
    public function restore(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->restore($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.reactivated'));
    }

    /**
     * 加入黑名单
     *
     * POST /api/licenses/{license}/blacklist
     */
    public function blacklist(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->blacklist($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.blacklisted'));
    }

    /**
     * 退款处理
     *
     * POST /api/licenses/{license}/refund
     */
    public function refund(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }
        $updated = $this->licenseService->refund($license, $request->input('reason'));
        return ApiResponse::success($updated, __('app.api.license.refunded'));
    }

    /**
     * 检查当前用户是否拥有该资源的租户
     */
    protected function isOwnTenant($model): bool
    {
        $userTenantId = auth()->user()?->tenant_id;
        if (! $userTenantId) {
            return false;
        }

        // 超级管理员可以访问所有租户
        if (auth()->user()?->hasRole('super-admin')) {
            return true;
        }

        return (int) $model->tenant_id === (int) $userTenantId;
    }

    /**
     * 导出 License 为 CSV
     *
     * GET /api/licenses/export?filter[status]=active&filter[type]=standard
     */
    public function export(Request $request): StreamedResponse
    {
        $query = License::query()->with(['product:id,name', 'customer:id,name', 'tenant:id,name']);

        // 租户隔离
        if ($tenantId = $request->user()->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        // 应用筛选
        (new class {
            use \App\Http\Concerns\QueryBuilder;
        })->buildFilterQuery($query, $request);

        $query->orderBy('created_at', 'desc');

        $headers = [
            'license_key', 'type', 'status', 'product_name', 'customer_name',
            'tenant_name', 'seats', 'max_devices', 'expires_at', 'activated_at',
            'created_at', 'metadata',
        ];

        $filename = 'licenses-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $headers) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'License Key', __('app.api.license.csv_type'), __('app.api.license.csv_status'), __('app.api.license.csv_product'), __('app.api.license.csv_customer'),
                __('app.api.license.csv_tenant'), __('app.api.license.csv_seats'), __('app.api.license.csv_devices'), __('app.api.license.csv_expires'), __('app.api.license.csv_activated'),
                __('app.api.license.csv_created'), __('app.api.license.csv_metadata'),
            ]);

            $query->chunk(200, function ($licenses) use ($handle, $headers) {
                foreach ($licenses as $license) {
                    fputcsv($handle, [
                        $license->license_key,
                        $license->type,
                        $license->status,
                        $license->product?->name ?? '',
                        $license->customer?->name ?? '',
                        $license->tenant?->name ?? '',
                        $license->seats,
                        $license->max_devices,
                        $license->expires_at ? $license->expires_at->format('Y-m-d H:i:s') : '',
                        $license->activated_at ? $license->activated_at->format('Y-m-d H:i:s') : '',
                        $license->created_at->format('Y-m-d H:i:s'),
                        json_encode($license->metadata ?? []),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 导入 License（从 CSV）
     *
     * POST /api/licenses/import
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120', // max 5MB
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');

        // Detect and skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ApiResponse::error('VALIDATION_ERROR', __('app.api.license.import_empty'), 422);
        }

        // Normalize headers (trim, lowercase)
        $header = array_map(fn($h) => trim(strtolower($h)), $header);

        // Map Chinese headers to field names
        $headerMap = [
            'license key' => 'license_key',
            '类型' => 'type',
            '产品' => 'product',
            '产品id' => 'product_id',
            '客户' => 'customer',
            '客户id' => 'customer_id',
            '座位数' => 'seats',
            '设备限制' => 'max_devices',
            '过期时间' => 'expires_at',
            '元数据' => 'metadata',
        ];

        $mappedFields = [];
        foreach ($header as $h) {
            $mappedFields[] = $headerMap[$h] ?? $h;
        }

        $tenantId = $request->user()->tenant_id;
        $isSuperAdmin = $request->user()->hasRole('super-admin');

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $rowNumber = 1; // header was row 1
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $results['total']++;

            // Pad row to match header length
            $row = array_pad($row, count($mappedFields), '');
            $data = array_combine($mappedFields, $row);

            // Skip empty rows
            if (empty(array_filter($data))) {
                $results['total']--;
                continue;
            }

            try {
                $errors = [];

                // Resolve product
                $productId = null;
                if (!empty($data['product'])) {
                    $product = Product::where('name', $data['product'])->first();
                    $productId = $product?->id;
                } elseif (!empty($data['product_id'])) {
                    $productId = (int) $data['product_id'];
                }

                if (!$productId) {
                    throw new \Exception(__('app.api.license.product_required'));
                }

                // Resolve customer
                $customerId = null;
                if (!empty($data['customer'])) {
                    $customer = Customer::where('name', $data['customer'])->first();
                    $customerId = $customer?->id;
                } elseif (!empty($data['customer_id'])) {
                    $customerId = (int) $data['customer_id'];
                }

                // Validate type
                $type = !empty($data['type']) ? strtolower(trim($data['type'])) : 'standard';
                if (!in_array($type, ['trial', 'standard', 'enterprise', 'development'])) {
                    throw new \Exception(__('app.api.license.invalid_type', ['type' => $type]));
                }

                // Validate seats
                $seats = !empty($data['seats']) ? (int) $data['seats'] : 1;
                if ($seats < 1) $seats = 1;

                // Validate max_devices
                $maxDevices = !empty($data['max_devices']) ? (int) $data['max_devices'] : 1;
                if ($maxDevices < 1) $maxDevices = 1;

                // Parse expires_at
                $expiresAt = null;
                if (!empty($data['expires_at'])) {
                    try {
                        $expiresAt = \Carbon\Carbon::parse($data['expires_at']);
                    } catch (\Exception $e) {
                        throw new \Exception(__('app.api.license.invalid_date', ['value' => $data['expires_at']]));
                    }
                }

                // Parse metadata (JSON)
                $metadata = null;
                if (!empty($data['metadata'])) {
                    $decoded = json_decode($data['metadata'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                // Generate license key
                $licenseKey = app(KeyGenerator::class)->generate($type);

                // Create license
                License::create([
                    'tenant_id' => $isSuperAdmin && !empty($data['tenant_id']) ? (int) $data['tenant_id'] : $tenantId,
                    'product_id' => $productId,
                    'customer_id' => $customerId,
                    'license_key' => $licenseKey,
                    'type' => $type,
                    'seats' => $seats,
                    'max_devices' => $maxDevices,
                    'expires_at' => $expiresAt,
                    'metadata' => $metadata,
                    'status' => 'pending',
                ]);

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = __('app.api.license.import_row', [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        return ApiResponse::success($results, __('app.api.license.import_done'));
    }

    // ═══════════════ Seat Pool 席位池管理 (M3-45) ═══════════════

    /**
     * 获取席位池状态
     */
    public function poolStatus(License $license): JsonResponse
    {
        $this->authorize('view', $license);

        return ApiResponse::success(
            $this->seatPoolService->getPoolStatus($license)
        );
    }

    /**
     * 获取席位列席列表
     */
    public function poolAssignments(Request $request, License $license): JsonResponse
    {
        $this->authorize('view', $license);

        $filters = $request->only(['status', 'search']);
        $perPage = (int) $request->get('per_page', 20);

        return ApiResponse::paginated(
            $this->seatPoolService->getAssignments($license, $filters, $perPage)
        );
    }

    /**
     * 获取排队队列
     */
    public function poolQueue(License $license): JsonResponse
    {
        $this->authorize('view', $license);

        return ApiResponse::success(
            $this->seatPoolService->getQueue($license)
        );
    }

    /**
     * 手动分配席位
     */
    public function poolAssign(Request $request, License $license): JsonResponse
    {
        $this->authorize('update', $license);

        $validated = $request->validate([
            'seat_identifier' => 'required|string|max:100',
            'label' => 'nullable|string|max:200',
            'device_id' => 'nullable|integer|exists:devices,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $result = $this->seatPoolService->assignSeat(
            $license,
            $validated['seat_identifier'],
            $validated['label'] ?? null,
            $validated['device_id'] ?? null,
            $validated['customer_id'] ?? null,
            'admin'
        );

        if ($result['success']) {
            return ApiResponse::success($result, __('app.api.license.seat_assigned'));
        }

        return ApiResponse::error($result['message'] ?? __('app.api.license.seat_assign_fail'), 409);
    }

    /**
     * 释放席位
     */
    public function poolRelease(Request $request, License $license): JsonResponse
    {
        $this->authorize('update', $license);

        $validated = $request->validate([
            'seat_identifier' => 'nullable|string|max:100',
            'assignment_id' => 'nullable|integer|exists:seat_assignments,id',
        ]);

        $released = $this->seatPoolService->releaseSeat(
            $license,
            $validated['seat_identifier'] ?? null,
            $validated['assignment_id'] ?? null,
        );

        if ($released) {
            return ApiResponse::success(['released' => $released], __('app.api.license.seat_released'));
        }

        return ApiResponse::error(__('app.api.license.seat_not_found'), 404);
    }

    /**
     * 心跳更新
     */
    public function poolHeartbeat(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'seat_identifier' => 'required|string|max:100',
        ]);

        $result = $this->seatPoolService->heartbeat($license, $validated['seat_identifier']);

        return ApiResponse::success(['updated' => $result]);
    }

    /**
     * 取消排队
     */
    public function poolCancelQueue(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'seat_identifier' => 'required|string|max:100',
        ]);

        $cancelled = $this->seatPoolService->cancelQueue($license, $validated['seat_identifier']);

        return ApiResponse::success(['cancelled' => $cancelled]);
    }

    /**
     * 更新席位池配置
     */
    public function poolUpdateConfig(Request $request, License $license): JsonResponse
    {
        $this->authorize('update', $license);

        $validated = $request->validate([
            'pool_mode' => 'nullable|in:shared,exclusive,auto',
            'pool_timeout_minutes' => 'nullable|integer|min:1|max:1440',
            'pool_waiting_limit' => 'nullable|integer|min:1|max:500',
        ]);

        $updated = $this->seatPoolService->updatePoolConfig($license, $validated);

        return ApiResponse::success($updated, __('app.api.license.pool_updated'));
    }

    /**
     * 批量释放过期席位
     */
    public function poolBatchReleaseExpired(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $results = $this->seatPoolService->batchReleaseExpiredSeats($tenantId);

        return ApiResponse::success([
            'licenses_affected' => count($results),
            'details' => $results,
        ], __('app.api.license.pool_cleaned'));
    }

    // ═══════════════ License Key 前缀格式化 (M3-23) ═══════════════

    /**
     * 获取 License Key 的可读格式信息
     */
    public function keyFormat(int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        $formatted = $this->keyPrefixFormatter->format($license);

        return ApiResponse::success([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'type' => $license->type,
            'formatted' => $formatted,
            'label' => $this->keyGenerator->getReadableType($license->license_key),
        ]);
    }

    /**
     * 批量格式化 License Key
     */
    public function batchKeyFormat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:licenses,id',
        ]);

        $licenses = License::whereIn('id', $validated['ids'])->get();
        $results = $this->keyPrefixFormatter->formatBatch($licenses);

        return ApiResponse::success([
            'total' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * 管理端：执行所有 License Key 前缀迁移
     *
     * POST /admin/license-key/prefix-migrate
     */
    public function prefixMigrate(): JsonResponse
    {
        $stats = $this->keyPrefixFormatter->migrateAll();

        return ApiResponse::success($stats, __('app.api.license.prefix_migrated_detail', [
            'updated' => $stats['updated'],
            'total' => $stats['total'],
        ]));
    }
}