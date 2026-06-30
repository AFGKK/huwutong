<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use App\Services\PromptTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromptTemplateController extends Controller
{
    public function __construct(
        protected PromptTemplateService $promptService,
    ) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->promptService->getDashboard());
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->promptService->list($request->all(), $request->input('per_page', 20))
        );
    }

    public function activeTemplates(): JsonResponse
    {
        return ApiResponse::success($this->promptService->getActiveGrouped());
    }

    public function show(int $id): JsonResponse
    {
        $template = PromptTemplate::with('creator:id,name')->findOrFail($id);
        return ApiResponse::success($template);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'content' => 'required|string',
            'description' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
            'engine' => 'nullable|string|max:30',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:100|max:32000',
            'status' => 'nullable|in:active,draft',
        ]);

        $validated['version'] = '1.0';
        $validated['is_current'] = true;
        $validated['created_by'] = $request->user()->id;
        if (isset($validated['variables'])) {
            $validated['variables'] = $validated['variables'];
        }

        $template = PromptTemplate::create($validated);

        return ApiResponse::created($template, 'Prompt 模板已创建');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = PromptTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'category' => 'sometimes|string|max:50',
            'content' => 'sometimes|string',
            'description' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
            'engine' => 'nullable|string|max:30',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:100|max:32000',
            'status' => 'nullable|in:active,draft,archived',
            'ab_test_config' => 'nullable|array',
        ]);

        $template->update($validated);

        return ApiResponse::success($template, 'Prompt 模板已更新');
    }

    public function createVersion(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'note' => 'nullable|string|max:500',
        ]);

        $template = PromptTemplate::findOrFail($id);
        $new = $this->promptService->createVersion($template, $validated['content'], $validated['note'] ?? null);

        return ApiResponse::created($new, '新版本已创建');
    }

    public function setActive(int $id): JsonResponse
    {
        $template = PromptTemplate::findOrFail($id);
        // 取消同分类下其他模板的 current 标志
        PromptTemplate::byCategory($template->category)
            ->where('id', '!=', $id)
            ->update(['is_current' => false]);
        $template->update(['is_current' => true, 'status' => 'active']);

        return ApiResponse::success($template, '已设为当前版本');
    }

    public function renderTest(Request $request, int $id): JsonResponse
    {
        $template = PromptTemplate::findOrFail($id);
        $vars = $request->input('variables', []);
        $result = $this->promptService->render($template, $vars);

        return ApiResponse::success(['rendered' => $result]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = PromptTemplate::findOrFail($id);
        $template->delete();
        return ApiResponse::success(null, '已删除');
    }
}
