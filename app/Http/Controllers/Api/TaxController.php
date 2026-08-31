<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TaxExemptCertificate;
use App\Models\TaxRate;
use App\Services\TaxCalculatorService;
use App\Services\Tax\TaxProviderService;
use App\Services\Tax\ChinaEInvoiceService;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(
        protected TaxCalculatorService $taxService,
        protected TaxProviderService $taxProvider,
        protected ChinaEInvoiceService $einvoiceService,
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
        ], __('app.api.tax.rate_updated'));
    }

    // ─── 免税证书管理 ───

    /**
     * 免税证书列表
     */
    public function certificates(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return ApiResponse::error('NO_TENANT', __('app.api.tax.no_tenant'), 400);
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
            return ApiResponse::error('NO_TENANT', __('app.api.tax.no_tenant'), 400);
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

        return ApiResponse::created(['id' => $cert->id], __('app.api.tax.cert_created'));
    }

    /**
     * 审批免税证书
     */
    public function approveCertificate(Request $request, TaxExemptCertificate $certificate): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($certificate->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.tax.forbidden'), 403);
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
        ], $validated['status'] === 'approved' ? __('app.api.tax.cert_approved') : __('app.api.tax.cert_rejected'));
    }

    /**
     * 删除免税证书
     */
    public function destroyCertificate(TaxExemptCertificate $certificate): JsonResponse
    {
        $certificate->delete();
        return ApiResponse::success(null, __('app.api.tax.deleted'));
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

    // ═══════════════════════════════════════════════════════════
    // 税务提供商接口
    // ═══════════════════════════════════════════════════════════

    /**
     * 通过外部提供商计算税额
     */
    public function providerCalculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'country_code' => 'required|string|size:2',
            'region_code' => 'nullable|string|max:10',
            'is_b2b' => 'sometimes|boolean',
            'currency' => 'sometimes|string|size:3',
            'customer_id' => 'sometimes|integer',
        ]);

        $result = $this->taxProvider->calculate(
            $validated['amount'],
            $validated['country_code'],
            $validated
        );

        return ApiResponse::success($result);
    }

    /**
     * 税务提供商状态
     */
    public function providerStatus(): JsonResponse
    {
        $providers = [];
        foreach (['local', 'taxjar', 'stripe', 'avalara'] as $p) {
            $config = config("tax-automation.{$p}", []);
            $providers[$p] = [
                'enabled' => $p === 'local' ? true : ($config['enabled'] ?? false),
                'configured' => $p === 'local' ? true : !empty($config['api_key'] ?? $config['secret_key'] ?? $config['account_id'] ?? ''),
            ];
        }

        $einvoice = new ChinaEInvoiceService();

        return ApiResponse::success([
            'providers' => $providers,
            'default_provider' => config('tax-automation.default_provider', 'local'),
            'china_einvoice_configured' => $einvoice->isConfigured(),
            'seller_info' => [
                'country' => config('tax-automation.seller.country_code'),
                'vat_number' => config('tax-automation.seller.vat_number'),
                'eu_vat_number' => config('tax-automation.seller.eu_vat_number'),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 中国电子发票
    // ═══════════════════════════════════════════════════════════

    /**
     * 开具电子发票
     */
    public function issueEInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->einvoiceService->isConfigured()) {
            return ApiResponse::error(__('app.api.tax.e_invoice_unconfigured'), 400);
        }

        $result = $this->einvoiceService->issueInvoice($invoice, $request->all());

        if ($result['success']) {
            return ApiResponse::success($result, __('app.api.tax.e_invoice_issued'));
        }

        return ApiResponse::error($result['error'] ?? __('app.api.tax.e_invoice_failed'), 500);
    }

    /**
     * 开具红字发票（冲红）
     */
    public function issueCreditNote(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->einvoiceService->isConfigured()) {
            return ApiResponse::error(__('app.api.tax.e_invoice_unconfigured'), 400);
        }

        $amount = $request->input('amount', $invoice->amount);
        $reason = $request->input('reason', __('app.api.tax.default_return_reason'));

        $result = $this->einvoiceService->issueCreditNote($invoice, (float) $amount, $reason);

        if ($result['success']) {
            return ApiResponse::success($result, __('app.api.tax.red_invoice_issued'));
        }

        return ApiResponse::error($result['error'] ?? __('app.api.tax.red_invoice_failed'), 500);
    }

    /**
     * 查询电子发票状态
     */
    public function queryEInvoice(string $invoiceNo): JsonResponse
    {
        $result = $this->einvoiceService->queryInvoice($invoiceNo);
        return ApiResponse::success($result);
    }

    /**
     * 电子发票服务状态
     */
    public function eInvoiceStatus(): JsonResponse
    {
        return ApiResponse::success([
            'configured' => $this->einvoiceService->isConfigured(),
            'provider' => config('tax-automation.china_einvoice.fapiao_tong.provider', 'fapiaotong'),
            'taxpayer_id' => config('tax-automation.china_einvoice.fapiao_tong.taxpayer_id'),
        ]);
    }
}
