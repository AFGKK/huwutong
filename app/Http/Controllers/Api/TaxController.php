<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TaxExemptCertificate;
use App\Models\TaxRate;
use App\Services\TaxCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(
        protected TaxCalculatorService $taxService,
    ) {}

    /**
     * 国家税率列表
     */
    public function countries(): JsonResponse
    {
        return ApiResponse::success($this->taxService->getCountryTaxInfo());
    }

    /**
     * 国家子区域税率（如美国各州）
     */
    public function regionTaxes(string $countryCode): JsonResponse
    {
        return ApiResponse::success($this->taxService->getRegionTaxes($countryCode));
    }

    /**
     * 完整的税率列表（管理）
     */
    public function rates(Request $request): JsonResponse
    {
        $query = TaxRate::orderBy('country_code')->orderBy('region_code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('country_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($country = $request->input('country_code')) {
            $query->where('country_code', strtoupper($country));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $rates = $query->paginate($request->input('per_page', 50));

        $rates->getCollection()->transform(function ($r) {
            return [
                'id' => $r->id,
                'country_code' => $r->country_code,
                'region_code' => $r->region_code,
                'name' => $r->name,
                'rate' => $r->rate,
                'rate_percent' => round($r->rate * 100, 2),
                'type' => $r->type,
                'category' => $r->category,
                'description' => $r->description,
                'is_eu' => $r->is_eu,
                'is_active' => $r->is_active,
                'effective_from' => $r->effective_from?->toDateString(),
                'effective_until' => $r->effective_until?->toDateString(),
            ];
        });

        return ApiResponse::success($rates);
    }

    /**
     * 计算税额
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'country_code' => 'required|string|size:2',
            'region_code' => 'nullable|string|max:10',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'is_b2b' => 'nullable|boolean',
            'seller_country' => 'nullable|string|size:2',
        ]);

        $result = $this->taxService->calculate(
            (float) $validated['amount'],
            $validated['country_code'],
            [
                'region_code' => $validated['region_code'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'tenant_id' => $request->user()->tenant?->id,
                'is_b2b' => $validated['is_b2b'] ?? false,
                'seller_country' => $validated['seller_country'] ?? null,
            ]
        );

        return ApiResponse::success($result);
    }

    /**
     * 更新税率
     */
    public function updateRate(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $request->validate([
            'rate' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'description' => 'nullable|string|max:255',
        ]);

        $taxRate->update($validated);

        return ApiResponse::success([
            'id' => $taxRate->id,
            'rate' => $taxRate->rate,
            'rate_percent' => round($taxRate->rate * 100, 2),
            'is_active' => $taxRate->is_active,
        ], '税率已更新');
    }

    // ─── 免税证书管理 ───

    /**
     * 免税证书列表
     */
    public function certificates(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return ApiResponse::error('NO_TENANT', '未关联租户', 400);
        }

        $query = TaxExemptCertificate::with(['customer', 'approver'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $certs = $query->paginate($request->input('per_page', 20));

        $certs->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'certificate_type' => $c->certificate_type,
                'certificate_number' => $c->certificate_number,
                'issuing_country' => $c->issuing_country,
                'status' => $c->status,
                'reason' => $c->reason,
                'valid_from' => $c->valid_from?->toDateString(),
                'valid_until' => $c->valid_until?->toDateString(),
                'customer_name' => $c->customer?->name ?? '—',
                'document_file' => $c->document_file,
                'notes' => $c->notes,
                'is_valid' => $c->isValid(),
                'created_at' => $c->created_at?->toIso8601String(),
                'approved_at' => $c->approved_at?->toIso8601String(),
                'approved_by' => $c->approver?->name,
            ];
        });

        return ApiResponse::success($certs);
    }

    /**
     * 创建免税证书
     */
    public function storeCertificate(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return ApiResponse::error('NO_TENANT', '未关联租户', 400);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'certificate_type' => 'required|string|in:vat_exempt,sales_tax_exempt,reseller',
            'certificate_number' => 'required|string|max:100',
            'issuing_country' => 'required|string|size:2',
            'reason' => 'nullable|string|max:500',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
        ]);

        $cert = TaxExemptCertificate::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $validated['customer_id'] ?? null,
            'certificate_type' => $validated['certificate_type'],
            'certificate_number' => $validated['certificate_number'],
            'issuing_country' => strtoupper($validated['issuing_country']),
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
        ]);

        return ApiResponse::created(['id' => $cert->id], '免税证书已创建，等待审批');
    }

    /**
     * 审批免税证书
     */
    public function approveCertificate(Request $request, TaxExemptCertificate $certificate): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($certificate->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', '无权操作', 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        $certificate->update([
            'status' => $validated['status'],
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
            'approved_by' => $validated['status'] === 'approved' ? $request->user()->id : null,
            'notes' => $validated['notes'] ?? $certificate->notes,
        ]);

        return ApiResponse::success([
            'id' => $certificate->id,
            'status' => $certificate->status,
        ], '免税证书已' . ($validated['status'] === 'approved' ? '批准' : '拒绝'));
    }

    /**
     * 删除免税证书
     */
    public function destroyCertificate(TaxExemptCertificate $certificate): JsonResponse
    {
        $certificate->delete();
        return ApiResponse::success(null, '已删除');
    }

    /**
     * 统计信息
     */
    public function stats(): JsonResponse
    {
        $totalRates = TaxRate::count();
        $activeRates = TaxRate::where('is_active', true)->count();
        $euRates = TaxRate::where('is_eu', true)->whereNull('region_code')->count();
        $pendingCerts = TaxExemptCertificate::where('status', 'pending')->count();

        return ApiResponse::success([
            'total_rates' => $totalRates,
            'active_rates' => $activeRates,
            'eu_countries' => $euRates,
            'pending_certificates' => $pendingCerts,
        ]);
    }
}
