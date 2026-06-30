<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CrossBorderPayment;
use App\Services\CrossBorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CrossBorderController extends Controller
{
    public function __construct(
        protected CrossBorderService $service
    ) {}

    // ─── 货币转换审计 ───

    public function conversionLogs(Request $request)
    {
        return ApiResponse::success(
            $this->service->getConversionLogs(
                $request->user()->tenant_id,
                $request->only(['from_currency', 'to_currency', 'source', 'from_date', 'to_date', 'customer_id']),
                $request->input('per_page', 20)
            )
        );
    }

    // ─── 跨境支付 ───

    public function payments(Request $request)
    {
        return ApiResponse::success(
            $this->service->getCrossBorderPayments(
                $request->user()->tenant_id,
                $request->only(['currency', 'status', 'payment_gateway', 'from_date', 'to_date']),
                $request->input('per_page', 20)
            )
        );
    }

    public function recordPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|max:10',
            'amount' => 'required|numeric|min:0',
            'payment_gateway' => 'nullable|string|max:30',
            'customer_country' => 'nullable|string|max:10',
            'status' => 'nullable|string|in:pending,completed,failed,refunded',
            'transaction_type' => 'nullable|string|in:payment,refund,chargeback',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->service->recordCrossBorderPayment($data),
            201
        );
    }

    // ─── 月度报表 ───

    public function generateReport(Request $request)
    {
        $reportMonth = $request->input('month', now()->format('Y-m'));

        return ApiResponse::success(
            $this->service->generateMonthlyReport($request->user()->tenant_id, $reportMonth)
        );
    }

    public function monthlyReports(Request $request)
    {
        return ApiResponse::success(
            $this->service->getMonthlyReports(
                $request->user()->tenant_id,
                $request->only(['currency', 'from_month', 'to_month'])
            )
        );
    }

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboardStats($request->user()->tenant_id)
        );
    }

    // ─── 合规检查 ───

    public function checkCompliance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|max:10',
            'amount' => 'required|numeric|min:0',
            'amount_cny' => 'nullable|numeric',
            'customer_country' => 'nullable|string|max:10',
            'transaction_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->performComplianceCheck($request->all())
        );
    }
}
