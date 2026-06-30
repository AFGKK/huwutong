<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnnounceBanner;
use App\Services\AnnounceBannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnnounceBannerController extends Controller
{
    public function __construct(
        protected AnnounceBannerService $bannerService,
    ) {}

    /**
     * 获取当前活跃公告（供前端注入）
     */
    public function active(Request $request): JsonResponse
    {
        $role = $request->user()?->roles?->first()?->name;
        $banners = $this->bannerService->getActiveBanners($role);

        return ApiResponse::success($banners);
    }

    /**
     * 获取所有公告（管理用）
     */
    public function index(): JsonResponse
    {
        $banners = AnnounceBanner::orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($banners);
    }

    /**
     * 创建公告
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'type' => 'sometimes|in:info,success,warning,danger',
            'position' => 'sometimes|in:top,bottom',
            'can_close' => 'sometimes|boolean',
            'link_url' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'roles' => 'nullable|array',
            'roles.*' => 'string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'sort_order' => 'sometimes|integer|min:0|max:999',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $banner = $this->bannerService->create(array_merge(
            $validator->validated(),
            ['created_by' => $request->user()?->id],
        ));

        return ApiResponse::created($banner, '公告已创建');
    }

    /**
     * 获取单个公告
     */
    public function show(AnnounceBanner $announceBanner): JsonResponse
    {
        return ApiResponse::success($announceBanner);
    }

    /**
     * 更新公告
     */
    public function update(Request $request, AnnounceBanner $announceBanner): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'content' => 'nullable|string',
            'type' => 'sometimes|in:info,success,warning,danger',
            'position' => 'sometimes|in:top,bottom',
            'can_close' => 'sometimes|boolean',
            'link_url' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'roles' => 'nullable|array',
            'roles.*' => 'string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:999',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $banner = $this->bannerService->update($announceBanner, $validator->validated());

        return ApiResponse::success($banner, '公告已更新');
    }

    /**
     * 删除公告
     */
    public function destroy(AnnounceBanner $announceBanner): JsonResponse
    {
        $this->bannerService->delete($announceBanner);

        return ApiResponse::success(null, '公告已删除');
    }
}
