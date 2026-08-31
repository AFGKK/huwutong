<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillingHistoryPortalController;
use App\Http\Controllers\Api\PrepaidBalanceController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerDataExportController;
use App\Http\Controllers\Api\EarningsPortalController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\GdprComplianceController;
use App\Http\Controllers\Api\EcommerceAPIController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PortalBrandingController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\TenantTeamController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\CustomerAnalyticsController;
use App\Http\Controllers\Api\Portal\LicenseHealthController;
use Illuminate\Support\Facades\Route;

// ========================
// 公开路由（无需登录）
// ========================

// 品牌信息（按域名解析租户）
Route::get('/branding', [PortalBrandingController::class, 'publicBranding']);

// 公开商品SKU列表
Route::get('/skus', [OrderController::class, 'skus']);
Route::get('/products/skus', [OrderController::class, 'skus']);
Route::get('/products/suggest', [EcommerceAPIController::class, 'productSuggest']);
Route::get('/products/search-suggest', [EcommerceAPIController::class, 'productSuggest']);
Route::get('/products/filter-tags', [EcommerceAPIController::class, 'filterTags']);
Route::get('/products/hot-search-terms', [EcommerceAPIController::class, 'hotSearchTerms']);

// 公开产品分类
Route::get('/product-categories/public', [ProductCategoryController::class, 'publicList']);
Route::get('/product-categories/tree', [ProductCategoryController::class, 'tree']);

// 公开促销
Route::get('/promotions/public', [PromotionController::class, 'activePromotions']);

// 公开促销
Route::get('/promotions/public', [PromotionController::class, 'activePromotions']);

