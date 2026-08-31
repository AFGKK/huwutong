<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiVersion;
use App\Services\ApiVersionManagerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiVersionController extends Controller
{
    public function __construct(
        protected ApiVersionManagerService $versionManager,
    ) {}

    /**
     * 获取所有版本列表
     */
    public function index(): JsonResponse
    {
        $versions = $this->versionManager->getAllVersions();

        return ApiResponse::success($versions);
    }

    /**
     * 获取版本详情
     */
    public function show(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $routes = $apiVersion->routes()->orderBy('method')->orderBy('path')->get();

        return ApiResponse::success([
            'version' => $apiVersion,
            'routes' => $routes,
        ]);
    }

    /**
     * 创建新版本
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string|max:20|unique:api_versions,version',
            'name' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,deprecated,sunset,retired',
            'changelog' => 'nullable|string',
            'migration_guide' => 'nullable|string',
            'deprecation_notice' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_version.validation_failed'), $validator->errors()->toArray());
        }

        $apiVersion = $this->versionManager->createVersion($validator->validated());

        return ApiResponse::created($apiVersion, __('app.api.api_version.version_created'));
    }

    /**
     * 更新版本
     */
    public function update(Request $request, string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,deprecated,sunset,retired',
            'changelog' => 'nullable|string',
            'migration_guide' => 'nullable|string',
            'deprecation_notice' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'deprecated_at' => 'nullable|date',
            'sunset_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_version.validation_failed'), $validator->errors()->toArray());
        }

        $apiVersion = $this->versionManager->updateVersion($apiVersion, $validator->validated());

        return ApiResponse::success($apiVersion, __('app.api.api_version.version_updated'));
    }

    /**
     * 标记版本为废弃
     */
    public function deprecate(Request $request, string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        if ($apiVersion->isRetired()) {
            return ApiResponse::error('VERSION_RETIRED', __('app.api.api_version.version_retired_block'), 400);
        }

        $validator = Validator::make($request->all(), [
            'migration_guide' => 'nullable|string',
            'deprecation_notice' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_version.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $apiVersion = $this->versionManager->deprecateVersion(
            $apiVersion,
            $data['migration_guide'] ?? null,
            $data['deprecation_notice'] ?? null,
        );

        return ApiResponse::success([
            'version' => $apiVersion,
            'sunset_at' => $apiVersion->sunset_at->format('Y-m-d H:i:s'),
            'grace_period_months' => ApiVersionManagerService::DEPRECATION_GRACE_MONTHS,
            'notice' => "This version will be sunset on {$apiVersion->sunset_at->format('Y-m-d')}",
        ], __('app.api.api_version.version_deprecated'));
    }

    /**
     * 停用版本（Sunset）
     */
    public function sunset(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        if (!$apiVersion->isDeprecated()) {
            return ApiResponse::error('VERSION_NOT_DEPRECATED', __('app.api.api_version.version_sunset_block'), 400);
        }

        $this->versionManager->sunsetVersion($apiVersion);

        return ApiResponse::success($apiVersion->fresh(), __('app.api.api_version.version_sunset'));
    }

    /**
     * 退役版本
     */
    public function retire(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $this->versionManager->retireVersion($apiVersion);

        return ApiResponse::success($apiVersion->fresh(), __('app.api.api_version.version_retired'));
    }

    /**
     * 删除版本
     */
    public function destroy(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $this->versionManager->deleteVersion($apiVersion);

        return ApiResponse::noContent();
    }

    /**
     * 获取版本路由列表
     */
    public function routes(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $routes = $apiVersion->routes()->orderBy('method')->orderBy('path')->get();

        return ApiResponse::success($routes);
    }

    /**
     * 注册版本路由
     */
    public function registerRoute(Request $request, string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'required|string|max:200',
            'route_name' => 'nullable|string|max:100',
            'controller' => 'nullable|string|max:200',
            'action' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_version.validation_failed'), $validator->errors()->toArray());
        }

        $route = $this->versionManager->registerRoute(
            $apiVersion,
            $validator->validated()['method'],
            $validator->validated()['path'],
            $validator->validated()['route_name'] ?? null,
            $validator->validated()['controller'] ?? null,
            $validator->validated()['action'] ?? null,
        );

        return ApiResponse::created($route, __('app.api.api_version.route_registered'));
    }

    /**
     * 批量注册路由
     */
    public function importRoutes(Request $request, string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $validator = Validator::make($request->all(), [
            'routes' => 'required|array|min:1',
            'routes.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'routes.*.path' => 'required|string|max:200',
            'routes.*.route_name' => 'nullable|string|max:100',
            'routes.*.controller' => 'nullable|string|max:200',
            'routes.*.action' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_version.validation_failed'), $validator->errors()->toArray());
        }

        $count = $this->versionManager->importRoutes($apiVersion, $validator->validated()['routes']);

        return ApiResponse::success(['imported_count' => $count], __('app.api.api_version.routes_imported', ['count' => $count]));
    }

    /**
     * 删除路由
     */
    public function deleteRoute(string $version, int $routeId): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $route = $apiVersion->routes()->find($routeId);

        if (!$route) {
            return ApiResponse::notFound(__('app.api.api_version.route_not_found'));
        }

        $route->delete();

        return ApiResponse::noContent();
    }

    // ─── 调用统计 ───

    /**
     * 获取版本调用统计
     */
    public function callStats(Request $request, string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $stats = $this->versionManager->getVersionCallStats($apiVersion->id, $startDate, $endDate);

        return ApiResponse::success([
            'version' => $apiVersion->version,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'stats' => $stats,
        ]);
    }

    /**
     * 获取版本使用趋势
     */
    public function usageTrend(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $trend = $this->versionManager->getVersionUsageTrend($startDate, $endDate);

        return ApiResponse::success($trend);
    }

    /**
     * 获取版本影响分析
     */
    public function impactAnalysis(string $version): JsonResponse
    {
        $apiVersion = $this->versionManager->getVersion($version);

        if (!$apiVersion) {
            return ApiResponse::notFound("API version '{$version}' not found");
        }

        $analysis = $this->versionManager->getImpactAnalysis($apiVersion->id);

        return ApiResponse::success([
            'version' => $apiVersion->version,
            'affected_tenants_count' => count($analysis),
            'tenants' => $analysis,
        ]);
    }

    // ─── 版本信息端点 ───

    /**
     * 获取默认版本信息（公开端点）
     */
    public function defaultInfo(): JsonResponse
    {
        $default = $this->versionManager->getDefaultVersion();

        if (!$default) {
            return ApiResponse::success([
                'versions' => [],
                'default' => null,
                'available_versions' => [],
            ]);
        }

        $active = $this->versionManager->getActiveVersions();

        return ApiResponse::success([
            'versions' => $this->versionManager->getAllVersions(),
            'default' => $default->version,
            'default_base_path' => $default->base_path,
            'available_versions' => array_column($active, 'version'),
        ]);
    }
}
