<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function __construct(
        protected TagService $tagService,
    ) {}

    /**
     * 标签列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::orderBy('group')->orderBy('name');

        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $paginator = $query->paginate($request->input('per_page', 50))
            ->through(function ($tag) {
                $tag->usage_count = $tag->tickets_count + $tag->licenses_count + $tag->customers_count;
                return $tag;
            });

        return ApiResponse::paginated($paginator);
    }

    /**
     * 获取分组后的标签（用于下拉选择器）
     */
    public function grouped(): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $grouped = $this->tagService->getGrouped();

        return ApiResponse::success($grouped);
    }

    /**
     * 创建标签
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'group' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_system' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $tag = $this->tagService->create($validator->validated());

        return ApiResponse::created($tag);
    }

    /**
     * 标签详情
     */
    public function show(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return ApiResponse::success($tag);
    }

    /**
     * 更新标签
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'group' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_system' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $tag = $this->tagService->update($tag, $validator->validated());

        return ApiResponse::success($tag);
    }

    /**
     * 删除标签
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        try {
            $this->tagService->delete($tag);
            return ApiResponse::success(null, __("app.tag.msg_722459ee"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        }
    }

    /**
     * 批量同步标签到某个模型
     * POST /api/tags/sync?type=license&id=123
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'taggable_type' => ['required', 'string', Rule::in(['license', 'ticket', 'customer', 'product', 'api_key'])],
            'taggable_id' => 'required|integer',
            'tags' => 'present|array',
            'tags.*' => 'string|max:100',
            'group' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $model = $this->resolveTaggable($data['taggable_type'], $data['taggable_id']);

        if (! $model) {
            return ApiResponse::error('NOT_FOUND', __("app.tag.msg_1080469d"), 404);
        }

        $this->authorize('update', $model);

        $tagIds = $model->syncTags($data['tags'], $data['group'] ?? null);

        return ApiResponse::success([
            'tag_ids' => $tagIds,
            'tags' => $model->tags()->get(),
        ], __('app.tag.tags_synced'));
    }

    /**
     * 为指定模型附加一个标签
     * POST /api/tags/attach
     */
    public function attach(Request $request): JsonResponse
    {
        return $this->handleAttachDetach($request, 'attach');
    }

    /**
     * 为指定模型移除一个标签
     * POST /api/tags/detach
     */
    public function detach(Request $request): JsonResponse
    {
        return $this->handleAttachDetach($request, 'detach');
    }

    private function handleAttachDetach(Request $request, string $action): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'taggable_type' => ['required', 'string', Rule::in(['license', 'ticket', 'customer', 'product', 'api_key'])],
            'taggable_id' => 'required|integer',
            'tag' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $model = $this->resolveTaggable($data['taggable_type'], $data['taggable_id']);

        if (! $model) {
            return ApiResponse::error('NOT_FOUND', __('app.tag.model_not_found'), 404);
        }

        $this->authorize('update', $model);

        if (! method_exists($model, $action === 'attach' ? 'attachTag' : 'detachTag')) {
            return ApiResponse::error('NOT_SUPPORTED', __("app.tag.msg_6f93c7e7"), 400);
        }

        if ($action === 'attach') {
            $model->attachTag($data['tag']);
        } else {
            $model->detachTag($data['tag']);
        }

        return ApiResponse::success([
            'tags' => $model->tags()->get(),
        ], $action === 'attach' ? __('app.tag.tag_added') : __('app.tag.tag_removed'));
    }

    /**
     * 根据类型名称解析模型实例
     */
    private function resolveTaggable(string $type, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        $map = [
            'license' => \App\Models\License::class,
            'ticket' => \App\Models\Ticket::class,
            'customer' => \App\Models\Customer::class,
            'product' => \App\Models\Product::class,
            'api_key' => \App\Models\ApiKey::class,
        ];

        $class = $map[$type] ?? null;
        if (! $class) {
            return null;
        }

        return $class::withTrashed()->find($id);
    }
}
