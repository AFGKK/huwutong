<?php

use App\Http\Controllers\Api\BugBountyController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use Illuminate\Support\Facades\Route;

// ========================
// Prometheus 指标端点
// ========================
Route::get('/metrics', [MetricsController::class, 'index']);

// Sitemap & Robots
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index']);
Route::get('/robots.txt', \App\Http\Controllers\RobotsController::class);

// ── 互物号文章 SEO ──
Route::get('/oa-article/{id}', function (int $id) {
    $article = \App\Models\OaArticle::where('status', 'published')
        ->with('author:id,name,avatar', 'account:id,name,avatar,description')
        ->findOrFail($id);

    $title = ($article->title ?? '文章') . ' - ' . ($article->account->name ?? '互物号');
    $description = $article->summary
        ? mb_substr($article->summary, 0, 160)
        : mb_substr(strip_tags($article->content ?? ''), 0, 160);
    $ogImage = $article->cover_image;
    $canonical = url('/oa-article/' . $article->id);
    $tags = $article->tags ?? [];

    return view('public-spa', [
        'title'               => $title,
        'description'         => $description,
        'og_image'            => $ogImage,
        'og_type'             => 'article',
        'canonical'           => $canonical,
        'article_published_time' => $article->published_at?->toIso8601String(),
        'article_author'      => $article->author->name ?? '',
        'article_section'     => $article->account->name ?? '',
        'article_tags'        => $tags,
    ]);
})->whereNumber('id');

// ========================
// security.txt 标准文件 (RFC 9116) — 必须在 SPA 路由之前
// ========================
Route::get('/.well-known/security.txt', [BugBountyController::class, 'getSecurityTxt']);
Route::get('/security-policy', [BugBountyController::class, 'getPolicyPage']);
Route::get('/hall-of-fame', [BugBountyController::class, 'getHallOfFamePage']);
Route::get('/pgp-key.txt', function () {
    $pgpPath = public_path('.well-known/pgp-key.asc');
    if (file_exists($pgpPath)) {
        return response(file_get_contents($pgpPath), 200, [
            'Content-Type' => 'application/pgp-keys',
        ]);
    }
    return response('PGP key not available', 404);
});

Route::get('/', [\App\Http\Controllers\Public\PublicPageController::class, 'landingPage']);

// 公开定价页（从数据库读取套餐）
Route::get('/pricing', function () {
    $plans = \App\Models\PricingPlan::where('is_active', true)
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
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
            'badge' => $plan->badge,
            'trial_days' => $plan->trial_days,
        ]);
    return view('public.pricing', compact('plans'));
});

// 竞品对比页 (M2-100)
Route::get('/compare', function () {
    $config = app(\App\Services\ComparePageService::class)->rawConfig();

    return view('public.compare', [
        'competitors' => $config['competitors'],
        'dimensions' => $config['dimensions'],
        'comparison_data' => $config['comparison_data'],
        'seo' => $config['seo'],
    ]);
});

// 产品商城
Route::get('/products', [\App\Http\Controllers\Public\PublicPageController::class, 'products']);
Route::get('/products/{slug}', [\App\Http\Controllers\Public\PublicPageController::class, 'products']);
Route::get('/compare-products', [\App\Http\Controllers\Public\PublicPageController::class, 'compareProducts']);

// 微信小程序 → H5 登录桥接页（web-view 内打开，写入 localStorage.auth_token）
Route::get('/miniprogram/bridge', function () {
    return view('public.miniprogram-bridge');
});

// 公司信息页（CMS published+非空 → cms-page；否则静态 Blade；contact 永远静态表单）
Route::get('/about', fn () => app(\App\Services\LegalCmsPageService::class)->resolve('about'))->name('about');
Route::get('/contact', fn () => app(\App\Services\LegalCmsPageService::class)->resolve('contact'))->name('contact');
Route::get('/privacy', fn () => app(\App\Services\LegalCmsPageService::class)->resolve('privacy'))->name('privacy');
Route::get('/terms', fn () => app(\App\Services\LegalCmsPageService::class)->resolve('terms'))->name('terms');
Route::get('/page/{slug}', function (string $slug) {
    if (isset(\App\Services\LegalCmsPageService::STATIC_FALLBACKS[$slug])
        || in_array($slug, \App\Services\LegalCmsPageService::FORM_RESERVED, true)) {
        return redirect('/'.$slug, 302);
    }
    $page = \App\Models\Page::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

    return view('public.cms-page', [
        'page' => $page,
        'canonicalPath' => '/page/'.$slug,
        'usesCms' => true,
    ]);
})->where('slug', '[A-Za-z0-9\-]+')->name('cms.page');

