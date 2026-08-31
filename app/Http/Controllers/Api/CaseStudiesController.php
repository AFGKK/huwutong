<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CaseStudiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 客户案例+Logo墙控制器 (M2-99)
 */
class CaseStudiesController extends Controller
{
    public function __construct(
        protected CaseStudiesService $caseStudies,
    ) {}

    /**
     * 案例列表（公开）
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'industry', 'featured']);
        return ApiResponse::success($this->caseStudies->getList($filters));
    }

    /**
     * 案例详情（公开）
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->caseStudies->getDetail($id);
        if (!$detail) {
            return ApiResponse::error(__("app.case_studies.msg_85d0eda0"), 404);
        }
        return ApiResponse::success($detail);
    }

    /**
     * 创建案例（管理）
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'company' => 'required|string|max:200',
            'category' => 'required|string|max:50',
            'industry' => 'nullable|string|max:50',
            'summary' => 'required|string|max:500',
            'content' => 'required|string',
            'logo' => 'nullable|image|max:1024',
            'cover_image' => 'nullable|image|max:2048',
            'quote' => 'nullable|string|max:500',
            'quote_author' => 'nullable|string|max:100',
            'results' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'tags' => 'nullable|array',
        ]);

        return ApiResponse::success($this->caseStudies->create($data), __('app.case_studies.case_created'));
    }

    /**
     * 更新案例
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'string|max:200',
            'company' => 'string|max:200',
            'category' => 'string|max:50',
            'industry' => 'nullable|string|max:50',
            'summary' => 'string|max:500',
            'content' => 'string',
            'logo' => 'nullable|image|max:1024',
            'cover_image' => 'nullable|image|max:2048',
            'quote' => 'nullable|string|max:500',
            'quote_author' => 'nullable|string|max:100',
            'results' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'tags' => 'nullable|array',
        ]);

        return ApiResponse::success($this->caseStudies->update($id, $data), __('app.case_studies.case_updated'));
    }

    /**
     * 删除案例
     */
    public function destroy(int $id): JsonResponse
    {
        return ApiResponse::success($this->caseStudies->delete($id));
    }

    /**
     * Logo 墙（公开）
     */
    public function logoWall(): JsonResponse
    {
        return ApiResponse::success([
            'logos' => $this->caseStudies->getLogoWall(),
            'config' => [
                'grid_columns' => config('case-studies.logo_wall.grid_columns', 6),
                'display_count' => config('case-studies.logo_wall.display_count', 12),
                'trusted_text' => config('case-studies.homepage.trusted_text')
                    ?: __('app.landing.trust_industries'),
            ],
        ]);
    }

    /**
     * 首页推荐（公开）
     */
    public function featured(): JsonResponse
    {
        return ApiResponse::success([
            'cases' => $this->caseStudies->getFeatured(),
            'count' => config('case-studies.homepage.featured_count', 3),
        ]);
    }

    /**
     * 上传 Logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|image|max:1024']);
        $result = $this->caseStudies->uploadLogo($request->file('file'));
        return $result['success']
            ? ApiResponse::success($result, __("app.case_studies.msg_0e1bad7a"))
            : ApiResponse::error(__("app.case_studies.msg_54e5de42"), 400);
    }

    /**
     * 获取统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->caseStudies->getStats());
    }

    /**
     * 获取分类（公开）
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(config('case-studies.categories', []));
    }

    /**
     * 获取行业标签
     */
    public function industryTags(): JsonResponse
    {
        return ApiResponse::success(config('case-studies.industry_tags', []));
    }
}
