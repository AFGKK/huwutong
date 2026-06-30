<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppInstallation;
use App\Models\MarketplaceDeveloper;
use App\Services\OpenPlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 开放平台 / 应用市场 (M3-28)
 */
class OpenPlatformController extends Controller
{
    public function __construct(
        protected OpenPlatformService $service,
    ) {}

    // ─── 管理端 ───

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }

    public function metadata(): JsonResponse
    {
        return ApiResponse::success($this->service->getMetadata());
    }

    public function developers(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listDevelopers(
            $request->only(['status', 'search']),
            (int) $request->input('per_page', 20)
        ));
    }

    public function verifyDeveloper(Request $request, MarketplaceDeveloper $developer): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,suspend',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->service->verifyDeveloper(
                $developer,
                $request->user(),
                $validated['action'],
                $validated['notes'] ?? null,
            );
            return ApiResponse::success($result, '开发者状态已更新');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function apps(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listApps(
            $request->only(['status', 'category', 'developer_id', 'search']),
            (int) $request->input('per_page', 20)
        ));
    }

    public function pendingApps(Request $request): JsonResponse
    {
        $request->merge(['status' => 'pending_review']);
        return $this->apps($request);
    }

    public function showApp(MarketplaceApp $app): JsonResponse
    {
        $app->load(['developer.user:id,name,email', 'versions', 'reviewLogs.reviewer:id,name', 'reviewer:id,name']);
        return ApiResponse::success($app);
    }

    public function reviewApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,request_changes,suspend',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $result = $this->service->reviewApp(
                $app,
                $request->user(),
                $validated['action'],
                $validated['notes'] ?? null,
            );
            return ApiResponse::success($result, '审核完成');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    /**
     * 紧急下架应用 — 绕过常规审核流程，一键下架并通知所有已安装用户。
     */
    public function suspendApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        try {
            $result = $this->service->reviewApp(
                $app,
                $request->user(),
                'suspend',
                $validated['reason'] ?? '紧急下架',
            );
            return ApiResponse::success($result, '应用已紧急下架，已通知所有安装用户');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    /**
     * 恢复已下架的应用。
     */
    public function unsuspendApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        if ($app->status !== 'suspended') {
            return ApiResponse::validationError('仅已下架的应用可恢复');
        }

        $result = $this->service->reviewApp(
            $app,
            $request->user(),
            'approve',
            '恢复上架',
        );
        return ApiResponse::success($result, '应用已恢复上架');
    }

    /**
     * 推送强制更新通知给所有已安装用户。
     */
    public function forceUpdate(Request $request, MarketplaceApp $app): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:2000',
            'version' => 'nullable|string|max:30',
        ]);

        try {
            $count = $this->service->forceUpdateNotification(
                $app,
                $validated['reason'] ?? '存在安全更新，请立即升级',
                $validated['version'] ?? null,
            );
            return ApiResponse::success(['notified_count' => $count], "已向 {$count} 个安装用户推送强制更新通知");
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function installations(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listInstallations(
            $request->only(['status', 'app_id']),
            (int) $request->input('per_page', 20)
        ));
    }

    // ─── 开发者端 ───

    public function registerDeveloper(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:100',
            'company_name' => 'nullable|string|max:200',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        try {
            $developer = $this->service->registerDeveloper($request->user(), $validated);
            return ApiResponse::created($developer, '开发者注册成功，等待审核');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function myDeveloper(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        return ApiResponse::success($developer);
    }

    public function myApps(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) {
            return ApiResponse::validationError('请先注册为开发者');
        }

        return ApiResponse::success($this->service->listApps(
            array_merge($request->only(['status', 'search']), ['developer_id' => $developer->id]),
            (int) $request->input('per_page', 20)
        ));
    }

    public function createApp(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) {
            return ApiResponse::validationError('请先注册为开发者');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:100',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:10000',
            'category' => 'nullable|in:integration,automation,analytics,security,billing,other',
            'icon_url' => 'nullable|url|max:500',
            'pricing_type' => 'nullable|in:free,paid,subscription',
            'price' => 'nullable|numeric|min:0',
            'webhook_url' => 'nullable|url|max:500',
            'permissions' => 'nullable|array',
            'documentation_url' => 'nullable|url|max:500',
            'repository_url' => 'nullable|url|max:500',
            'version' => 'nullable|string|max:30',
            'changelog' => 'nullable|string|max:5000',
            'package_url' => 'nullable|url|max:500',
        ]);

        try {
            $app = $this->service->createApp($developer, $validated);
            return ApiResponse::created($app, '应用已创建');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function updateApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        $this->ensureAppOwner($request, $app);

        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:10000',
            'category' => 'nullable|in:integration,automation,analytics,security,billing,other',
            'icon_url' => 'nullable|url|max:500',
            'pricing_type' => 'nullable|in:free,paid,subscription',
            'price' => 'nullable|numeric|min:0',
            'webhook_url' => 'nullable|url|max:500',
            'permissions' => 'nullable|array',
            'documentation_url' => 'nullable|url|max:500',
            'repository_url' => 'nullable|url|max:500',
        ]);

        try {
            return ApiResponse::success($this->service->updateApp($app, $validated), '应用已更新');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function submitApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        $this->ensureAppOwner($request, $app);

        try {
            return ApiResponse::success($this->service->submitForReview($app), '已提交审核');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function addVersion(Request $request, MarketplaceApp $app): JsonResponse
    {
        $this->ensureAppOwner($request, $app);

        $validated = $request->validate([
            'version' => 'required|string|max:30',
            'changelog' => 'nullable|string|max:5000',
            'package_url' => 'nullable|url|max:500',
            'min_platform_version' => 'nullable|string|max:30',
        ]);

        $version = $this->service->addVersion($app, $validated);
        return ApiResponse::created($version, '版本已添加');
    }

    // ─── 应用市场（浏览/安装）───

    public function marketplace(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listMarketplaceApps(
            $request->only(['category', 'search']),
            (int) $request->input('per_page', 20)
        ));
    }

    public function installApp(Request $request, MarketplaceApp $app): JsonResponse
    {
        $validated = $request->validate([
            'config' => 'nullable|array',
        ]);

        try {
            $installation = $this->service->installApp(
                $app,
                $request->user(),
                $request->user()->tenant_id,
                $validated['config'] ?? [],
            );
            return ApiResponse::created($installation, '应用安装成功');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function uninstallApp(Request $request, MarketplaceAppInstallation $installation): JsonResponse
    {
        if ($installation->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            return ApiResponse::success($this->service->uninstallApp($installation), '应用已卸载');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function myInstallations(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listInstallations(
            ['status' => $request->input('status', 'active'), 'user_id' => $request->user()->id],
            (int) $request->input('per_page', 20)
        ));
    }

    // ══════════════════════════════════════════
    //  排行榜
    // ══════════════════════════════════════════

    public function rankings(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getRankings(
                $request->input('type', 'downloads'),
                $request->input('category'),
                (int) $request->input('limit', 20)
            )
        );
    }

    // ══════════════════════════════════════════
    //  下载趋势
    // ══════════════════════════════════════════

    public function downloadTrend(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        return ApiResponse::success($this->service->getDownloadTrend($days));
    }

    public function appDownloadTrend(Request $request, MarketplaceApp $app): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        return ApiResponse::success($this->service->getAppDownloadTrend($app->id, $days));
    }

    // ══════════════════════════════════════════
    //  版本更新检查
    // ══════════════════════════════════════════

    public function checkUpdate(Request $request, MarketplaceApp $app): JsonResponse
    {
        $validated = $request->validate(['current_version' => 'required|string']);
        $update = $this->service->checkForUpdate($app->id, $validated['current_version']);

        if (!$update) {
            return ApiResponse::success(['update_available' => false, 'message' => '已是最新版本'], '已是最新版本');
        }

        return ApiResponse::success(array_merge(['update_available' => true], $update));
    }

    // ══════════════════════════════════════════
    //  市场摘要
    // ══════════════════════════════════════════

    public function summary(): JsonResponse
    {
        return ApiResponse::success($this->service->getMarketplaceSummary());
    }

    // ══════════════════════════════════════════
    //  文件上传
    // ══════════════════════════════════════════

    public function uploadPackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        try {
            $result = $this->service->uploadPackage($request->file('file'));
            return ApiResponse::created($result, '上传成功');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function uploadScreenshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|image|max:5120|mimes:jpg,jpeg,png,webp',
        ]);

        try {
            $result = $this->service->uploadScreenshot($request->file('file'));
            return ApiResponse::created($result, '截图上传成功');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    // ══════════════════════════════════════════
    //  开发者收益
    // ══════════════════════════════════════════

    public function initEarnings(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) return ApiResponse::validationError('请先注册为开发者');

        $account = $this->service->initEarningsAccount($developer);
        return ApiResponse::success($account, '收益账户已开通');
    }

    public function myEarnings(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) return ApiResponse::validationError('请先注册为开发者');

        return ApiResponse::success($this->service->getDeveloperEarnings($developer->id));
    }

    public function developerEarnings(int $developerId): JsonResponse
    {
        return ApiResponse::success($this->service->getDeveloperEarnings($developerId));
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) return ApiResponse::validationError('请先注册为开发者');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:bank,alipay,wechat,paypal',
            'account' => 'required|string|max:200',
            'account_name' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:200',
            'account_no' => 'nullable|string|max:100',
        ]);

        try {
            $withdrawal = $this->service->developerWithdraw($developer->id, $validated['amount'], $validated['channel'], $validated);
            return ApiResponse::created($withdrawal, '提现请求已提交');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function myWithdrawals(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) return ApiResponse::validationError('请先注册为开发者');

        return ApiResponse::success($this->service->getDeveloperWithdrawals($developer->id, (int) $request->input('per_page', 20)));
    }

    public function updateTaxInfo(Request $request): JsonResponse
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer) return ApiResponse::validationError('请先注册为开发者');

        $validated = $request->validate([
            'tax_id' => 'nullable|string|max:100',
            'tax_type' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:500',
        ]);

        return ApiResponse::success(
            $this->service->updateDeveloperTaxInfo($developer->id, $validated),
            '税务信息已更新'
        );
    }

    public function financialDashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->getFinancialDashboard());
    }

    protected function ensureAppOwner(Request $request, MarketplaceApp $app): void
    {
        $developer = $this->service->getDeveloperForUser($request->user());
        if (!$developer || $app->developer_id !== $developer->id) {
            abort(403, '无权操作此应用');
        }
    }
}
