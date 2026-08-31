<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiChangelog;
use App\Models\ApiDocCodeSnippet;
use App\Models\ApiDocEndpoint;
use App\Models\ApiDocSchema;
use App\Models\ApiSdkConfig;
use App\Services\ApiDocsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiDocsController extends Controller
{
    public function __construct(
        protected ApiDocsService $apiDocs,
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getDashboard());
    }

    // ─── 端点管理 ───

    public function endpoints(Request $request): JsonResponse
    {
        $filters = $request->only(['api_version_id', 'method', 'group', 'tag', 'status', 'search', 'per_page']);
        return ApiResponse::success($this->apiDocs->getEndpointList($filters));
    }

    public function showEndpoint(int $id): JsonResponse
    {
        $endpoint = $this->apiDocs->getEndpoint($id);
        return ApiResponse::success($endpoint);
    }

    public function createEndpoint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'required|string|max:500',
            'summary' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
            'tag' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,deprecated,beta,experimental',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $endpoint = $this->apiDocs->createEndpoint($validator->validated());
        return ApiResponse::created($endpoint, __('app.api.api_docs.endpoint_created'));
    }

    public function updateEndpoint(Request $request, int $id): JsonResponse
    {
        $endpoint = ApiDocEndpoint::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'sometimes|string|max:500',
            'summary' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
            'tag' => 'nullable|string|max:100',
            'parameters' => 'nullable|array',
            'request_body' => 'nullable|array',
            'responses' => 'nullable|array',
            'example_request' => 'nullable|array',
            'example_response' => 'nullable|array',
            'code_examples' => 'nullable|array',
            'status' => 'nullable|string|in:active,deprecated,beta,experimental',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $endpoint = $this->apiDocs->updateEndpoint($endpoint, $validator->validated());
        return ApiResponse::success($endpoint, __('app.api.api_docs.endpoint_updated'));
    }

    public function deleteEndpoint(int $id): JsonResponse
    {
        $endpoint = ApiDocEndpoint::findOrFail($id);
        $this->apiDocs->deleteEndpoint($endpoint);
        return ApiResponse::success(null, __('app.api.api_docs.endpoint_deleted'));
    }

    // ─── 标签 ───

    public function tags(): JsonResponse
    {
        $tags = \App\Models\ApiDocTag::orderBy('sort_order')->orderBy('name')->get();
        return ApiResponse::success($tags);
    }

    // ─── Schema 注册表 ───

    public function schemas(): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getSchemas());
    }

    // ─── 分组 ───

    public function groups(): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getGroups());
    }

    // ─── 代码片段 ───

    public function addSnippet(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint_id' => 'required|exists:api_doc_endpoints,id',
            'language' => 'required|string|max:30',
            'code' => 'required|string',
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $snippet = $this->apiDocs->addCodeSnippet($validator->validated());
        return ApiResponse::created($snippet, __('app.api.api_docs.snippet_added'));
    }

    public function deleteSnippet(int $id): JsonResponse
    {
        $this->apiDocs->deleteCodeSnippet($id);
        return ApiResponse::success(null, __('app.api.api_docs.snippet_deleted'));
    }

    // ─── 测试控制台 ───

    public function sendTestRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'url' => 'required|string|max:1000',
            'headers' => 'nullable|array',
            'body' => 'nullable',
            'endpoint_id' => 'nullable|exists:api_doc_endpoints,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $result = $this->apiDocs->sendTestRequest(
            $request->user()->id,
            $validator->validated()
        );

        return ApiResponse::success($result, __('app.api.api_docs.request_sent'));
    }

    public function testHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->apiDocs->getTestHistory($request->user()->id)
        );
    }

    // ─── SDK ───

    public function sdks(): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getActiveSdks());
    }

    public function generateSdk(Request $request, string $language): JsonResponse
    {
        $language = strtolower($language);
        $supported = ['php', 'python', 'javascript', 'go', 'java', 'ruby'];

        if (!in_array($language, $supported)) {
            return ApiResponse::error('LANGUAGE_NOT_SUPPORTED', __('app.api.api_docs.lang_unsupported', ['language' => $language]), 422);
        }

        $endpointIds = $request->input('endpoint_ids', []);

        try {
            $result = $this->apiDocs->generateSdkClient($language, $endpointIds);
            return ApiResponse::success($result, __('app.api.api_docs.sdk_generated'));
        } catch (\Exception $e) {
            return ApiResponse::error('SDK_GENERATION_FAILED', __('app.api.api_docs.sdk_failed', ['error' => $e->getMessage()]), 500);
        }
    }

    // ─── 变更日志 ───

    public function changelogs(Request $request): JsonResponse
    {
        $filters = $request->only(['version', 'type']);
        return ApiResponse::success($this->apiDocs->getChangelogs($filters));
    }

    public function createChangelog(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string|max:30',
            'release_date' => 'required|date',
            'type' => 'required|string|in:update,breaking,new,deprecation,removal',
            'title' => 'required|string|max:300',
            'description' => 'nullable|string',
            'affected_endpoints' => 'nullable|array',
            'migration_guide' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $changelog = $this->apiDocs->createChangelog($validator->validated());
        return ApiResponse::created($changelog, __('app.api.api_docs.changelog_created'));
    }

    // ─── 版本差异对比 ───

    public function versionDiff(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_version_id' => 'nullable|integer|exists:api_versions,id',
            'to_version_id' => 'required|integer|exists:api_versions,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $diff = $this->apiDocs->diffVersions(
            $request->input('from_version_id'),
            $request->input('to_version_id')
        );

        return ApiResponse::success($diff);
    }

    // ─── 公开 API 文档 ───

    /**
     * 公开 API 文档列表（无需认证）
     */
    public function publicDocs(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->apiDocs->getPublicDocs($request->input('version'))
        );
    }

    // ─── 文档抓取 ───

    /**
     * 从路由列表自动抓取生成文档
     */
    public function scanRoutes(Request $request): JsonResponse
    {
        $apiVersionId = $request->input('api_version_id');

        try {
            $routes = \Illuminate\Support\Facades\Route::getRoutes();
            $created = 0;
            $updated = 0;

            foreach ($routes as $route) {
                $path = $route->uri();
                $methods = $route->methods();

                // 只处理 API 路由
                if (!str_starts_with($path, 'api/')) continue;
                // 跳过 OPTIONS/HEAD
                $methods = array_filter($methods, fn($m) => in_array($m, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']));
                if (empty($methods)) continue;

                $action = $route->getAction();
                $controller = $action['controller'] ?? null;
                $middleware = $action['middleware'] ?? [];

                // 提取控制器注释作为摘要
                $summary = null;
                if ($controller) {
                    try {
                        $ref = new \ReflectionMethod(
                            explode('@', $controller)[0],
                            explode('@', $controller)[1]
                        );
                        $comment = $ref->getDocComment();
                        if ($comment) {
                            preg_match('/@summary\s+(.+)/', $comment, $m);
                            $summary = $m[1] ?? null;
                            if (!$summary) {
                                preg_match('/\*\s*(.+)/', $comment, $m);
                                $summary = $m[1] ?? null;
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                foreach ($methods as $method) {
                    ApiDocEndpoint::updateOrCreate(
                        ['api_version_id' => $apiVersionId, 'method' => $method, 'path' => '/' . $path],
                        [
                            'summary' => $summary ?? "{$method} {$path}",
                            'api_version_id' => $apiVersionId,
                            'group' => explode('/', $path)[1] ?? 'general',
                        ]
                    );
                    $created++;
                }
            }

            return ApiResponse::success([
                'created' => $created,
                'message' => __('app.api.api_docs.scan_done', ['count' => $created]),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('SCAN_FAILED', __('app.api.api_docs.scan_failed', ['error' => $e->getMessage()]), 500);
        }
    }

    // ─── M3-09 增强功能 ──────────────────────────────────────

    // ─── 端点收藏 ───

    public function toggleFavorite(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint_id' => 'required|exists:api_doc_endpoints,id',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        return ApiResponse::success(
            $this->apiDocs->toggleFavorite($request->user()->id, $request->input('endpoint_id'), $request->input('note'))
        );
    }

    public function favorites(Request $request): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getUserFavorites($request->user()->id));
    }

    // ─── OpenAPI 导出 ───

    public function exportOpenApi(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->apiDocs->exportOpenApi($request->input('api_version_id'))
        );
    }

    // ─── 自动生成代码片段 ───

    public function autoGenerateSnippets(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint_id' => 'required|exists:api_doc_endpoints,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $endpoint = ApiDocEndpoint::findOrFail($request->input('endpoint_id'));
        $snippets = $this->apiDocs->autoGenerateSnippets($endpoint);

        // 保存自动生成的代码片段
        $saved = [];
        foreach ($snippets as $snippet) {
            $existing = ApiDocCodeSnippet::where('endpoint_id', $endpoint->id)
                ->where('language', $snippet['language'])
                ->first();
            if ($existing) {
                $existing->update(['code' => $snippet['code'], 'title' => $snippet['title']]);
                $saved[] = $existing->fresh();
            } else {
                $saved[] = ApiDocCodeSnippet::create(array_merge($snippet, ['endpoint_id' => $endpoint->id]));
            }
        }

        return ApiResponse::success($saved, __('app.api.api_docs.snippets_generated'));
    }

    // ─── 批量更新端点状态 ───

    public function batchUpdateEndpoints(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint_ids' => 'required|array',
            'endpoint_ids.*' => 'integer|exists:api_doc_endpoints,id',
            'status' => 'required|string|in:active,deprecated,beta,experimental',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $count = ApiDocEndpoint::whereIn('id', $request->input('endpoint_ids'))
            ->update(['status' => $request->input('status')]);

        return ApiResponse::success(['updated' => $count], __('app.api.api_docs.status_updated_n', ['count' => $count]));
    }

    // ─── 端点统计 ───

    public function endpointStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint_id' => 'required|exists:api_doc_endpoints,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $endpointId = $request->input('endpoint_id');

        $stats = [
            'total_tests' => ApiTestRequest::where('endpoint_id', $endpointId)->count(),
            'successful_tests' => ApiTestRequest::where('endpoint_id', $endpointId)->where('status', 'success')->count(),
            'failed_tests' => ApiTestRequest::where('endpoint_id', $endpointId)->where('status', 'failed')->count(),
            'favorite_count' => \App\Models\ApiDocFavorite::where('endpoint_id', $endpointId)->count(),
            'avg_response_time_ms' => (int) ApiTestRequest::where('endpoint_id', $endpointId)
                ->whereNotNull('response_time_ms')
                ->avg('response_time_ms'),
            'last_tested_at' => ApiTestRequest::where('endpoint_id', $endpointId)
                ->orderByDesc('created_at')
                ->value('created_at'),
        ];

        return ApiResponse::success($stats);
    }

    // ═══════════════ Changelog 自动生成 (M3-32) ═══════════════

    /**
     * 自动检测端点变更并生成 Changelog
     */
    public function autoDetectChanges(Request $request): JsonResponse
    {
        $request->validate([
            'api_version_id' => 'required|exists:api_versions,id',
        ]);

        try {
            $result = $this->apiDocs->autoGenerateChangelog($request->input('api_version_id'));

            if ($result['status'] === 'snapshot_created') {
                return ApiResponse::success($result, __('app.api.api_docs.first_snapshot'));
            }

            $message = __('app.api.api_docs.auto_detect_done');
            if ($result['changelogs_created'] > 0) {
                $changes = $result['changes'];
                $parts = [];
                foreach (['added', 'changed', 'deprecated', 'removed', 'reactivated'] as $key) {
                    if (($changes[$key] ?? 0) > 0) {
                        $labels = ['added' => __('app.api.api_docs.detect_label_added'), 'changed' => __('app.api.api_docs.detect_label_changed'), 'deprecated' => __('app.api.api_docs.detect_label_deprecated'), 'removed' => __('app.api.api_docs.detect_label_removed'), 'reactivated' => __('app.api.api_docs.detect_label_reactivated')];
                        $parts[] = "{$labels[$key]} {$changes[$key]}";
                    }
                }
                $message = __('app.api.api_docs.detect_summary', ['parts' => implode('，', $parts), 'count' => $result['changelogs_created']]);
            } else {
                $message = __('app.api.api_docs.detect_unchanged');
            }

            return ApiResponse::success($result, $message);
        } catch (\Exception $e) {
            return ApiResponse::error('AUTO_DETECT_FAILED', __('app.api.api_docs.auto_detect_failed', ['error' => $e->getMessage()]), 500);
        }
    }

    /**
     * 创建端点快照（手动触发）
     */
    public function createSnapshot(Request $request): JsonResponse
    {
        $request->validate([
            'api_version_id' => 'required|exists:api_versions,id',
            'version_label' => 'nullable|string|max:30',
        ]);

        $apiVersion = \App\Models\ApiVersion::findOrFail($request->input('api_version_id'));
        $label = $request->input('version_label', $apiVersion->version . '-snapshot-' . now()->format('Ymd'));

        $count = $this->apiDocs->createSnapshot($request->input('api_version_id'), $label);

        return ApiResponse::success(['count' => $count, 'label' => $label], __('app.api.api_docs.snapshots_created', ['count' => $count]));
    }

    /**
     * 获取自动检测历史
     */
    public function autoDetectHistory(): JsonResponse
    {
        return ApiResponse::success($this->apiDocs->getAutoDetectionHistory());
    }

    // ═══════════════ 多语言 API 文档 (M2-115) ═══════════════

    /**
     * 导出国本地化的 OpenAPI 规范
     */
    public function exportLocalizedOpenApi(Request $request): JsonResponse
    {
        $service = app(\App\Services\MultilingualOpenApiService::class);
        $locale = $request->input('locale') ?: $request->header('Accept-Language');
        $resolvedLocale = \App\Services\MultilingualOpenApiService::resolveLocale($locale);

        $spec = $service->generateOpenApiSpec($resolvedLocale, [
            'api_version_id' => $request->input('api_version_id'),
            'api_version' => $request->input('api_version', '1.0.0'),
            'status' => $request->input('status'),
            'group' => $request->input('group'),
            'include_x_groups' => true,
        ]);

        return ApiResponse::success($spec);
    }

    /**
     * 获取支持的文档语言列表
     */
    public function supportedLocales(): JsonResponse
    {
        $service = app(\App\Services\MultilingualOpenApiService::class);
        return ApiResponse::success([
            'locales' => $service->getSupportedLocales(),
            'default' => \App\Services\MultilingualOpenApiService::DEFAULT_LOCALE,
        ]);
    }

    /**
     * 更新端点多语言翻译
     */
    public function updateEndpointTranslations(Request $request, int $id): JsonResponse
    {
        $endpoint = ApiDocEndpoint::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'translations' => 'required|array',
            'translations.*.summary' => 'nullable|string|max:500',
            'translations.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $endpoint->update(['translations' => $validator->validated()['translations']]);

        return ApiResponse::success($endpoint->fresh(), __('app.api.api_docs.translation_updated'));
    }

    /**
     * 批量导入端点多语言翻译
     */
    public function batchImportTranslations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'translations' => 'required|array',
            'translations.*.endpoint_id' => 'required|exists:api_doc_endpoints,id',
            'translations.*.locale' => 'required|string|in:' . implode(',', \App\Services\MultilingualOpenApiService::SUPPORTED_LOCALES),
            'translations.*.summary' => 'nullable|string|max:500',
            'translations.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.api_docs.validation_failed'), $validator->errors()->toArray());
        }

        $imported = 0;
        foreach ($request->input('translations') as $item) {
            $endpoint = ApiDocEndpoint::find($item['endpoint_id']);
            if (!$endpoint) continue;

            $existing = $endpoint->translations ?? [];
            $locale = $item['locale'];
            unset($item['endpoint_id'], $item['locale']);

            $existing[$locale] = array_merge($existing[$locale] ?? [], $item);
            $endpoint->update(['translations' => $existing]);
            $imported++;
        }

        return ApiResponse::success(['imported' => $imported], __('app.api.api_docs.translations_imported', ['count' => $imported]));
    }

    /**
     * 通过 Accept-Language 提供本地化的端点列表
     */
    public function localizedEndpoints(Request $request): JsonResponse
    {
        $locale = \App\Services\MultilingualOpenApiService::resolveLocale(
            $request->header('Accept-Language')
        );

        $filters = $request->only(['api_version_id', 'method', 'group', 'tag', 'status', 'search', 'per_page']);
        $endpoints = $this->apiDocs->getEndpointList($filters);

        // 本地化端点数据
        if (!empty($endpoints['data'])) {
            foreach ($endpoints['data'] as &$ep) {
                $translations = $ep['translations'] ?? [];
                if (!empty($translations[$locale])) {
                    $loc = $translations[$locale];
                    $ep['localized_summary'] = $loc['summary'] ?? $ep['summary'];
                    $ep['localized_description'] = $loc['description'] ?? ($ep['description'] ?? '');
                } else {
                    $ep['localized_summary'] = $ep['summary'];
                    $ep['localized_description'] = $ep['description'] ?? '';
                }
            }
        }

        return ApiResponse::success($endpoints);
    }
}