// 开发者文档
Route::view('/sdk', 'public.sdk')->name('sdk');
Route::redirect('/docs/sdk', '/sdk', 301);
Route::get('/docs/sdk/{lang}', [\App\Http\Controllers\Public\SdkDocsController::class, 'show'])
    ->where('lang', '[A-Za-z0-9\-]+')
    ->name('sdk.docs');
Route::view('/docs/quickstart', 'public.quickstart')->name('quickstart');

// 开发者 Blog (M3-57)
Route::get('/blog', function () {
    return view('public.blog');
});
Route::get('/blog/{slug}', function ($slug) {
    return view('public.blog', ['slug' => $slug]);
});
// 帮助中心/知识库 (M2-107)
Route::get('/help', function () {
    return view('public.help');
});
Route::get('/help/{id}', function ($id) {
    return view('public.help', ['slug' => $id]);
})->whereNumber('id');

// 互物库 — 统一搜索引擎
Route::get('/search', function () {
    return view('public.search');
});

// 公开授权查询
Route::get('/license/query', function () {
    return view('public.license-query');
});

// Cookie 政策
Route::get('/cookie-policy', function () {
    return view('public.cookie-policy');
});
// 聊天 FAQ 管理（独立 Blade 页面）
Route::prefix('admin/chat-faqs')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'index'])->name('admin.chat-faqs.index');
    Route::post('/', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'store'])->name('admin.chat-faqs.store');
    Route::put('/{faq}', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'update'])->name('admin.chat-faqs.update');
    Route::post('/{faq}/toggle-active', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'toggleActive'])->name('admin.chat-faqs.toggle-active');
    Route::post('/{faq}/move-up', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'moveUp'])->name('admin.chat-faqs.move-up');
    Route::post('/{faq}/move-down', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'moveDown'])->name('admin.chat-faqs.move-down');
    Route::delete('/{faq}', [\App\Http\Controllers\Admin\ChatFaqAdminController::class, 'destroy'])->name('admin.chat-faqs.destroy');
});

// 联盟推广链接跳转 - 公开活动落地页
Route::get('/ref/{slug}', function (string $slug) {
    $campaign = \App\Models\AffiliateCampaign::where('slug', $slug)
        ->where('status', 'active')
        ->first();

    if (!$campaign) {
        return redirect('/');
    }

    $ref = request()->query('ref', '');
    $remaining = ($campaign->budget_deposited ?? 0) - ($campaign->budget_used ?? 0);
    $typeLabels = [
        'referral' => '推荐返佣',
        'commission' => '佣金加成',
        'reward' => '奖励计划',
        'rebate' => '返现活动',
    ];

    return view('public.affiliate-landing', [
        'campaign' => $campaign,
        'typeLabel' => $typeLabels[$campaign->type] ?? $campaign->type,
        'remaining' => max(0, $remaining),
        'referralCode' => $ref,
    ]);
});

// 推广点击追踪中间页 - 记录点击后跳转到目标URL
Route::get('/go/{creative}', function (\App\Models\AffiliateCreative $creative) {
    if (!$creative->is_active) {
        return redirect('/');
    }

    $campaign = $creative->campaign;
    if (!$campaign || $campaign->status !== 'active') {
        return redirect('/');
    }

    $ref = request()->query('ref', '');

    // 预算检查：预算耗尽时停止追踪
    $budgetRemaining = ($campaign->budget_deposited ?? 0) - ($campaign->budget_used ?? 0);
    $budgetExhausted = $budgetRemaining <= 0 && ($campaign->budget_deposited ?? 0) > 0;

    if (!$budgetExhausted) {
        $agent = null;
        if ($ref) {
            $tracking = \App\Models\RegistrationTracking::where('invite_code', $ref)->first();
            $agent = $tracking ? \App\Models\Agent::find($tracking->agent_id) : null;
        }

        // 记录点击
        $platformShareRate = $campaign->platform_share_rate ?? 0;
        \App\Models\AffiliateClick::create([
            'agent_id' => $agent?->id,
            'campaign_id' => $campaign->id,
            'creative_id' => $creative->id,
            'referral_code' => $ref,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer_url' => request()->header('referer'),
            'landing_url' => $creative->url ?: url("/ref/{$campaign->slug}"),
            'commission_amount' => $creative->commission_amount,
            'commission_rate' => $creative->commission_rate,
            'platform_share_rate' => $platformShareRate,
            'utm_params' => request()->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']),
        ]);

        // CPC 计费：每次点击扣费
        if ($campaign->billing_mode === 'cpc' && $campaign->cost_per_click > 0) {
            $campaign->increment('budget_used', $campaign->cost_per_click);
        }
    }

    // 构建目标URL（追加 ref 参数）
    $targetUrl = $creative->url ?: url("/ref/{$campaign->slug}");
    if ($ref) {
        $separator = str_contains($targetUrl, '?') ? '&' : '?';
        $targetUrl .= $separator . 'ref=' . urlencode($ref);
    }

    return redirect($targetUrl);
});

