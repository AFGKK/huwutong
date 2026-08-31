<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\PreSaleCampaign;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSku;
use App\Models\Promotion;
use App\Models\WishlistItem;
use App\Services\DemoBookingService;
use App\Services\UserChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

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
                $p->has_discount = $activeSkus->contains(fn ($s) => $s->compare_at_price && $s->compare_at_price > $s->price);
                $p->is_hot = $p->sold_total > 20;
            });

        $landingPlans = $this->publicPricingPlanPayload();

        return view('public.landing', compact('featuredProducts', 'landingPlans'));
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
                ->where('is_active', true);
            
            // PostgreSQL 严格类型：id 是 bigint，只有传入数字时才按 ID 查找
            if (is_numeric($slug)) {
                $product->orWhere(function($q) use ($slug) {
                    $q->where('id', $slug)->where('is_active', true);
                });
            }
            
            $product = $product->with(['category', 'specGroups.specs', 'featureFlags', 'creator:id,name,avatar,region,phone'])
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

            // ── 加载促销/优惠券/秒杀/预售数据 ──
            $activePromotions = Promotion::active()
                ->where(function($q) use ($product) {
                    $q->whereJsonContains('applicable_products', $product->id)
                      ->orWhereNull('applicable_products');
                })
                ->limit(3)
                ->get();

            $activeCoupons = Coupon::valid()
                ->where(function($q) use ($product) {
                    $q->whereJsonContains('applicable_products', (string)$product->id)
                      ->orWhereNull('applicable_products');
                })
                ->limit(3)
                ->get();

            $flashSale = FlashSale::whereHas('sku', fn($q) => $q->where('product_id', $product->id))
                ->where('status', 'active')
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->with('sku')
                ->first();

            $preSale = PreSaleCampaign::where('product_id', $product->id)
                ->where('status', 'active')
                ->first();

            return view('public.product-detail', compact(
                'product', 'pricingPlans', 'skus', 'relatedProducts',
                '_wishlistCount', '_siteSettings',
                'activePromotions', 'activeCoupons', 'flashSale', 'preSale'
            ));
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
        $userId = auth()->id();
        $aiScores = [];
        $cfScores = [];
        $seqScores = [];
        if ($sort === 'ai' && $userId) {
            try {
                $aiService = app(\App\Services\AiRecommendationService::class);
                $aiScores = $aiService->recommendProducts($userId, 50);
            } catch (\Exception $e) { $aiScores = []; }
        } elseif ($sort === 'collaborative' && $userId) {
            try {
                $aiService = app(\App\Services\AiRecommendationService::class);
                $cfScores = $aiService->productCollaborativeFiltering($userId, 30);
            } catch (\Exception $e) { $cfScores = []; }
        } elseif ($sort === 'sequence' && $userId) {
            try {
                $seqService = app(\App\Services\BehaviorSequenceService::class);
                $seqScores = $seqService->predictNextProduct($userId, 20);
            } catch (\Exception $e) { $seqScores = []; }
        }

        $sorted = match ($sort) {
            'price' => $allProducts->sortBy('lowest_price'),
            '-price' => $allProducts->sortByDesc('lowest_price'),
            '-sold_total' => $allProducts->sortByDesc('sold_total'),
            'name' => $allProducts->sortBy('name'),
            'recommended' => $allProducts->sortByDesc(function($p) {
                $score = $p->sold_total * 2 + $p->sales_count + ($p->avg_rating ?? 0) * 10;
                if ($p->is_featured) $score += 50;
                if ($p->created_at && $p->created_at->gt(now()->subDays(7))) $score += 20;
                return $score;
            }),
            'ai' => $allProducts->sortByDesc(function($p) use ($aiScores) {
                $score = $p->sold_total + $p->sales_count + ($p->avg_rating ?? 0) * 5;
                if ($p->is_featured) $score += 30;
                if (isset($aiScores[$p->id])) $score += $aiScores[$p->id] * 100;
                if ($p->created_at && $p->created_at->gt(now()->subDays(3))) $score += 15;
                return $score;
            }),
            'collaborative' => $allProducts->sortByDesc(function($p) use ($cfScores) {
                $score = $p->sold_total + $p->sales_count + ($p->avg_rating ?? 0) * 5;
                if (isset($cfScores[$p->id])) $score += $cfScores[$p->id] * 10;
                return $score;
            }),
            'sequence' => $allProducts->sortByDesc(function($p) use ($seqScores) {
                $score = $p->sold_total + $p->sales_count + ($p->avg_rating ?? 0) * 2;
                if (isset($seqScores[$p->id])) $score += $seqScores[$p->id] * 20;
                return $score;
            }),
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
        return response()->json([
            'data' => $this->publicPricingPlanPayload(),
            'currency' => 'CNY',
            'currency_symbol' => '¥',
        ]);
    }

    /**
     * 公开定价套餐 payload（首页 SSR + API 共用）
     *
     * @return list<array<string, mixed>>
     */
    private function publicPricingPlanPayload(): array
    {
        return PricingPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => (float) $plan->price_monthly,
                'price_quarterly' => (float) $plan->price_quarterly,
                'price_semi_annually' => (float) $plan->price_semi_annually,
                'price_yearly' => (float) $plan->price_yearly,
                'currency' => $plan->currency,
                'features' => $plan->features ?? [],
                'limits' => $plan->limits,
                'badge' => $plan->badge,
                'trial_days' => $plan->trial_days,
            ])
            ->values()
            ->all();
    }

    /**
     * API: 提交企业联系表单
     *
     * POST /api/public/enterprise-contact
     */
    /**
     * 联系卖家 - 发送咨询消息
     *
     * POST /contact-seller
     */
    public function contactSeller(Request $request, UserChatConversationService $chatService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'seller_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:2000',
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
        ]);

        $product = Product::find($validated['product_id']);
        if ($product && (int) $product->user_id !== (int) $validated['seller_id']) {
            return response()->json([
                'success' => false,
                'message' => __('app.public_page.product_seller_mismatch'),
            ], 422);
        }

        $user = null;
        if ($token = $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($token)?->tokenable;
        }

        if ($user && (int) $user->id !== (int) $validated['seller_id']) {
            try {
                $conv = $chatService->findOrCreatePrivateConversation((int) $user->id, (int) $validated['seller_id']);
                if ($product) {
                    $chatService->pushProductCard(
                        $conv,
                        (int) $user->id,
                        $product,
                        __('app.public_page.product_inquiry_subject', ['product' => $product->name]),
                        'contact-seller-' . $conv->id . '-' . $validated['product_id'],
                        ['source' => 'contact_seller', 'product_id' => $validated['product_id']]
                    );
                }
                $prefix = $product ? __('app.public_page.product_inquiry_prefix', ['product' => $product->name]) . "\n" : '';
                $chatService->pushTextMessage($conv, (int) $user->id, $prefix . $validated['message'], [
                    'product_id' => $validated['product_id'],
                    'source' => 'contact_seller',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => __('app.public_page.message_sent'),
                    'conversation_id' => $conv->id,
                    'redirect' => '/build/user-chat?seller_id=' . $validated['seller_id'] . '&product_id=' . $validated['product_id'],
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('联系卖家 IM 失败: ' . $e->getMessage());
            }
        }

        try {
            \Illuminate\Support\Facades\Log::info('卖家咨询', [
                'product_id' => $validated['product_id'],
                'seller_id' => $validated['seller_id'],
                'message' => $validated['message'],
                'from_name' => $validated['name'] ?? ($user?->name ?? '匿名用户'),
                'from_email' => $validated['email'] ?? ($user?->email ?? ''),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.public_page_msg_589'),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('联系卖家失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('app.public_page.message_failed'),
            ], 500);
        }
    }

    public function enterpriseContact(Request $request): JsonResponse
    {
        return $this->submitLead($request, 'pricing');
    }

    /**
     * API: 提交联系/预约表单
     *
     * POST /api/public/contact
     */
    public function submitContact(Request $request): JsonResponse
    {
        return $this->submitLead($request, 'contact');
    }

    /**
     * API: 公开 SDK 列表
     *
     * GET /api/public/sdks
     */
    public function publicSdks(): JsonResponse
    {
        $sdks = collect(config('dev-portal.sdks', []))
            ->map(fn (array $sdk, string $key) => array_merge($sdk, ['id' => $key]))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $sdks,
            'quick_links' => config('dev-portal.quick_links', []),
        ]);
    }

    protected function submitLead(Request $request, string $defaultSource): JsonResponse
    {
        $honeypot = config('demo-booking.form.honeypot', 'website_url');

        $validated = $request->validate([
            'company_name' => 'required_without:company|string|max:200',
            'company' => 'required_without:company_name|string|max:200',
            'contact_name' => 'required_without:name|string|max:100',
            'name' => 'required_without:contact_name|string|max:100',
            'email' => 'required|email|max:200',
            'phone' => 'nullable|string|max:50',
            'employee_count' => 'nullable|string|max:50',
            'employees' => 'nullable|string|max:50',
            'product_interest' => 'nullable|string|max:500',
            'interest' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:2000',
            'source' => 'nullable|string|max:50',
            $honeypot => 'nullable|string',
        ]);

        $payload = [
            'company_name' => $validated['company_name'] ?? $validated['company'],
            'contact_name' => $validated['contact_name'] ?? $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'employee_count' => $validated['employee_count'] ?? $validated['employees'] ?? null,
            'product_interest' => $validated['product_interest'] ?? $validated['interest'] ?? null,
            'message' => $validated['message'] ?? null,
            'source' => $validated['source'] ?? $defaultSource,
            $honeypot => $validated[$honeypot] ?? null,
        ];

        $result = app(DemoBookingService::class)->submit($payload);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? __('app.contact_page.submit_fail'),
        ], ($result['success'] ?? false) ? 200 : 400);
    }

    /**
     * API: 获取 Landing Page 数据
     *
     * GET /api/public/landing
     */
    public function landing(): JsonResponse
    {
        // Capability proofs only — no vanity counts / fake ISO·SOC / invented SLA %.
        return response()->json([
            'hero' => [
                'title' => __('app.landing.hero_title'),
                'subtitle' => __('app.landing.hero_subtitle'),
                'cta_primary' => __('app.landing.hero_cta_primary'),
                'cta_secondary' => __('app.landing.hero_cta_secondary'),
            ],
            'features' => [
                [
                    'icon' => 'shield-check',
                    'title' => __('app.landing.feat_secure_title'),
                    'description' => __('app.landing.feat_secure_desc'),
                ],
                [
                    'icon' => 'bolt',
                    'title' => __('app.landing.feat_perf_title'),
                    'description' => __('app.landing.feat_perf_desc'),
                ],
                [
                    'icon' => 'device-mobile',
                    'title' => __('app.landing.feat_sdk_title'),
                    'description' => __('app.landing.feat_sdk_desc'),
                ],
                [
                    'icon' => 'cloud',
                    'title' => __('app.landing.feat_deploy_title'),
                    'description' => __('app.landing.feat_deploy_desc'),
                ],
                [
                    'icon' => 'chart-bar',
                    'title' => __('app.landing.feat_ops_title'),
                    'description' => __('app.landing.feat_ops_desc'),
                ],
                [
                    'icon' => 'globe',
                    'title' => __('app.landing.feat_compliance_title'),
                    'description' => __('app.landing.feat_compliance_desc'),
                ],
            ],
            'stats' => [
                [
                    'value' => __('app.landing.trust_proof_1_value'),
                    'label' => __('app.landing.trust_proof_1_label'),
                ],
                [
                    'value' => __('app.landing.trust_proof_2_value'),
                    'label' => __('app.landing.trust_proof_2_label'),
                ],
                [
                    'value' => __('app.landing.trust_proof_3_value'),
                    'label' => __('app.landing.trust_proof_3_label'),
                ],
                [
                    'value' => __('app.landing.trust_proof_4_value'),
                    'label' => __('app.landing.trust_proof_4_label'),
                ],
            ],
        ]);
    }
}
