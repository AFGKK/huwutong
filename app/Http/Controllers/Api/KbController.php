<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KbController extends Controller
{
    public function __construct(
        protected KnowledgeBaseService $kbService,
    ) {}

    // ─── 公开 API ───

    /**
     * 分类树
     */
    public function categories(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'zh-CN');
        $tree = $this->kbService->getCategoryTree($locale);

        return response()->json(['success' => true, 'data' => $tree]);
    }

    /**
     * 搜索文章（公开）
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->kbService->searchArticles($request->input('q'), [
            'category_id' => $request->input('category_id'),
            'locale' => $request->input('locale', 'zh-CN'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 文章详情（公开）
     */
    public function show(KbArticle $article): JsonResponse
    {
        if (!$article->isPublished()) {
            return response()->json(['success' => false, 'message' => '文章未发布'], 404);
        }

        $article->recordView();
        $article->load(['category:id,name', 'author:id,name']);

        $related = $this->kbService->getRelatedArticles($article);

        return response()->json([
            'success' => true,
            'data' => [
                'article' => $article,
                'related_articles' => $related,
            ],
        ]);
    }

    /**
     * 提交反馈
     */
    public function feedback(Request $request, KbArticle $article): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_helpful' => 'required|boolean',
            'comment' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->kbService->recordFeedback(
            $article,
            $request->input('is_helpful'),
            $request->input('comment'),
            $request->input('session_id')
        );

        return response()->json(['success' => true, 'message' => '感谢您的反馈']);
    }

    // ─── 管理 API ───

    /**
     * 文章列表（管理）
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KbArticle::class);

        $articles = KbArticle::with(['category:id,name', 'author:id,name'])
            ->when($request->filled('status'), fn($q, $v) => $q->where('status', $v))
            ->when($request->filled('category_id'), fn($q, $v) => $q->where('category_id', $v))
            ->when($request->filled('search'), fn($q, $v) => $q->search($v))
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $articles]);
    }

    /**
     * 创建文章
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', KbArticle::class);

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:kb_categories,id',
            'title' => 'required|string|max:300',
            'content' => 'required|string',
            'excerpt' => 'sometimes|string|max:500',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'status' => 'sometimes|in:draft,published',
            'locale' => 'sometimes|string|max:10',
            'related_article_id' => 'sometimes|exists:kb_articles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $article = $this->kbService->createArticle(array_merge(
            $request->only(['category_id', 'title', 'content', 'excerpt', 'tags', 'status', 'locale', 'related_article_id']),
            ['author_id' => $request->user()->id]
        ));

        return response()->json([
            'success' => true,
            'message' => '文章创建成功',
            'data' => $article->load('category:id,name'),
        ], 201);
    }

    /**
     * 更新文章
     */
    public function update(Request $request, KbArticle $article): JsonResponse
    {
        $this->authorize('update', $article);

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:kb_categories,id',
            'title' => 'sometimes|string|max:300',
            'content' => 'sometimes|string',
            'excerpt' => 'sometimes|string|max:500',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'status' => 'sometimes|in:draft,published,archived',
            'locale' => 'sometimes|string|max:10',
            'related_article_id' => 'sometimes|nullable|exists:kb_articles,id',
            'change_summary' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updated = $this->kbService->updateArticle(
            $article,
            $request->only(['category_id', 'title', 'content', 'excerpt', 'tags', 'status', 'locale', 'related_article_id']),
            $request->input('change_summary')
        );

        return response()->json([
            'success' => true,
            'message' => '文章已更新',
            'data' => $updated,
        ]);
    }

    /**
     * 发布文章
     */
    public function publish(KbArticle $article): JsonResponse
    {
        $this->authorize('update', $article);

        $this->kbService->publishArticle($article);

        return response()->json(['success' => true, 'message' => '文章已发布']);
    }

    /**
     * 归档文章
     */
    public function archive(KbArticle $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $this->kbService->archiveArticle($article);

        return response()->json(['success' => true, 'message' => '文章已归档']);
    }

    /**
     * 删除文章
     */
    public function destroy(KbArticle $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return response()->json(['success' => true, 'message' => '文章已删除']);
    }

    /**
     * 获取版本历史
     */
    public function versions(KbArticle $article): JsonResponse
    {
        $this->authorize('view', $article);

        $versions = $article->versions()
            ->with('author:id,name')
            ->orderBy('version_number', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $versions]);
    }

    // ─── 分类管理 ───

    /**
     * 创建分类
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('create', KbCategory::class);

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:kb_categories,id',
            'name' => 'required|string|max:200',
            'description' => 'sometimes|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
            'locale' => 'sometimes|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = KbCategory::create($request->all());

        return response()->json([
            'success' => true,
            'message' => '分类创建成功',
            'data' => $category,
        ], 201);
    }

    /**
     * 更新分类
     */
    public function updateCategory(Request $request, KbCategory $category): JsonResponse
    {
        $this->authorize('update', KbCategory::class);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'sometimes|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category->update($request->all());

        return response()->json(['success' => true, 'message' => '分类已更新', 'data' => $category->fresh()]);
    }

    /**
     * 删除分类
     */
    public function destroyCategory(KbCategory $category): JsonResponse
    {
        $this->authorize('delete', KbCategory::class);

        if ($category->articles()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该分类下还有文章，无法删除',
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => '分类已删除']);
    }
}