// ========================
// 需要登录的客户门户路由
// ========================
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

    // ── 商品搜索历史 ──
    Route::get('/products/search-history', [OrderController::class, 'skus']);
    Route::delete('/products/search-history', [OrderController::class, 'skus']);

    // ── 购物车 ──
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::put('/cart/update', [CartController::class, 'update']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::post('/cart/clear', [CartController::class, 'clear']);
    Route::get('/cart/summary', [CartController::class, 'summary']);
    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon']);
    Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);
    Route::post('/cart/validate', [CartController::class, 'validateCheckout']);
    Route::post('/cart/validate-checkout', [CartController::class, 'validateCheckout']);
    Route::post('/cart/quick-buy', [CartController::class, 'quickBuy']);

    // ── 订单 ──
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');
    Route::post('/orders/{id}/pay', [OrderController::class, 'pay'])->whereNumber('id');
    Route::get('/orders/{id}/payment-status', [OrderController::class, 'paymentStatus'])->whereNumber('id');

    // ── 退款售后 ──
    Route::post('/refunds', [EcommerceAPIController::class, 'refundRequest']);
    Route::get('/refunds', [EcommerceAPIController::class, 'myRefundList']);

    // ── License 转移 ──
    Route::get('/portal/transfers', [TransferController::class, 'myRequests']);
    Route::get('/portal/transfers/licenses', [TransferController::class, 'transferableLicenses']);
    Route::post('/portal/transfers', [TransferController::class, 'store']);
    Route::get('/portal/transfers/{transfer}', [TransferController::class, 'myShow'])->whereNumber('transfer');
    Route::post('/portal/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->whereNumber('transfer');
    Route::post('/portal/transfers/{transfer}/code', [TransferController::class, 'generateCode'])->whereNumber('transfer');
    Route::post('/portal/transfers/{transfer}/verify', [TransferController::class, 'verifyCode'])->whereNumber('transfer');

    // ── 收益/佣金 ──
    Route::get('/portal/earnings/dashboard', [EarningsPortalController::class, 'dashboard']);
    Route::get('/portal/earnings/commissions', [EarningsPortalController::class, 'commissions']);
    Route::get('/portal/earnings/channels', [EarningsPortalController::class, 'withdrawalChannels']);
    Route::post('/portal/earnings/save-account', [EarningsPortalController::class, 'saveAccount']);
    Route::post('/portal/earnings/channels/account', [EarningsPortalController::class, 'saveAccount']);
    Route::delete('/portal/earnings/accounts/{channel}', [EarningsPortalController::class, 'deleteAccount']);
    Route::delete('/portal/earnings/channels/account/{channel}', [EarningsPortalController::class, 'deleteAccount']);
    Route::match(['get', 'post', 'put'], '/portal/earnings/preferences', [EarningsPortalController::class, 'preferences']);
    Route::match(['get', 'post'], '/portal/earnings/tax-info', [EarningsPortalController::class, 'taxInfo']);
    Route::get('/portal/earnings/settlement-calendar', [EarningsPortalController::class, 'settlementCalendar']);
    Route::get('/portal/earnings/export', [EarningsPortalController::class, 'exportCommissions']);

    // ── 提现 ──
    Route::get('/withdrawals/channels', [WithdrawalController::class, 'channels']);
    Route::post('/withdrawals', [WithdrawalController::class, 'requestWithdrawal']);
    Route::get('/withdrawals/my', [WithdrawalController::class, 'myWithdrawals']);
    Route::get('/withdrawals/my/stats', [WithdrawalController::class, 'myStats']);
    Route::post('/withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancelWithdrawal'])->whereNumber('withdrawal');

    // ── 促销/优惠券（客户侧） ──
    Route::get('/portal/promotions/active', [PromotionController::class, 'activePromotions']);
    Route::get('/portal/promotions/coupons', [PromotionController::class, 'index']);

    // ── 团队协作 ──
    Route::get('/team', [TenantTeamController::class, 'overview']);
    Route::get('/team/members', [TenantTeamController::class, 'members']);
    Route::post('/team/invite', [TenantTeamController::class, 'invite']);
    Route::post('/team/invitations/{invitation}/accept', [TenantTeamController::class, 'acceptInvitation']);
    Route::post('/team/invitations/{invitation}/decline', [TenantTeamController::class, 'declineInvitation']);
    Route::post('/team/invitations/{invitation}/cancel', [TenantTeamController::class, 'cancelInvitation']);
    Route::post('/team/invitations/{invitation}/resend', [TenantTeamController::class, 'resendInvitation']);
    Route::get('/team/invitations/pending', [TenantTeamController::class, 'pendingInvitations']);
    Route::put('/team/members/{member}/role', [TenantTeamController::class, 'updateMemberRole'])->whereNumber('member');
    Route::delete('/team/members/{member}', [TenantTeamController::class, 'removeMember'])->whereNumber('member');
    Route::post('/team/transfer-admin', [TenantTeamController::class, 'transferAdmin']);
    Route::post('/team/leave', [TenantTeamController::class, 'leave']);

    // ── 数据导出 ──
    Route::get('/portal/data-exports/types', [CustomerDataExportController::class, 'availableTypes']);
    Route::post('/portal/data-exports', [CustomerDataExportController::class, 'createExport']);
    Route::get('/portal/data-exports/my', [CustomerDataExportController::class, 'myExports']);
    Route::get('/portal/data-exports/{id}/download', [CustomerDataExportController::class, 'downloadExport'])->whereNumber('id');
    Route::delete('/portal/data-exports/{id}', [CustomerDataExportController::class, 'deleteExport'])->whereNumber('id');

    // ── 通知偏好 ──
    Route::prefix('portal/notification-preferences')->group(function () {
        Route::get('/', [NotificationPreferenceController::class, 'myPreferences']);
        Route::put('/', [NotificationPreferenceController::class, 'updateMyPreferences']);
        Route::post('/initialize', [NotificationPreferenceController::class, 'initializeMyPreferences']);
        Route::get('/check', [NotificationPreferenceController::class, 'checkNotification']);
        Route::patch('/general', [NotificationPreferenceController::class, 'updateGeneralSettings']);
        Route::get('/resolve-channels', [NotificationPreferenceController::class, 'resolveChannels']);
    });

    // ── 客户反馈 ──
    Route::get('/portal/feedback', [FeedbackController::class, 'myFeedback']);
    Route::post('/portal/feedback', [FeedbackController::class, 'store']);
    Route::get('/portal/feedback/{feedback}', [FeedbackController::class, 'myShow'])->whereNumber('feedback');
    Route::post('/portal/feedback/{feedback}/vote', [FeedbackController::class, 'vote'])->whereNumber('feedback');

    // ── GDPR 请求 ──
    Route::post('/gdpr/requests', [GdprComplianceController::class, 'submitRequest']);

    // ── 计费/套餐 ──
    Route::get('/billing/plans/public', [BillingController::class, 'plans']);
    Route::post('/billing/coupons/validate', [BillingController::class, 'validateCoupon']);
    Route::prefix('portal/billing')->group(function () {
        Route::get('/invoices', [BillingHistoryPortalController::class, 'invoices']);
        Route::get('/invoices/{id}', [BillingHistoryPortalController::class, 'show'])->whereNumber('id');
        Route::get('/stats', [BillingHistoryPortalController::class, 'stats']);
        Route::get('/subscriptions', [BillingHistoryPortalController::class, 'subscriptions']);
        Route::get('/failed-payments', [BillingHistoryPortalController::class, 'failedPayments']);
        Route::get('/auto-renewals', [BillingHistoryPortalController::class, 'autoRenewals']);
        Route::get('/filter-options', [BillingHistoryPortalController::class, 'filterOptions']);
        Route::post('/invoices/{id}/pay', [BillingHistoryPortalController::class, 'payInvoice'])->whereNumber('id');
    });

    // ── 预付费余额 ──
    Route::prefix('portal/prepaid')->group(function () {
        Route::get('/balance', [PrepaidBalanceController::class, 'myBalance']);
        Route::post('/recharge', [PrepaidBalanceController::class, 'recharge']);
        Route::get('/transactions', [PrepaidBalanceController::class, 'myTransactions']);
        Route::post('/auto-recharge', [PrepaidBalanceController::class, 'saveAutoRecharge']);
        Route::post('/check-auto-recharge', [PrepaidBalanceController::class, 'checkAutoRecharge']);
    });

    // ── 支付方式管理 ──
    Route::get('/billing/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/billing/payment-methods', [PaymentMethodController::class, 'store']);
    Route::delete('/billing/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->whereNumber('id');

    // ── License 健康评分 ──
    Route::get('/portal/license-health/dashboard', [LicenseHealthController::class, 'dashboard']);
    Route::get('/portal/license-health', [LicenseHealthController::class, 'index']);
    Route::get('/portal/license-health/{licenseId}', [LicenseHealthController::class, 'show'])->whereNumber('licenseId');

    // ── 客户自助分析仪表盘 ──
    Route::prefix('/analytics')->group(function () {
        Route::get('/overview', [CustomerAnalyticsController::class, 'overview']);
        Route::get('/license-trend', [CustomerAnalyticsController::class, 'licenseTrend']);
        Route::get('/license-distribution', [CustomerAnalyticsController::class, 'licenseDistribution']);
        Route::get('/spend-trend', [CustomerAnalyticsController::class, 'spendTrend']);
        Route::get('/device-trend', [CustomerAnalyticsController::class, 'deviceTrend']);
        Route::get('/top-licenses', [CustomerAnalyticsController::class, 'topLicenses']);
        Route::get('/health-score', [CustomerAnalyticsController::class, 'healthScore']);
        Route::get('/export/{type}', [CustomerAnalyticsController::class, 'export'])->whereIn('type', ['licenses', 'devices', 'orders']);
    });
});
