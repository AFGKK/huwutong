<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FooterNavItem;
use App\Services\FooterNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 页脚导航配置控制器 (M2-85)
 */
class FooterNavController extends Controller
{
    public function __construct(
        protected FooterNavService $footerNav,
    ) {
    }

    /**
     * 获取全部页脚链接
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            $this->footerNav->getAll()
        );
    }

    /**
     * 公开页脚链接（无认证）
     */
    public function publicNav(): JsonResponse
    {
        return ApiResponse::success(
            $this->footerNav->getPublic()
        );
    }

    /**
     * 创建链接
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'type' => 'nullable|string|in:page,custom,help,api_docs,status,social,contact|max:30',
            'url' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'target' => 'nullable|string|in:_self,_blank',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'boolean',
            'group' => 'nullable|string|in:footer,social,bottom',
        ]);

        $item = $this->footerNav->create($data);
        return ApiResponse::success(['item' => $item], __('app.api.footer_nav.link_created'));
    }

    /**
     * 更新链接
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:100',
            'type' => 'nullable|string|in:page,custom,help,api_docs,status,social,contact|max:30',
            'url' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'target' => 'nullable|string|in:_self,_blank',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'boolean',
            'group' => 'nullable|string|in:footer,social,bottom',
        ]);

        $item = $this->footerNav->update($id, $data);
        return ApiResponse::success(['item' => $item], __('app.api.footer_nav.link_updated'));
    }

    /**
     * 删除链接
     */
    public function destroy(int $id): JsonResponse
    {
        $this->footerNav->delete($id);
        return ApiResponse::success(null, __('app.api.footer_nav.link_deleted'));
    }

    /**
     * 批量排序
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:footer_nav_items,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        $this->footerNav->reorder($data['items']);
        return ApiResponse::success(null, __('app.api.footer_nav.sort_updated'));
    }

    /**
     * 切换启用
     */
    public function toggle(int $id): JsonResponse
    {
        $item = $this->footerNav->toggle($id);
        return ApiResponse::success([
            'item' => $item,
            'is_active' => $item->is_active,
        ], $item->is_active ? __('app.api.footer_nav.enabled') : __('app.api.footer_nav.disabled'));
    }

    /**
     * 初始化默认链接
     */
    public function initDefaults(): JsonResponse
    {
        $count = $this->footerNav->initDefaults();
        return ApiResponse::success(['created' => $count], __('app.api.footer_nav.defaults_seeded', ['count' => $count]));
    }

    /**
     * 获取配置选项
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'link_types' => $this->footerNav->getLinkTypes(),
            'social_platforms' => $this->footerNav->getSocialPlatforms(),
            'groups' => [
                ['value' => 'footer', 'label' => __('app.api.footer_nav.label_footer')],
                ['value' => 'social', 'label' => __('app.api.footer_nav.label_social')],
                ['value' => 'bottom', 'label' => __('app.api.footer_nav.label_bottom')],
            ],
        ]);
    }
}
