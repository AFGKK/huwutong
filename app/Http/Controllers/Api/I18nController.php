<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\TranslationNamespace;
use App\Services\I18nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class I18nController extends Controller
{
    public function __construct(
        protected I18nService $i18nService
    ) {}

    // ─── Dashboard ──────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->i18nService->getDashboard(),
        ]);
    }

    // ─── Language CRUD ──────────────────────────────────────────

    public function languages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->i18nService->getLanguages(),
        ]);
    }

    public function createLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:20|unique:languages,locale',
            'name' => 'required|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'flag' => 'nullable|string|max:50',
            'direction' => 'nullable|in:ltr,rtl',
            'is_rtl' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $language = $this->i18nService->createLanguage($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language created successfully.',
            'data' => $language,
        ], 201);
    }

    public function updateLanguage(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'nullable|string|max:20|unique:languages,locale,' . $id,
            'name' => 'nullable|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'flag' => 'nullable|string|max:50',
            'direction' => 'nullable|in:ltr,rtl',
            'is_rtl' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $language = $this->i18nService->updateLanguage($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully.',
            'data' => $language,
        ]);
    }

    public function deleteLanguage(int $id): JsonResponse
    {
        try {
            $this->i18nService->deleteLanguage($id);
            return response()->json([
                'success' => true,
                'message' => 'Language deleted successfully.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─── Namespace CRUD ────────────────────────────────────────

    public function namespaces(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->i18nService->getNamespaces(),
        ]);
    }

    public function createNamespace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'namespace' => 'required|string|max:100|unique:translation_namespaces,namespace',
            'label' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:1000',
        ]);

        $ns = $this->i18nService->createNamespace($validated);

        return response()->json([
            'success' => true,
            'message' => 'Namespace created successfully.',
            'data' => $ns,
        ], 201);
    }

    public function deleteNamespace(int $id): JsonResponse
    {
        $this->i18nService->deleteNamespace($id);

        return response()->json([
            'success' => true,
            'message' => 'Namespace deleted successfully.',
        ]);
    }

    // ─── Translation CRUD ──────────────────────────────────────

    public function translations(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'locale' => 'nullable|string|max:20',
            'namespace_id' => 'nullable|integer|exists:translation_namespaces,id',
            'namespace' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:200',
            'is_published' => 'nullable|boolean',
            'is_auto_translated' => 'nullable|boolean',
            'missing_only' => 'nullable|boolean',
            'sort_field' => 'nullable|string|in:key,locale,value,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:10|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->i18nService->getTranslations($filters, $filters['per_page'] ?? 50),
        ]);
    }

    public function showTranslation(int $id): JsonResponse
    {
        $translation = $this->i18nService->getTranslation($id);

        return response()->json([
            'success' => true,
            'data' => [
                'translation' => $translation,
                'history' => $this->i18nService->getHistory($id),
            ],
        ]);
    }

    public function updateTranslation(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'nullable|string',
            'default_value' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'is_auto_translated' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $translation = $this->i18nService->updateTranslation($id, $validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully.',
            'data' => $translation,
        ]);
    }

    public function bulkUpdateTranslations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'translations' => 'required|array|min:1|max:500',
            'translations.*.id' => 'required|integer|exists:translations,id',
            'translations.*.value' => 'nullable|string',
        ]);

        $results = $this->i18nService->bulkUpdateTranslations($validated['translations'], Auth::id());

        return response()->json([
            'success' => true,
            'message' => "Updated {$results['updated']}, failed {$results['failed']}.",
            'data' => $results,
        ]);
    }

    public function publishTranslation(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_published' => 'required|boolean',
        ]);

        $translation = $this->i18nService->publishTranslation($id, $validated['is_published'], Auth::id());

        return response()->json([
            'success' => true,
            'message' => $validated['is_published'] ? 'Translation published.' : 'Translation unpublished.',
            'data' => $translation,
        ]);
    }

    // ─── Scan ──────────────────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $locale = $request->input('locale');

        $results = $this->i18nService->scanPhpLanguageFiles($locale);

        return response()->json([
            'success' => true,
            'message' => "Scan complete: {$results['namespaces']} namespaces, {$results['keys']} keys found ({$results['new']} new, {$results['updated']} updated).",
            'data' => $results,
        ]);
    }

    // ─── Export ─────────────────────────────────────────────────

    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:20',
            'format' => 'required|in:json,csv,php,xliff',
            'namespace_id' => 'nullable|integer|exists:translation_namespaces,id',
        ]);

        try {
            $path = $this->i18nService->exportTranslations(
                $validated['locale'],
                $validated['format'],
                $validated['namespace_id'] ?? null
            );

            $downloadUrl = asset($path);

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully.',
                'data' => [
                    'path' => $path,
                    'download_url' => $downloadUrl,
                    'format' => $validated['format'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Import ─────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'format' => 'required|in:json,csv,xliff',
        ]);

        try {
            $results = $this->i18nService->importTranslations(
                $validated['file'],
                $validated['format'],
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => "Import completed: {$results['created']} created, {$results['updated']} updated, {$results['skipped']} skipped.",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Auto-translate ────────────────────────────────────────

    public function autoTranslate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:20',
            'namespace_id' => 'nullable|integer|exists:translation_namespaces,id',
        ]);

        $results = $this->i18nService->autoTranslateAll(
            $validated['locale'],
            $validated['namespace_id'] ?? null,
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Auto-translate complete: {$results['translated']} translated, {$results['failed']} failed.",
            'data' => $results,
        ]);
    }

    public function autoTranslateSingle(int $id): JsonResponse
    {
        try {
            $translation = $this->i18nService->autoTranslate($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Auto-translated successfully.',
                'data' => $translation,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─── Import History ────────────────────────────────────────

    public function importHistory(): JsonResponse
    {
        $imports = \App\Models\TranslationImport::with('user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $imports,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // M3-85 翻译引擎增强 API
    // ═══════════════════════════════════════════════════════════

    /**
     * 使用 LLM 翻译引擎翻译单个条目
     */
    public function engineTranslateSingle(int $id, \App\Services\TranslationEngineService $engine): JsonResponse
    {
        try {
            $translation = $engine->translateSingle($id, Auth::id());
            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.i18n_msg_373'),
                'data' => $translation,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 使用 LLM 翻译引擎批量翻译缺失条目
     */
    public function engineTranslateMissing(Request $request, \App\Services\TranslationEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:20',
            'namespace_id' => 'nullable|integer|exists:translation_namespaces,id',
        ]);

        $results = $engine->translateMissing(
            $validated['locale'],
            $validated['namespace_id'] ?? null,
            Auth::id(),
        );

        return response()->json([
            'success' => true,
            'message' => "批量翻译完成: {$results['translated']} 条成功, {$results['failed']} 条失败, {$results['skipped']} 条跳过。",
            'data' => $results,
        ]);
    }

    /**
     * 评估翻译质量
     */
    public function assessQuality(int $id, \App\Services\TranslationEngineService $engine): JsonResponse
    {
        $translation = \App\Models\Translation::findOrFail($id);
        $quality = $engine->assessQuality($translation);

        return response()->json([
            'success' => true,
            'data' => $quality,
        ]);
    }

    /**
     * 获取翻译记忆统计
     */
    public function memoryStats(\App\Services\TranslationEngineService $engine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $engine->getMemoryStats(),
        ]);
    }

    /**
     * 批量翻译文本（用于即时翻译）
     */
    public function translateBatch(Request $request, \App\Services\TranslationEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'target_locale' => 'required|string|max:20',
            'items.*' => 'string',
        ]);

        $results = $engine->translateBatch($validated['items'], $validated['target_locale']);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
