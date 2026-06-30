<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSku;
use App\Services\ProductSkuService;
use App\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SKU 商品规格管理 (M1.1-24)
 *
 * product_skus 表的 CRUD + 库存管理后台 API
 */
class ProductSkuController extends Controller
{
    public function __construct(
        protected ProductSkuService $skuService
    ) {}

    /**
     * SKU 仪表盘
     * GET /api/v1/admin/product-skus/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->skuService->getDashboard(), 'SKU仪表盘获取成功');
    }

    /**
     * SKU 列表
     * GET /api/v1/admin/product-skus
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->only([
            'product_id', 'is_active', 'billing_cycle', 'search',
            'stock_status', 'per_page', 'page',
        ]);
        return ApiResponse::success($this->skuService->getSkus($params), 'SKU列表获取成功');
    }

    /**
     * SKU 详情
     * GET /api/v1/admin/product-skus/{product_sku}
     */
    public function show(ProductSku $productSku): JsonResponse
    {
        $productSku->load('product');
        return ApiResponse::success($productSku, 'SKU详情获取成功');
    }

    /**
     * 创建 SKU
     * POST /api/v1/admin/product-skus
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku_code' => 'nullable|string|max:100|unique:product_skus,sku_code',
            'name' => 'required|string|max:255',
            'specs' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stock' => 'nullable|integer|min:-1',
            'is_active' => 'nullable|boolean',
            'billing_cycle' => 'nullable|string|in:monthly,quarterly,yearly,one-time',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'deliverables.*.type' => 'required|string|in:file,link,text',
            'deliverables.*.category' => 'required|string|in:software,document,template,api,tutorial,other',
            'deliverables.*.name' => 'required|string|max:255',
            'deliverables.*.description' => 'nullable|string|max:500',
            'deliverables.*.file_url' => 'nullable|string|max:2048',
            'deliverables.*.file_size' => 'nullable|integer|min:0',
            'deliverables.*.mime_type' => 'nullable|string|max:100',
            'deliverables.*.original_name' => 'nullable|string|max:255',
            'deliverables.*.content' => 'nullable|string',
        ]);

        $sku = $this->skuService->createSku($validated);
        return ApiResponse::success($sku, 'SKU创建成功');
    }

    /**
     * 更新 SKU
     * PUT /api/v1/admin/product-skus/{product_sku}
     */
    public function update(Request $request, ProductSku $productSku): JsonResponse
    {
        $validated = $request->validate([
            'sku_code' => 'nullable|string|max:100|unique:product_skus,sku_code,' . $productSku->id,
            'name' => 'nullable|string|max:255',
            'specs' => 'nullable|array',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stock' => 'nullable|integer|min:-1',
            'is_active' => 'nullable|boolean',
            'billing_cycle' => 'nullable|string|in:monthly,quarterly,yearly,one-time',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'deliverables.*.type' => 'required|string|in:file,link,text',
            'deliverables.*.category' => 'required|string|in:software,document,template,api,tutorial,other',
            'deliverables.*.name' => 'required|string|max:255',
            'deliverables.*.description' => 'nullable|string|max:500',
            'deliverables.*.file_url' => 'nullable|string|max:2048',
            'deliverables.*.file_size' => 'nullable|integer|min:0',
            'deliverables.*.mime_type' => 'nullable|string|max:100',
            'deliverables.*.original_name' => 'nullable|string|max:255',
            'deliverables.*.content' => 'nullable|string',
        ]);

        $sku = $this->skuService->updateSku($productSku->id, $validated);
        return ApiResponse::success($sku, 'SKU更新成功');
    }

    /**
     * 删除 SKU
     * DELETE /api/v1/admin/product-skus/{product_sku}
     */
    public function destroy(ProductSku $productSku): JsonResponse
    {
        $this->skuService->deleteSku($productSku->id);
        return ApiResponse::success(null, 'SKU删除成功');
    }

    /**
     * 切换上下架
     * POST /api/v1/admin/product-skus/{product_sku}/toggle
     */
    public function toggle(ProductSku $productSku): JsonResponse
    {
        $sku = $this->skuService->toggleActive($productSku->id);
        return ApiResponse::success($sku, $sku->is_active ? 'SKU已上架' : 'SKU已下架');
    }

    /**
     * 批量更新库存
     * POST /api/v1/admin/product-skus/batch-stock
     */
    public function batchStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:product_skus,id',
            'items.*.stock' => 'required|integer|min:-1',
        ]);

        $results = $this->skuService->batchUpdateStock($validated['items']);
        return ApiResponse::success($results, '库存批量更新成功');
    }

    /**
     * 上传交付物文件
     * POST /api/v1/admin/product-skus/upload-deliverable
     */
    public function uploadDeliverable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:204800', // 最大 200MB
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $extension = $file->getClientOriginalExtension();

        // 生成唯一文件名
        $fileName = 'deliverables/' . date('Ymd_His') . '_' . \Illuminate\Support\Str::random(8) . '.' . $extension;

        // 存储到本地 storage/app/public/deliverables/
        $path = $file->storeAs('public', $fileName);

        // 生成可访问 URL
        $url = \Illuminate\Support\Facades\Storage::url($fileName);

        return ApiResponse::success([
            'url' => $url,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'path' => $fileName,
        ], '文件上传成功');
    }
}
