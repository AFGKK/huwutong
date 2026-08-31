<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\TrialLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrialController extends Controller
{
    public function __construct(
        protected TrialLicenseService $trialService,
    ) {}

    /**
     * 创建 Trial 试用
     *
     * POST /api/trial
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'tenant_id' => 'required|integer|exists:tenants,id',
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $product = Product::findOrFail($data['product_id']);
        $customer = Customer::findOrFail($data['customer_id']);

        try {
            $license = $this->trialService->createTrial($tenant, $customer, $product);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('TRIAL_NOT_ALLOWED', $e->validator->errors()->first(), 422);
        }

        return ApiResponse::created([
            'license' => $license,
            'trial_days' => TrialLicenseService::DEFAULT_TRIAL_DAYS,
            'expires_at' => $license->expires_at,
        ], __('app.api.trial.trial_created'));
    }

    /**
     * 查询 Trial 状态
     *
     * GET /api/trial/{license}
     */
    public function status(int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);

        if ($license->type !== 'trial') {
            return ApiResponse::success([
                'type' => $license->type,
                'message' => __('app.api.trial.non_trial_license'),
            ]);
        }

        $result = $this->trialService->checkTrialStatus($license);

        return ApiResponse::success(array_merge($result, [
            'license_key' => $license->license_key,
            'type' => $license->type,
            'status' => $license->status,
            'expires_at' => $license->expires_at,
            'activated_at' => $license->activated_at,
        ]));
    }

    /**
     * Trial 一键转正
     *
     * POST /api/trial/{license}/convert
     */
    public function convert(Request $request, int $licenseId): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:standard,enterprise,development',
            'days' => 'nullable|integer|min:30|max:3650',
            'max_devices' => 'nullable|integer|min:1|max:1000',
        ]);

        $license = License::findOrFail($licenseId);

        try {
            $updated = $this->trialService->convertToPaid(
                $license,
                $data['type'],
                $data['days'] ?? 365,
                $data['max_devices'] ?? 3,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('CONVERSION_FAILED', $e->validator->errors()->first(), 422);
        }

        return ApiResponse::success([
            'license' => $updated,
        ], __('app.api.trial.converted_to', ['type' => $data['type']]));
    }
}
