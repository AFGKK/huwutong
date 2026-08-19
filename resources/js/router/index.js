import { createRouter, createWebHistory } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import i18n from '@/i18n';
import { resolveDocumentTitle } from '@/utils/resolveDocumentTitle';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('@/views/auth/Login.vue'),
        meta: { layout: 'blank' },
    },
    {
        path: '/register',
        name: 'Register',
        component: () => import('@/views/auth/Register.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.register' },
    },
    {
        path: '/forgot-password',
        name: 'ForgotPassword',
        component: () => import('@/views/auth/ForgotPassword.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.forgot_password' },
    },
    {
        path: '/checkout',
        name: 'Checkout',
        component: () => import('@/views/checkout/Index.vue'),
        meta: { titleKey: 'route_titles.checkout' },
    },
    {
        path: '/status',
        name: 'StatusPage',
        component: () => import('@/views/status/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.status' },
    },
    {
        path: '/following-feed',
        name: 'FollowingFeed',
        component: () => import('@/views/account/FollowingFeed.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.following_feed' },
    },
    {
        path: '/appeal',
        name: 'Appeal',
        component: () => import('@/views/appeal/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.appeal' },
    },
    {
        path: '/demo',
        name: 'InteractiveDemo',
        component: () => import('@/views/demo/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.demo' },
    },
    {
        path: '/tenant-select',
        name: 'TenantSelect',
        component: () => import('@/views/tenants/Select.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.tenant_select' },
    },
    {
        path: '/onboarding',
        name: 'Onboarding',
        component: () => import('@/views/onboarding/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.setup' },
    },
    {
        path: '/qr-confirm',
        name: 'QrConfirm',
        component: () => import('@/views/auth/QrConfirm.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.qr_confirm' },
    },
    {
        path: '/oa-editor',
        name: 'OaEditor',
        component: () => import('@/views/im/OaArticleEditor.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.oa_editor' },
    },
    {
        path: '/oa-article/:id',
        name: 'OaArticleDetail',
        component: () => import('@/views/im/OaArticleDetail.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.oa_article' },
    },
    {
        path: '/plaza/:id',
        name: 'PlazaDetail',
        component: () => import('@/views/plaza/Detail.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.plaza_detail' },
    },
    // ── 社区 / 广场 ──
    {
        path: '/community',
        name: 'Community',
        component: () => import('@/views/community/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.community' },
    },
    {
        path: '/plaza/user/:id',
        name: 'UserProfile',
        component: () => import('@/views/community/UserProfile.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.user_profile' },
    },
    // ── 互物号 ──
    {
        path: '/channels',
        name: 'Channels',
        component: () => import('@/views/channels/Index.vue'),
        meta: { layout: 'blank', titleKey: 'route_titles.channels' },
    },
    // ── 已合并 IM 页面重定向（保持向后兼容） ──
    { path: '/ai-chat', redirect: '/im' },
    { path: '/handoff', redirect: '/im?tab=ai-chat' },
    { path: '/handoff/:id', redirect: '/im?tab=ai-chat' },
    { path: '/live-chat', redirect: '/user-chat' },
    { path: '/teams-notifier', redirect: '/im-integration' },
    {
        path: '/',
        component: () => import('@/layouts/AdminLayout.vue'),
        redirect: '/dashboard',
        children: [
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: () => import('@/views/dashboard/Index.vue'),
                meta: { titleKey: 'admin.menu.dashboard', icon: 'Odometer' },
            },
            {
                path: 'licenses',
                name: 'Licenses',
                component: () => import('@/views/licenses/Index.vue'),
                meta: { titleKey: 'admin.menu.licenses', icon: 'Key' },
            },
            {
                path: 'licenses/:id',
                name: 'LicenseDetail',
                component: () => import('@/views/licenses/Detail.vue'),
                meta: { titleKey: 'route_titles.licenses_id', hidden: true },
            },
            {
                path: 'license-analytics',
                name: 'LicenseAnalytics',
                component: () => import('@/views/license-analytics/Index.vue'),
                meta: { titleKey: 'admin.menu.license_analytics', icon: 'DataBoard' },
            },
            {
                path: 'product-analytics',
                name: 'ProductAnalytics',
                component: () => import('@/views/product-analytics/Index.vue'),
                meta: { titleKey: 'admin.menu.product_analytics', icon: 'DataAnalysis' },
            },
            // ── SKU 商品规格管理 (M1.1-24 🛒) ──
            {
                path: 'product-sku',
                name: 'ProductSku',
                component: () => import('@/views/product-sku/Index.vue'),
                meta: { titleKey: 'admin.menu.product_sku', icon: 'Goods' },
            },
            // ── 优惠券管理 (M1.4-65) ──
            {
                path: 'coupons',
                name: 'Coupon',
                component: () => import('@/views/coupon/Index.vue'),
                meta: { titleKey: 'admin.menu.coupons', icon: 'Coin' },
            },
            // ── API Mock Server (M1.4-59) ──
            {
                path: 'mock-server',
                name: 'MockServer',
                component: () => import('@/views/mock-server/Index.vue'),
                meta: { titleKey: 'admin.menu.mock_server', icon: 'Connection' },
            },
            // ── API Key 管理中心 (Merged) ──
            {
                path: 'api-key-center',
                name: 'ApiKeyCenter',
                component: () => import('@/views/api-key-center/Index.vue'),
                meta: { titleKey: 'admin.menu.api_key_center', icon: 'Key' },
            },
            // ── CRL 吊销列表管理 (M1.3-03) ──
            {
                path: 'crl',
                name: 'Crl',
                component: () => import('@/views/crl/Index.vue'),
                meta: { titleKey: 'admin.menu.crl', icon: 'RemoveFilled' },
            },
            // ── 异常检测 (M2-04) ──
            {
                path: 'anomaly-detection',
                name: 'AnomalyDetection',
                component: () => import('@/views/anomaly-detection/Index.vue'),
                meta: { titleKey: 'admin.menu.anomaly_detection', icon: 'WarningFilled' },
            },
            // ── 密钥泄露扫描 (M1.3-29) ──
            {
                path: 'secret-scan',
                name: 'SecretScan',
                component: () => import('@/views/secret-scan/Index.vue'),
                meta: { titleKey: 'admin.menu.secret_scan', icon: 'WarningFilled' },
            },
            // ── AI Token 用量计费 (M2-77) ──
            {
                path: 'token-meter',
                name: 'TokenMeter',
                component: () => import('@/views/token-meter/Index.vue'),
                meta: { titleKey: 'admin.menu.token_meter', icon: 'Money' },
            },
            {
                path: 'customer-merge',
                name: 'CustomerMerge',
                component: () => import('@/views/customer-merge/Index.vue'),
                meta: { titleKey: 'admin.menu.customer_merge', icon: 'Connection' },
            },
            {
                path: 'license-merge',
                name: 'LicenseMerge',
                component: () => import('@/views/license-merge/Index.vue'),
                meta: { titleKey: 'route_titles.license_merge', icon: 'CopyDocument' },
            },
            {
                path: 'license-trash',
                name: 'LicenseTrash',
                component: () => import('@/views/license-trash/Index.vue'),
                meta: { titleKey: 'admin.menu.license_trash', icon: 'Delete' },
            },
            {
                path: 'license-snapshot',
                name: 'LicenseSnapshot',
                component: () => import('@/views/license-snapshot/Index.vue'),
                meta: { titleKey: 'admin.menu.license_snapshot', icon: 'Clock' },
            },
            {
                path: 'license-approval',
                name: 'LicenseApproval',
                component: () => import('@/views/license-approval/Index.vue'),
                meta: { titleKey: 'admin.menu.license_approval', icon: 'Checked' },
            },
            {
                path: 'ecommerce-dashboard',
                name: 'EcommerceDashboard',
                component: () => import('@/views/ecommerce-dashboard/Index.vue'),
                meta: { titleKey: 'admin.menu.ecommerce_dashboard', icon: 'TrendCharts' },
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: () => import('@/views/inventory/Index.vue'),
                meta: { titleKey: 'admin.menu.inventory', icon: 'Goods' },
            },
            {
                path: 'billing-cycles',
                name: 'BillingCycles',
                component: () => import('@/views/billing-cycles/Index.vue'),
                meta: { titleKey: 'admin.menu.billing_cycles', icon: 'Timer' },
            },
            {
                path: 'payment-security',
                name: 'PaymentSecurity',
                component: () => import('@/views/payment-security/Index.vue'),
                meta: { titleKey: 'admin.menu.payment_security', icon: 'Shield' },
            },
            {
                path: 'local-proxy',
                name: 'LocalProxy',
                component: () => import('@/views/local-proxy/Index.vue'),
                meta: { titleKey: 'route_titles.local_proxy', icon: 'Connection' },
            },
            {
                path: 'heatmap',
                name: 'Heatmap',
                component: () => import('@/views/heatmap/Index.vue'),
                meta: { titleKey: 'route_titles.heatmap', icon: 'MapLocation' },
            },
            {
                path: 'contracts',
                name: 'SmartContract',
                component: () => import('@/views/contracts/Index.vue'),
                meta: { titleKey: 'route_titles.contracts', icon: 'Document' },
            },
            {
                path: 'license-transfers',
                name: 'LicenseTransfer',
                component: () => import('@/views/transfers/Index.vue'),
                meta: { titleKey: 'route_titles.license_transfers', icon: 'Switch' },
            },
            {
                path: 'ownership-transfer',
                name: 'OwnershipTransfer',
                component: () => import('@/views/ownership-transfer/Index.vue'),
                meta: { titleKey: 'route_titles.ownership_transfer', icon: 'Connection' },
            },
            {
                path: 'files',
                name: 'CustomerFiles',
                component: () => import('@/views/files/Index.vue'),
                meta: { titleKey: 'route_titles.files', icon: 'FolderOpened' },
            },
            {
                path: 'roi-calculator',
                name: 'RoiCalculator',
                component: () => import('@/views/roi-calculator/Index.vue'),
                meta: { titleKey: 'route_titles.roi_calculator', icon: 'DataAnalysis' },
            },
            {
                path: 'blog',
                name: 'BlogManager',
                component: () => import('@/views/blog/Index.vue'),
                meta: { titleKey: 'admin.menu.blog_changelog', icon: 'Document' },
            },
            {
                path: 'prompt-templates',
                name: 'PromptTemplates',
                component: () => import('@/views/prompt-templates/Index.vue'),
                meta: { titleKey: 'admin.menu.prompt_templates', icon: 'EditPen' },
            },
            {
                path: 'sensitive-words',
                name: 'SensitiveWords',
                component: () => import('@/views/sensitive-words/Index.vue'),
                meta: { titleKey: 'admin.menu.sensitive_words', icon: 'WarningFilled' },
            },
            {
                path: 'bundles',
                name: 'BundleManager',
                component: () => import('@/views/bundles/Index.vue'),
                meta: { titleKey: 'admin.menu.bundles', icon: 'Goods' },
            },
            {
                path: 'product-localization',
                name: 'ProductLocalization',
                component: () => import('@/views/product-localization/Index.vue'),
                meta: { titleKey: 'admin.menu.product_localization', icon: 'ChatRound' },
            },
            {
                path: 'reviews',
                name: 'ProductReview',
                component: () => import('@/views/product-review/Index.vue'),
                meta: { titleKey: 'admin.menu.reviews', icon: 'Star' },
            },
            {
                path: 'product-comparison',
                name: 'ProductComparison',
                component: () => import('@/views/product-comparison/Index.vue'),
                meta: { titleKey: 'admin.menu.product_comparison', icon: 'Histogram' },
            },
            {
                path: 'pre-sale',
                name: 'PreSale',
                component: () => import('@/views/pre-sale/Index.vue'),
                meta: { titleKey: 'admin.menu.pre_sale', icon: 'Money' },
            },
            {
                path: 'resale',
                name: 'ResaleMarketplace',
                component: () => import('@/views/resale/Index.vue'),
                meta: { titleKey: 'admin.menu.resale', icon: 'ShoppingCart' },
            },
            {
                path: 'certification',
                name: 'Certification',
                component: () => import('@/views/certification/Index.vue'),
                meta: { titleKey: 'admin.menu.certification', icon: 'Medal' },
            },
            {
                path: 'open-platform',
                name: 'OpenPlatform',
                component: () => import('@/views/open-platform/Index.vue'),
                props: { defaultTab: 'pending' },
                meta: { titleKey: 'admin.menu.open_platform', icon: 'Grid' },
            },
            {
                path: 'app-marketplace',
                name: 'AppMarketplace',
                component: () => import('@/views/app-marketplace/Index.vue'),
                meta: { titleKey: 'admin.menu.app_marketplace', icon: 'ShoppingCart' },
            },
            {
                path: 'app-marketplace/:id',
                name: 'AppMarketplaceDetail',
                component: () => import('@/views/app-marketplace/Detail.vue'),
                meta: { titleKey: 'route_titles.app_marketplace_id', icon: 'ShoppingCart' },
            },
            {
                path: 'quota',
                name: 'Quota',
                component: () => import('@/views/quota/Index.vue'),
                meta: { titleKey: 'admin.menu.quota', icon: 'TrendCharts' },
            },
            {
                path: 'monitor',
                name: 'MonitorDashboard',
                component: () => import('@/views/monitor/Index.vue'),
                meta: { titleKey: 'admin.menu.monitor', icon: 'DataAnalysis' },
            },
            {
                path: 'compat-test',
                name: 'CompatTest',
                component: () => import('@/views/compat-test/Index.vue'),
                meta: { titleKey: 'admin.menu.compat_test', icon: 'Monitor' },
            },
            {
                path: 'wishlist',
                name: 'Wishlist',
                component: () => import('@/views/wishlist/Index.vue'),
                meta: { titleKey: 'route_titles.wishlist', icon: 'Star' },
            },
            {
                path: 'license-key-prefix',
                name: 'LicenseKeyPrefix',
                component: () => import('@/views/key-prefix/Index.vue'),
                meta: { titleKey: 'admin.menu.license_key_prefix', icon: 'EditPen' },
            },
            {
                path: 'licenses/:id/seat-pool',
                name: 'SeatPool',
                component: () => import('@/views/licenses/SeatPool.vue'),
                meta: { titleKey: 'route_titles.licenses_id_2', hidden: true },
            },
            // ── 席位池管理 (M3-45) ──
            {
                path: 'seat-pool',
                name: 'SeatPoolAdmin',
                component: () => import('@/views/seat-pool/Index.vue'),
                meta: { titleKey: 'route_titles.seat_pool', icon: 'Connection' },
            },
            // ── Webhook 管理 (已整合) ──
            {
                path: 'webhooks',
                name: 'Webhooks',
                component: () => import('@/views/webhooks/Index.vue'),
                meta: { titleKey: 'admin.menu.webhooks', icon: 'Link' },
            },
            // ── SIEM 审计日志导出 (M2-52) ──
            {
                path: 'siem-export',
                name: 'SiemExport',
                component: () => import('@/views/siem-export/Index.vue'),
                meta: { titleKey: 'admin.menu.siem_export', icon: 'DataBoard' },
            },
            // ── 页脚导航配置 (M2-85) ──
            {
                path: 'footer-nav',
                name: 'FooterNav',
                component: () => import('@/views/footer-nav/Index.vue'),
                meta: { titleKey: 'admin.menu.footer_nav', icon: 'Link' },
            },
            // ── 两阶段提交 (M3-13) ──
            {
                path: 'two-phase-commit',
                name: 'TwoPhaseCommit',
                component: () => import('@/views/two-phase-commit/Index.vue'),
                meta: { titleKey: 'route_titles.two_phase_commit', icon: 'Timer' },
            },
            // ── MCP Server / AI Agent 授权 (M3-15) ──
            {
                path: 'mcp-auth',
                name: 'McpAuth',
                component: () => import('@/views/mcp-auth/Index.vue'),
                meta: { title: 'MCP / AI Agent', icon: 'Connection' },
            },
            // ── 区块链/NFT License (M3-14) ──
            {
                path: 'blockchain-license',
                name: 'BlockchainLicense',
                component: () => import('@/views/blockchain-license/Index.vue'),
                meta: { titleKey: 'admin.menu.blockchain_license', icon: 'Coin' },
            },
            // ── Serverless 云函数授权 (M3-16) ──
            {
                path: 'serverless-auth',
                name: 'ServerlessAuth',
                component: () => import('@/views/serverless-auth/Index.vue'),
                meta: { titleKey: 'admin.menu.serverless_auth', icon: 'Monitor' },
            },
            // ── 边缘计算授权 (M3-17) ──
            {
                path: 'edge-auth',
                name: 'EdgeAuth',
                component: () => import('@/views/edge-auth/Index.vue'),
                meta: { titleKey: 'admin.menu.edge_auth', icon: 'Connection' },
            },
            // ── License 二级市场 (M3-81) ──
            {
                path: 'license-marketplace',
                name: 'LicenseMarketplace',
                component: () => import('@/views/license-marketplace/Index.vue'),
                meta: { titleKey: 'admin.menu.license_marketplace', icon: 'Shop' },
            },
            // ── 队列死信监控 (M2-82) ──
            {
                path: 'queue-monitor',
                name: 'QueueMonitor',
                component: () => import('@/views/queue-monitor/Index.vue'),
                meta: { titleKey: 'route_titles.queue_monitor', icon: 'Monitor' },
            },
            // ── Trial→付费转化漏斗 (M2-101) ──
            {
                path: 'conversion-funnel',
                name: 'ConversionFunnel',
                component: () => import('@/views/conversion-funnel/Index.vue'),
                meta: { titleKey: 'admin.menu.conversion_funnel', icon: 'DataAnalysis' },
            },
            // ── 邮件管理 (已整合) ──
            {
                path: 'email',
                name: 'EmailManager',
                component: () => import('@/views/email/Index.vue'),
                meta: { titleKey: 'admin.menu.email', icon: 'Message' },
            },
            // ── 电商对账系统 (M2-158 🛒) ──
            {
                path: 'reconciliation',
                name: 'Reconciliation',
                component: () => import('@/views/reconciliation/Index.vue'),
                meta: { titleKey: 'admin.menu.reconciliation', icon: 'DataBoard' },
            },
            {
                path: 'ci-cd',
                name: 'CiCd',
                component: () => import('@/views/ci-cd/Index.vue'),
                meta: { titleKey: 'admin.menu.ci_cd', icon: 'Connection' },
            },
            {
                path: 'customers',
                name: 'Customers',
                component: () => import('@/views/customers/Index.vue'),
                meta: { titleKey: 'admin.menu.customers', icon: 'User' },
            },
            {
                path: 'customers/:id',
                name: 'CustomerDetail',
                component: () => import('@/views/customers/Detail.vue'),
                meta: { titleKey: 'route_titles.customers_id', hidden: true },
            },
            {
                path: 'products',
                name: 'Products',
                component: () => import('@/views/products/Index.vue'),
                meta: { titleKey: 'admin.menu.products', icon: 'Goods' },
            },
            {
                path: 'products/:id',
                name: 'ProductDetail',
                component: () => import('@/views/products/Detail.vue'),
                meta: { titleKey: 'route_titles.products_id', hidden: true },
            },
            {
                path: 'devices',
                name: 'Devices',
                component: () => import('@/views/devices/Index.vue'),
                meta: { titleKey: 'admin.menu.devices', icon: 'Monitor' },
            },
            {
                path: 'device-lifecycle',
                name: 'DeviceLifecycle',
                component: () => import('@/views/device-lifecycle/Index.vue'),
                meta: { titleKey: 'route_titles.device_lifecycle', icon: 'TrendCharts' },
            },
            // ── 设备指纹漂移追踪 (M2-25) ──
            {
                path: 'fingerprint-drift',
                name: 'FingerprintDrift',
                component: () => import('@/views/fingerprint-drift/Index.vue'),
                meta: { titleKey: 'route_titles.fingerprint_drift', icon: 'Monitor' },
            },
            // ── SCIM 自动用户同步 (M2-51) ──
            {
                path: 'scim',
                name: 'Scim',
                component: () => import('@/views/scim/Index.vue'),
                meta: { titleKey: 'admin.menu.scim', icon: 'Refresh' },
            },
            // ── 数据血缘追踪 (M2-113) ──
            {
                path: 'data-lineage',
                name: 'DataLineage',
                component: () => import('@/views/data-lineage/Index.vue'),
                meta: { titleKey: 'route_titles.data_lineage', icon: 'Connection' },
            },
            // ── AI 智能套件 (M2-43~48) ──
            {
                path: 'ai',
                name: 'AiIntelligence',
                component: () => import('@/views/ai/Index.vue'),
                meta: { titleKey: 'admin.menu.ai', icon: 'Monitor' },
                children: [
                    { path: 'revenue-forecast', name: 'AiRevenueForecast', component: () => import('@/views/ai/RevenueForecast.vue'), meta: { titleKey: 'route_titles.revenue_forecast' } },
                    { path: 'churn-prediction', name: 'AiChurnPrediction', component: () => import('@/views/ai/ChurnPrediction.vue'), meta: { titleKey: 'route_titles.churn_prediction' } },
                    { path: 'adaptive-security', name: 'AiAdaptiveSecurity', component: () => import('@/views/ai/AdaptiveSecurity.vue'), meta: { titleKey: 'route_titles.adaptive_security' } },
                    { path: 'pricing-optimizer', name: 'AiPricingOptimizer', component: () => import('@/views/ai/PricingOptimizer.vue'), meta: { titleKey: 'route_titles.pricing_optimizer' } },
                    { path: 'sdk-generator', name: 'AiSdkGenerator', component: () => import('@/views/ai/SdkGenerator.vue'), meta: { titleKey: 'route_titles.sdk_generator' } },
                    { path: 'test-generator', name: 'AiTestGenerator', component: () => import('@/views/ai/TestGenerator.vue'), meta: { titleKey: 'route_titles.test_generator' } },
                ],
            },
            // ── AI-043 长期记忆 Memory ──
            {
                path: 'ai-memory',
                name: 'AiMemory',
                component: () => import('@/views/ai-memory/Index.vue'),
                meta: { titleKey: 'admin.menu.ai_memory', icon: 'Collection' },
            },
            // ── AI-045 主动洞察推送 ──
            {
                path: 'ai-proactive',
                name: 'AiProactive',
                component: () => import('@/views/ai-proactive/Index.vue'),
                meta: { titleKey: 'admin.menu.ai_proactive', icon: 'DataAnalysis' },
            },
            // ── PRAC-009 值班轮换 On-Call ──
            {
                path: 'on-call',
                name: 'OnCall',
                component: () => import('@/views/on-call/Index.vue'),
                meta: { titleKey: 'admin.menu.on_call', icon: 'AlarmClock' },
            },
            // ── 自动发货管理 (M2-142 🛒) ──
            {
                path: 'auto-delivery',
                name: 'AutoDelivery',
                component: () => import('@/views/auto-delivery/Index.vue'),
                meta: { titleKey: 'admin.menu.auto_delivery', icon: 'ShoppingCart' },
            },
            // ── 支付回调管理 (M2-144 🛒) ──
            {
                path: 'payment-callbacks',
                name: 'PaymentCallbacks',
                component: () => import('@/views/payment-callback/Index.vue'),
                meta: { titleKey: 'admin.menu.payment_callbacks', icon: 'Coin' },
            },
            {
                path: 'rbac',
                name: 'Rbac',
                component: () => import('@/views/rbac/Index.vue'),
                meta: { titleKey: 'admin.menu.rbac', icon: 'Setting' },
            },
            {
                path: 'users',
                name: 'AdminUsers',
                component: () => import('@/views/users/Index.vue'),
                meta: { titleKey: 'admin.menu.users', icon: 'User' },
            },
            {
                path: 'wizard',
                name: 'AiWizard',
                component: () => import('@/views/wizard/Index.vue'),
                meta: { titleKey: 'admin.menu.wizard', icon: 'MagicStick' },
            },
            {
                path: 'notifications',
                name: 'NotificationList',
                component: () => import('@/views/notifications/Index.vue'),
                meta: { titleKey: 'admin.menu.notifications', icon: 'Bell' },
            },
            // ── 团队管理 (M2-129) ──
            {
                path: 'team',
                name: 'TeamManagement',
                component: () => import('@/views/team/Index.vue'),
                meta: { titleKey: 'route_titles.team', icon: 'UserFilled' },
            },
            {
                path: 'customer-audit-logs',
                name: 'CustomerAuditLogs',
                component: () => import('@/views/audit/CustomerAuditLogs.vue'),
                meta: { titleKey: 'route_titles.customer_audit_logs', icon: 'View' },
            },
            {
                path: 'billing/history',
                name: 'BillingHistory',
                component: () => import('@/views/billing/BillingHistory.vue'),
                meta: { titleKey: 'route_titles.billing_history', icon: 'Document' },
            },
            {
                path: 'usage/endpoint',
                name: 'EndpointUsageAnalytics',
                component: () => import('@/views/usage/EndpointUsageAnalytics.vue'),
                meta: { titleKey: 'route_titles.usage_endpoint', icon: 'DataAnalysis' },
            },
            {
                path: 'cloud-marketplace',
                name: 'CloudMarketplace',
                component: () => import('@/views/cloud-marketplace/Index.vue'),
                meta: { titleKey: 'route_titles.cloud_marketplace', icon: 'Connection' },
            },
            {
                path: 'accounting',
                name: 'Accounting',
                component: () => import('@/views/accounting/Index.vue'),
                meta: { titleKey: 'admin.menu.accounting', icon: 'Coin' },
            },
            {
                path: 'bi-export',
                name: 'BiExport',
                component: () => import('@/views/bi-export/Index.vue'),
                meta: { titleKey: 'admin.menu.bi_export', icon: 'DataBoard' },
            },
            // ── 告警中心（统一合并：智能告警 + 告警中心 + 告警管理）──
            {
                path: 'alerts',
                name: 'Alerts',
                component: () => import('@/views/alert-center/Index.vue'),
                meta: { titleKey: 'alert_center.title', icon: 'WarnTriangleFilled' },
            },
            {
                path: 'settings',
                name: 'SiteSettings',
                component: () => import('@/views/settings/Index.vue'),
                meta: { titleKey: 'admin.menu.settings', icon: 'Setting' },
            },
            {
                path: 'tenants',
                name: 'AdminTenants',
                component: () => import('@/views/tenants/Index.vue'),
                meta: { titleKey: 'admin.menu.tenants', icon: 'OfficeBuilding' },
            },
            // ── 续费管理中心（统一合并）──
            {
                path: 'renewal',
                name: 'RenewalDashboard',
                component: () => import('@/views/renewal/Index.vue'),
                meta: { titleKey: 'renewal_center.title', icon: 'Refresh' },
            },
            {
                path: 'pages',
                name: 'PageManager',
                component: () => import('@/views/pages/Index.vue'),
                meta: { titleKey: 'admin.menu.pages', icon: 'Document' },
            },
            {
                path: 'playground',
                name: 'ApiPlayground',
                component: () => import('@/views/playground/Index.vue'),
                meta: { titleKey: 'admin.menu.playground', icon: 'Monitor' },
            },
            {
                path: 'deps-security',
                name: 'DepsSecurity',
                component: () => import('@/views/deps-security/Index.vue'),
                meta: { titleKey: 'admin.menu.deps_security', icon: 'Shield' },
            },
            {
                path: 'mfa',
                name: 'MfaSettings',
                component: () => import('@/views/mfa/Index.vue'),
                meta: { titleKey: 'admin.menu.mfa', icon: 'Lock' },
            },
            {
                path: 'cors-configs',
                name: 'CorsConfigs',
                component: () => import('@/views/cors/Index.vue'),
                meta: { titleKey: 'admin.menu.cors_configs', icon: 'Connection' },
            },
            {
                path: 'csp-configs',
                name: 'CspConfigs',
                component: () => import('@/views/csp/Index.vue'),
                meta: { titleKey: 'route_titles.csp_configs', icon: 'Shield' },
            },
            {
                path: 'maintenance',
                name: 'MaintenanceMode',
                component: () => import('@/views/maintenance/Index.vue'),
                meta: { titleKey: 'route_titles.maintenance', icon: 'WarnTriangleFilled' },
            },
            {
                path: 'apm',
                name: 'ApmMonitor',
                component: () => import('@/views/apm/Index.vue'),
                meta: { titleKey: 'admin.menu.apm', icon: 'DataAnalysis' },
            },
            {
                path: 'slow-query-monitor',
                name: 'SlowQueryMonitor',
                component: () => import('@/views/slow-query-monitor/Index.vue'),
                meta: { titleKey: 'admin.menu.slow_query_monitor', icon: 'Monitor' },
            },
            {
                path: 'db-read-write',
                name: 'DbReadWrite',
                component: () => import('@/views/db-read-write/Index.vue'),
                meta: { titleKey: 'admin.menu.db_read_write', icon: 'Connection' },
            },
            {
                path: 'synthetic-monitor',
                name: 'SyntheticMonitor',
                component: () => import('@/views/synthetic-monitor/Index.vue'),
                meta: { titleKey: 'admin.menu.synthetic_monitor', icon: 'Monitor' },
            },
            {
                path: 'incident-alerting',
                name: 'IncidentAlerting',
                component: () => import('@/views/incident-alerting/Index.vue'),
                meta: { titleKey: 'admin.menu.incident_alerting', icon: 'Bell' },
            },
            {
                path: 'auto-pentest',
                name: 'AutoPenTest',
                component: () => import('@/views/auto-pentest/Index.vue'),
                meta: { titleKey: 'admin.menu.auto_pentest', icon: 'Monitor' },
            },
            {
                path: 'license-restrictions',
                name: 'LicenseRestrictions',
                component: () => import('@/views/license-restrictions/Index.vue'),
                meta: { titleKey: 'admin.menu.license_restrictions', icon: 'Lock' },
            },
            {
                path: 'budget-guard',
                name: 'BudgetGuard',
                component: () => import('@/views/budget-guard/Index.vue'),
                meta: { titleKey: 'admin.menu.budget_guard', icon: 'Coin' },
            },
            {
                path: 'domain-whitelist',
                name: 'DomainWhitelist',
                component: () => import('@/views/domain-whitelist/Index.vue'),
                meta: { titleKey: 'admin.menu.domain_whitelist', icon: 'Link' },
            },
            {
                path: 'utm-tracker',
                name: 'UtmTracker',
                component: () => import('@/views/utm-tracker/Index.vue'),
                meta: { titleKey: 'admin.menu.utm_tracker', icon: 'TrendCharts' },
            },
            {
                path: 'nps-survey',
                name: 'NpsSurvey',
                component: () => import('@/views/nps-survey/Index.vue'),
                meta: { titleKey: 'admin.menu.nps_survey', icon: 'WarningFilled' },
            },
            {
                path: 'admin-appeals',
                name: 'AdminAppeals',
                component: () => import('@/views/admin-appeals/Index.vue'),
                meta: { titleKey: 'admin.menu.admin_appeals', icon: 'WarningFilled' },
            },
            {
                path: 'custom-emoji',
                name: 'CustomEmoji',
                component: () => import('@/views/custom-emoji/Index.vue'),
                meta: { titleKey: 'admin.menu.custom_emoji', icon: 'Mug' },
            },
            // ── Meilisearch 全文搜索 ──
            {
                path: 'meilisearch',
                name: 'Meilisearch',
                component: () => import('@/views/meilisearch/Index.vue'),
                meta: { titleKey: 'admin.menu.meilisearch', icon: 'Search' },
            },
            {
                path: 'auto-reply',
                name: 'AutoReply',
                component: () => import('@/views/auto-reply/Index.vue'),
                meta: { titleKey: 'admin.menu.auto_reply', icon: 'ChatDotRound' },
            },
            {
                path: 'feature-adoption',
                name: 'FeatureAdoption',
                component: () => import('@/views/feature-adoption/Index.vue'),
                meta: { titleKey: 'admin.menu.feature_adoption', icon: 'TrendCharts' },
            },
            {
                path: 'scheduled-notification',
                name: 'ScheduledNotification',
                component: () => import('@/views/scheduled-notification/Index.vue'),
                meta: { titleKey: 'admin.menu.scheduled_notification', icon: 'Timer' },
            },
            {
                path: 'quota-alert',
                name: 'QuotaAlert',
                component: () => import('@/views/quota-alert/Index.vue'),
                meta: { titleKey: 'admin.menu.quota_alert', icon: 'WarningFilled' },
            },
            {
                path: 'dev-portal',
                name: 'DevPortal',
                component: () => import('@/views/dev-portal/Index.vue'),
                meta: { titleKey: 'admin.menu.dev_portal', icon: 'Monitor' },
            },
            {
                path: 'postman',
                name: 'PostmanCollection',
                component: () => import('@/views/postman/Index.vue'),
                meta: { titleKey: 'admin.menu.postman', icon: 'Connection' },
            },
            // ── IM 通知集成 (M2-57) ──
            {
                path: 'im-integration',
                name: 'ImIntegration',
                component: () => import('@/views/im-integration/Index.vue'),
                meta: { titleKey: 'admin.menu.im_integration', icon: 'ChatDotSquare' },
            },
            {
                path: 'cache-invalidation',
                name: 'CacheInvalidation',
                component: () => import('@/views/cache-invalidation/Index.vue'),
                meta: { titleKey: 'admin.menu.cache_invalidation', icon: 'Connection' },
            },
            {
                path: 'public-key',
                name: 'PublicKeyVersion',
                component: () => import('@/views/public-key/Index.vue'),
                meta: { titleKey: 'admin.menu.public_key', icon: 'Key' },
            },
            {
                path: 'text-to-sql',
                name: 'TextToSql',
                component: () => import('@/views/text-to-sql/Index.vue'),
                meta: { titleKey: 'admin.menu.text_to_sql', icon: 'Monitor' },
            },
            {
                path: 'sdk-manager',
                name: 'SdkManager',
                component: () => import('@/views/sdk-manager/Index.vue'),
                meta: { titleKey: 'admin.menu.sdk_manager', icon: 'Connection' },
            },
            {
                path: 'slo',
                name: 'SloBudget',
                component: () => import('@/views/slo/Index.vue'),
                meta: { titleKey: 'route_titles.slo', icon: 'Coin' },
            },
            {
                path: 'sandbox',
                name: 'DevSandbox',
                component: () => import('@/views/sandbox/Index.vue'),
                meta: { titleKey: 'sandbox_center.title', icon: 'EditPen' },
            },
            {
                path: 'billing',
                name: 'Billing',
                component: () => import('@/views/billing/Index.vue'),
                meta: { titleKey: 'admin.menu.billing', icon: 'Coin' },
            },
            {
                path: 'billing/metered',
                name: 'BillingMetered',
                component: () => import('@/views/billing/MeteredBilling.vue'),
                meta: { titleKey: 'route_titles.billing_metered', icon: 'Histogram' },
            },
            // ── 支付集成 (M2-06) ──
            {
                path: 'payment',
                name: 'Payment',
                component: () => import('@/views/payment/Index.vue'),
                meta: { titleKey: 'admin.menu.payment', icon: 'Wallet' },
            },
            {
                path: 'payment/transactions',
                name: 'PaymentTransactions',
                component: () => import('@/views/payment/Transactions.vue'),
                meta: { titleKey: 'admin.menu.payment_transactions', icon: 'List' },
            },
            {
                path: 'payment/webhook-logs',
                name: 'PaymentWebhookLogs',
                component: () => import('@/views/payment/WebhookLogs.vue'),
                meta: { titleKey: 'admin.menu.payment_webhook_logs', icon: 'Notification' },
            },
            // ── 支付方式管理 (M2-07b) ──
            {
                path: 'payment-methods',
                name: 'PaymentMethodsAdmin',
                component: () => import('@/views/payment-method/Index.vue'),
                meta: { titleKey: 'admin.menu.payment_methods', icon: 'CreditCard' },
            },
            {
                path: 'pricing/dynamic',
                name: 'DynamicPricing',
                component: () => import('@/views/pricing/DynamicPricing.vue'),
                meta: { titleKey: 'admin.menu.pricing_dynamic', icon: 'TrendCharts' },
            },
            {
                path: 'pricing/experiments',
                name: 'PricingExperiments',
                component: () => import('@/views/pricing-experiments/Index.vue'),
                meta: { titleKey: 'route_titles.pricing_experiments', icon: 'DataAnalysis' },
            },
            // ── 套餐系统 (M3-06) ──
            {
                path: 'plans',
                name: 'PlanIndex',
                component: () => import('@/views/pricing/PlanIndex.vue'),
                meta: { titleKey: 'admin.menu.plans', icon: 'Coin' },
            },
            // ── 增值服务 VAS (M3-70) ──
            {
                path: 'vas',
                name: 'VasManagement',
                component: () => import('@/views/vas/Index.vue'),
                meta: { titleKey: 'route_titles.vas', icon: 'Shop' },
            },
            // ── 发票增强 (M3-74) ──
            {
                path: 'invoice-enhance',
                name: 'InvoiceEnhance',
                component: () => import('@/views/invoice-enhance/Index.vue'),
                meta: { titleKey: 'route_titles.invoice_enhance', icon: 'Document' },
            },
            // ── 自动开票 (M2-148 🛒) ──
            {
                path: 'auto-invoice',
                name: 'AutoInvoice',
                component: () => import('@/views/auto-invoice/Index.vue'),
                meta: { titleKey: 'admin.menu.auto_invoice', icon: 'PriceTag' },
            },
            // ── 分销联盟 (M2-149 🛒) ──
            {
                path: 'store-affiliate',
                name: 'StoreAffiliate',
                component: () => import('@/views/store-affiliate/Index.vue'),
                meta: { titleKey: 'route_titles.store_affiliate', icon: 'Connection' },
            },
            // ── AI 个性化 (M3-80) ──
            {
                path: 'personalization',
                name: 'Personalization',
                component: () => import('@/views/personalization/Index.vue'),
                meta: { titleKey: 'route_titles.personalization', icon: 'MagicStick' },
            },
            // ── 低代码工作流设计器 (M3-82) ──
            {
                path: 'flow-designer',
                name: 'FlowDesigner',
                component: () => import('@/views/flow-designer/Index.vue'),
                meta: { titleKey: 'route_titles.flow_designer', icon: 'Connection' },
            },
            // ── 跨境支付与多币种 (M3-83) ──
            {
                path: 'cross-border',
                name: 'CrossBorder',
                component: () => import('@/views/cross-border/Index.vue'),
                meta: { titleKey: 'route_titles.cross_border', icon: 'Coin' },
            },
            // ── 促销管理 (统一合并：促销系统 + 促销引擎 + 定时促销) ──
            {
                path: 'promotions',
                name: 'Promotions',
                component: () => import('@/views/promotion/Index.vue'),
                meta: { titleKey: 'promo_center.title', icon: 'Present' },
            },
            {
                path: 'billing/prepaid',
                name: 'PrepaidBalance',
                component: () => import('@/views/billing/PrepaidBalance.vue'),
                meta: { titleKey: 'route_titles.billing_prepaid', icon: 'Money' },
            },
            // ── License 转移 (M3-08) ──
            {
                path: 'transfers',
                name: 'AdminTransfer',
                component: () => import('@/views/transfer/AdminTransfer.vue'),
                meta: { titleKey: 'admin.menu.transfers', icon: 'Connection' },
            },
            {
                path: 'billing/revenue-recognition',
                name: 'RevenueRecognition',
                component: () => import('@/views/billing/RevenueRecognition.vue'),
                meta: { titleKey: 'route_titles.billing_revenue_recognition', icon: 'DataBoard' },
            },
            {
                path: 'refunds',
                name: 'Refunds',
                component: () => import('@/views/refunds/Index.vue'),
                meta: { titleKey: 'admin.menu.refunds', icon: 'Money' },
            },
            {
                path: 'points',
                name: 'PointsManagement',
                component: () => import('@/views/points/Index.vue'),
                meta: { titleKey: 'admin.menu.points', icon: 'Coin' },
            },
            {
                path: 'knowledge-base',
                name: 'KnowledgeBase',
                component: () => import('@/views/kb/Index.vue'),
                meta: { titleKey: 'admin.menu.knowledge_base', icon: 'Reading' },
            },
            // ── 客户反馈 (M3-44) ──
            {
                path: 'feedback',
                name: 'Feedback',
                component: () => import('@/views/feedback/Index.vue'),
                meta: { titleKey: 'admin.menu.feedback', icon: 'ChatLineSquare' },
            },
            // ─── SEO 优化 (M3-49) ───
            {
                path: 'seo',
                name: 'Seo',
                component: () => import('@/views/seo/Index.vue'),
                meta: { titleKey: 'route_titles.seo', icon: 'Search' },
            },
            // ─── 多区域部署/数据中心 (M3-52/53) ───
            {
                path: 'multi-region',
                name: 'MultiRegion',
                component: () => import('@/views/multi-region/Index.vue'),
                meta: { titleKey: 'route_titles.multi_region', icon: 'Connection' },
            },
            {
                path: 'license-files',
                name: 'LicenseFileCdn',
                component: () => import('@/views/license-files/Index.vue'),
                meta: { titleKey: 'admin.menu.license_files', icon: 'Upload' },
            },
            {
                path: 'static-assets-cdn',
                name: 'StaticAssetCdn',
                component: () => import('@/views/static-assets-cdn/Index.vue'),
                meta: { titleKey: 'route_titles.static_assets_cdn', icon: 'Connection' },
            },
            {
                path: 'deploy',
                name: 'Deploy',
                component: () => import('@/views/deploy/Index.vue'),
                meta: { titleKey: 'route_titles.deploy', icon: 'Connection' },
            },
            // ─── 气隙部署 (M3-61) ───
            // ─── Edge 授权验证 (M3-53) ───
            {
                path: 'edge-verifier',
                name: 'EdgeVerifier',
                component: () => import('@/views/edge-verifier/Index.vue'),
                meta: { titleKey: 'admin.menu.edge_verifier', icon: 'Connection' },
            },
            // ─── Istio 服务网格 (M3-68) ───
            {
                path: 'istio',
                name: 'IstioManager',
                component: () => import('@/views/istio/Index.vue'),
                meta: { titleKey: 'admin.menu.istio', icon: 'Connection' },
            },
            // ─── PWA 移动端 (M3-51) ───
            {
                path: 'pwa',
                name: 'Pwa',
                component: () => import('@/views/pwa/Index.vue'),
                meta: { titleKey: 'admin.menu.pwa', icon: 'Monitor' },
            },
            // ─── 交互式产品演示 (M3-70) ───
            // ─── 混沌工程 (M3-80) ───
            {
                path: 'chaos-engineering',
                name: 'ChaosEngineering',
                component: () => import('@/views/chaos-engineering/Index.vue'),
                meta: { titleKey: 'admin.menu.chaos_engineering', icon: 'WarningFilled' },
            },
            // ─── Zapier/Make 无代码集成 (M3-43) ───
            {
                path: 'zapier',
                name: 'Zapier',
                component: () => import('@/views/zapier/Index.vue'),
                meta: { titleKey: 'admin.menu.zapier', icon: 'Connection' },
            },
            // ─── 蓝绿部署 (M3-63) ───
            {
                path: 'blue-green',
                name: 'BlueGreen',
                component: () => import('@/views/blue-green/Index.vue'),
                meta: { titleKey: 'admin.menu.blue_green', icon: 'Connection' },
            },
            {
                path: 'license-templates',
                name: 'LicenseTemplates',
                component: () => import('@/views/license-templates/Index.vue'),
                meta: { titleKey: 'admin.menu.license_templates', icon: 'List' },
            },
            {
                path: 'time-restriction',
                name: 'TimeRestriction',
                component: () => import('@/views/time-restriction/Index.vue'),
                meta: { titleKey: 'route_titles.time_restriction', icon: 'Timer' },
            },
            {
                path: 'llm',
                name: 'LlmProvider',
                component: () => import('@/views/llm/Index.vue'),
                meta: { titleKey: 'admin.menu.llm', icon: 'Monitor' },
            },
            {
                path: 'tax',
                name: 'TaxCalculator',
                component: () => import('@/views/tax/Index.vue'),
                meta: { titleKey: 'admin.menu.tax', icon: 'Document' },
            },
            {
                path: 'china-invoice',
                name: 'ChinaInvoice',
                component: () => import('@/views/china-invoice/Index.vue'),
                meta: { titleKey: 'admin.menu.china_invoice', icon: 'Document' },
            },
            // ── 全球税收合规 (M3-18) ──
            {
                path: 'tax-compliance',
                name: 'TaxCompliance',
                component: () => import('@/views/tax-compliance/Index.vue'),
                meta: { titleKey: 'route_titles.tax_compliance', icon: 'List' },
            },
            // ── 多渠道营销自动化 (M3-20) ──
            {
                path: 'marketing-campaign',
                name: 'MarketingCampaign',
                component: () => import('@/views/marketing-campaign/Index.vue'),
                meta: { titleKey: 'route_titles.marketing_campaign', icon: 'Promotion' },
            },
            // ── 智能合同管理 (M3-21) ──
            {
                path: 'enterprise-contracts',
                name: 'EnterpriseContract',
                component: () => import('@/views/enterprise-contract/Index.vue'),
                meta: { titleKey: 'route_titles.enterprise_contracts', icon: 'Document' },
            },
            {
                path: 'global-resources',
                name: 'GlobalResources',
                component: () => import('@/views/global-resources/Index.vue'),
                meta: { titleKey: 'admin.menu.global_resources', icon: 'Key' },
            },
            {
                path: 'domains',
                name: 'CustomDomains',
                component: () => import('@/views/domains/Index.vue'),
                meta: { titleKey: 'domain_center.title', icon: 'Link' },
            },
            {
                path: 'tickets',
                name: 'Tickets',
                component: () => import('@/views/tickets/Index.vue'),
                meta: { titleKey: 'admin.menu.tickets', icon: 'Tickets' },
            },
            {
                path: 'tickets/:id',
                name: 'TicketDetail',
                component: () => import('@/views/tickets/Detail.vue'),
                meta: { titleKey: 'route_titles.tickets_id', hidden: true },
            },
            {
                path: 'status-page',
                name: 'StatusPageAdmin',
                component: () => import('@/views/status/Admin.vue'),
                meta: { titleKey: 'admin.menu.status_page', icon: 'Monitor' },
            },
            {
                path: 'workflows',
                name: 'WorkflowEngine',
                component: () => import('@/views/workflows/Index.vue'),
                meta: { titleKey: 'admin.menu.workflows', icon: 'Timer' },
            },
            // ── 账号安全 ──
            {
                path: 'sessions',
                name: 'Sessions',
                component: () => import('@/views/sessions/Index.vue'),
                meta: { titleKey: 'admin.menu.sessions', icon: 'Monitor' },
            },
            // ── 信任设备管理 ──
            {
                path: 'device-trust',
                name: 'DeviceTrust',
                component: () => import('@/views/device-trust/Index.vue'),
                meta: { titleKey: 'admin.menu.device_trust', icon: 'Monitor' },
            },
            // ── 设备地理位置记录 (M2-26) ──
            {
                path: 'geo-location',
                name: 'GeoLocation',
                component: () => import('@/views/geo-location/Index.vue'),
                meta: { titleKey: 'route_titles.geo_location', icon: 'Monitor' },
            },
            // ── 密码策略 ──
            {
                path: 'password-policy',
                name: 'PasswordPolicy',
                component: () => import('@/views/password-policy/Index.vue'),
                meta: { titleKey: 'admin.menu.password_policy', icon: 'Lock' },
            },
            // ── 邀请码管理 ──
            {
                path: 'invite-codes',
                name: 'InviteCodes',
                component: () => import('@/views/invite-codes/Index.vue'),
                meta: { titleKey: 'route_titles.invite_codes', icon: 'Key' },
            },
            // ── 门户品牌化 ──
            {
                path: 'portal-branding',
                name: 'PortalBranding',
                component: () => import('@/views/portal-branding/Index.vue'),
                meta: { titleKey: 'admin.menu.portal_branding', icon: 'Brush' },
            },
            // ── 账号注销审核 ──
            {
                path: 'account-deletions',
                name: 'AccountDeletions',
                component: () => import('@/views/account-deletion/Index.vue'),
                meta: { titleKey: 'admin.menu.account_deletions', icon: 'Delete' },
            },
            {
                path: 'account/profile',
                name: 'AccountProfile',
                component: () => import('@/views/account/Profile.vue'),
                meta: { titleKey: 'admin.menu.account_profile', icon: 'User' },
            },
            {
                path: 'account/binding',
                name: 'AccountBinding',
                component: () => import('@/views/account/Binding.vue'),
                meta: { titleKey: 'admin.menu.account_binding', icon: 'Link' },
            },
            {
                path: 'tags',
                name: 'Tags',
                component: () => import('@/views/tags/Index.vue'),
                meta: { titleKey: 'admin.menu.tags', icon: 'PriceTag' },
            },
            {
                path: 'account/login-history',
                name: 'LoginHistory',
                component: () => import('@/views/account/LoginHistory.vue'),
                meta: { titleKey: 'admin.menu.account_login_history', icon: 'Time' },
            },
            {
                path: 'account/passkey',
                name: 'PasskeyManagement',
                component: () => import('@/views/account/PasskeyManagement.vue'),
                meta: { titleKey: 'admin.menu.account_passkey', icon: 'Key' },
            },
            // ── 功能开关 ──
            {
                path: 'feature-flags',
                name: 'FeatureFlags',
                component: () => import('@/views/feature-flags/Index.vue'),
                meta: { titleKey: 'admin.menu.feature_flags', icon: 'Switch' },
            },
            // ── 系统公告 ──
            {
                path: 'announce-banners',
                name: 'AnnounceBanners',
                component: () => import('@/views/announce-banners/Index.vue'),
                meta: { titleKey: 'admin.menu.announce_banners', icon: 'Bell' },
            },
            // ── Cookie Consent ──
            {
                path: 'cookie-consent',
                name: 'CookieConsent',
                component: () => import('@/views/cookie-consent/Index.vue'),
                meta: { titleKey: 'admin.menu.cookie_consent', icon: 'SetUp' },
            },
            // ── 预约Demo/联系销售 (M2-98) ──
            {
                path: 'demo-booking',
                name: 'DemoBooking',
                component: () => import('@/views/demo-booking/Index.vue'),
                meta: { titleKey: 'demo_center.title', icon: 'Calendar' },
            },
            // ── 客户案例/Logo墙 (M2-99) ──
            {
                path: 'case-studies',
                name: 'CaseStudies',
                component: () => import('@/views/case-studies/Index.vue'),
                meta: { titleKey: 'admin.menu.case_studies', icon: 'Star' },
            },
            // ── 竞品对比页 (M2-100) ──
            {
                path: 'compare-page',
                name: 'ComparePage',
                component: () => import('@/views/compare-page/Index.vue'),
                meta: { titleKey: 'admin.menu.compare_page', icon: 'Histogram' },
            },
            // ── 限流规则 (M2-92) ──
            {
                path: 'rate-limits',
                name: 'RateLimits',
                component: () => import('@/views/rate-limits/Index.vue'),
                meta: { titleKey: 'admin.menu.rate_limits', icon: 'Monitor' },
            },
            // ── 佣金结算 (M2-127) ──
            {
                path: 'commission',
                name: 'Commission',
                component: () => import('@/views/commission/Index.vue'),
                meta: { titleKey: 'admin.menu.commission', icon: 'Money' },
            },
            // ── 开发者收益 ──
            {
                path: 'developer-earnings',
                name: 'DeveloperEarnings',
                component: () => import('@/views/developer-earnings/Index.vue'),
                meta: { titleKey: 'admin.menu.developer_earnings', icon: 'Money' },
            },
            // ── 财务结算系统 (P3) ──
            {
                path: 'settlement',
                name: 'Settlement',
                component: () => import('@/views/settlement/Index.vue'),
                meta: { titleKey: 'admin.menu.settlement', icon: 'DataBoard' },
            },
            // ── 市场推送管理 ──
            {
                path: 'marketplace-push',
                name: 'MarketplacePush',
                component: () => import('@/views/marketplace-push/Index.vue'),
                meta: { titleKey: 'admin.menu.marketplace_push', icon: 'Bell' },
            },
            // ── 内容安全审核 ──
            {
                path: 'marketplace-security',
                name: 'MarketplaceSecurity',
                component: () => import('@/views/marketplace-security/Index.vue'),
                meta: { titleKey: 'admin.menu.marketplace_security', icon: 'WarningFilled' },
            },
            // ── 社区管理 ──
            {
                path: 'moments',
                name: 'AdminMoments',
                component: () => import('@/views/community/AdminMoments.vue'),
                meta: { titleKey: 'admin.menu.moments', icon: 'ChatDotSquare' },
            },
            // ── 互物号管理 ──
            {
                path: 'official-accounts',
                name: 'AdminOfficialAccounts',
                component: () => import('@/views/channels/AdminOfficialAccounts.vue'),
                meta: { titleKey: 'admin.menu.official_accounts', icon: 'Monitor' },
            },
            {
                path: 'articles/manage',
                name: 'AdminArticles',
                component: () => import('@/views/channels/AdminArticles.vue'),
                meta: { titleKey: 'admin.menu.articles_manage', icon: 'Document' },
            },
            // ── 灰度发布 ──
            {
                path: 'marketplace-rollout',
                name: 'MarketplaceRollout',
                component: () => import('@/views/marketplace-rollout/Index.vue'),
                meta: { titleKey: 'admin.menu.marketplace_rollout', icon: 'Switch' },
            },
            // ── 提现管理 (M3-72) ──
            {
                path: 'withdrawals',
                name: 'Withdrawals',
                component: () => import('@/views/withdrawal/Index.vue'),
                meta: { titleKey: 'route_titles.withdrawals', icon: 'Wallet' },
            },
            // ── 佣金风控 (M2-127b) ──
            {
                path: 'commission-risk',
                name: 'CommissionRisk',
                component: () => import('@/views/commission/RiskDashboard.vue'),
                meta: { titleKey: 'route_titles.commission_risk', icon: 'WarningFilled' },
            },
            // ── 收益通知 (M2-128) ──
            {
                path: 'earning-notifications',
                name: 'EarningNotifications',
                component: () => import('@/views/commission/EarningNotifications.vue'),
                meta: { titleKey: 'route_titles.earning_notifications', icon: 'Bell' },
            },
            // ── 等级晋升管理 ──
            {
                path: 'agent-tiers',
                name: 'AgentTiers',
                component: () => import('@/views/commission/AgentTiers.vue'),
                meta: { titleKey: 'route_titles.agent_tiers', icon: 'Medal' },
            },
            // ── 代理商/经销商管理 (M3-04) ──
            {
                path: 'agent-manager',
                name: 'AgentManager',
                component: () => import('@/views/agent-manager/Index.vue'),
                meta: { titleKey: 'admin.menu.agent_manager', icon: 'UserFilled' },
            },
            // ── 联盟推广 M3-05 ──
            {
                path: 'affiliate',
                name: 'AffiliateCampaigns',
                component: () => import('@/views/commission/AffiliateCampaigns.vue'),
                meta: { titleKey: 'admin.menu.affiliate', icon: 'Connection' },
            },
            // ── 联盟推广增强 M3-05 ──
            {
                path: 'affiliate-enhanced',
                name: 'AffiliateEnhanced',
                component: () => import('@/views/affiliate-enhanced/Index.vue'),
                meta: { titleKey: 'route_titles.affiliate_enhanced', icon: 'Promotion' },
            },
            // ── AI 风控 & 行为风控 (M3-01, M3-02) ──
            {
                path: 'fraud-risk',
                name: 'FraudRisk',
                component: () => import('@/views/fraud-risk/Index.vue'),
                meta: { titleKey: 'admin.menu.fraud_risk', icon: 'Monitor' },
            },
            // ── OEM 白标系统 (M3-03) ──
            {
                path: 'oem',
                name: 'Oem',
                component: () => import('@/views/oem/Index.vue'),
                meta: { titleKey: 'route_titles.oem', icon: 'Tools' },
            },
            // ── 创新授权管理 (M3-14~17) ──
            {
                path: 'innovation-auth',
                name: 'InnovationAuth',
                component: () => import('@/views/innovation/Index.vue'),
                meta: { titleKey: 'route_titles.innovation_auth', icon: 'TrendCharts' },
            },
            // ── AI 盗版溯源 (M3-34) ──
            {
                path: 'piracy-trace',
                name: 'PiracyTrace',
                component: () => import('@/views/piracy-trace/Index.vue'),
                meta: { titleKey: 'route_titles.piracy_trace', icon: 'WarningFilled' },
            },
            // ── AI 交叉销售推荐 (M3-35) ──
            {
                path: 'cross-sell',
                name: 'CrossSell',
                component: () => import('@/views/cross-sell/Index.vue'),
                meta: { titleKey: 'route_titles.cross_sell', icon: 'TrendCharts' },
            },
            // ── AI 客户行为聚类 (M3-37) ──
            {
                path: 'customer-clustering',
                name: 'CustomerClustering',
                component: () => import('@/views/customer-clustering/Index.vue'),
                meta: { titleKey: 'route_titles.customer_clustering', icon: 'DataBoard' },
            },
            // ── 云文件存储 (M3-48) ──
            {
                path: 'cloud-upload',
                name: 'CloudUpload',
                component: () => import('@/views/cloud-upload/Index.vue'),
                meta: { titleKey: 'admin.menu.cloud_upload', icon: 'CloudUpload' },
            },
            // ── 虚拟环境检测 (M1.3-14) ──
            {
                path: 'vm-detection',
                name: 'VmDetection',
                component: () => import('@/views/vm-detection/Index.vue'),
                meta: { titleKey: 'admin.menu.vm_detection', icon: 'Monitor' },
            },
            // ── Redis 高可用 (M1.3-17) ──
            {
                path: 'redis-ha',
                name: 'RedisHa',
                component: () => import('@/views/redis-ha/Index.vue'),
                meta: { titleKey: 'admin.menu.redis_ha', icon: 'DataAnalysis' },
            },
            // ── WAF 基础防护 (M1.3-18) ──
            {
                path: 'waf',
                name: 'Waf',
                component: () => import('@/views/waf/Index.vue'),
                meta: { titleKey: 'admin.menu.waf', icon: 'WarningFilled' },
            },
            // ── API 网关统一层 (M1.3-20) ──
            {
                path: 'api-gateway',
                name: 'ApiGateway',
                component: () => import('@/views/api-gateway/Index.vue'),
                meta: { titleKey: 'route_titles.api_gateway', icon: 'Connection' },
            },
            // ── gRPC 服务间通信 (M1.3-28) ──
            {
                path: 'grpc',
                name: 'Grpc',
                component: () => import('@/views/grpc/Index.vue'),
                meta: { titleKey: 'admin.menu.grpc', icon: 'Connection' },
            },
            // ── 数据留存策略 (M1.1-14) ──
            {
                path: 'data-retention',
                name: 'DataRetention',
                component: () => import('@/views/data-retention/Index.vue'),
                meta: { titleKey: 'admin.menu.data_retention', icon: 'Timer' },
            },
            // ── CRM 集成 (M3-42) ──
            {
                path: 'crm-integration',
                name: 'CrmIntegration',
                component: () => import('@/views/crm-integration/Index.vue'),
                meta: { titleKey: 'admin.menu.crm_integration', icon: 'Connection' },
            },
            // ── AI 合规报告生成 (M3-38) ──
            {
                path: 'compliance-ai',
                name: 'ComplianceAi',
                component: () => import('@/views/compliance-ai/Index.vue'),
                meta: { titleKey: 'route_titles.compliance_ai', icon: 'Document' },
            },
            // ── TPM 硬件安全绑定 (M2-116) ──
            {
                path: 'tpm-binding',
                name: 'TpmBinding',
                component: () => import('@/views/tpm-binding/Index.vue'),
                meta: { titleKey: 'admin.menu.tpm_binding', icon: 'Key' },
            },
            // ── 合规中心(已整合) ──
            {
                path: 'compliance-center',
                name: 'ComplianceCenter',
                component: () => import('@/views/compliance-center/Index.vue'),
                meta: { titleKey: 'admin.menu.compliance_center', icon: 'DocumentChecked' },
            },
            {
                path: 'data-management',
                name: 'DataManagement',
                component: () => import('@/views/data-management/Index.vue'),
                meta: { titleKey: 'admin.menu.data_management', icon: 'Connection' },
            },
            // ── AI 攻击模式识别 (M3-36) ──
            {
                path: 'attack-detection',
                name: 'AttackDetection',
                component: () => import('@/views/attack-detection/Index.vue'),
                meta: { titleKey: 'admin.menu.attack_detection', icon: 'WarningFilled' },
            },
            // ── 主动蜜罐防御 (M2-03) ──
            {
                path: 'honeypot',
                name: 'Honeypot',
                component: () => import('@/views/honeypot/Index.vue'),
                meta: { titleKey: 'admin.menu.honeypot', icon: 'WarningFilled' },
            },
            // ── AI 迁移助手 (M3-39) ──
            {
                path: 'migration-assistant',
                name: 'MigrationAssistant',
                component: () => import('@/views/migration-assistant/Index.vue'),
                meta: { titleKey: 'route_titles.migration_assistant', icon: 'MagicStick' },
            },
            // ── 自动备份 (M2-24) ──
            {
                path: 'backups',
                name: 'Backups',
                component: () => import('@/views/backups/Index.vue'),
                meta: { titleKey: 'admin.menu.backups', icon: 'Timer' },
            },
            // ── 断路器监控 ──
            {
                path: 'circuit-breaker',
                name: 'CircuitBreaker',
                component: () => import('@/views/circuit-breaker/Index.vue'),
                meta: { titleKey: 'admin.menu.circuit_breaker', icon: 'Monitor' },
            },
            // ── 模拟登录 ──
            {
                path: 'impersonate',
                name: 'Impersonate',
                component: () => import('@/views/impersonate/Index.vue'),
                meta: { titleKey: 'admin.menu.impersonate', icon: 'Key' },
            },
            // ── 密钥管理 (M2-78) ──
            {
                path: 'secrets',
                name: 'SecretManager',
                component: () => import('@/views/secret-manager/Index.vue'),
                meta: { titleKey: 'admin.menu.secrets', icon: 'Lock' },
            },
            // ── HSM 硬件安全模块 (M3-79) ──
            {
                path: 'hsm',
                name: 'HsmManagement',
                component: () => import('@/views/hsm/Index.vue'),
                meta: { titleKey: 'admin.menu.hsm', icon: 'Key' },
            },
            // ── 用量计量系统 (M2-10) ──
            {
                path: 'usage-meter',
                name: 'UsageMeter',
                component: () => import('@/views/usage-meter/Index.vue'),
                meta: { titleKey: 'admin.menu.usage_meter', icon: 'DataBoard' },
            },
            {
                path: 'metered-billing',
                name: 'MeteredBillingDeep',
                component: () => import('@/views/metered-billing/Index.vue'),
                meta: { titleKey: 'admin.menu.metered_billing', icon: 'TrendCharts' },
            },
            // ── 高级报表 (M3) ──
            {
                path: 'reports',
                name: 'Reports',
                component: () => import('@/views/reports/Index.vue'),
                meta: { titleKey: 'admin.menu.reports', icon: 'TrendCharts' },
            },
            // ── 平台收益总览 M3-73 ──
            {
                path: 'revenue',
                name: 'RevenueDashboard',
                component: () => import('@/views/revenue/Index.vue'),
                meta: { titleKey: 'admin.menu.revenue', icon: 'DataBoard' },
            },
            {
                path: 'mrr-waterfall',
                name: 'MrrWaterfall',
                component: () => import('@/views/mrr-waterfall/Index.vue'),
                meta: { titleKey: 'route_titles.mrr_waterfall', icon: 'TrendCharts' },
            },
            // ── 业务指标看板 M2-121 ──
            {
                path: 'business-metrics',
                name: 'BusinessMetrics',
                component: () => import('@/views/business-metrics/Index.vue'),
                meta: { titleKey: 'admin.menu.business_metrics', icon: 'DataBoard' },
            },
            // ── 多币种定价 / 汇率管理 (M2-30) ──
            {
                path: 'currency',
                name: 'Currency',
                component: () => import('@/views/currency/Index.vue'),
                meta: { titleKey: 'admin.menu.currency', icon: 'Coin' },
            },
            // ── 客户健康度评分 (M2-29) ──
            {
                path: 'health-score',
                name: 'HealthScore',
                component: () => import('@/views/health-score/Index.vue'),
                meta: { titleKey: 'admin.menu.health_score', icon: 'Monitor' },
            },
            // ── CSM 客户成功仪表盘 (M3-78) ──
            {
                path: 'csm',
                name: 'CsmDashboard',
                component: () => import('@/views/csm/Index.vue'),
                meta: { titleKey: 'route_titles.csm', icon: 'DataAnalysis' },
            },
            // ── 客户流失预测与干预 (M3-25) ──
            {
                path: 'churn-prediction',
                name: 'ChurnPrediction',
                component: () => import('@/views/churn-prediction/Index.vue'),
                meta: { titleKey: 'route_titles.churn_prediction_2', icon: 'WarningFilled' },
            },
            // ── 客户生命周期管理 (M3-19) ──
            {
                path: 'lifecycle',
                name: 'Lifecycle',
                component: () => import('@/views/lifecycle/Index.vue'),
                meta: { titleKey: 'route_titles.lifecycle', icon: 'Histogram' },
            },
            // ── CRM 客户关系管理 ──
            {
                path: 'crm',
                name: 'CrmDashboard',
                component: () => import('@/views/crm/Index.vue'),
                meta: { titleKey: 'admin.menu.crm', icon: 'DataBoard' },
            },
            // ── 批量操作工具 (M2-08) ──
            {
                path: 'batch',
                name: 'Batch',
                component: () => import('@/views/batch/Index.vue'),
                meta: { titleKey: 'admin.menu.batch', icon: 'List' },
            },
            // ─── 订单管理 ───
            {
                path: 'orders',
                name: 'Orders',
                component: () => import('@/views/orders/Index.vue'),
                meta: { titleKey: 'admin.menu.orders', icon: 'List' },
            },
            // ─── SKU 管理 ───
            {
                path: 'skus',
                name: 'Skus',
                component: () => import('@/views/skus/Index.vue'),
                meta: { titleKey: 'route_titles.skus', icon: 'Goods' },
            },
            // ── 客户分级 SLA (M2-31) ──
            {
                path: 'sla',
                name: 'SlaTracking',
                component: () => import('@/views/sla/Index.vue'),
                meta: { titleKey: 'admin.menu.sla', icon: 'Timer' },
            },
            // ── 计费管理 ──
            {
                path: 'billing/retention',
                name: 'BillingRetention',
                component: () => import('@/views/billing/Retention.vue'),
                meta: { titleKey: 'route_titles.billing_retention', icon: 'Connection' },
            },
            // ── AI 诊断 ──
            {
                path: 'diagnostic',
                name: 'Diagnostic',
                component: () => import('@/views/diagnostic/Index.vue'),
                meta: { titleKey: 'admin.menu.diagnostic', icon: 'MagicStick' },
            },
            // ── RAG 知识库索引管理 ──
            {
                path: 'rag',
                name: 'RagAdmin',
                component: () => import('@/views/rag/Index.vue'),
                meta: { titleKey: 'admin.menu.rag', icon: 'Reading' },
            },
            // ── AI 运营中心（知识库自增长/深度研究/幻觉检测等）──
            {
                path: 'ai-operations',
                name: 'AiOperations',
                component: () => import('@/views/ai-operations/Index.vue'),
                meta: { titleKey: 'admin.menu.ai_operations', icon: 'SetUp' },
            },
            // ── 客户用量看板 (M2-97) ──
            {
                path: 'usage-dashboard',
                name: 'UsageDashboard',
                component: () => import('@/views/usage-dashboard/Index.vue'),
                meta: { titleKey: 'admin.menu.usage_dashboard', icon: 'DataBoard' },
            },
            // ── AI MLOps 平台 (M3-40) ──
            {
                path: 'mlops',
                name: 'Mlops',
                component: () => import('@/views/mlops/Index.vue'),
                meta: { titleKey: 'route_titles.mlops', icon: 'Cpu' },
            },
            // ── AI 特征工程平台 (M3-41) ──
            {
                path: 'feature-store',
                name: 'FeatureStore',
                component: () => import('@/views/feature-store/Index.vue'),
                meta: { titleKey: 'route_titles.feature_store', icon: 'DataBoard' },
            },
            // ── SSO（统一合并） ──
            {
                path: 'sso',
                name: 'SsoSettings',
                component: () => import('@/views/sso/Index.vue'),
                meta: { titleKey: 'sso_unified.title', icon: 'Link' },
            },
            // ── 嵌入式 Widget (M2-141) ──
            {
                path: 'embedded-widget',
                name: 'EmbeddedWidget',
                component: () => import('@/views/embedded-widget/Index.vue'),
                meta: { titleKey: 'admin.menu.embedded_widget', icon: 'Monitor' },
            },
            // ── OAuth 登录配置 ──
            {
                path: 'oauth',
                name: 'OAuthSettings',
                component: () => import('@/views/oauth/Index.vue'),
                meta: { titleKey: 'admin.menu.oauth', icon: 'Link' },
            },
            // ── 飞书集成 (M3-38) ──
            {
                path: 'lark',
                name: 'LarkIntegration',
                component: () => import('@/views/lark/Index.vue'),
                meta: { titleKey: 'route_titles.lark', icon: 'ChatDotSquare' },
            },
            // ── OpenFeature ──
            {
                path: 'openfeature',
                name: 'OpenFeature',
                component: () => import('@/views/openfeature/Index.vue'),
                meta: { titleKey: 'admin.menu.openfeature', icon: 'Switch' },
            },
            // ── IM 即时通讯中心 ──
            {
                path: 'im',
                name: 'ImCenter',
                component: () => import('@/views/im/Index.vue'),
                meta: { titleKey: 'admin.menu.im', icon: 'ChatDotSquare' },
            },
            // 🆕 即时通讯
            {
                path: 'user-chat',
                name: 'UserChat',
                component: () => import('@/views/user-chat/Index.vue'),
                meta: { titleKey: 'admin.menu.user_chat', icon: 'ChatRound' },
            },
            // ── Bot 机器人管理 ──
            {
                path: 'bot-manager',
                name: 'BotManager',
                component: () => import('@/views/bot-manager/Index.vue'),
                meta: { titleKey: 'route_titles.bot_manager', icon: 'Cpu' },
            },
            // ── 更新包管理 ──
            {
                path: 'update-manager',
                name: 'UpdateManager',
                component: () => import('@/views/update-manager/Index.vue'),
                meta: { titleKey: 'admin.menu.update_manager', icon: 'Upload' },
            },
            // ── 审计治理中心 ──
            {
                path: 'audit',
                name: 'AuditCenter',
                component: () => import('@/views/audit/Index.vue'),
                meta: { titleKey: 'admin.menu.audit', icon: 'DataBoard' },
            },
            // ── 审计日志归档 (M2-73) ──
            {
                path: 'log-archiver',
                name: 'LogArchiver',
                component: () => import('@/views/log-archiver/Index.vue'),
                meta: { titleKey: 'admin.menu.log_archiver', icon: 'Coin' },
            },
            // ── 暗水印与防篡改 (M3-10) ──
            {
                path: 'watermark-tamper',
                name: 'WatermarkTamper',
                component: () => import('@/views/watermark/Index.vue'),
                meta: { titleKey: 'admin.menu.watermark_tamper', icon: 'Lock' },
            },
            // ── 团队协作中心 ──
            {
                path: 'collaboration',
                name: 'Collaboration',
                component: () => import('@/views/collaboration/Index.vue'),
                meta: { titleKey: 'admin.menu.collaboration', icon: 'ChatDotSquare' },
            },
            // ── 搜索中心 ──
            {
                path: 'search-center',
                name: 'SearchCenter',
                component: () => import('@/views/search-center/Index.vue'),
                meta: { titleKey: 'admin.menu.search_center', icon: 'Search' },
            },
            // ── 系统健康监控 ──
            {
                path: 'system-health',
                name: 'SystemHealth',
                component: () => import('@/views/system-health/Index.vue'),
                meta: { titleKey: 'admin.menu.system_health', icon: 'Monitor' },
            },
            // ── 国际化管理 ──
            {
                path: 'i18n',
                name: 'I18n',
                component: () => import('@/views/i18n/Index.vue'),
                meta: { titleKey: 'admin.menu.i18n', icon: 'ChatRound' },
            },
            // ── API 文档门户 ──
            {
                path: 'api-docs',
                name: 'ApiDocs',
                component: () => import('@/views/api-docs/Index.vue'),
                meta: { titleKey: 'admin.menu.api_docs', icon: 'Document' },
            },
            {
                path: 'api-docs-public',
                name: 'ApiDocsPublic',
                component: () => import('@/views/api-docs/PublicView.vue'),
                meta: { titleKey: 'route_titles.api_docs_public', icon: 'Reading' },
            },
            // ── 批量数据导入 ──
            {
                path: 'data-import',
                name: 'DataImport',
                component: () => import('@/views/data-import/Index.vue'),
                meta: { titleKey: 'admin.menu.data_import', icon: 'Upload' },
            },
            // ── 安全中心 ──
            {
                path: 'security',
                name: 'SecurityCenter',
                component: () => import('@/views/security/Index.vue'),
                meta: { titleKey: 'admin.menu.security', icon: 'Lock' },
            },
            {
                path: 'security/bug-bounty',
                name: 'BugBounty',
                component: () => import('@/views/security/BugBounty.vue'),
                meta: { title: 'Bug Bounty', icon: 'WarningFilled' },
            },
            // ── IDS/IPS 入侵检测与防御 ──
            {
                path: 'security/ids',
                name: 'IntrusionDetection',
                component: () => import('@/views/ids/Index.vue'),
                meta: { titleKey: 'route_titles.security_ids', icon: 'Monitor' },
            },
            // ── 租户隔离 ──
            {
                path: 'tenant-isolation',
                name: 'TenantIsolation',
                component: () => import('@/views/tenant-isolation/Index.vue'),
                meta: { titleKey: 'admin.menu.tenant_isolation', icon: 'Lock' },
            },
            // ── 自动化规则引擎 ──
            {
                path: 'automation',
                name: 'Automation',
                component: () => import('@/views/automation/Index.vue'),
                meta: { titleKey: 'admin.menu.automation', icon: 'Setting' },
            },
            // ── Merkle 审计链验证 ──
            {
                path: 'merkle-chain',
                name: 'MerkleChain',
                component: () => import('@/views/merkle-chain/Index.vue'),
                meta: { titleKey: 'admin.menu.merkle_chain', icon: 'Connection' },
            },
            // ── 试用管理 ──
            {
                path: 'trials',
                name: 'Trials',
                component: () => import('@/views/trials/Index.vue'),
                meta: { titleKey: 'admin.menu.trials', icon: 'Timer' },
            },
            // ── 离线 License ──
            {
                path: 'offline',
                name: 'OfflineLicense',
                component: () => import('@/views/offline/Index.vue'),
                meta: { titleKey: 'offline_center.title', icon: 'Connection' },
            },
            // ── 无障碍帮助 M3-54 ──
            {
                path: 'a11y',
                name: 'A11yHelp',
                component: () => import('@/views/a11y/Index.vue'),
                meta: { titleKey: 'admin.menu.a11y', icon: 'Reading' },
            },
            {
                path: 'graphql',
                name: 'GraphQLExplorer',
                component: () => import('@/views/graphql/Index.vue'),
                meta: { title: 'GraphQL API', icon: 'Connection' },
            },
            // ── 自定义字段 M3-46 ──
            {
                path: 'custom-fields',
                name: 'CustomFields',
                component: () => import('@/views/system/CustomFields.vue'),
                meta: { titleKey: 'admin.menu.custom_fields', icon: 'EditPen' },
            },
            // ── 数据导出管理 M3-30 ──
            {
                path: 'data-exports',
                name: 'DataExport',
                component: () => import('@/views/system/DataExport.vue'),
                meta: { titleKey: 'admin.menu.data_exports', icon: 'Download' },
            },
            // ── 通知偏好管理 M3-29 ──
            {
                path: 'notification-preferences',
                name: 'AdminNotificationPreferences',
                component: () => import('@/views/system/NotificationPreferences.vue'),
                meta: { titleKey: 'route_titles.notification_preferences', icon: 'Bell' },
            },
            // ── API 版本管理 (M2-33) ──
            {
                path: 'api-versions',
                name: 'ApiVersions',
                component: () => import('@/views/api-versions/Index.vue'),
                meta: { titleKey: 'admin.menu.api_versions', icon: 'Connection' },
            },
            // ── SDK Telemetry 心跳上报 (M2-32) ──
            {
                path: 'telemetry',
                name: 'Telemetry',
                component: () => import('@/views/telemetry/Index.vue'),
                meta: { titleKey: 'admin.menu.telemetry', icon: 'DataAnalysis' },
            },
            // ── SDK 错误码参考手册 (M2-34) ──
            {
                path: 'error-codes',
                name: 'ErrorCodes',
                component: () => import('@/views/error-codes/Index.vue'),
                meta: { titleKey: 'admin.menu.error_codes', icon: 'WarningFilled' },
            },
            // ── SSL 证书管理 ──
            {
                path: 'ssl-certificates',
                name: 'SslCertificates',
                component: () => import('@/views/ssl-certificates/Index.vue'),
                meta: { titleKey: 'admin.menu.ssl_certificates', icon: 'Lock' },
            },
            // ── 订阅详情 ──
            {
                path: 'billing/:id',
                name: 'BillingDetail',
                component: () => import('@/views/billing/Detail.vue'),
                meta: { titleKey: 'route_titles.billing_id', hidden: true },
            },
        ],
    },
    {
        path: '/portal',
        component: () => import('@/layouts/PortalLayout.vue'),
        redirect: '/portal/dashboard',
        children: [
            {
                path: 'dashboard',
                name: 'PortalDashboard',
                component: () => import('@/views/portal/Index.vue'),
                meta: { titleKey: 'route_titles.portal' },
            },
            // ── 商品商店 (M1.4-61) ──
            {
                path: 'shop',
                name: 'Shop',
                component: () => import('@/views/shop/Index.vue'),
                meta: { titleKey: 'route_titles.shop' },
            },
            {
                path: 'cart',
                name: 'ShopCart',
                component: () => import('@/views/shop/Cart.vue'),
                meta: { titleKey: 'route_titles.cart' },
            },
            {
                path: 'checkout',
                name: 'ShopCheckout',
                component: () => import('@/views/shop/Checkout.vue'),
                meta: { titleKey: 'route_titles.checkout' },
            },
            {
                path: 'payment-result/:id',
                name: 'PaymentResult',
                component: () => import('@/views/shop/PaymentResult.vue'),
                meta: { titleKey: 'route_titles.payment_result_id' },
            },
            // ── 客户订单历史 (M1.4-66) ──
            {
                path: 'orders',
                name: 'PortalOrders',
                component: () => import('@/views/portal/Orders.vue'),
                meta: { titleKey: 'route_titles.orders' },
            },
            {
                path: 'licenses',
                name: 'PortalLicenses',
                component: () => import('@/views/portal/Licenses.vue'),
                meta: { titleKey: 'route_titles.licenses' },
            },
            {
                path: 'licenses/:id',
                name: 'PortalLicenseDetail',
                component: () => import('@/views/portal/LicenseDetail.vue'),
                meta: { titleKey: 'route_titles.licenses_id' },
            },
            {
                path: 'devices',
                name: 'PortalDevices',
                component: () => import('@/views/portal/Devices.vue'),
                meta: { titleKey: 'route_titles.devices' },
            },
            {
                path: 'billing',
                name: 'PortalBilling',
                component: () => import('@/views/portal/Billing.vue'),
                meta: { titleKey: 'route_titles.billing' },
            },
            {
                path: 'invoices',
                name: 'PortalInvoices',
                component: () => import('@/views/portal/Invoices.vue'),
                meta: { titleKey: 'route_titles.invoices' },
            },
            {
                path: 'payment-methods',
                name: 'PortalPaymentMethods',
                component: () => import('@/views/portal/PaymentMethods.vue'),
                meta: { titleKey: 'route_titles.payment_methods' },
            },
            {
                path: 'usage',
                name: 'PortalUsage',
                component: () => import('@/views/portal/Usage.vue'),
                meta: { titleKey: 'route_titles.usage' },
            },
            {
                path: 'analytics',
                name: 'PortalAnalytics',
                component: () => import('@/views/portal/AnalyticsDashboard.vue'),
                meta: { titleKey: 'route_titles.analytics' },
            },
            // ── License 健康评分 (M2-110) ──
            {
                path: 'license-health',
                name: 'PortalLicenseHealth',
                component: () => import('@/views/portal/LicenseHealth.vue'),
                meta: { titleKey: 'admin.menu.license_health' },
            },
            {
                path: 'notification-preferences',
                name: 'PortalNotificationPreferences',
                component: () => import('@/views/portal/NotificationPreferences.vue'),
                meta: { titleKey: 'route_titles.notification_preferences_2' },
            },
            {
                path: 'audit-log',
                name: 'PortalAuditLog',
                component: () => import('@/views/portal/AuditLog.vue'),
                meta: { titleKey: 'route_titles.audit_log' },
            },
            {
                path: 'tickets',
                name: 'PortalTickets',
                component: () => import('@/views/portal/Tickets.vue'),
                meta: { titleKey: 'route_titles.tickets' },
            },
            {
                path: 'tickets/:id',
                name: 'PortalTicketDetail',
                component: () => import('@/views/portal/TicketDetail.vue'),
                meta: { titleKey: 'route_titles.tickets_id' },
            },
            {
                path: 'settings',
                name: 'PortalSettings',
                component: () => import('@/views/portal/Settings.vue'),
                meta: { titleKey: 'route_titles.settings' },
            },
            {
                path: 'sessions',
                name: 'PortalSessions',
                component: () => import('@/views/portal/Sessions.vue'),
                meta: { titleKey: 'route_titles.sessions' },
            },
            {
                path: 'data-exports',
                name: 'PortalDataExport',
                component: () => import('@/views/portal/DataExport.vue'),
                meta: { titleKey: 'route_titles.data_exports' },
            },
            // ── 帮助中心 ──
            {
                path: 'help',
                redirect: '/portal/knowledge-base',
            },
            {
                path: 'knowledge-base',
                name: 'PortalKnowledgeBase',
                component: () => import('@/views/portal/KnowledgeBase.vue'),
                meta: { titleKey: 'admin.menu.knowledge_base' },
            },
            // ── 收益账户 ──
            {
                path: 'earnings',
                name: 'PortalEarnings',
                component: () => import('@/views/portal/Earnings.vue'),
                meta: { titleKey: 'route_titles.earnings' },
            },
            // ── 合作伙伴中心 ──
            {
                path: 'partner',
                name: 'PortalPartner',
                component: () => import('@/views/commission/MyPartnerPortal.vue'),
                meta: { titleKey: 'route_titles.partner' },
            },
            // ── 联盟推广 ──
            {
                path: 'affiliate',
                name: 'PortalAffiliate',
                component: () => import('@/views/commission/MyAffiliate.vue'),
                meta: { titleKey: 'admin.menu.affiliate' },
            },
            // ── 优惠促销 ──
            {
                path: 'promotions',
                name: 'PortalPromotions',
                component: () => import('@/views/portal/Promotions.vue'),
                meta: { titleKey: 'route_titles.promotions' },
            },
            // ── License 转移 ──
            {
                path: 'transfers',
                name: 'PortalTransfers',
                component: () => import('@/views/portal/Transfers.vue'),
                meta: { titleKey: 'admin.menu.transfers' },
            },
            // ── 通知中心 ──
            {
                path: 'notifications',
                name: 'PortalNotifications',
                component: () => import('@/views/notifications/Index.vue'),
                meta: { titleKey: 'admin.menu.notifications' },
            },
            // ── 客户反馈 ──
            {
                path: 'feedback',
                name: 'PortalFeedback',
                component: () => import('@/views/portal/MyFeedback.vue'),
                meta: { titleKey: 'route_titles.feedback' },
            },
            // ── API Keys ──
            {
                path: 'api-keys',
                name: 'PortalApiKeys',
                component: () => import('@/views/portal/ApiKeys.vue'),
                meta: { title: 'API Keys' },
            },
            // ── 团队协作 ──
            {
                path: 'team',
                name: 'PortalTeam',
                component: () => import('@/views/portal/Team.vue'),
                meta: { titleKey: 'route_titles.team_2' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: () => import('@/views/errors/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL || '/admin'),
    routes,
});

// 路由守卫
router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();

    // OAuth 回调：落地页带 oauth_token 时写入本地并拉用户
    if (to.query.oauth_token && typeof to.query.oauth_token === 'string') {
        localStorage.setItem('auth_token', to.query.oauth_token);
        auth.token = to.query.oauth_token;
        try {
            await auth.fetchUser();
            ElMessage.success(i18n.global.t('auth.oauth_login_ok'));
        } catch { /* ignore */ }
        const q = { ...to.query };
        delete q.oauth_token;
        delete q.oauth_provider;
        return next({ path: to.path, query: q, replace: true });
    }
    if (to.query.oauth_error && typeof to.query.oauth_error === 'string') {
        ElMessage.error(decodeURIComponent(to.query.oauth_error));
        const q = { ...to.query };
        delete q.oauth_error;
        return next({ path: to.path, query: q, replace: true });
    }
    if (to.query.oauth_bound) {
        ElMessage.success(i18n.global.t('auth.oauth_bound_ok'));
        const q = { ...to.query };
        delete q.oauth_bound;
        delete q.provider;
        return next({ path: to.path, query: q, replace: true });
    }

    // 设置页面标题（双语）
    const docTitle = resolveDocumentTitle(to);
    if (docTitle) {
        document.title = docTitle;
    }

    // 登录、注册、密码重置页面 - 已登录时跳转仪表盘
    if (to.name === 'Login' || to.name === 'Register' || to.name === 'ForgotPassword') {
        if (auth.isLoggedIn) {
            return next('/dashboard');
        }
        return next();
    }

    // 公开页面 - 无需认证，已登录用户也可访问
    if (to.name === 'Appeal' || to.name === 'StatusPage' || to.name === 'Community' || to.name === 'Channels' || to.name === 'UserProfile' || to.name === 'PlazaDetail' || to.name === 'OaArticleDetail' || to.name === 'OaEditor' || to.name === 'InteractiveDemo' || String(to.path).startsWith('/oa-editor') || String(to.path).startsWith('/oa-article/')) {
        return next();
    }

    // 租户选择页需要已登录
    if (to.name === 'TenantSelect') {
        if (!auth.isLoggedIn) {
            return next({ path: '/login', query: { redirect: to.fullPath } });
        }
        return next();
    }

    // 检查认证
    if (!auth.isLoggedIn) {
        return next({ path: '/login', query: { redirect: to.fullPath } });
    }

    // 如果是多租户用户且没有选择租户，跳转到租户选择页
    if (auth.isMultiTenant && !auth.activeTenantId && to.name !== 'TenantSelect') {
        return next('/tenant-select');
    }

    next();
});

export default router;
