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
     * 分类树 + 文章列表
     */
    public function categories(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'zh-CN');
        $categories = \App\Models\KbCategory::active()
            ->where('locale', $locale)
            ->orderBy('sort_order')
            ->get();

        $tree = $categories->map(function ($cat) {
            $articles = \App\Models\KbArticle::published()
                ->where('category_id', $cat->id)
                ->orderByDesc('helpful_count')
                ->get(['id', 'title', 'excerpt', 'slug', 'view_count']);

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'articles' => $articles->toArray(),
            ];
        });

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
     * 搜索建议（自动补全）— 返回轻量标题匹配
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        if (mb_strlen($q) < 1) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $articles = KbArticle::published()
            ->where('title', 'like', '%' . $q . '%')
            ->orderByDesc('view_count')
            ->limit(6)
            ->get(['id', 'title', 'category_id']);

        $articles->load('category:id,name');

        return response()->json(['success' => true, 'data' => $articles]);
    }

    /**
     * 文章详情（公开）
     */
    public function show(KbArticle $article): JsonResponse
    {
        if (!$article->isPublished()) {
            return response()->json(['success' => false, 'message' => __('app.api.kb.unpublished')], 404);
        }

        $article->recordView();
        $article->load(['category:id,name', 'author:id,name']);

        $related = $this->kbService->getRelatedArticles($article);

        // 上一篇 / 下一篇
        $prev = KbArticle::published()
            ->where('id', '<', $article->id)
            ->orderByDesc('id')
            ->first(['id', 'title']);
        $next = KbArticle::published()
            ->where('id', '>', $article->id)
            ->orderBy('id')
            ->first(['id', 'title']);

        return response()->json([
            'success' => true,
            'data' => [
                'article' => $article,
                'related_articles' => $related,
                'prev_article' => $prev,
                'next_article' => $next,
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

        return response()->json(['success' => true, 'message' => __('app.api.kb.thanks_feedback')]);
    }

    // ─── 管理 API ───

    /**
     * 文章列表（管理）
     */
    public function index(Request $request): JsonResponse
    {
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
            'message' => __('app.api.kb.article_created'),
            'data' => $article->load('category:id,name'),
        ], 201);
    }

    /**
     * 更新文章
     */
    public function update(Request $request, KbArticle $article): JsonResponse
    {
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
            'message' => __('app.api.kb.article_updated'),
            'data' => $updated,
        ]);
    }

    /**
     * 发布文章
     */
    public function publish(KbArticle $article): JsonResponse
    {
        $this->kbService->publishArticle($article);

        return response()->json(['success' => true, 'message' => __('app.api.kb.article_published')]);
    }

    /**
     * 归档文章
     */
    public function archive(KbArticle $article): JsonResponse
    {
        $this->kbService->archiveArticle($article);

        return response()->json(['success' => true, 'message' => __('app.api.kb.article_archived')]);
    }

    /**
     * 删除文章
     */
    public function destroy(KbArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(['success' => true, 'message' => __('app.api.kb.article_deleted')]);
    }

    /**
     * 获取版本历史
     */
    public function versions(KbArticle $article): JsonResponse
    {
        $versions = $article->versions()
            ->with('author:id,name')
            ->orderBy('version_number', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $versions]);
    }

    // ─── 批量操作 ───

    public function batchDelete(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return response()->json(['success' => false, 'message' => __('app.api.kb.select_articles')], 422);
        $count = \App\Models\KbArticle::whereIn('id', $ids)->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => $count], 'message' => __('app.api.kb.deleted_n', ['count' => $count])]);
    }

    public function batchPublish(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return response()->json(['success' => false, 'message' => __('app.api.kb.select_articles')], 422);
        $count = \App\Models\KbArticle::whereIn('id', $ids)->where('status', 'draft')
            ->update(['status' => 'published', 'published_at' => now()]);
        return response()->json(['success' => true, 'data' => ['published' => $count], 'message' => __('app.api.kb.published_n', ['count' => $count])]);
    }

    public function batchArchive(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return response()->json(['success' => false, 'message' => __('app.api.kb.select_articles')], 422);
        $count = \App\Models\KbArticle::whereIn('id', $ids)->update(['status' => 'archived']);
        return response()->json(['success' => true, 'data' => ['archived' => $count], 'message' => __('app.api.kb.archived_n', ['count' => $count])]);
    }

    // ─── 导出 ───

    public function exportMarkdown()
    {
        $articles = \App\Models\KbArticle::with('category')->orderBy('category_id')->orderBy('title')->get();

        $filename = 'kb-export-' . now()->format('YmdHis') . '.md';
        $headers = [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($articles) {
            echo "# " . __('app.api.kb.export_title') . "\n\n";
            echo __('app.api.kb.export_time', ['time' => now()->format('Y-m-d H:i:s')]) . "\n\n---\n\n";
            $currentCat = null;
            foreach ($articles as $a) {
                $catName = $a->category?->name ?? __('app.api.kb.uncategorized');
                if ($currentCat !== $catName) { $currentCat = $catName; echo "## {$catName}\n\n"; }
                echo "### {$a->title}\n\n";
                if ($a->excerpt) echo "> {$a->excerpt}\n\n";
                echo $a->content . "\n\n---\n\n";
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── 分类管理 ───

    /**
     * 创建分类
     */
    public function storeCategory(Request $request): JsonResponse
    {
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
            'message' => __('app.api.kb.category_created'),
            'data' => $category,
        ], 201);
    }

    /**
     * 更新分类
     */
    public function updateCategory(Request $request, KbCategory $category): JsonResponse
    {
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

        return response()->json(['success' => true, 'message' => __('app.api.kb.category_updated'), 'data' => $category->fresh()]);
    }

    /**
     * 删除分类
     */
    public function destroyCategory(KbCategory $category): JsonResponse
    {
        if ($category->articles()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.kb.category_in_use'),
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => __('app.api.kb.category_deleted')]);
    }
}
