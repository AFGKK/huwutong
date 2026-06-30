<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ZapierIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zapier/Make 无代码集成控制器 (M3-43)
 */
class ZapierController extends Controller
{
    public function __construct(
        protected ZapierIntegrationService $zapierService,
    ) {}

    /**
     * 验证 API Key 中间件
     */
    protected function verifyApiKey(Request $request): bool
    {
        $token = $request->bearerToken();

        if (!$token) {
            return false;
        }

        // 验证 API Key
        $apiKey = config('zapier.api_key');
        if ($apiKey && $token === $apiKey) {
            return true;
        }

        // 从数据库验证 API Key
        $key = \App\Models\ApiKey::where('key', $token)
            ->where('is_active', true)
            ->first();

        return $key !== null;
    }

    /**
     * ─── Zapier 触发器 ───
     */
    public function triggerNewLicenses(Request $request): JsonResponse
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 50);

        $data = $this->zapierService->newLicenses($offset, $limit);

        return response()->json($data);
    }

    public function triggerExpiringLicenses(Request $request): JsonResponse
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 50);

        $data = $this->zapierService->expiringLicenses(30, $offset, $limit);

        return response()->json($data);
    }

    public function triggerNewCustomers(Request $request): JsonResponse
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 50);

        $data = $this->zapierService->newCustomers($offset, $limit);

        return response()->json($data);
    }

    public function triggerLicenseActivated(Request $request): JsonResponse
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 50);

        $data = $this->zapierService->licenseActivations($offset, $limit);

        return response()->json($data);
    }

    /**
     * ─── Zapier 动作 ───
     */
    public function actionCreateLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'type' => 'required|string',
            'customer_id' => 'required|integer|exists:customers,id',
            'seats' => 'required|integer|min:1',
            'expires_in_days' => 'nullable|integer|min:1',
        ]);

        $result = $this->zapierService->createLicense($validated);

        return response()->json($result, 201);
    }

    public function actionSuspendLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|exists:licenses,license_key',
            'reason' => 'nullable|string',
        ]);

        $result = $this->zapierService->suspendLicense(
            $validated['license_key'],
            $validated['reason'] ?? null,
        );

        return response()->json($result);
    }

    public function actionRevokeLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|exists:licenses,license_key',
        ]);

        $result = $this->zapierService->revokeLicense($validated['license_key']);

        return response()->json($result);
    }

    /**
     * ─── Zapier 搜索 ───
     */
    public function searchFindLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $results = $this->zapierService->findLicense($validated['query']);

        return response()->json($results);
    }

    /**
     * ─── Zapier 资源列表 (动态下拉字段) ───
     */
    public function resourceProducts(): JsonResponse
    {
        $products = $this->zapierService->listProducts();

        return response()->json($products);
    }

    public function resourceCustomers(): JsonResponse
    {
        $customers = $this->zapierService->listCustomers();

        return response()->json($customers);
    }

    // ──────────────── Make.com 端点 ────────────────

    public function makeTriggersLicenses(Request $request): JsonResponse
    {
        return $this->triggerNewLicenses($request);
    }

    public function makeTriggersExpiring(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $data = $this->zapierService->expiringLicenses($days);

        return response()->json(['data' => $data]);
    }

    public function makeTriggersCustomers(Request $request): JsonResponse
    {
        return $this->triggerNewCustomers($request);
    }

    public function makeActionCreateLicense(Request $request): JsonResponse
    {
        return $this->actionCreateLicense($request);
    }

    public function makeActionSuspendLicense(Request $request): JsonResponse
    {
        return $this->actionSuspendLicense($request);
    }

    public function makeActionRevokeLicense(Request $request): JsonResponse
    {
        return $this->actionRevokeLicense($request);
    }

    public function makeSearchFindLicense(Request $request): JsonResponse
    {
        return $this->searchFindLicense($request);
    }

    // ──────────────── 管理端 API ────────────────

    /**
     * 获取仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $templates = $this->zapierService->getWorkflowTemplates();
        $categories = collect($templates)->groupBy('category')->map(fn($g) => $g->count());

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) config('zapier.enabled'),
                'has_api_key' => !empty(config('zapier.api_key')),
                'template_count' => count($templates),
                'categories' => $categories,
                'zapier_app_dir' => 'deploy/zapier-app/',
                'make_app_dir' => 'deploy/make-app/',
            ],
        ]);
    }

    /**
     * 获取工作流模板
     */
    public function workflowTemplates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => config('zapier.workflow_templates', []),
        ]);
    }

    /**
     * 获取嵌入配置代码
     */
    public function embedConfig(): JsonResponse
    {
        $baseUrl = config('app.url');
        $zapierJson = json_decode(file_get_contents(base_path('deploy/zapier-app/index.json')), true);
        $makeJson = json_decode(file_get_contents(base_path('deploy/make-app/index.json')), true);

        return response()->json([
            'success' => true,
            'data' => [
                'zapier' => [
                    'app_json_url' => $baseUrl . '/deploy/zapier-app/index.json',
                    'platform' => 'https://zapier.com/apps/hwt-license/integrations',
                    'publish_guide' => 'cd deploy/zapier-app && zapier push',
                ],
                'make' => [
                    'app_json_url' => $baseUrl . '/deploy/make-app/index.json',
                    'platform' => 'https://make.com/apps/hwt-license',
                    'import_guide' => '在 Make.com 后台 → Apps → 导入 JSON',
                ],
            ],
        ]);
    }
}
