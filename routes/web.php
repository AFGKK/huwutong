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
    $config = config('compare-page');
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

// 公司信息页
Route::view('/about', 'public.about')->name('about');
Route::view('/contact', 'public.contact')->name('contact');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/terms', 'public.terms')->name('terms');

// 开发者文档
Route::view('/sdk', 'public.sdk')->name('sdk');
Route::redirect('/docs/sdk', '/sdk', 301);
Route::redirect('/docs/sdk/{lang}', '/sdk', 301);
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

// Vue 管理后台 SPA（所有 /admin/* 路由指向 admin Blade 模板）
Route::get('/admin/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// 登录页（供 auth 中间件重定向）
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Vue 管理后台 SPA（开发模式下 Vite base URL 路径）
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
});

// Bug Bounty 漏洞报告提交&政策（公开）
Route::post('/bug-bounty/reports', [BugBountyController::class, 'submitReport']);
Route::get('/bug-bounty/policy', [BugBountyController::class, 'getPolicy']);