// 推广展示追踪（CPM 计费用 / 图片像素方式调用）
Route::get('/impression/{creative}', function (\App\Models\AffiliateCreative $creative) {
    if (!$creative->is_active) return response('', 204);

    $campaign = $creative->campaign;
    if (!$campaign || $campaign->status !== 'active' || $campaign->billing_mode !== 'cpm') {
        return response('', 204);
    }

    // 预算检查
    $budgetRemaining = ($campaign->budget_deposited ?? 0) - ($campaign->budget_used ?? 0);
    if ($budgetRemaining <= 0 && ($campaign->budget_deposited ?? 0) > 0) {
        return response('', 204);
    }

    // 记录展示
    \App\Models\AffiliateClick::create([
        'campaign_id' => $campaign->id,
        'creative_id' => $creative->id,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'referrer_url' => request()->header('referer'),
        'utm_params' => request()->only(['utm_source', 'utm_medium', 'utm_campaign']),
    ]);

    // CPM 计费：每1000次展示扣费（使用缓存计数）
    $cacheKey = "cpm_impression_{$campaign->id}";
    $count = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0) + 1;
    \Illuminate\Support\Facades\Cache::put($cacheKey, $count, now()->addDay());
    if ($count % 1000 === 0 && $campaign->cost_per_impression > 0) {
        $campaign->increment('budget_used', $campaign->cost_per_impression);
    }

    // 返回 1x1 透明 GIF 用于图片像素追踪
    return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
        'Content-Type' => 'image/gif',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
});

// Vue 管理后台 SPA（所有 /admin/* 路由指向 admin Blade 模板）
Route::get('/admin/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// 登录页（供 auth 中间件重定向）
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Vue 管理后台 SPA（开发模式下 Vite base URL 路径）
// 公开 SPA 页面 - 使用带官网导航的布局
Route::get('/community', function () {
    return view('public-spa', ['title' => '社区 - 互物通']);
});
Route::get('/community/{path?}', function () {
    return view('public-spa', ['title' => '社区 - 互物通']);
})->where('path', '.*');
Route::get('/build/community', function () {
    return view('public-spa', ['title' => '社区 - 互物通']);
});
Route::get('/build/community/{path?}', function () {
    return view('public-spa', ['title' => '社区 - 互物通']);
})->where('path', '.*');
Route::get('/build/channels', function () {
    return view('public-spa', ['title' => '互物号 - 互物通']);
});
Route::get('/build/channels/{path?}', function () {
    return view('public-spa', ['title' => '互物号 - 互物通']);
})->where('path', '.*');
Route::get('/build/oa-article/{id}', function (int $id) {
    $article = \App\Models\OaArticle::find($id);
    return view('public-spa', [
        'title' => ($article?->title ?? '文章') . ' - ' . ($article?->account?->name ?? '互物号'),
    ]);
})->whereNumber('id');
Route::get('/build/plaza/{path?}', function () {
    return view('public-spa', ['title' => '广场 - 互物通']);
})->where('path', '.*');
Route::get('/build/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// Auth SPA routes (magic-link verify, forgot-password, etc.)
Route::get('/auth/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// 客户门户 SPA（所有 /portal/* 路由指向 admin Blade 模板）
Route::get('/portal/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// PWA 离线回退页
Route::get('/build/offline', function () {
    return view('offline');
});

// Widget 嵌入页面（M2-141: 可嵌入式授权管理 Widget）
Route::get('/widget/embed', function () {
    return view('widget-embed');
});

// WCAG 无障碍声明公开页面（M3-54）
Route::get('/accessibility', function () {
    return view('a11y-declaration');
});

// ========================
// 支付 Webhook 路由（公开，CSRF 白名单中排除）
// ========================
Route::prefix('api/payment')->group(function () {
    Route::post('/stripe/webhook', [PaymentWebhookController::class, 'stripe'])->name('payment.stripe.webhook');
    Route::post('/alipay/webhook', [PaymentWebhookController::class, 'alipay'])->name('payment.alipay.webhook');
    Route::post('/paypal/webhook', [PaymentWebhookController::class, 'paypal'])->name('payment.paypal.webhook');
    Route::post('/wechat/webhook', [PaymentWebhookController::class, 'wechat'])->name('payment.wechat.webhook');
    Route::post('/yipay/webhook', [PaymentWebhookController::class, 'yipay'])->name('payment.yipay.webhook');
});

// Bug Bounty 漏洞报告提交&政策（公开）
Route::post('/bug-bounty/reports', [BugBountyController::class, 'submitReport']);
Route::get('/bug-bounty/policy', [BugBountyController::class, 'getPolicy']);
