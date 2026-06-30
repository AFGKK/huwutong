<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

/**
 * 公开营销页面控制器 (M1.4-49/50)
 */
class PublicPageController extends Controller
{
    /**
     * Landing Page
     *
     * GET /
     */
    public function landingPage(): View
    {
        $featuredProducts = Product::where('is_active', true)
            ->with(['category', 'creator:id,name,avatar', 'reviews' => function ($q) {
                $q->where('status', 'approved');
            }, 'skus' => function ($q) {
                $q->where('is_active', true)->select('id', 'product_id', 'price', 'compare_at_price', 'sold_count', 'billing_cycle');
            }])
            ->withCount('licenses')
            ->orderByDesc('is_featured')
            ->orderByDesc('sales_count')
            ->latest()
            ->take(4)
            ->get()
            ->each(function ($p) {
                $activeSkus = $p->skus ?? collect();
                $p->lowest_price = $activeSkus->min('price');
                $p->highest_price = $activeSkus->max('price');
                $p->sold_total = $activeSkus->sum('sold_count');
                $p->is_new = $p->created_at && $p->created_at->gt(now()->subDays(7));
                $p->has_discount = $activeSkus->contains(fn($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
                $p->is_hot = $p->sold_total > 20;
            });

        return view('public.landing', compact('featuredProducts'));
    }

    /**
     * 产品商城页 / 产品详情页
     *
     * GET /products
     * GET /products/{slug}
     */
    public function products(Request $request, ?string $slug = null): View
    {
        // 产品详情页
        if ($slug) {
            $product = Product::where('slug', $slug)
                ->where('is_active', true)
                ->with(['category', 'specGroups.specs', 'featureFlags', 'creator:id,name,avatar,region,phone'])
                ->withCount('licenses')
                ->firstOrFail();

            $pricingPlans = PricingPlan::where('product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            // 也获取 SKU（直接购买选项）
            $skus = \App\Models\ProductSku::where('product_id', $product->id)
                ->where('is_active', true)
                ->get();

            $relatedProducts = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->when($product->category_id, fn($q) => $q->where('category_id', $product->category_id))
                ->with(['category', 'creator:id,name,avatar', 'reviews' => function ($q) {
                    $q->where('status', 'approved');
                }, 'skus' => function ($q) {
                    $q->where('is_active', true)->select('id', 'product_id', 'price', 'compare_at_price', 'sold_count', 'billing_cycle');
                }])
                ->limit(4)
                ->get()
                ->each(function ($p) {
                    $activeSkus = $p->skus ?? collect();
                    $p->lowest_price = $activeSkus->min('price');
                    $p->highest_price = $activeSkus->max('price');
                    $p->sold_total = $activeSkus->sum('sold_count');
                    $p->is_new = $p->created_at && $p->created_at->gt(now()->subDays(7));
                    $p->has_discount = $activeSkus->contains(fn($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
                    $p->is_hot = $p->sold_total > 20;
                });

            // 记录浏览数 + 收藏数
            try {
                $product->increment('view_count');
            } catch (\Exception $e) {}
            $_wishlistCount = WishlistItem::where('product_id', $product->id)->count();

            // 获取公开站点设置（含聊天开关等）
            $_siteSettings = \App\Models\SiteSetting::getPublic();

            return view('public.product-detail', compact('product', 'pricingPlans', 'skus', 'relatedProducts', '_wishlistCount', '_siteSettings'));
        }

        // 产品列表页
        $categories = ProductCategory::where('is_active', true)->orderBy('sort_order')->get();
        $search = $request->input('search');
        $highlight = $search;
        $products = Product::where('is_active', true)
            ->withCount('licenses as licenses_count')
            ->with(['category', 'creator:id,name,avatar', 'reviews' => function ($q) {
                $q->where('status', 'approved');
            }, 'skus' => function ($q) {
                $q->where('is_active', true)->select('id', 'product_id', 'price', 'compare_at_price', 'sold_count', 'billing_cycle');
            }])
            ->when($search, fn($q) => $q->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        // 附加计算字段
        $products->getCollection()->transform(function ($p) {
            $activeSkus = $p->skus ?? collect();
            $p->lowest_price = $activeSkus->min('price');
            $p->highest_price = $activeSkus->max('price');
            $p->sold_total = $activeSkus->sum('sold_count');
            $p->sku_count = $activeSkus->count();
            $p->is_new = $p->created_at && $p->created_at->gt(now()->subDays(7));
            $p->has_discount = $activeSkus->contains(fn($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
            $p->is_hot = $p->sold_total > 20;
            return $p;
        });

        $_siteSettings = \App\Models\SiteSetting::getPublic();

        // 获取卖家列表（用于高级筛选）
        $creatorIds = \App\Models\Product::where('is_active', true)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        $sellers = \App\Models\User::whereIn('id', $creatorIds)
            ->select('id', 'name', 'avatar')
            ->orderBy('name')
            ->get();

        // 获取所有产品标签（用于标签云）
        $allTags = \App\Models\Product::where('is_active', true)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn($t) => is_string($t) ? json_decode($t, true) ?? [] : ($t ?? []))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return view('public.products', compact('products', 'categories', 'highlight', '_siteSettings', 'sellers', 'allTags'));
    }

    /**
     * 产品对比页
     *
     * GET /compare-products?ids=1,2,3
     */
    public function compareProducts(Request $request): View
    {
        $ids = $request->input('ids', '');
        $productIds = array_filter(array_map('intval', explode(',', $ids)));

        $products = collect();
        if (count($productIds) > 0) {
            $products = Product::whereIn('id', $productIds)
                ->where('is_active', true)
                ->with(['category', 'featureFlags', 'specGroups.specs', 'skus' => function ($q) {
                    $q->where('is_active', true)->select('id', 'product_id', 'price', 'compare_at_price', 'sold_count', 'billing_cycle', 'name');
                }, 'reviews' => function ($q) {
                    $q->where('status', 'approved');
                }])
                ->get()
                ->each(function ($p) {
                    $activeSkus = $p->skus ?? collect();
                    $p->lowest_price = $activeSkus->min('price');
                    $p->highest_price = $activeSkus->max('price');
                    $p->sold_total = $activeSkus->sum('sold_count');
                    $_rev = $p->review_stats ?? [];
                    $p->avg_rating = $_rev['avg_rating'] ?? 0;
                    $p->review_count = $_rev['total'] ?? 0;
                });
        }

        return view('public.compare-products', compact('products'));
    }

    /**
     * API: 获取公开产品列表
     *
     * GET /api/public/products
     */
    public function apiProducts(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true)
            ->withCount('licenses as licenses_count')
            ->with(['category', 'creator:id,name,avatar', 'reviews' => function ($q) {
                $q->where('status', 'approved');
            }, 'skus' => function ($q) {
                $q->where('is_active', true)->select('id', 'product_id', 'price', 'compare_at_price', 'sold_count', 'billing_cycle');
            }]);

        // 分类筛选（支持 slug 或 ID）
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        } elseif ($request->filled('filter.category_id')) {
            $query->where('category_id', $request->input('filter.category_id'));
        }

        // 搜索
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        // 卖家筛选
        if ($request->filled('creator_id')) {
            $query->where('user_id', $request->creator_id);
        }

        // 标签筛选（数据库字段）
        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag === 'demo_enabled') {
                    $query->where('demo_enabled', true);
                }
            }
        }

        // 产品标签云筛选（tags JSON 列）
        if ($request->filled('product_tag')) {
            $query->whereJsonContains('tags', $request->product_tag);
        }

        // 排序
        $sort = $request->input('sort', 'latest');

        // Fetch all active products with relationships
        $allProducts = $query->get();

        // Compute lowest price from SKUs
        foreach ($allProducts as $p) {
            $activeSkus = $p->skus ?? collect();
            $p->lowest_price = $activeSkus->min('price');
            $p->highest_price = $activeSkus->max('price');
            $soldTotal = $activeSkus->sum('sold_count');
            $p->sold_total = $soldTotal;
            // 覆盖 sales_count 为 SKU 实际销量（数据库 sales_count 可能不准确）
            $p->sales_count = $soldTotal;
            $rev = $p->review_stats ?? [];
            $p->avg_rating = $rev['avg_rating'] ?? 0;
            $p->review_count = $rev['total'] ?? 0;
        }

        // 价格范围筛选（基于计算后的 lowest_price）
        if ($request->filled('price_min')) {
            $allProducts = $allProducts->filter(fn($p) => $p->lowest_price !== null && $p->lowest_price >= (float)$request->price_min);
        }
        if ($request->filled('price_max')) {
            $allProducts = $allProducts->filter(fn($p) => $p->lowest_price !== null && $p->lowest_price <= (float)$request->price_max);
        }

        // 标签筛选（PHP 端处理计算字段 is_new / is_hot / has_discount）
        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag === 'is_new') {
                    $allProducts = $allProducts->filter(fn($p) => $p->created_at && $p->created_at->gt(now()->subDays(7)));
                } elseif ($tag === 'is_hot') {
                    $allProducts = $allProducts->filter(fn($p) => ($p->sold_total ?? 0) > 20);
                } elseif ($tag === 'has_discount') {
                    $allProducts = $allProducts->filter(function($p) {
                        return $p->skus && $p->skus->contains(fn($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
                    });
                }
            }
        }

        // Collection sorting (returns new sorted collection)
        $sorted = match ($sort) {
            'price' => $allProducts->sortBy('lowest_price'),
            '-price' => $allProducts->sortByDesc('lowest_price'),
            '-sold_total' => $allProducts->sortByDesc('sold_total'),
            'name' => $allProducts->sortBy('name'),
            default => $allProducts->sortByDesc('created_at'),
        };
        $sorted = $sorted->values();

        // Manual pagination
        $perPage = min((int) $request->input('per_page', 12), 48);
        $page = Paginator::resolveCurrentPage('page');
        $items = $sorted->values()->forPage($page, $perPage);
        $products = new LengthAwarePaginator(
            $items,
            $allProducts->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        // 附加计算字段
        $items = collect($products->items())->map(function ($p) {
            $activeSkus = $p->skus ?? collect();
            $soldTotal = $activeSkus->sum('sold_count');
            $p->lowest_price = $activeSkus->min('price');
            $p->highest_price = $activeSkus->max('price');
            $p->sold_total = $soldTotal;
            $p->sales_count = $soldTotal;
            $p->sku_count = $activeSkus->count();
            $p->is_new = $p->created_at && $p->created_at->gt(now()->subDays(7));
            $p->has_discount = $activeSkus->contains(fn($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
            $p->is_hot = $p->sold_total > 20;
            return $p;
        });

        // PHP端排序（补充数据库无法直接排序的计算字段）
        $sorted = match ($sort) {
            'price_asc' => $items->sortBy('lowest_price'),
            'price_desc' => $items->sortByDesc('lowest_price'),
            'sold' => $items->sortByDesc('sold_total'),
            default => $items,
        };
        $items = $sorted->values();

        return response()->json([
            'data' => $items->values()->map(function ($p) {
                $data = $p->toArray();
                // 直接查库获取SKU销量（避免关系加载/序列化问题）
                $data['sold_total'] = $data['sales_count'] = (int) \App\Models\ProductSku::where('product_id', $p->id)
                    ->where('is_active', true)
                    ->sum('sold_count');
                return $data;
            }),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * API: 获取公开产品详情
     *
     * GET /api/public/products/{slug}
     */
    public function apiProductDetail(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with(['category', 'specGroups.specs'])
            ->firstOrFail();

        return response()->json(['data' => $product]);
    }
    /**
     * API: 获取公开定价方案
     *
     * GET /api/public/pricing-plans
     */
    public function pricingPlans(): JsonResponse
    {
        $plans = PricingPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => (float) $plan->price_monthly,
                'price_quarterly' => (float) $plan->price_quarterly,
                'price_semi_annually' => (float) $plan->price_semi_annually,
                'price_yearly' => (float) $plan->price_yearly,
                'currency' => $plan->currency,
                'features' => $plan->features,
                'limits' => $plan->limits,
                'badge' => $plan->badge,
                'trial_days' => $plan->trial_days,
            ]);

        return response()->json([
            'data' => $plans,
            'currency' => 'CNY',
            'currency_symbol' => '¥',
        ]);
    }

    /**
     * API: 提交企业联系表单
     *
     * POST /api/public/enterprise-contact
     */
    public function enterpriseContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company' => 'required|string|max:200',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'phone' => 'nullable|string|max:50',
            'employees' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:2000',
        ]);

        // 存储到数据库或发送通知
        // 简化实现：记录日志
        \Illuminate\Support\Facades\Log::channel('stack')->info('企业联系表单提交', $validated);

        return response()->json([
            'message' => '提交成功，我们将在1个工作日内联系您',
        ]);
    }

    /**
     * API: 获取 Landing Page 数据
     *
     * GET /api/public/landing
     */
    public function landing(): JsonResponse
    {
        return response()->json([
            'hero' => [
                'title' => '企业级授权管理',
                'subtitle' => '为您的软件产品提供安全、灵活、可扩展的授权解决方案',
                'cta_primary' => '免费开始',
                'cta_secondary' => '查看定价',
            ],
            'features' => [
                [
                    'icon' => 'shield-check',
                    'title' => '安全可靠',
                    'description' => 'Ed25519 签名 + 离线验证 + CRL 吊销列表，银行级安全保障',
                ],
                [
                    'icon' => 'bolt',
                    'title' => '高性能',
                    'description' => '单机 5000+ QPS，边缘节点 <10ms 验证延迟，全球加速',
                ],
                [
                    'icon' => 'device-mobile',
                    'title' => '全平台覆盖',
                    'description' => 'PHP/Node.js/Python/Go/Java/C# SDK，支持桌面/移动/嵌入式',
                ],
                [
                    'icon' => 'cloud',
                    'title' => '灵活部署',
                    'description' => 'SaaS 云服务 + 私有化部署 + 完全离线气隙模式',
                ],
                [
                    'icon' => 'chart-bar',
                    'title' => '数据驱动',
                    'description' => '实时分析看板 + AI 运营分析 + 自动报表，洞察业务增长',
                ],
                [
                    'icon' => 'globe',
                    'title' => '全球合规',
                    'description' => 'GDPR/PIPL/SOC2/ISO27001，满足全球合规要求',
                ],
            ],
            'stats' => [
                ['value' => '10,000+', 'label' => '活跃客户'],
                ['value' => '500万+', 'label' => 'License 生成'],
                ['value' => '99.99%', 'label' => '服务可用性'],
                ['value' => '<10ms', 'label' => '平均验证延迟'],
            ],
        ]);
    }
}
