<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SkuController extends Controller
{
    /**
     * SKU 仪表盘统计
     */
    public function dashboard(): JsonResponse
    {
        $totalSkus = ProductSku::count();
        $activeSkus = ProductSku::where('is_active', true)->count();
        $lowStock = ProductSku::where('stock', '>', 0)->where('stock', '<=', 10)->count();
        $outOfStock = ProductSku::where('stock', 0)->count();
        $unlimited = ProductSku::where('stock', -1)->count();

        return ApiResponse::success([
            'total_skus' => $totalSkus,
            'active_skus' => $activeSkus,
            'low_stock_count' => $lowStock,
            'out_of_stock' => $outOfStock,
            'unlimited' => $unlimited,
        ]);
    }

    /**
     * SKU 列表（分页+筛选）
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductSku::with('product:id,name');

        // 按产品筛选
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // 按状态
        if ($request->has('is_active') && $request->input('is_active') !== '' && $request->input('is_active') !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // 计费周期
        if ($request->filled('billing_cycle')) {
            $query->where('billing_cycle', $request->input('billing_cycle'));
        }

        // 库存状态
        if ($request->filled('stock_status')) {
            match ($request->input('stock_status')) {
                'low' => $query->where('stock', '>', 0)->where('stock', '<=', 10),
                'out' => $query->where('stock', 0),
                'unlimited' => $query->where('stock', -1),
                default => null,
            };
        }

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 200);
        $skus = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($skus);
    }

    /**
     * SKU 详情
     */
    public function show(int $id): JsonResponse
    {
        $sku = ProductSku::with('product:id,name')->findOrFail($id);
        return ApiResponse::success($sku);
    }

    /**
     * 创建 SKU
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku_code' => 'nullable|string|max:100|unique:product_skus,sku_code',
            'name' => 'required|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'specs' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'stock' => 'integer|min:-1',
            'billing_cycle' => ['nullable', 'string', Rule::in(BillingCycle::activeCodes())],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'deliverables' => 'nullable|array',
        ]);

        $validated['sku_code'] ??= $this->generateSkuCode($validated['product_id']);
        $validated['stock'] ??= -1;
        $validated['is_active'] ??= true;
        $validated['currency'] ??= 'CNY';

        $sku = ProductSku::create($validated);
        return ApiResponse::success($sku->load('product'), __('app.api.sku.created'), 201);
    }

    /**
     * 更新 SKU
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'sku_code' => 'sometimes|string|max:100|unique:product_skus,sku_code,' . $id,
            'name' => 'sometimes|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'specs' => 'nullable|array',
            'price' => 'sometimes|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'stock' => 'integer|min:-1',
            'billing_cycle' => ['nullable', 'string', Rule::in(BillingCycle::activeCodes())],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'deliverables' => 'nullable|array',
        ]);

        $sku->update($validated);
        return ApiResponse::success($sku->fresh()->load('product'), __('app.api.sku.updated'));
    }

    /**
     * 删除 SKU
     */
    public function destroy(int $id): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $sku->delete();
        return ApiResponse::success(null, __('app.api.sku.deleted'));
    }

    /**
     * 切换上下架
     */
    public function toggle(int $id): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $sku->update(['is_active' => !$sku->is_active]);
        return ApiResponse::success($sku->fresh(), $sku->is_active ? __('app.api.sku.activated') : __('app.api.sku.deactivated'));
    }

    /**
     * 批量操作（上下架/删除/修改价格）
     */
    public function batchAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:activate,deactivate,delete,set_price,adjust_stock',
            'ids' => 'required|array',
            'ids.*' => 'exists:product_skus,id',
            'price' => 'required_if:action,set_price|numeric|min:0',
            'stock_change' => 'required_if:action,adjust_stock|integer',
        ]);

        $skus = ProductSku::whereIn('id', $validated['ids']);
        $count = $skus->count();

        return match ($validated['action']) {
            'activate' => $this->doBatchActivate($validated['ids']),
            'deactivate' => $this->doBatchDeactivate($validated['ids']),
            'delete' => $this->doBatchDelete($validated['ids']),
            'set_price' => $this->doBatchSetPrice($validated['ids'], $validated['price']),
            'adjust_stock' => $this->doBatchAdjustStock($validated['ids'], $validated['stock_change']),
        };
    }

    protected function doBatchActivate(array $ids): JsonResponse
    {
        $count = ProductSku::whereIn('id', $ids)->update(['is_active' => true]);
        return ApiResponse::success(['affected' => $count], __('app.api.sku.activated_n', ['count' => $count]));
    }

    protected function doBatchDeactivate(array $ids): JsonResponse
    {
        $count = ProductSku::whereIn('id', $ids)->update(['is_active' => false]);
        return ApiResponse::success(['affected' => $count], __('app.api.sku.deactivated_n', ['count' => $count]));
    }

    protected function doBatchDelete(array $ids): JsonResponse
    {
        $count = ProductSku::whereIn('id', $ids)->delete();
        return ApiResponse::success(['affected' => $count], __('app.api.sku.deleted_n', ['count' => $count]));
    }

    protected function doBatchSetPrice(array $ids, float $price): JsonResponse
    {
        $count = ProductSku::whereIn('id', $ids)->update(['price' => $price]);
        return ApiResponse::success(['affected' => $count], __('app.api.sku.price_updated_n', ['count' => $count]));
    }

    protected function doBatchAdjustStock(array $ids, int $change): JsonResponse
    {
        $affected = 0;
        foreach ($ids as $id) {
            $sku = ProductSku::find($id);
            if (!$sku) continue;
            $oldStock = $sku->stock;
            $newStock = max(-1, $oldStock + $change);
            $sku->update(['stock' => $newStock]);
            $this->logStockChange($sku->id, $change, $oldStock, $newStock, __('app.api.sku.stock_batch_reason'));
            $affected++;
        }
        return ApiResponse::success(['affected' => $affected], __('app.api.sku.stock_adjusted_n', ['count' => $affected]));
    }

    /**
     * 克隆 SKU
     */
    public function clone(int $id): JsonResponse
    {
        $source = ProductSku::with('currencyPrices')->findOrFail($id);
        $clone = $source->replicate(['sku_code', 'sold_count']);
        $clone->sku_code = $this->generateSkuCode($source->product_id);
        $clone->sold_count = 0;
        $clone->stock = 0;
        $clone->save();

        // 克隆多币种定价
        foreach ($source->currencyPrices as $cp) {
            $clone->currencyPrices()->create($cp->only(['currency', 'price', 'compare_at_price', 'cost_price']));
        }

        return ApiResponse::success($clone->load('product', 'currencyPrices'), __('app.api.sku.cloned'), 201);
    }

    // ── 多币种定价 ──

    /**
     * 获取 SKU 的多币种定价
     */
    public function currencyPrices(int $id): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        return ApiResponse::success($sku->currencyPrices);
    }

    /**
     * 保存多币种定价
     */
    public function saveCurrencyPrices(int $id, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*.currency' => 'required|string|size:3',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
            'prices.*.cost_price' => 'nullable|numeric|min:0',
        ]);

        $sku->currencyPrices()->delete();
        foreach ($validated['prices'] as $p) {
            $sku->currencyPrices()->create([
                'currency' => $p['currency'],
                'price' => $p['price'],
                'compare_at_price' => $p['compare_at_price'] ?? null,
                'cost_price' => $p['cost_price'] ?? null,
            ]);
        }

        return ApiResponse::success($sku->fresh()->currencyPrices, __('app.api.sku.currency_saved'));
    }

    // ── 库存日志 ──

    /**
     * 获取库存变更日志
     */
    public function stockLogs(int $id, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $query = $sku->stockLogs()->with('user:id,name');
        $perPage = min((int) $request->input('per_page', 20), 100);
        return ApiResponse::paginated($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    /**
     * 手动调整库存（含日志）
     */
    public function adjustStock(int $id, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $validated = $request->validate([
            'change' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $oldStock = $sku->stock;
        $newStock = max(-1, $oldStock + $validated['change']);
        $sku->update(['stock' => $newStock]);

        $this->logStockChange($sku->id, $validated['change'], $oldStock, $newStock, $validated['reason'] ?? __('app.api.sku.stock_manual_reason'));

        return ApiResponse::success([
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'change' => $validated['change'],
        ], __('app.api.sku.stock_adjusted'));
    }

    protected function logStockChange(int $skuId, int $change, int $oldStock, int $newStock, string $reason): void
    {
        try {
            \App\Models\SkuStockLog::create([
                'product_sku_id' => $skuId,
                'user_id' => auth()->id(),
                'change' => $change,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主操作
        }
    }

    // ── 导入/导出 ──

    /**
     * 导出 SKU 为 CSV
     */
    public function export(): \Illuminate\Http\Response
    {
        $skus = ProductSku::with('product:id,name')->orderBy('product_id')->get();

        $csv = "product_name,sku_code,name,price,compare_at_price,currency,stock,billing_cycle,is_active,commission_rate\n";
        foreach ($skus as $sku) {
            $csv .= implode(',', [
                $this->csvEscape($sku->product?->name ?? ''),
                $this->csvEscape($sku->sku_code),
                $this->csvEscape($sku->name),
                $sku->price,
                $sku->compare_at_price ?? '',
                $sku->currency,
                $sku->stock,
                $sku->billing_cycle ?? '',
                $sku->is_active ? '1' : '0',
                $sku->commission_rate ?? '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="skus.csv"',
        ]);
    }

    /**
     * 导入 SKU
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'csv' => 'required|string',
        ]);

        $lines = explode("\n", $request->input('csv'));
        $header = str_getcsv(array_shift($lines));
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($lines as $line) {
            $row = str_getcsv(trim($line));
            if (count($row) < 3) continue;

            try {
                $data = array_combine($header, $row);
                $product = \App\Models\Product::where('name', $data['product_name'])->first();
                if (!$product) {
                    $errors[] = __('app.api.sku.product_missing', ['name' => $data['product_name']]);
                    continue;
                }

                $existing = ProductSku::where('sku_code', $data['sku_code'])->first();
                $payload = [
                    'product_id' => $product->id,
                    'name' => $data['name'],
                    'price' => (float) ($data['price'] ?? 0),
                    'compare_at_price' => !empty($data['compare_at_price']) ? (float) $data['compare_at_price'] : null,
                    'currency' => $data['currency'] ?? 'CNY',
                    'stock' => (int) ($data['stock'] ?? -1),
                    'billing_cycle' => $data['billing_cycle'] ?? null,
                    'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'commission_rate' => !empty($data['commission_rate']) ? (float) $data['commission_rate'] : null,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    $payload['sku_code'] = $data['sku_code'];
                    ProductSku::create($payload);
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = __('app.api.sku.row_error', ['code' => $data['sku_code'], 'error' => $e->getMessage()]);
            }
        }

        return ApiResponse::success([
            'message' => __('app.api.sku.import_result', ['created' => $created, 'updated' => $updated]) . ($errors ? __('app.api.sku.import_errors', ['count' => count($errors)]) : ''),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    // ── 低库存 SKU 列表 ──

    /**
     * 获取低库存 SKU 列表（用于告警）
     */
    public function lowStockList(): JsonResponse
    {
        $skus = ProductSku::with('product:id,name')
            ->where('is_active', true)
            ->where('stock', '>=', 0)
            ->whereRaw('stock <= COALESCE(low_stock_threshold, 10)')
            ->orderBy('stock')
            ->limit(50)
            ->get();

        return ApiResponse::success($skus);
    }

    // ── 图片上传 ──

    /**
     * 上传 SKU 图片
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:5120',
        ]);

        $path = $request->file('file')->store('skus', 'public');

        return ApiResponse::success([
            'url' => \Illuminate\Support\Facades\Storage::url($path),
        ]);
    }

    protected function csvEscape(?string $value): string
    {
        if ($value === null || $value === '') return '';
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /**
     * 生成 SKU 编码
     */
    protected function generateSkuCode(int $productId): string
    {
        $prefix = 'SKU';
        $product = Product::find($productId);
        if ($product) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $product->name), 0, 5)) ?: 'SKU';
        }
        $timestamp = now()->format('ymdHi');
        $rand = strtoupper(Str::random(4));
        $code = "{$prefix}-{$timestamp}-{$rand}";

        // 确保唯一
        while (ProductSku::where('sku_code', $code)->exists()) {
            $rand = strtoupper(Str::random(4));
            $code = "{$prefix}-{$timestamp}-{$rand}";
        }

        return $code;
    }
}
