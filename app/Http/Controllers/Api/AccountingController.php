<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingIntegration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Customer;
use App\Services\Accounting\AccountingServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountingController extends Controller
{
    /**
     * 集成列表
     */
    public function index()
    {
        $integrations = AccountingIntegration::withCount('syncMappings')->orderBy('provider')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => AccountingServiceFactory::providers(),
                'integrations' => $integrations,
            ],
        ]);
    }

    /**
     * 创建集成配置
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:quickbooks,xero,yonyou,kingdee',
            'name' => 'nullable|string|max:255',
            'environment' => 'sometimes|in:sandbox,production',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'api_endpoint' => 'nullable|string|url',
            'company_id' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'sync_config' => 'nullable|array',
        ]);

        $integration = AccountingIntegration::create(array_merge(
            $validated,
            [
                'tenant_id' => $request->user()->tenant_id ?? 1,
                'environment' => $validated['environment'] ?? 'sandbox',
                'is_active' => false,
            ]
        ));

        return response()->json(['success' => true, 'data' => $integration], 201);
    }

    /**
     * 更新集成配置
     */
    public function update(Request $request, AccountingIntegration $integration)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'environment' => 'sometimes|in:sandbox,production',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'api_endpoint' => 'nullable|string|url',
            'company_id' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'sync_config' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $integration->update($validated);

        return response()->json(['success' => true, 'data' => $integration]);
    }

    /**
     * 删除集成配置
     */
    public function destroy(AccountingIntegration $integration)
    {
        $integration->syncMappings()->delete();
        $integration->syncLogs()->delete();
        $integration->delete();

        return response()->json(['success' => true]);
    }

    /**
     * 获取OAuth授权URL
     */
    public function authorizeUrl(AccountingIntegration $integration)
    {
        $service = AccountingServiceFactory::make($integration);

        if ($integration->provider === 'quickbooks' && method_exists($service, 'getAuthorizationUrl')) {
            return response()->json([
                'success' => true,
                'data' => ['url' => $service->getAuthorizationUrl()],
            ]);
        }

        if ($integration->provider === 'xero' && method_exists($service, 'getAuthorizationUrl')) {
            return response()->json([
                'success' => true,
                'data' => ['url' => $service->getAuthorizationUrl()],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Provider does not support OAuth'], 400);
    }

    /**
     * OAuth 回调处理
     */
    public function oauthCallback(Request $request, string $provider)
    {
        $integration = AccountingIntegration::where('provider', $provider)
            ->where('tenant_id', $request->user()->tenant_id ?? 1)
            ->firstOrFail();

        $service = AccountingServiceFactory::make($integration);
        $code = $request->get('code');

        if (!$code) {
            return response()->json(['success' => false, 'message' => 'Missing authorization code'], 400);
        }

        if ($service->handleCallback($code)) {
            return response()->json(['success' => true, 'message' => '授权成功']);
        }

        return response()->json(['success' => false, 'message' => '授权失败'], 500);
    }

    /**
     * 测试连接
     */
    public function testConnection(AccountingIntegration $integration)
    {
        $service = AccountingServiceFactory::make($integration);
        $result = $service->checkConnection();

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 同步单张发票到会计系统
     */
    public function syncInvoice(AccountingIntegration $integration, Invoice $invoice)
    {
        $service = AccountingServiceFactory::make($integration);
        $result = $service->syncInvoice($invoice);

        return response()->json(['success' => $result['success'], 'data' => $result]);
    }

    /**
     * 批量同步待处理单据
     */
    public function syncPending(AccountingIntegration $integration)
    {
        $service = AccountingServiceFactory::make($integration);
        $log = $service->syncPending();

        return response()->json([
            'success' => true,
            'data' => [
                'log_id' => $log->id,
                'total' => $log->total_count,
                'success' => $log->success_count,
                'failed' => $log->fail_count,
            ],
        ]);
    }

    /**
     * 同步日志
     */
    public function syncLogs(AccountingIntegration $integration)
    {
        $logs = $integration->syncLogs()->orderByDesc('created_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * 同步映射列表
     */
    public function syncMappings(AccountingIntegration $integration, Request $request)
    {
        $query = $integration->syncMappings();

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->local_type) {
            $query->where('local_type', $request->local_type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }
}
