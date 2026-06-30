<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\UrlRedirect;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function __construct(
        protected SeoService $service,
    ) {}

    // ═══════════ SEO 元数据 ═══════════

    /**
     * 获取某个模型的SEO元数据
     */
    public function showMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $model = $this->resolveModel($validated['model_type'], $validated['model_id']);
        if (!$model) {
            return ApiResponse::error('NOT_FOUND', '模型不存在', 404);
        }

        $metadata = $this->service->getMetadataFor($model);
        return ApiResponse::success($metadata);
    }

    /**
     * 创建/更新SEO元数据
     */
    public function upsertMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:160',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'robots' => 'nullable|string|in:index,follow,noindex,nofollow,noindex,follow,index,nofollow',
            'priority' => 'nullable|string|regex:/^0(\.\d)?$|^1(\.0)?$/',
            'change_frequency' => 'nullable|string|in:always,hourly,daily,weekly,monthly,yearly,never',
            'json_ld' => 'nullable|array',
        ]);

        $model = $this->resolveModel($validated['model_type'], $validated['model_id']);
        if (!$model) {
            return ApiResponse::error('NOT_FOUND', '模型不存在', 404);
        }

        $tenantId = $model->tenant_id ?? auth()->user()->tenant_id;
        $metadata = $this->service->upsertMetadata($model, $tenantId, $validated);

        return ApiResponse::success($metadata, 'SEO元数据已保存');
    }

    /**
     * 删除SEO元数据
     */
    public function destroyMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $model = $this->resolveModel($validated['model_type'], $validated['model_id']);
        if (!$model) {
            return ApiResponse::error('NOT_FOUND', '模型不存在', 404);
        }

        $this->service->deleteMetadata($model);
        return ApiResponse::success(null, 'SEO元数据已删除');
    }

    // ═══════════ URL 重定向 ═══════════

    public function listRedirects(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->service->listRedirects(
            $tenantId,
            $request->only(['search', 'status_code', 'is_active']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function showRedirect(UrlRedirect $redirect): JsonResponse
    {
        return ApiResponse::success($redirect);
    }

    public function storeRedirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => 'required|string|max:500',
            'target_url' => 'required|string|max:500',
            'status_code' => 'nullable|integer|in:301,302,307',
            'is_active' => 'nullable|boolean',
            'is_wildcard' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $redirect = $this->service->createRedirect($tenantId, $validated);

        return ApiResponse::success($redirect, '重定向已创建', 201);
    }

    public function updateRedirect(Request $request, UrlRedirect $redirect): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => 'nullable|string|max:500',
            'target_url' => 'nullable|string|max:500',
            'status_code' => 'nullable|integer|in:301,302,307',
            'is_active' => 'nullable|boolean',
            'is_wildcard' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $updated = $this->service->updateRedirect($redirect->id, $validated);
        return ApiResponse::success($updated, '重定向已更新');
    }

    public function destroyRedirect(UrlRedirect $redirect): JsonResponse
    {
        $this->service->deleteRedirect($redirect->id);
        return ApiResponse::success(null, '重定向已删除');
    }

    // ═══════════ 站点地图 ═══════════

    public function sitemap(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $entries = $this->service->getAllIndexableContent($tenantId);
        return ApiResponse::success([
            'entries' => $entries,
            'total' => count($entries),
        ]);
    }

    // ═══════════ 仪表盘 ═══════════

    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success([
            'stats' => $this->service->getDashboardStats($tenantId),
            'suggestions' => $this->service->getGlobalSeoSuggestions($tenantId),
        ]);
    }

    // ═══════════ 批量导入重定向 ═══════════

    public function bulkImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.source' => 'required|string|max:500',
            'entries.*.target' => 'required|string|max:500',
            'entries.*.status_code' => 'nullable|integer|in:301,302,307',
            'entries.*.is_active' => 'nullable|boolean',
            'entries.*.is_wildcard' => 'nullable|boolean',
            'entries.*.notes' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $result = $this->service->bulkImportRedirects($tenantId, $validated['entries']);

        return ApiResponse::success($result, "已导入 {$result['imported']} 条，跳过 {$result['skipped']} 条");
    }

    // ═══════════ 助手 ═══════════

    private function resolveModel(string $type, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($type) {
            'page' => Page::find($id),
            'blog' => BlogPost::find($id),
            'kb' => KbArticle::find($id),
            default => null,
        };
    }
}
