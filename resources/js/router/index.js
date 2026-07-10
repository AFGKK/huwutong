import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

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
        meta: { layout: 'blank', title: '免费注册' },
    },
    {
        path: '/forgot-password',
        name: 'ForgotPassword',
        component: () => import('@/views/auth/ForgotPassword.vue'),
        meta: { layout: 'blank', title: '忘记密码' },
    },
    {
        path: '/checkout',
        name: 'Checkout',
        component: () => import('@/views/checkout/Index.vue'),
        meta: { title: '下单购买' },
    },
    {
        path: '/status',
        name: 'StatusPage',
        component: () => import('@/views/status/Index.vue'),
        meta: { layout: 'blank', title: '系统状态' },
    },
    {
        path: '/following-feed',
        name: 'FollowingFeed',
        component: () => import('@/views/account/FollowingFeed.vue'),
        meta: { layout: 'blank', title: '关注动态' },
    },
    {
        path: '/appeal',
        name: 'Appeal',
        component: () => import('@/views/appeal/Index.vue'),
        meta: { layout: 'blank', title: '账号申诉' },
    },
    {
        path: '/demo',
        name: 'InteractiveDemo',
        component: () => import('@/views/demo/Index.vue'),
        meta: { layout: 'blank', title: '产品演示' },
    },
    {
        path: '/tenant-select',
        name: 'TenantSelect',
        component: () => import('@/views/tenants/Select.vue'),
        meta: { layout: 'blank', title: '选择租户' },
    },
    {
        path: '/onboarding',
        name: 'Onboarding',
        component: () => import('@/views/onboarding/Index.vue'),
        meta: { layout: 'blank', title: '系统初始化' },
    },
    {
        path: '/qr-confirm',
        name: 'QrConfirm',
        component: () => import('@/views/auth/QrConfirm.vue'),
        meta: { layout: 'blank', title: '扫码确认' },
    },
    {
        path: '/oa-editor',
        name: 'OaEditor',
        component: () => import('@/views/im/OaArticleEditor.vue'),
        meta: { layout: 'blank', title: '文章编辑器' },
    },
    {
        path: '/oa-article/:id',
        name: 'OaArticleDetail',
        component: () => import('@/views/im/OaArticleDetail.vue'),
        meta: { layout: 'blank', title: '文章详情' },
    },
    {
        path: '/plaza/:id',
        name: 'PlazaDetail',
        component: () => import('@/views/plaza/Detail.vue'),
        meta: { layout: 'blank', title: '广场详情' },
    },
    // ── 社区 / 广场 ──
    {
        path: '/community',
        name: 'Community',
        component: () => import('@/views/community/Index.vue'),
        meta: { layout: 'blank', title: '社区' },
    },
    {
        path: '/plaza/user/:id',
        name: 'UserProfile',
        component: () => import('@/views/community/UserProfile.vue'),
        meta: { layout: 'blank', title: '用户主页' },
    },
    // ── 互物号 ──
    {
        path: '/channels',
        name: 'Channels',
        component: () => import('@/views/channels/Index.vue'),
        meta: { layout: 'blank', title: '互物号' },
    },
    // ── 已合并 IM 页面重定向（保持向后兼容） ──
    { path: '/ai-chat', redirect: '/im' },
    { path: '/handoff', redirect: '/im' },
    { path: '/handoff/:id', redirect: '/im' },
    { path: '/live-chat', redirect: '/im' },
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
                meta: { title: '仪表盘', icon: 'Odometer' },
            },
            {
                path: 'licenses',
                name: 'Licenses',
                component: () => import('@/views/licenses/Index.vue'),
                meta: { title: 'License 管理', icon: 'Key' },
            },
            {
                path: 'licenses/:id',
                name: 'LicenseDetail',
                component: () => import('@/views/licenses/Detail.vue'),
                meta: { title: 'License 详情', hidden: true },
            },
            {
                path: 'license-analytics',
                name: 'LicenseAnalytics',
                component: () => import('@/views/license-analytics/Index.vue'),
                meta: { title: 'License 分析引擎', icon: 'DataBoard' },
            },
            {
                path: 'product-analytics',
                name: 'ProductAnalytics',
                component: () => import('@/views/product-analytics/Index.vue'),
                meta: { title: '产品使用分析', icon: 'DataAnalysis' },
            },
            // ── SKU 商品规格管理 (M1.1-24 🛒) ──
            {
                path: 'product-sku',
                name: 'ProductSku',
                component: () => import('@/views/product-sku/Index.vue'),
                meta: { title: 'SKU 商品规格', icon: 'Goods' },
            },
            // ── 优惠券管理 (M1.4-65) ──
            {
                path: 'coupons',
                name: 'Coupon',
                component: () => import('@/views/coupon/Index.vue'),
                meta: { title: '优惠券管理', icon: 'Coin' },
            },
            // ── API Mock Server (M1.4-59) ──
            {
                path: 'mock-server',
                name: 'MockServer',
                component: () => import('@/views/mock-server/Index.vue'),
                meta: { title: 'API Mock Server', icon: 'Connection' },
            },
            // ── 商品搜索管理 (M2-156 🛒) ──
            {
                path: 'product-search',
                name: 'ProductSearch',
                component: () => import('@/views/product-search/Index.vue'),
                meta: { title: '商品搜索管理', icon: 'Search' },
            },
            // ── 客户 API Key 管理 (M2-96) ──
            {
                path: 'customer-api-key',
                name: 'CustomerApiKey',
                component: () => import('@/views/customer-api-key/Index.vue'),
                meta: { title: 'API Key 管理', icon: 'Key' },
            },
            // ── CRL 吊销列表管理 (M1.3-03) ──
            {
                path: 'crl',
                name: 'Crl',
                component: () => import('@/views/crl/Index.vue'),
                meta: { title: 'CRL 吊销列表', icon: 'RemoveFilled' },
            },
            // ── 异常检测 (M2-04) ──
            {
                path: 'anomaly-detection',
                name: 'AnomalyDetection',
                component: () => import('@/views/anomaly-detection/Index.vue'),
                meta: { title: '异常检测', icon: 'WarningFilled' },
            },
            // ── 密钥泄露扫描 (M1.3-29) ──
            {
                path: 'secret-scan',
                name: 'SecretScan',
                component: () => import('@/views/secret-scan/Index.vue'),
                meta: { title: '密钥泄露扫描', icon: 'WarningFilled' },
            },
            // ── AI Token 用量计费 (M2-77) ──
            {
                path: 'token-meter',
                name: 'TokenMeter',
                component: () => import('@/views/token-meter/Index.vue'),
                meta: { title: 'Token 计费追踪', icon: 'Money' },
            },
            {
                path: 'customer-merge',
                name: 'CustomerMerge',
                component: () => import('@/views/customer-merge/Index.vue'),
                meta: { title: '客户合并', icon: 'Connection' },
            },
            {
                path: 'license-merge',
                name: 'LicenseMerge',
                component: () => import('@/views/license-merge/Index.vue'),
                meta: { title: 'License继承/合并', icon: 'CopyDocument' },
            },
            {
                path: 'license-trash',
                name: 'LicenseTrash',
                component: () => import('@/views/license-trash/Index.vue'),
                meta: { title: 'License 回收站', icon: 'Delete' },
            },
            {
                path: 'license-compliance',
                name: 'LicenseCompliance',
                component: () => import('@/views/license-compliance/Index.vue'),
                meta: { title: '📋 合规审计报告', icon: 'Document' },
            },
            {
                path: 'license-snapshot',
                name: 'LicenseSnapshot',
                component: () => import('@/views/license-snapshot/Index.vue'),
                meta: { title: 'License 快照', icon: 'Clock' },
            },
            {
                path: 'license-approval',
                name: 'LicenseApproval',
                component: () => import('@/views/license-approval/Index.vue'),
                meta: { title: 'License 审批', icon: 'Checked' },
            },
            {
                path: 'promotion-engine',
                name: 'PromotionEngine',
                component: () => import('@/views/promotion-engine/Index.vue'),
                meta: { title: '满减/满折促销', icon: 'Discount' },
            },
            {
                path: 'ecommerce-dashboard',
                name: 'EcommerceDashboard',
                component: () => import('@/views/ecommerce-dashboard/Index.vue'),
                meta: { title: '电商数据看板', icon: 'TrendCharts' },
            },
            {
                path: 'ecommerce-analytics',
                name: 'EcommerceAnalytics',
                component: () => import('@/views/ecommerce-analytics/Index.vue'),
                meta: { title: '电商数据分析', icon: 'DataBoard' },
            },
            {
                path: 'multi-currency-pricing',
                name: 'MultiCurrencyPricing',
                component: () => import('@/views/multi-currency-pricing/Index.vue'),
                meta: { title: '多币种商品定价', icon: 'Coin' },
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: () => import('@/views/inventory/Index.vue'),
                meta: { title: '库存管理', icon: 'Goods' },
            },
            {
                path: 'billing-cycles',
                name: 'BillingCycles',
                component: () => import('@/views/billing-cycles/Index.vue'),
                meta: { title: '计费周期', icon: 'Timer' },
            },
            {
                path: 'payment-security',
                name: 'PaymentSecurity',
                component: () => import('@/views/payment-security/Index.vue'),
                meta: { title: '支付安全', icon: 'Shield' },
            },
            {
                path: 'refund-workflow',
                name: 'RefundWorkflow',
                component: () => import('@/views/refund-workflow/Index.vue'),
                meta: { title: '退款售后', icon: 'ChatDotSquare' },
            },
            {
                path: 'local-proxy',
                name: 'LocalProxy',
                component: () => import('@/views/local-proxy/Index.vue'),
                meta: { title: '本地License代理', icon: 'Connection' },
            },
            {
                path: 'license-analytics',
                name: 'LicenseAnalytics',
                component: () => import('@/views/license-analytics/Index.vue'),
                meta: { title: 'License使用分析', icon: 'DataBoard' },
            },
            {
                path: 'heatmap',
                name: 'Heatmap',
                component: () => import('@/views/heatmap/Index.vue'),
                meta: { title: '多层热力地图', icon: 'MapLocation' },
            },
            {
                path: 'contracts',
                name: 'SmartContract',
                component: () => import('@/views/contracts/Index.vue'),
                meta: { title: '智能合约授权', icon: 'Document' },
            },
            {
                path: 'license-transfers',
                name: 'LicenseTransfer',
                component: () => import('@/views/transfers/Index.vue'),
                meta: { title: 'License转移', icon: 'Switch' },
            },
            {
                path: 'ownership-transfer',
                name: 'OwnershipTransfer',
                component: () => import('@/views/ownership-transfer/Index.vue'),
                meta: { title: '所有权转移', icon: 'Connection' },
            },
            {
                path: 'files',
                name: 'CustomerFiles',
                component: () => import('@/views/files/Index.vue'),
                meta: { title: '文件管理', icon: 'FolderOpened' },
            },
            {
                path: 'roi-calculator',
                name: 'RoiCalculator',
                component: () => import('@/views/roi-calculator/Index.vue'),
                meta: { title: 'ROI计算器', icon: 'DataAnalysis' },
            },
            {
                path: 'blog',
                name: 'BlogManager',
                component: () => import('@/views/blog/Index.vue'),
                meta: { title: 'Blog / Changelog', icon: 'Document' },
            },
            {
                path: 'prompt-templates',
                name: 'PromptTemplates',
                component: () => import('@/views/prompt-templates/Index.vue'),
                meta: { title: 'Prompt 模板管理', icon: 'EditPen' },
            },
            {
                path: 'sensitive-words',
                name: 'SensitiveWords',
                component: () => import('@/views/sensitive-words/Index.vue'),
                meta: { title: '敏感词管理', icon: 'WarningFilled' },
            },
            {
                path: 'changelog',
                name: 'ChangelogManager',
                component: () => import('@/views/changelog/Index.vue'),
                meta: { title: 'API Changelog', icon: 'Document' },
            },
            {
                path: 'bundles',
                name: 'BundleManager',
                component: () => import('@/views/bundles/Index.vue'),
                meta: { title: '组合套餐', icon: 'Goods' },
            },
            {
                path: 'product-localization',
                name: 'ProductLocalization',
                component: () => import('@/views/product-localization/Index.vue'),
                meta: { title: '多语言商品', icon: 'ChatRound' },
            },
            {
                path: 'reviews',
                name: 'ProductReview',
                component: () => import('@/views/product-review/Index.vue'),
                meta: { title: '商品评论', icon: 'Star' },
            },
            {
                path: 'product-comparison',
                name: 'ProductComparison',
                component: () => import('@/views/product-comparison/Index.vue'),
                meta: { title: '规格对比', icon: 'Histogram' },
            },
            {
                path: 'pre-sale',
                name: 'PreSale',
                component: () => import('@/views/pre-sale/Index.vue'),
                meta: { title: '预售/众筹', icon: 'Money' },
            },
            {
                path: 'resale',
                name: 'ResaleMarketplace',
                component: () => import('@/views/resale/Index.vue'),
                meta: { title: '二级市场转售', icon: 'ShoppingCart' },
            },
            {
                path: 'certification',
                name: 'Certification',
                component: () => import('@/views/certification/Index.vue'),
                meta: { title: '开发者认证', icon: 'Medal' },
            },
            {
                path: 'open-platform',
                name: 'OpenPlatform',
                component: () => import('@/views/open-platform/Index.vue'),
                props: { defaultTab: 'pending' },
                meta: { title: '开放平台', icon: 'Grid' },
            },
            {
                path: 'app-marketplace',
                name: 'AppMarketplace',
                component: () => import('@/views/app-marketplace/Index.vue'),
                meta: { title: '应用市场', icon: 'ShoppingCart' },
            },
            {
                path: 'app-marketplace/:id',
                name: 'AppMarketplaceDetail',
                component: () => import('@/views/app-marketplace/Detail.vue'),
                meta: { title: '应用详情', icon: 'ShoppingCart' },
            },
            {
                path: 'api-key-audit',
                name: 'ApiKeyAudit',
                component: () => import('@/views/api-key-audit/Index.vue'),
                meta: { title: 'API 密钥审计', icon: 'Reading' },
            },
            {
                path: 'quota',
                name: 'Quota',
                component: () => import('@/views/quota/Index.vue'),
                meta: { title: '限流配额管理', icon: 'TrendCharts' },
            },
            {
                path: 'monitor',
                name: 'MonitorDashboard',
                component: () => import('@/views/monitor/Index.vue'),
                meta: { title: 'API 监控', icon: 'DataAnalysis' },
            },
            {
                path: 'compat-test',
                name: 'CompatTest',
                component: () => import('@/views/compat-test/Index.vue'),
                meta: { title: '兼容性测试', icon: 'Monitor' },
            },
            {
                path: 'wishlist',
                name: 'Wishlist',
                component: () => import('@/views/wishlist/Index.vue'),
                meta: { title: '商品收藏', icon: 'Star' },
            },
            {
                path: 'license-key-prefix',
                name: 'LicenseKeyPrefix',
                component: () => import('@/views/key-prefix/Index.vue'),
                meta: { title: 'License Key 前缀', icon: 'EditPen' },
            },
            {
                path: 'licenses/:id/seat-pool',
                name: 'SeatPool',
                component: () => import('@/views/licenses/SeatPool.vue'),
                meta: { title: '席位池管理', hidden: true },
            },
            // ── 席位池管理 (M3-45) ──
            {
                path: 'seat-pool',
                name: 'SeatPoolAdmin',
                component: () => import('@/views/seat-pool/Index.vue'),
                meta: { title: '席位池管理', icon: 'Connection' },
            },
            // ── Webhook 管理 (已整合) ──
            {
                path: 'webhooks',
                name: 'Webhooks',
                component: () => import('@/views/webhooks/Index.vue'),
                meta: { title: 'Webhook 管理', icon: 'Link' },
            },
            // ── SIEM 审计日志导出 (M2-52) ──
            {
                path: 'siem-export',
                name: 'SiemExport',
                component: () => import('@/views/siem-export/Index.vue'),
                meta: { title: 'SIEM 日志导出', icon: 'DataBoard' },
            },
            // ── 页脚导航配置 (M2-85) ──
            {
                path: 'footer-nav',
                name: 'FooterNav',
                component: () => import('@/views/footer-nav/Index.vue'),
                meta: { title: '页脚导航配置', icon: 'Link' },
            },
            // ── 两阶段提交 (M3-13) ──
            {
                path: 'two-phase-commit',
                name: 'TwoPhaseCommit',
                component: () => import('@/views/two-phase-commit/Index.vue'),
                meta: { title: '两阶段提交', icon: 'Timer' },
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
                meta: { title: '区块链 License', icon: 'Coin' },
            },
            // ── Serverless 云函数授权 (M3-16) ──
            {
                path: 'serverless-auth',
                name: 'ServerlessAuth',
                component: () => import('@/views/serverless-auth/Index.vue'),
                meta: { title: 'Serverless 授权', icon: 'Monitor' },
            },
            // ── 边缘计算授权 (M3-17) ──
            {
                path: 'edge-auth',
                name: 'EdgeAuth',
                component: () => import('@/views/edge-auth/Index.vue'),
                meta: { title: '边缘计算授权', icon: 'Connection' },
            },
            // ── License 二级市场 (M3-81) ──
            {
                path: 'license-marketplace',
                name: 'LicenseMarketplace',
                component: () => import('@/views/license-marketplace/Index.vue'),
                meta: { title: 'License 二级市场', icon: 'Shop' },
            },
            // ── 队列死信监控 (M2-82) ──
            {
                path: 'queue-monitor',
                name: 'QueueMonitor',
                component: () => import('@/views/queue-monitor/Index.vue'),
                meta: { title: '队列死信监控', icon: 'Monitor' },
            },
            // ── 在线客服管理（已合并到客服工作台） ──
            { path: 'live-chat', redirect: '/im' },
            // ── Trial→付费转化漏斗 (M2-101) ──
            {
                path: 'conversion-funnel',
                name: 'ConversionFunnel',
                component: () => import('@/views/conversion-funnel/Index.vue'),
                meta: { title: '转化漏斗', icon: 'DataAnalysis' },
            },
            // ── 邮件管理 (已整合) ──
            {
                path: 'email',
                name: 'EmailManager',
                component: () => import('@/views/email/Index.vue'),
                meta: { title: '邮件管理', icon: 'Message' },
            },
            // ── 🛒 秒杀/抢购 (M2-159) ──
            {
                path: 'flash-sale',
                name: 'FlashSale',
                component: () => import('@/views/flash-sale/Index.vue'),
                meta: { title: '秒杀/抢购', icon: 'Lightning' },
            },
            // ── 🛒 订单售后工单 (M2-157) ──
            {
                path: 'order-after-sales',
                name: 'OrderAfterSales',
                component: () => import('@/views/order-after-sales/Index.vue'),
                meta: { title: '订单售后工单', icon: 'Service' },
            },
            // ── 电商对账系统 (M2-158 🛒) ──
            {
                path: 'reconciliation',
                name: 'Reconciliation',
                component: () => import('@/views/reconciliation/Index.vue'),
                meta: { title: '电商对账系统', icon: 'DataBoard' },
            },
            {
                path: 'ci-cd',
                name: 'CiCd',
                component: () => import('@/views/ci-cd/Index.vue'),
                meta: { title: '🔑 CI/CD 自动授权', icon: 'Connection' },
            },
            {
                path: 'customers',
                name: 'Customers',
                component: () => import('@/views/customers/Index.vue'),
                meta: { title: '客户管理', icon: 'User' },
            },
            {
                path: 'customers/:id',
                name: 'CustomerDetail',
                component: () => import('@/views/customers/Detail.vue'),
                meta: { title: '客户详情', hidden: true },
            },
            {
                path: 'products',
                name: 'Products',
                component: () => import('@/views/products/Index.vue'),
                meta: { title: '产品管理', icon: 'Goods' },
            },
            {
                path: 'products/:id',
                name: 'ProductDetail',
                component: () => import('@/views/products/Detail.vue'),
                meta: { title: '产品详情', hidden: true },
            },
            {
                path: 'product-categories',
                name: 'ProductCategories',
                component: () => import('@/views/product-categories/Index.vue'),
                meta: { title: '产品分类', icon: 'Collection' },
            },
            {
                path: 'devices',
                name: 'Devices',
                component: () => import('@/views/devices/Index.vue'),
                meta: { title: '设备管理', icon: 'Monitor' },
            },
            {
                path: 'device-lifecycle',
                name: 'DeviceLifecycle',
                component: () => import('@/views/device-lifecycle/Index.vue'),
                meta: { title: '设备生命周期画像', icon: 'TrendCharts' },
            },
            // ── 设备指纹漂移追踪 (M2-25) ──
            {
                path: 'fingerprint-drift',
                name: 'FingerprintDrift',
                component: () => import('@/views/fingerprint-drift/Index.vue'),
                meta: { title: '指纹漂移追踪', icon: 'Monitor' },
            },
            // ── SCIM 自动用户同步 (M2-51) ──
            {
                path: 'scim',
                name: 'Scim',
                component: () => import('@/views/scim/Index.vue'),
                meta: { title: 'SCIM 用户同步', icon: 'Refresh' },
            },
            // ── 数据血缘追踪 (M2-113) ──
            {
                path: 'data-lineage',
                name: 'DataLineage',
                component: () => import('@/views/data-lineage/Index.vue'),
                meta: { title: '数据血缘追踪', icon: 'Connection' },
            },
            // ── AI 智能套件 (M2-43~48) ──
            {
                path: 'ai',
                name: 'AiIntelligence',
                component: () => import('@/views/ai/Index.vue'),
                meta: { title: 'AI 智能套件', icon: 'Monitor' },
                children: [
                    { path: 'revenue-forecast', name: 'AiRevenueForecast', component: () => import('@/views/ai/RevenueForecast.vue'), meta: { title: '收入预测' } },
                    { path: 'churn-prediction', name: 'AiChurnPrediction', component: () => import('@/views/ai/ChurnPrediction.vue'), meta: { title: '流失预警' } },
                    { path: 'adaptive-security', name: 'AiAdaptiveSecurity', component: () => import('@/views/ai/AdaptiveSecurity.vue'), meta: { title: '自适应安全' } },
                    { path: 'pricing-optimizer', name: 'AiPricingOptimizer', component: () => import('@/views/ai/PricingOptimizer.vue'), meta: { title: '定价建议' } },
                    { path: 'sdk-generator', name: 'AiSdkGenerator', component: () => import('@/views/ai/SdkGenerator.vue'), meta: { title: 'SDK 生成器' } },
                    { path: 'test-generator', name: 'AiTestGenerator', component: () => import('@/views/ai/TestGenerator.vue'), meta: { title: '测试生成器' } },
                ],
            },
            // ── AI-043 长期记忆 Memory ──
            {
                path: 'ai-memory',
                name: 'AiMemory',
                component: () => import('@/views/ai-memory/Index.vue'),
                meta: { title: 'AI 长期记忆', icon: 'Collection' },
            },
            // ── AI-045 主动洞察推送 ──
            {
                path: 'ai-proactive',
                name: 'AiProactive',
                component: () => import('@/views/ai-proactive/Index.vue'),
                meta: { title: 'AI 主动洞察', icon: 'DataAnalysis' },
            },
            // ── PRAC-009 值班轮换 On-Call ──
            {
                path: 'on-call',
                name: 'OnCall',
                component: () => import('@/views/on-call/Index.vue'),
                meta: { title: '值班轮换', icon: 'AlarmClock' },
            },
            // ── 自动发货管理 (M2-142 🛒) ──
            {
                path: 'auto-delivery',
                name: 'AutoDelivery',
                component: () => import('@/views/auto-delivery/Index.vue'),
                meta: { title: '多渠道送达', icon: 'ShoppingCart' },
            },
            // ── 支付回调管理 (M2-144 🛒) ──
            {
                path: 'payment-callbacks',
                name: 'PaymentCallbacks',
                component: () => import('@/views/payment-callback/Index.vue'),
                meta: { title: '支付回调', icon: 'Coin' },
            },
            {
                path: 'rbac',
                name: 'Rbac',
                component: () => import('@/views/rbac/Index.vue'),
                meta: { title: '权限管理', icon: 'Setting' },
            },
            {
                path: 'users',
                name: 'AdminUsers',
                component: () => import('@/views/users/Index.vue'),
                meta: { title: '用户管理', icon: 'User' },
            },
            {
                path: 'wizard',
                name: 'AiWizard',
                component: () => import('@/views/wizard/Index.vue'),
                meta: { title: 'AI 集成向导', icon: 'MagicStick' },
            },
            {
                path: 'notifications',
                name: 'NotificationList',
                component: () => import('@/views/notifications/Index.vue'),
                meta: { title: '通知中心', icon: 'Bell' },
            },
            // ── 团队管理 (M2-129) ──
            {
                path: 'team',
                name: 'TeamManagement',
                component: () => import('@/views/team/Index.vue'),
                meta: { title: '团队管理', icon: 'UserFilled' },
            },
            {
                path: 'customer-audit-logs',
                name: 'CustomerAuditLogs',
                component: () => import('@/views/audit/CustomerAuditLogs.vue'),
                meta: { title: '操作审计日志', icon: 'View' },
            },
            {
                path: 'billing/history',
                name: 'BillingHistory',
                component: () => import('@/views/billing/BillingHistory.vue'),
                meta: { title: '账单历史', icon: 'Document' },
            },
            {
                path: 'usage/endpoint',
                name: 'EndpointUsageAnalytics',
                component: () => import('@/views/usage/EndpointUsageAnalytics.vue'),
                meta: { title: 'API 用量统计', icon: 'DataAnalysis' },
            },
            {
                path: 'tutorials',
                name: 'Tutorials',
                component: () => import('@/views/tutorials/Index.vue'),
                meta: { title: '入门教程', icon: 'Reading' },
            },
            {
                path: 'cloud-marketplace',
                name: 'CloudMarketplace',
                component: () => import('@/views/cloud-marketplace/Index.vue'),
                meta: { title: '☁️ 云市场集成', icon: 'Connection' },
            },
            {
                path: 'accounting',
                name: 'Accounting',
                component: () => import('@/views/accounting/Index.vue'),
                meta: { title: '📊 会计系统集成', icon: 'Coin' },
            },
            {
                path: 'bi-export',
                name: 'BiExport',
                component: () => import('@/views/bi-export/Index.vue'),
                meta: { title: '📈 BI 数据仓库导出', icon: 'DataBoard' },
            },
            {
                path: 'alerts',
                name: 'Alerts',
                component: () => import('@/views/alert/Index.vue'),
                meta: { title: '智能告警', icon: 'WarnTriangleFilled' },
            },
            {
                path: 'settings',
                name: 'SiteSettings',
                component: () => import('@/views/settings/Index.vue'),
                meta: { title: '系统设置', icon: 'Setting' },
            },
            {
                path: 'tenants',
                name: 'AdminTenants',
                component: () => import('@/views/tenants/Index.vue'),
                meta: { title: '租户管理', icon: 'OfficeBuilding' },
            },
            {
                path: 'renewal',
                name: 'RenewalDashboard',
                component: () => import('@/views/renewal/Index.vue'),
                meta: { title: '续期管理', icon: 'Refresh' },
            },
            // ── 续费提醒与优化 (M3-23) ──
            {
                path: 'renewal-reminder',
                name: 'RenewalReminder',
                component: () => import('@/views/renewal-reminder/Index.vue'),
                meta: { title: '续费提醒与优化', icon: 'Bell' },
            },
            {
                path: 'auto-renewal',
                name: 'AutoRenewal',
                component: () => import('@/views/auto-renewal/Index.vue'),
                meta: { title: '自动续费管理', icon: 'Refresh' },
            },
            {
                path: 'dunning',
                name: 'Dunning',
                component: () => import('@/views/dunning/Index.vue'),
                meta: { title: '智能催缴', icon: 'WarnTriangleFilled' },
            },
            {
                path: 'pages',
                name: 'PageManager',
                component: () => import('@/views/pages/Index.vue'),
                meta: { title: '页面管理', icon: 'Document' },
            },
            {
                path: 'playground',
                name: 'ApiPlayground',
                component: () => import('@/views/playground/Index.vue'),
                meta: { title: 'API Playground', icon: 'Monitor' },
            },
            {
                path: 'custom-fields',
                name: 'CustomFields',
                component: () => import('@/views/custom-fields/Index.vue'),
                meta: { title: '自定义字段', icon: 'Edit' },
            },
            {
                path: 'deps-security',
                name: 'DepsSecurity',
                component: () => import('@/views/deps-security/Index.vue'),
                meta: { title: '依赖安全', icon: 'Shield' },
            },
            {
                path: 'mfa',
                name: 'MfaSettings',
                component: () => import('@/views/mfa/Index.vue'),
                meta: { title: 'MFA 设置', icon: 'Lock' },
            },
            {
                path: 'cors-configs',
                name: 'CorsConfigs',
                component: () => import('@/views/cors/Index.vue'),
                meta: { title: 'CORS 配置', icon: 'Connection' },
            },
            {
                path: 'csp-configs',
                name: 'CspConfigs',
                component: () => import('@/views/csp/Index.vue'),
                meta: { title: 'CSP 安全策略', icon: 'Shield' },
            },
            {
                path: 'maintenance',
                name: 'MaintenanceMode',
                component: () => import('@/views/maintenance/Index.vue'),
                meta: { title: '维护模式', icon: 'WarnTriangleFilled' },
            },
            {
                path: 'apm',
                name: 'ApmMonitor',
                component: () => import('@/views/apm/Index.vue'),
                meta: { title: 'APM 监控', icon: 'DataAnalysis' },
            },
            {
                path: 'slow-query-monitor',
                name: 'SlowQueryMonitor',
                component: () => import('@/views/slow-query-monitor/Index.vue'),
                meta: { title: '慢查询监控', icon: 'Monitor' },
            },
            {
                path: 'db-read-write',
                name: 'DbReadWrite',
                component: () => import('@/views/db-read-write/Index.vue'),
                meta: { title: '读写分离', icon: 'Connection' },
            },
            {
                path: 'synthetic-monitor',
                name: 'SyntheticMonitor',
                component: () => import('@/views/synthetic-monitor/Index.vue'),
                meta: { title: '拨测监控', icon: 'Monitor' },
            },
            {
                path: 'alert-manager',
                name: 'AlertManager',
                component: () => import('@/views/alert-manager/Index.vue'),
                meta: { title: '告警疲劳管理', icon: 'WarningFilled' },
            },
            {
                path: 'incident-alerting',
                name: 'IncidentAlerting',
                component: () => import('@/views/incident-alerting/Index.vue'),
                meta: { title: 'PagerDuty/OpsGenie', icon: 'Bell' },
            },
            {
                path: 'auto-pentest',
                name: 'AutoPenTest',
                component: () => import('@/views/auto-pentest/Index.vue'),
                meta: { title: '自动渗透测试', icon: 'Monitor' },
            },
            {
                path: 'license-restrictions',
                name: 'LicenseRestrictions',
                component: () => import('@/views/license-restrictions/Index.vue'),
                meta: { title: 'License 访问限制', icon: 'Lock' },
            },
            {
                path: 'budget-guard',
                name: 'BudgetGuard',
                component: () => import('@/views/budget-guard/Index.vue'),
                meta: { title: '消费预警+预算上限', icon: 'Coin' },
            },
            {
                path: 'domain-whitelist',
                name: 'DomainWhitelist',
                component: () => import('@/views/domain-whitelist/Index.vue'),
                meta: { title: '域名白名单验证', icon: 'Link' },
            },
            {
                path: 'utm-tracker',
                name: 'UtmTracker',
                component: () => import('@/views/utm-tracker/Index.vue'),
                meta: { title: 'UTM/渠道归因', icon: 'TrendCharts' },
            },
            {
                path: 'nps-survey',
                name: 'NpsSurvey',
                component: () => import('@/views/nps-survey/Index.vue'),
                meta: { title: 'NPS 满意度调查', icon: 'WarningFilled' },
            },
            {
                path: 'admin-appeals',
                name: 'AdminAppeals',
                component: () => import('@/views/admin-appeals/Index.vue'),
                meta: { title: '账号申诉审核', icon: 'WarningFilled' },
            },
            {
                path: 'custom-emoji',
                name: 'CustomEmoji',
                component: () => import('@/views/custom-emoji/Index.vue'),
                meta: { title: '自定义 Emoji', icon: 'Mug' },
            },
            {
                path: 'code-sandbox',
                name: 'CodeSandbox',
                component: () => import('@/views/code-sandbox/Index.vue'),
                meta: { title: '代码沙箱', icon: 'Monitor' },
            },
            // ── Meilisearch 全文搜索 ──
            {
                path: 'meilisearch',
                name: 'Meilisearch',
                component: () => import('@/views/meilisearch/Index.vue'),
                meta: { title: 'Meilisearch 搜索', icon: 'Search' },
            },
            {
                path: 'auto-reply',
                name: 'AutoReply',
                component: () => import('@/views/auto-reply/Index.vue'),
                meta: { title: '自动回复', icon: 'ChatDotRound' },
            },
            {
                path: 'feature-adoption',
                name: 'FeatureAdoption',
                component: () => import('@/views/feature-adoption/Index.vue'),
                meta: { title: '功能使用率追踪', icon: 'TrendCharts' },
            },
            {
                path: 'scheduled-notification',
                name: 'ScheduledNotification',
                component: () => import('@/views/scheduled-notification/Index.vue'),
                meta: { title: '批量通知定时发送', icon: 'Timer' },
            },
            {
                path: 'quota-alert',
                name: 'QuotaAlert',
                component: () => import('@/views/quota-alert/Index.vue'),
                meta: { title: '用量配额预警', icon: 'WarningFilled' },
            },
            { path: 'teams-notifier', redirect: '/im-integration' },
            {
                path: 'api-impact',
                name: 'ApiImpact',
                component: () => import('@/views/api-impact/Index.vue'),
                meta: { title: 'API 变更影响', icon: 'Connection' },
            },
            {
                path: 'dev-portal',
                name: 'DevPortal',
                component: () => import('@/views/dev-portal/Index.vue'),
                meta: { title: '开发者门户', icon: 'Monitor' },
            },
            {
                path: 'postman',
                name: 'PostmanCollection',
                component: () => import('@/views/postman/Index.vue'),
                meta: { title: 'Postman Collection', icon: 'Connection' },
            },
            // ── IM 通知集成 (M2-57) ──
            {
                path: 'im-integration',
                name: 'ImIntegration',
                component: () => import('@/views/im-integration/Index.vue'),
                meta: { title: 'IM 通知集成', icon: 'ChatDotSquare' },
            },
            {
                path: 'cache-invalidation',
                name: 'CacheInvalidation',
                component: () => import('@/views/cache-invalidation/Index.vue'),
                meta: { title: 'SDK 缓存推送', icon: 'Connection' },
            },
            {
                path: 'public-key',
                name: 'PublicKeyVersion',
                component: () => import('@/views/public-key/Index.vue'),
                meta: { title: '公钥版本管理', icon: 'Key' },
            },
            {
                path: 'text-to-sql',
                name: 'TextToSql',
                component: () => import('@/views/text-to-sql/Index.vue'),
                meta: { title: 'Text-to-SQL 安全', icon: 'Monitor' },
            },
            // ── AI 运营分析 (M2-42) ──
            {
                path: 'ai-ops',
                name: 'AIOps',
                component: () => import('@/views/ai-ops/Index.vue'),
                meta: { title: 'AI 运营分析', icon: 'MagicStick' },
            },
            {
                path: 'sdk-manager',
                name: 'SdkManager',
                component: () => import('@/views/sdk-manager/Index.vue'),
                meta: { title: 'SDK 管理', icon: 'Connection' },
            },
            {
                path: 'slo',
                name: 'SloBudget',
                component: () => import('@/views/slo/Index.vue'),
                meta: { title: 'SLO 错误预算', icon: 'Coin' },
            },
            {
                path: 'sla-probes',
                name: 'SlaProbes',
                component: () => import('@/views/sla-probes/Index.vue'),
                meta: { title: 'SLA 拨测', icon: 'Monitor' },
            },
            {
                path: 'health',
                name: 'HealthCheck',
                component: () => import('@/views/system/Health.vue'),
                meta: { title: '系统健康', icon: 'Monitor' },
            },
            {
                path: 'sandbox',
                name: 'DevSandbox',
                component: () => import('@/views/sandbox/Index.vue'),
                meta: { title: '开发者沙箱', icon: 'EditPen' },
            },
            {
                path: 'staging',
                name: 'StagingEnv',
                component: () => import('@/views/staging/Index.vue'),
                meta: { title: 'Staging 环境', icon: 'Connection' },
            },
            {
                path: 'billing',
                name: 'Billing',
                component: () => import('@/views/billing/Index.vue'),
                meta: { title: '订阅计费', icon: 'Coin' },
            },
            {
                path: 'billing/metered',
                name: 'MeteredBilling',
                component: () => import('@/views/billing/MeteredBilling.vue'),
                meta: { title: '用量计费', icon: 'Histogram' },
            },
            // ── 支付集成 (M2-06) ──
            {
                path: 'payment',
                name: 'Payment',
                component: () => import('@/views/payment/Index.vue'),
                meta: { title: '支付管理', icon: 'Wallet' },
            },
            {
                path: 'payment/transactions',
                name: 'PaymentTransactions',
                component: () => import('@/views/payment/Transactions.vue'),
                meta: { title: '交易流水', icon: 'List' },
            },
            {
                path: 'payment/webhook-logs',
                name: 'PaymentWebhookLogs',
                component: () => import('@/views/payment/WebhookLogs.vue'),
                meta: { title: 'Webhook 日志', icon: 'Notification' },
            },
            // ── 支付方式管理 (M2-07b) ──
            {
                path: 'payment-methods',
                name: 'PaymentMethodsAdmin',
                component: () => import('@/views/payment-method/Index.vue'),
                meta: { title: '支付方式管理', icon: 'CreditCard' },
            },
            {
                path: 'pricing/dynamic',
                name: 'DynamicPricing',
                component: () => import('@/views/pricing/DynamicPricing.vue'),
                meta: { title: '动态定价引擎', icon: 'TrendCharts' },
            },
            {
                path: 'pricing/experiments',
                name: 'PricingExperiments',
                component: () => import('@/views/pricing-experiments/Index.vue'),
                meta: { title: '价格实验/AB定价', icon: 'DataAnalysis' },
            },
            // ── 套餐系统 (M3-06) ──
            {
                path: 'plans',
                name: 'PlanIndex',
                component: () => import('@/views/pricing/PlanIndex.vue'),
                meta: { title: '套餐系统', icon: 'Coin' },
            },
            // ── 增值服务 VAS (M3-70) ──
            {
                path: 'vas',
                name: 'VasManagement',
                component: () => import('@/views/vas/Index.vue'),
                meta: { title: '增值服务', icon: 'Shop' },
            },
            // ── 发票增强 (M3-74) ──
            {
                path: 'invoice-enhance',
                name: 'InvoiceEnhance',
                component: () => import('@/views/invoice-enhance/Index.vue'),
                meta: { title: '发票增强', icon: 'Document' },
            },
            // ── 自动开票 (M2-148 🛒) ──
            {
                path: 'auto-invoice',
                name: 'AutoInvoice',
                component: () => import('@/views/auto-invoice/Index.vue'),
                meta: { title: '自动开票', icon: 'PriceTag' },
            },
            // ── 分销联盟 (M2-149 🛒) ──
            {
                path: 'store-affiliate',
                name: 'StoreAffiliate',
                component: () => import('@/views/store-affiliate/Index.vue'),
                meta: { title: '分销联盟', icon: 'Connection' },
            },
            // ── 定时促销 (M2-151 🛒) ──
            {
                path: 'scheduled-promotion',
                name: 'ScheduledPromotion',
                component: () => import('@/views/scheduled-promotion/Index.vue'),
                meta: { title: '定时促销', icon: 'Timer' },
            },
            // ── AI 个性化 (M3-80) ──
            {
                path: 'personalization',
                name: 'Personalization',
                component: () => import('@/views/personalization/Index.vue'),
                meta: { title: 'AI 个性化', icon: 'MagicStick' },
            },
            // ── 低代码工作流设计器 (M3-82) ──
            {
                path: 'flow-designer',
                name: 'FlowDesigner',
                component: () => import('@/views/flow-designer/Index.vue'),
                meta: { title: '工作流设计器', icon: 'Connection' },
            },
            // ── 跨境支付与多币种 (M3-83) ──
            {
                path: 'cross-border',
                name: 'CrossBorder',
                component: () => import('@/views/cross-border/Index.vue'),
                meta: { title: '跨境支付', icon: 'Coin' },
            },
            // ── 促销系统 (M3-07) ──
            {
                path: 'promotions',
                name: 'Promotions',
                component: () => import('@/views/promotion/Index.vue'),
                meta: { title: '促销系统', icon: 'Present' },
            },
            {
                path: 'billing/prepaid',
                name: 'PrepaidBalance',
                component: () => import('@/views/billing/PrepaidBalance.vue'),
                meta: { title: '预付余额管理', icon: 'Money' },
            },
            // ── License 转移 (M3-08) ──
            {
                path: 'transfers',
                name: 'AdminTransfer',
                component: () => import('@/views/transfer/AdminTransfer.vue'),
                meta: { title: 'License 转移', icon: 'Connection' },
            },
            {
                path: 'billing/revenue-recognition',
                name: 'RevenueRecognition',
                component: () => import('@/views/billing/RevenueRecognition.vue'),
                meta: { title: '收入确认报告', icon: 'DataBoard' },
            },
            {
                path: 'refunds',
                name: 'Refunds',
                component: () => import('@/views/refunds/Index.vue'),
                meta: { title: '退款管理', icon: 'Money' },
            },
            {
                path: 'points',
                name: 'PointsManagement',
                component: () => import('@/views/points/Index.vue'),
                meta: { title: '积分管理', icon: 'Coin' },
            },
            {
                path: 'knowledge-base',
                name: 'KnowledgeBase',
                component: () => import('@/views/kb/Index.vue'),
                meta: { title: '帮助中心', icon: 'Reading' },
            },
            // ── 客户反馈 (M3-44) ──
            {
                path: 'feedback',
                name: 'Feedback',
                component: () => import('@/views/feedback/Index.vue'),
                meta: { title: '客户反馈', icon: 'ChatLineSquare' },
            },
            // ─── SEO 优化 (M3-49) ───
            {
                path: 'seo',
                name: 'Seo',
                component: () => import('@/views/seo/Index.vue'),
                meta: { title: 'SEO 优化', icon: 'Search' },
            },
            // ─── 多区域部署/数据中心 (M3-52/53) ───
            {
                path: 'multi-region',
                name: 'MultiRegion',
                component: () => import('@/views/multi-region/Index.vue'),
                meta: { title: '多区域部署', icon: 'Connection' },
            },
            {
                path: 'license-files',
                name: 'LicenseFileCdn',
                component: () => import('@/views/license-files/Index.vue'),
                meta: { title: 'License 文件分发', icon: 'Upload' },
            },
            {
                path: 'static-assets-cdn',
                name: 'StaticAssetCdn',
                component: () => import('@/views/static-assets-cdn/Index.vue'),
                meta: { title: '静态资源 CDN', icon: 'Connection' },
            },
            {
                path: 'deploy',
                name: 'Deploy',
                component: () => import('@/views/deploy/Index.vue'),
                meta: { title: 'DevOps 部署', icon: 'Connection' },
            },
            // ─── 气隙部署 (M3-61) ───
            {
                path: 'air-gapped',
                name: 'AirGapped',
                component: () => import('@/views/air-gapped/Index.vue'),
                meta: { title: '气隙部署', icon: 'Lock' },
            },
            // ─── Edge 授权验证 (M3-53) ───
            {
                path: 'edge-verifier',
                name: 'EdgeVerifier',
                component: () => import('@/views/edge-verifier/Index.vue'),
                meta: { title: 'Edge 授权验证', icon: 'Connection' },
            },
            // ─── Istio 服务网格 (M3-68) ───
            {
                path: 'istio',
                name: 'IstioManager',
                component: () => import('@/views/istio/Index.vue'),
                meta: { title: 'Istio 服务网格', icon: 'Connection' },
            },
            // ─── PWA 移动端 (M3-51) ───
            {
                path: 'pwa',
                name: 'Pwa',
                component: () => import('@/views/pwa/Index.vue'),
                meta: { title: 'PWA 移动端', icon: 'Monitor' },
            },
            // ─── 交互式产品演示 (M3-70) ───
            {
                path: 'demo-admin',
                name: 'DemoAdmin',
                component: () => import('@/views/demo/Admin.vue'),
                meta: { title: '产品演示管理', icon: 'Monitor' },
            },
            // ─── 混沌工程 (M3-80) ───
            {
                path: 'chaos-engineering',
                name: 'ChaosEngineering',
                component: () => import('@/views/chaos-engineering/Index.vue'),
                meta: { title: '混沌工程', icon: 'WarningFilled' },
            },
            // ─── Zapier/Make 无代码集成 (M3-43) ───
            {
                path: 'zapier',
                name: 'Zapier',
                component: () => import('@/views/zapier/Index.vue'),
                meta: { title: 'Zapier/Make 集成', icon: 'Connection' },
            },
            // ─── 蓝绿部署 (M3-63) ───
            {
                path: 'blue-green',
                name: 'BlueGreen',
                component: () => import('@/views/blue-green/Index.vue'),
                meta: { title: '蓝绿部署', icon: 'Connection' },
            },
            {
                path: 'license-templates',
                name: 'LicenseTemplates',
                component: () => import('@/views/license-templates/Index.vue'),
                meta: { title: 'License 模板', icon: 'List' },
            },
            {
                path: 'time-restriction',
                name: 'TimeRestriction',
                component: () => import('@/views/time-restriction/Index.vue'),
                meta: { title: '时段限制管理', icon: 'Timer' },
            },
            {
                path: 'llm',
                name: 'LlmProvider',
                component: () => import('@/views/llm/Index.vue'),
                meta: { title: '大模型管理', icon: 'Monitor' },
            },
            {
                path: 'usage-dashboard',
                name: 'UsageDashboard',
                component: () => import('@/views/usage-dashboard/Index.vue'),
                meta: { title: '客户用量看板', icon: 'Connection' },
            },
            {
                path: 'tax',
                name: 'TaxCalculator',
                component: () => import('@/views/tax/Index.vue'),
                meta: { title: '税务管理', icon: 'Document' },
            },
            {
                path: 'china-invoice',
                name: 'ChinaInvoice',
                component: () => import('@/views/china-invoice/Index.vue'),
                meta: { title: '🧾 中国电子发票', icon: 'Document' },
            },
            // ── 全球税收合规 (M3-18) ──
            {
                path: 'tax-compliance',
                name: 'TaxCompliance',
                component: () => import('@/views/tax-compliance/Index.vue'),
                meta: { title: '税收合规', icon: 'List' },
            },
            // ── 多渠道营销自动化 (M3-20) ──
            {
                path: 'marketing-campaign',
                name: 'MarketingCampaign',
                component: () => import('@/views/marketing-campaign/Index.vue'),
                meta: { title: '营销自动化', icon: 'Promotion' },
            },
            // ── 智能合同管理 (M3-21) ──
            {
                path: 'enterprise-contracts',
                name: 'EnterpriseContract',
                component: () => import('@/views/enterprise-contract/Index.vue'),
                meta: { title: '合同管理', icon: 'Document' },
            },
            {
                path: 'global-resources',
                name: 'GlobalResources',
                component: () => import('@/views/global-resources/Index.vue'),
                meta: { title: '全局资源白名单', icon: 'Key' },
            },
            {
                path: 'domains',
                name: 'CustomDomains',
                component: () => import('@/views/domains/Index.vue'),
                meta: { title: '自定义域名', icon: 'Link' },
            },
            {
                path: 'domain-overview',
                name: 'DomainOverview',
                component: () => import('@/views/domain-overview/Index.vue'),
                meta: { title: '域名管理总览', icon: 'Monitor' },
            },
            {
                path: 'tickets',
                name: 'Tickets',
                component: () => import('@/views/tickets/Index.vue'),
                meta: { title: '工单管理', icon: 'Tickets' },
            },
            {
                path: 'tickets/:id',
                name: 'TicketDetail',
                component: () => import('@/views/tickets/Detail.vue'),
                meta: { title: '工单详情', hidden: true },
            },
            { path: 'handoff', redirect: '/im' },
            {
                path: 'status-page',
                name: 'StatusPageAdmin',
                component: () => import('@/views/status/Admin.vue'),
                meta: { title: '状态页管理', icon: 'Monitor' },
            },
            {
                path: 'workflows',
                name: 'WorkflowEngine',
                component: () => import('@/views/workflows/Index.vue'),
                meta: { title: '工作流引擎', icon: 'Timer' },
            },
            // ── 账号安全 ──
            {
                path: 'sessions',
                name: 'Sessions',
                component: () => import('@/views/sessions/Index.vue'),
                meta: { title: '活跃会话', icon: 'Monitor' },
            },
            // ── 信任设备管理 ──
            {
                path: 'device-trust',
                name: 'DeviceTrust',
                component: () => import('@/views/device-trust/Index.vue'),
                meta: { title: '信任设备', icon: 'Monitor' },
            },
            // ── 设备地理位置记录 (M2-26) ──
            {
                path: 'geo-location',
                name: 'GeoLocation',
                component: () => import('@/views/geo-location/Index.vue'),
                meta: { title: '地理位置记录', icon: 'Monitor' },
            },
            // ── 密码策略 ──
            {
                path: 'password-policy',
                name: 'PasswordPolicy',
                component: () => import('@/views/password-policy/Index.vue'),
                meta: { title: '密码策略', icon: 'Lock' },
            },
            // ── 邀请码管理 ──
            {
                path: 'invite-codes',
                name: 'InviteCodes',
                component: () => import('@/views/invite-codes/Index.vue'),
                meta: { title: '邀请码管理', icon: 'Key' },
            },
            // ── 门户品牌化 ──
            {
                path: 'portal-branding',
                name: 'PortalBranding',
                component: () => import('@/views/portal-branding/Index.vue'),
                meta: { title: '门户品牌化', icon: 'Brush' },
            },
            // ── 账号注销审核 ──
            {
                path: 'account-deletions',
                name: 'AccountDeletions',
                component: () => import('@/views/account-deletion/Index.vue'),
                meta: { title: '注销审核', icon: 'Delete' },
            },
            {
                path: 'account/profile',
                name: 'AccountProfile',
                component: () => import('@/views/account/Profile.vue'),
                meta: { title: '个人资料', icon: 'User' },
            },
            {
                path: 'account/binding',
                name: 'AccountBinding',
                component: () => import('@/views/account/Binding.vue'),
                meta: { title: '账号绑定', icon: 'Link' },
            },
            {
                path: 'tags',
                name: 'Tags',
                component: () => import('@/views/tags/Index.vue'),
                meta: { title: '标签管理', icon: 'PriceTag' },
            },
            {
                path: 'account/login-history',
                name: 'LoginHistory',
                component: () => import('@/views/account/LoginHistory.vue'),
                meta: { title: '登录历史', icon: 'Time' },
            },
            {
                path: 'account/passkey',
                name: 'PasskeyManagement',
                component: () => import('@/views/account/PasskeyManagement.vue'),
                meta: { title: 'Passkey 管理', icon: 'Key' },
            },
            {
                path: 'invite-codes',
                name: 'InviteCodes',
                component: () => import('@/views/account/InviteCodes.vue'),
                meta: { title: '邀请码管理', icon: 'Key' },
            },
            // ── 功能开关 ──
            {
                path: 'feature-flags',
                name: 'FeatureFlags',
                component: () => import('@/views/feature-flags/Index.vue'),
                meta: { title: '功能开关', icon: 'Switch' },
            },
            // ── 系统公告 ──
            {
                path: 'announce-banners',
                name: 'AnnounceBanners',
                component: () => import('@/views/announce-banners/Index.vue'),
                meta: { title: '系统公告', icon: 'Bell' },
            },
            // ── Cookie Consent ──
            {
                path: 'cookie-consent',
                name: 'CookieConsent',
                component: () => import('@/views/cookie-consent/Index.vue'),
                meta: { title: 'Cookie 管理', icon: 'SetUp' },
            },
            // ── 预约Demo/联系销售 (M2-98) ──
            {
                path: 'demo-booking',
                name: 'DemoBooking',
                component: () => import('@/views/demo-booking/Index.vue'),
                meta: { title: 'Demo 预约', icon: 'Calendar' },
            },
            // ── 客户案例/Logo墙 (M2-99) ──
            {
                path: 'case-studies',
                name: 'CaseStudies',
                component: () => import('@/views/case-studies/Index.vue'),
                meta: { title: '客户案例', icon: 'Star' },
            },
            // ── 竞品对比页 (M2-100) ──
            {
                path: 'compare-page',
                name: 'ComparePage',
                component: () => import('@/views/compare-page/Index.vue'),
                meta: { title: '竞品对比', icon: 'Histogram' },
            },
            // ── 限流规则 (M2-92) ──
            {
                path: 'rate-limits',
                name: 'RateLimits',
                component: () => import('@/views/rate-limits/Index.vue'),
                meta: { title: '限流规则', icon: 'Monitor' },
            },
            // ── 佣金结算 (M2-127) ──
            {
                path: 'commission',
                name: 'Commission',
                component: () => import('@/views/commission/Index.vue'),
                meta: { title: '佣金结算', icon: 'Money' },
            },
            // ── 开发者收益 ──
            {
                path: 'developer-earnings',
                name: 'DeveloperEarnings',
                component: () => import('@/views/developer-earnings/Index.vue'),
                meta: { title: '开发者收益', icon: 'Money' },
            },
            // ── 财务结算系统 (P3) ──
            {
                path: 'settlement',
                name: 'Settlement',
                component: () => import('@/views/settlement/Index.vue'),
                meta: { title: '财务结算', icon: 'DataBoard' },
            },
            // ── 市场推送管理 ──
            {
                path: 'marketplace-push',
                name: 'MarketplacePush',
                component: () => import('@/views/marketplace-push/Index.vue'),
                meta: { title: '市场推送', icon: 'Bell' },
            },
            // ── 内容安全审核 ──
            {
                path: 'marketplace-security',
                name: 'MarketplaceSecurity',
                component: () => import('@/views/marketplace-security/Index.vue'),
                meta: { title: '内容安全', icon: 'WarningFilled' },
            },
            // ── 社区管理 ──
            {
                path: 'moments',
                name: 'AdminMoments',
                component: () => import('@/views/community/AdminMoments.vue'),
                meta: { title: '社区管理', icon: 'ChatDotSquare' },
            },
            // ── 互物号管理 ──
            {
                path: 'official-accounts',
                name: 'AdminOfficialAccounts',
                component: () => import('@/views/channels/AdminOfficialAccounts.vue'),
                meta: { title: '互物号管理', icon: 'Monitor' },
            },
            {
                path: 'articles/manage',
                name: 'AdminArticles',
                component: () => import('@/views/channels/AdminArticles.vue'),
                meta: { title: '文章审核', icon: 'Document' },
            },
            // ── 灰度发布 ──
            {
                path: 'marketplace-rollout',
                name: 'MarketplaceRollout',
                component: () => import('@/views/marketplace-rollout/Index.vue'),
                meta: { title: '灰度发布', icon: 'Switch' },
            },
            // ── 提现管理 (M3-72) ──
            {
                path: 'withdrawals',
                name: 'Withdrawals',
                component: () => import('@/views/withdrawal/Index.vue'),
                meta: { title: '提现管理', icon: 'Wallet' },
            },
            // ── 佣金风控 (M2-127b) ──
            {
                path: 'commission-risk',
                name: 'CommissionRisk',
                component: () => import('@/views/commission/RiskDashboard.vue'),
                meta: { title: '佣金风控', icon: 'WarningFilled' },
            },
            // ── 收益通知 (M2-128) ──
            {
                path: 'earning-notifications',
                name: 'EarningNotifications',
                component: () => import('@/views/commission/EarningNotifications.vue'),
                meta: { title: '收益通知', icon: 'Bell' },
            },
            // ── 渠道合作伙伴 ──
            {
                path: 'channel',
                name: 'ChannelPartners',
                component: () => import('@/views/channel/Index.vue'),
                meta: { title: '渠道合作伙伴', icon: 'Link' },
            },
            // ── 等级晋升管理 ──
            {
                path: 'agent-tiers',
                name: 'AgentTiers',
                component: () => import('@/views/commission/AgentTiers.vue'),
                meta: { title: '等级晋升管理', icon: 'Medal' },
            },
            // ── 代理商/经销商管理 (M3-04) ──
            {
                path: 'agent-manager',
                name: 'AgentManager',
                component: () => import('@/views/agent-manager/Index.vue'),
                meta: { title: '代理商管理', icon: 'UserFilled' },
            },
            // ── 联盟推广 M3-05 ──
            {
                path: 'affiliate',
                name: 'AffiliateCampaigns',
                component: () => import('@/views/commission/AffiliateCampaigns.vue'),
                meta: { title: '联盟推广', icon: 'Connection' },
            },
            // ── 联盟推广增强 M3-05 ──
            {
                path: 'affiliate-enhanced',
                name: 'AffiliateEnhanced',
                component: () => import('@/views/affiliate-enhanced/Index.vue'),
                meta: { title: '联盟推广增强', icon: 'Promotion' },
            },
            // ── AI 风控 & 行为风控 (M3-01, M3-02) ──
            {
                path: 'fraud-risk',
                name: 'FraudRisk',
                component: () => import('@/views/fraud-risk/Index.vue'),
                meta: { title: 'AI 风控中心', icon: 'Monitor' },
            },
            // ── OEM 白标系统 (M3-03) ──
            {
                path: 'oem',
                name: 'Oem',
                component: () => import('@/views/oem/Index.vue'),
                meta: { title: 'OEM 白标', icon: 'Tools' },
            },
            // ── 创新授权管理 (M3-14~17) ──
            {
                path: 'innovation-auth',
                name: 'InnovationAuth',
                component: () => import('@/views/innovation/Index.vue'),
                meta: { title: '创新授权', icon: 'TrendCharts' },
            },
            // ── AI 盗版溯源 (M3-34) ──
            {
                path: 'piracy-trace',
                name: 'PiracyTrace',
                component: () => import('@/views/piracy-trace/Index.vue'),
                meta: { title: 'AI盗版溯源', icon: 'WarningFilled' },
            },
            // ── AI 交叉销售推荐 (M3-35) ──
            {
                path: 'cross-sell',
                name: 'CrossSell',
                component: () => import('@/views/cross-sell/Index.vue'),
                meta: { title: '交叉销售', icon: 'TrendCharts' },
            },
            // ── AI 客户行为聚类 (M3-37) ──
            {
                path: 'customer-clustering',
                name: 'CustomerClustering',
                component: () => import('@/views/customer-clustering/Index.vue'),
                meta: { title: '客户行为聚类', icon: 'DataBoard' },
            },
            // ── 云文件存储 (M3-48) ──
            {
                path: 'cloud-upload',
                name: 'CloudUpload',
                component: () => import('@/views/cloud-upload/Index.vue'),
                meta: { title: '云文件存储', icon: 'CloudUpload' },
            },
            // ── 虚拟环境检测 (M1.3-14) ──
            {
                path: 'vm-detection',
                name: 'VmDetection',
                component: () => import('@/views/vm-detection/Index.vue'),
                meta: { title: '虚拟环境检测', icon: 'Monitor' },
            },
            // ── Redis 高可用 (M1.3-17) ──
            {
                path: 'redis-ha',
                name: 'RedisHa',
                component: () => import('@/views/redis-ha/Index.vue'),
                meta: { title: 'Redis 高可用', icon: 'DataAnalysis' },
            },
            // ── WAF 基础防护 (M1.3-18) ──
            {
                path: 'waf',
                name: 'Waf',
                component: () => import('@/views/waf/Index.vue'),
                meta: { title: 'WAF 基础防护', icon: 'WarningFilled' },
            },
            // ── API 网关统一层 (M1.3-20) ──
            {
                path: 'api-gateway',
                name: 'ApiGateway',
                component: () => import('@/views/api-gateway/Index.vue'),
                meta: { title: 'API 网关统一层', icon: 'Connection' },
            },
            // ── gRPC 服务间通信 (M1.3-28) ──
            {
                path: 'grpc',
                name: 'Grpc',
                component: () => import('@/views/grpc/Index.vue'),
                meta: { title: 'gRPC 服务通信', icon: 'Connection' },
            },
            // ── 数据留存策略 (M1.1-14) ──
            {
                path: 'data-retention',
                name: 'DataRetention',
                component: () => import('@/views/data-retention/Index.vue'),
                meta: { title: '数据留存策略', icon: 'Timer' },
            },
            // ── CRM 集成 (M3-42) ──
            {
                path: 'crm-integration',
                name: 'CrmIntegration',
                component: () => import('@/views/crm-integration/Index.vue'),
                meta: { title: 'CRM 集成', icon: 'Connection' },
            },
            // ── 竞品迁移工具 (M3-71) ──
            {
                path: 'migration-enhancement',
                name: 'MigrationEnhancement',
                component: () => import('@/views/migration-enhancement/Index.vue'),
                meta: { title: '竞品迁移', icon: 'Upload' },
            },
            // ── AI 合规报告生成 (M3-38) ──
            {
                path: 'compliance-ai',
                name: 'ComplianceAi',
                component: () => import('@/views/compliance-ai/Index.vue'),
                meta: { title: '合规报告AI', icon: 'Document' },
            },
            // ── TPM 硬件安全绑定 (M2-116) ──
            {
                path: 'tpm-binding',
                name: 'TpmBinding',
                component: () => import('@/views/tpm-binding/Index.vue'),
                meta: { title: 'TPM 硬件绑定', icon: 'Key' },
            },
            // ── 合规中心(已整合) ──
            {
                path: 'compliance-center',
                name: 'ComplianceCenter',
                component: () => import('@/views/compliance-center/Index.vue'),
                meta: { title: '合规中心', icon: 'DocumentChecked' },
            },
            {
                path: 'data-management',
                name: 'DataManagement',
                component: () => import('@/views/data-management/Index.vue'),
                meta: { title: '数据管理', icon: 'Connection' },
            },
            // ── AI 攻击模式识别 (M3-36) ──
            {
                path: 'attack-detection',
                name: 'AttackDetection',
                component: () => import('@/views/attack-detection/Index.vue'),
                meta: { title: '攻击模式识别', icon: 'WarningFilled' },
            },
            // ── 主动蜜罐防御 (M2-03) ──
            {
                path: 'honeypot',
                name: 'Honeypot',
                component: () => import('@/views/honeypot/Index.vue'),
                meta: { title: '主动蜜罐防御', icon: 'WarningFilled' },
            },
            // ── AI 迁移助手 (M3-39) ──
            {
                path: 'migration-assistant',
                name: 'MigrationAssistant',
                component: () => import('@/views/migration-assistant/Index.vue'),
                meta: { title: 'AI迁移助手', icon: 'MagicStick' },
            },
            // ── 自动备份 (M2-24) ──
            {
                path: 'backups',
                name: 'Backups',
                component: () => import('@/views/backups/Index.vue'),
                meta: { title: '自动备份', icon: 'Timer' },
            },
            // ── 断路器监控 ──
            {
                path: 'circuit-breaker',
                name: 'CircuitBreaker',
                component: () => import('@/views/circuit-breaker/Index.vue'),
                meta: { title: '断路器监控', icon: 'Monitor' },
            },
            // ── 模拟登录 ──
            {
                path: 'impersonate',
                name: 'Impersonate',
                component: () => import('@/views/impersonate/Index.vue'),
                meta: { title: '模拟登录', icon: 'Key' },
            },
            // ── 密钥管理 (M2-78) ──
            {
                path: 'secrets',
                name: 'SecretManager',
                component: () => import('@/views/secret-manager/Index.vue'),
                meta: { title: '密钥管理', icon: 'Lock' },
            },
            // ── HSM 硬件安全模块 (M3-79) ──
            {
                path: 'hsm',
                name: 'HsmManagement',
                component: () => import('@/views/hsm/Index.vue'),
                meta: { title: 'HSM 签名', icon: 'Key' },
            },
            // ── 用量计量系统 (M2-10) ──
            {
                path: 'usage-meter',
                name: 'UsageMeter',
                component: () => import('@/views/usage-meter/Index.vue'),
                meta: { title: '用量计量', icon: 'DataBoard' },
            },
            {
                path: 'metered-billing',
                name: 'MeteredBilling',
                component: () => import('@/views/metered-billing/Index.vue'),
                meta: { title: '按量计费深度', icon: 'TrendCharts' },
            },
            // ── 高级报表 (M3) ──
            {
                path: 'reports',
                name: 'Reports',
                component: () => import('@/views/reports/Index.vue'),
                meta: { title: '高级报表', icon: 'TrendCharts' },
            },
            // ── 平台收益总览 M3-73 ──
            {
                path: 'revenue',
                name: 'RevenueDashboard',
                component: () => import('@/views/revenue/Index.vue'),
                meta: { title: '平台收益总览', icon: 'DataBoard' },
            },
            {
                path: 'mrr-waterfall',
                name: 'MrrWaterfall',
                component: () => import('@/views/mrr-waterfall/Index.vue'),
                meta: { title: 'MRR 瀑布图', icon: 'TrendCharts' },
            },
            // ── 业务指标看板 M2-121 ──
            {
                path: 'business-metrics',
                name: 'BusinessMetrics',
                component: () => import('@/views/business-metrics/Index.vue'),
                meta: { title: '业务指标看板', icon: 'DataBoard' },
            },
            // ── 多币种定价 / 汇率管理 (M2-30) ──
            {
                path: 'currency',
                name: 'Currency',
                component: () => import('@/views/currency/Index.vue'),
                meta: { title: '多币种定价', icon: 'Coin' },
            },
            // ── 客户健康度评分 (M2-29) ──
            {
                path: 'health-score',
                name: 'HealthScore',
                component: () => import('@/views/health-score/Index.vue'),
                meta: { title: '客户健康度', icon: 'Monitor' },
            },
            // ── License 健康评分 (M2-110) ──
            {
                path: 'license-health',
                name: 'LicenseHealth',
                component: () => import('@/views/license-health/Index.vue'),
                meta: { title: 'License 健康评分', icon: 'CircleCheck' },
            },
            // ── CSM 客户成功仪表盘 (M3-78) ──
            {
                path: 'csm',
                name: 'CsmDashboard',
                component: () => import('@/views/csm/Index.vue'),
                meta: { title: '客户成功仪表盘', icon: 'DataAnalysis' },
            },
            // ── 客户流失预测与干预 (M3-25) ──
            {
                path: 'churn-prediction',
                name: 'ChurnPrediction',
                component: () => import('@/views/churn-prediction/Index.vue'),
                meta: { title: '流失预测与干预', icon: 'WarningFilled' },
            },
            // ── 客户生命周期管理 (M3-19) ──
            {
                path: 'lifecycle',
                name: 'Lifecycle',
                component: () => import('@/views/lifecycle/Index.vue'),
                meta: { title: '客户生命周期', icon: 'Histogram' },
            },
            // ── CRM 客户关系管理 ──
            {
                path: 'crm',
                name: 'CrmDashboard',
                component: () => import('@/views/crm/Index.vue'),
                meta: { title: 'CRM 客户分析', icon: 'DataBoard' },
            },
            // ── 批量操作工具 (M2-08) ──
            {
                path: 'batch',
                name: 'Batch',
                component: () => import('@/views/batch/Index.vue'),
                meta: { title: '批量操作', icon: 'List' },
            },
            // ─── 订单管理 ───
            {
                path: 'orders',
                name: 'Orders',
                component: () => import('@/views/orders/Index.vue'),
                meta: { title: '订单管理', icon: 'List' },
            },
            // ─── SKU 管理 ───
            {
                path: 'skus',
                name: 'Skus',
                component: () => import('@/views/skus/Index.vue'),
                meta: { title: 'SKU 管理', icon: 'Goods' },
            },
            // ── 客户分级 SLA (M2-31) ──
            {
                path: 'sla',
                name: 'SlaTier',
                component: () => import('@/views/sla/Index.vue'),
                meta: { title: 'SLA 等级', icon: 'Odometer' },
            },
            // ── 计费管理 ──
            {
                path: 'billing/retention',
                name: 'BillingRetention',
                component: () => import('@/views/billing/Retention.vue'),
                meta: { title: '续费失败流水线', icon: 'Connection' },
            },
            // ── AI 诊断 ──
            {
                path: 'diagnostic',
                name: 'Diagnostic',
                component: () => import('@/views/diagnostic/Index.vue'),
                meta: { title: 'AI 错误诊断', icon: 'MagicStick' },
            },
            // ── RAG 知识库索引管理 ──
            {
                path: 'rag',
                name: 'RagAdmin',
                component: () => import('@/views/rag/Index.vue'),
                meta: { title: 'RAG 知识库管理', icon: 'Reading' },
            },
            // ── AI 运营中心（知识库自增长/深度研究/幻觉检测等）──
            {
                path: 'ai-operations',
                name: 'AiOperations',
                component: () => import('@/views/ai-operations/Index.vue'),
                meta: { title: 'AI 运营中心', icon: 'SetUp' },
            },
            // ── 客户用量看板 (M2-97) ──
            {
                path: 'usage-dashboard',
                name: 'UsageDashboard',
                component: () => import('@/views/usage-dashboard/Index.vue'),
                meta: { title: '客户用量看板', icon: 'DataBoard' },
            },
            // ── AI MLOps 平台 (M3-40) ──
            {
                path: 'mlops',
                name: 'Mlops',
                component: () => import('@/views/mlops/Index.vue'),
                meta: { title: 'AI MLOps 平台', icon: 'Cpu' },
            },
            // ── AI 特征工程平台 (M3-41) ──
            {
                path: 'feature-store',
                name: 'FeatureStore',
                component: () => import('@/views/feature-store/Index.vue'),
                meta: { title: 'AI 特征工程', icon: 'DataBoard' },
            },
            // ── SSO ──
            {
                path: 'sso',
                name: 'SsoSettings',
                component: () => import('@/views/sso/Index.vue'),
                meta: { title: '单点登录', icon: 'Link' },
            },
            {
                path: 'enterprise-sso',
                name: 'EnterpriseSso',
                component: () => import('@/views/enterprise-sso/Index.vue'),
                meta: { title: '🔐 企业 SSO 深度', icon: 'Link' },
            },
            // ── 嵌入式 Widget (M2-141) ──
            {
                path: 'embedded-widget',
                name: 'EmbeddedWidget',
                component: () => import('@/views/embedded-widget/Index.vue'),
                meta: { title: '嵌入式 Widget', icon: 'Monitor' },
            },
            // ── OAuth 登录配置 ──
            {
                path: 'oauth',
                name: 'OAuthSettings',
                component: () => import('@/views/oauth/Index.vue'),
                meta: { title: 'OAuth 登录', icon: 'Link' },
            },
            // ── 飞书集成 (M3-38) ──
            {
                path: 'lark',
                name: 'LarkIntegration',
                component: () => import('@/views/lark/Index.vue'),
                meta: { title: '飞书集成', icon: 'ChatDotSquare' },
            },
            // ── OpenFeature ──
            {
                path: 'openfeature',
                name: 'OpenFeature',
                component: () => import('@/views/openfeature/Index.vue'),
                meta: { title: 'OpenFeature 标志', icon: 'Switch' },
            },
            // ── IM 即时通讯中心 ──
            {
                path: 'im',
                name: 'ImCenter',
                component: () => import('@/views/im/Index.vue'),
                meta: { title: 'IM 即时通讯中心', icon: 'ChatDotSquare' },
            },
            // ── IM 管理后台 ──
            {
                path: 'im-admin',
                name: 'ImAdmin',
                component: () => import('@/views/im-admin/Index.vue'),
                meta: { title: 'IM 管理后台', icon: 'DataBoard' },
            },
            // ── AI 客服（已合并到 IM 中心） ──
            { path: 'ai-chat', redirect: '/im' },
            // 🆕 即时通讯
            {
                path: 'user-chat',
                name: 'UserChat',
                component: () => import('@/views/user-chat/Index.vue'),
                meta: { title: '即时通讯', icon: 'ChatRound' },
            },
            // ── Bot 机器人管理 ──
            {
                path: 'bot-manager',
                name: 'BotManager',
                component: () => import('@/views/bot-manager/Index.vue'),
                meta: { title: 'Bot 机器人管理', icon: 'Cpu' },
            },
            // ── API 密钥 ──
            {
                path: 'api-keys',
                name: 'ApiKeys',
                component: () => import('@/views/api-keys/Index.vue'),
                meta: { title: 'API 密钥管理', icon: 'Key' },
            },
            // ── 更新包管理 ──
            {
                path: 'update-manager',
                name: 'UpdateManager',
                component: () => import('@/views/update-manager/Index.vue'),
                meta: { title: '更新管理', icon: 'Upload' },
            },
            // ── 审计治理中心 ──
            {
                path: 'audit',
                name: 'AuditCenter',
                component: () => import('@/views/audit/Index.vue'),
                meta: { title: '审计中心', icon: 'DataBoard' },
            },
            // ── 审计日志归档 (M2-73) ──
            {
                path: 'log-archiver',
                name: 'LogArchiver',
                component: () => import('@/views/log-archiver/Index.vue'),
                meta: { title: '日志归档存储', icon: 'Coin' },
            },
            // ── 集中式日志平台 (M2-117) ──
            {
                path: 'log-aggregation',
                name: 'LogAggregation',
                component: () => import('@/views/log-aggregation/Index.vue'),
                meta: { title: '日志平台', icon: 'DataBoard' },
            },
            // ── 暗水印与防篡改 (M3-10) ──
            {
                path: 'watermark-tamper',
                name: 'WatermarkTamper',
                component: () => import('@/views/watermark/Index.vue'),
                meta: { title: '暗水印与防篡改', icon: 'Lock' },
            },
            // ── 团队协作中心 ──
            {
                path: 'collaboration',
                name: 'Collaboration',
                component: () => import('@/views/collaboration/Index.vue'),
                meta: { title: '团队协作中心', icon: 'ChatDotSquare' },
            },
            // ── 搜索中心 ──
            {
                path: 'search-center',
                name: 'SearchCenter',
                component: () => import('@/views/search-center/Index.vue'),
                meta: { title: '搜索中心', icon: 'Search' },
            },
            // ── 系统健康监控 ──
            {
                path: 'system-health',
                name: 'SystemHealth',
                component: () => import('@/views/system-health/Index.vue'),
                meta: { title: '系统健康监控', icon: 'Monitor' },
            },
            // ── 国际化管理 ──
            {
                path: 'i18n',
                name: 'I18n',
                component: () => import('@/views/i18n/Index.vue'),
                meta: { title: '国际化管理', icon: 'ChatRound' },
            },
            // ── 报表生成器 ──
            {
                path: 'report-builder',
                name: 'ReportBuilder',
                component: () => import('@/views/report-builder/Index.vue'),
                meta: { title: '报表生成器', icon: 'Histogram' },
            },
            // ── 报表调度器 ──
            {
                path: 'report-scheduler',
                name: 'ReportScheduler',
                component: () => import('@/views/report-scheduler/Index.vue'),
                meta: { title: '报表调度器', icon: 'Timer' },
            },
            // ── API 文档门户 ──
            {
                path: 'api-docs',
                name: 'ApiDocs',
                component: () => import('@/views/api-docs/Index.vue'),
                meta: { title: 'API 文档门户', icon: 'Document' },
            },
            {
                path: 'api-docs-public',
                name: 'ApiDocsPublic',
                component: () => import('@/views/api-docs/PublicView.vue'),
                meta: { title: 'API 文档浏览', icon: 'Reading' },
            },
            // ── 自定义仪表盘 ──
            {
                path: 'custom-dashboard',
                name: 'CustomDashboard',
                component: () => import('@/views/custom-dashboard/Index.vue'),
                meta: { title: '自定义仪表盘', icon: 'Monitor' },
            },
            // ── 批量数据导入 ──
            {
                path: 'data-import',
                name: 'DataImport',
                component: () => import('@/views/data-import/Index.vue'),
                meta: { title: '批量数据导入', icon: 'Upload' },
            },
            // ── 安全中心 ──
            {
                path: 'security',
                name: 'SecurityCenter',
                component: () => import('@/views/security/Index.vue'),
                meta: { title: '安全中心', icon: 'Lock' },
            },
            {
                path: 'security-headers',
                name: 'SecurityHeaders',
                component: () => import('@/views/security-headers/Index.vue'),
                meta: { title: '安全响应头', icon: 'Lock' },
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
                meta: { title: 'IDS/IPS 入侵检测', icon: 'Monitor' },
            },
            // ── SLA 追踪 ──
            {
                path: 'sla',
                name: 'SlaTracking',
                component: () => import('@/views/sla/Index.vue'),
                meta: { title: 'SLA 追踪', icon: 'DataBoard' },
            },
            // ── 智能告警 ──
            {
                path: 'alerting',
                name: 'Alerting',
                component: () => import('@/views/alerting/Index.vue'),
                meta: { title: '智能告警中心', icon: 'WarningFilled' },
            },
            // ── 租户隔离 ──
            {
                path: 'tenant-isolation',
                name: 'TenantIsolation',
                component: () => import('@/views/tenant-isolation/Index.vue'),
                meta: { title: '租户隔离管理', icon: 'Lock' },
            },
            // ── 自动化规则引擎 ──
            {
                path: 'automation',
                name: 'Automation',
                component: () => import('@/views/automation/Index.vue'),
                meta: { title: '自动化规则引擎', icon: 'Setting' },
            },
            // ── Merkle 审计链验证 ──
            {
                path: 'merkle-chain',
                name: 'MerkleChain',
                component: () => import('@/views/merkle-chain/Index.vue'),
                meta: { title: 'Merkle 验证链', icon: 'Connection' },
            },
            // ── 试用管理 ──
            {
                path: 'trials',
                name: 'Trials',
                component: () => import('@/views/trials/Index.vue'),
                meta: { title: '试用管理', icon: 'Timer' },
            },
            // ── 离线 License ──
            {
                path: 'offline',
                name: 'OfflineLicense',
                component: () => import('@/views/offline/Index.vue'),
                meta: { title: '离线 License', icon: 'Connection' },
            },
            // ── 无障碍帮助 M3-54 ──
            {
                path: 'a11y',
                name: 'A11yHelp',
                component: () => import('@/views/a11y/Index.vue'),
                meta: { title: '无障碍帮助', icon: 'Reading' },
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
                meta: { title: '自定义字段', icon: 'List' },
            },
            // ── 数据导出管理 M3-30 ──
            {
                path: 'data-exports',
                name: 'DataExport',
                component: () => import('@/views/system/DataExport.vue'),
                meta: { title: '数据导出管理', icon: 'Download' },
            },
            // ── 保存搜索管理 (M2-54) ──
            {
                path: 'saved-search',
                name: 'SavedSearch',
                component: () => import('@/views/saved-search/Index.vue'),
                meta: { title: '保存搜索管理', icon: 'Search' },
            },
            // ── 通知偏好管理 M3-29 ──
            {
                path: 'notification-preferences',
                name: 'AdminNotificationPreferences',
                component: () => import('@/views/system/NotificationPreferences.vue'),
                meta: { title: '通知偏好管理', icon: 'Bell' },
            },
            // ── API 版本管理 (M2-33) ──
            {
                path: 'api-versions',
                name: 'ApiVersions',
                component: () => import('@/views/api-versions/Index.vue'),
                meta: { title: 'API 版本管理', icon: 'Connection' },
            },
            // ── SDK Telemetry 心跳上报 (M2-32) ──
            {
                path: 'telemetry',
                name: 'Telemetry',
                component: () => import('@/views/telemetry/Index.vue'),
                meta: { title: 'SDK Telemetry', icon: 'DataAnalysis' },
            },
            // ── SDK 错误码参考手册 (M2-34) ──
            {
                path: 'error-codes',
                name: 'ErrorCodes',
                component: () => import('@/views/error-codes/Index.vue'),
                meta: { title: '错误码参考', icon: 'WarningFilled' },
            },
            // ── SSL 证书管理 ──
            {
                path: 'ssl-certificates',
                name: 'SslCertificates',
                component: () => import('@/views/ssl-certificates/Index.vue'),
                meta: { title: 'SSL 证书', icon: 'Lock' },
            },
            // ── 订阅详情 ──
            {
                path: 'billing/:id',
                name: 'BillingDetail',
                component: () => import('@/views/billing/Detail.vue'),
                meta: { title: '订阅详情', hidden: true },
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
                meta: { title: '客户门户' },
            },
            // ── 商品商店 (M1.4-61) ──
            {
                path: 'shop',
                name: 'Shop',
                component: () => import('@/views/shop/Index.vue'),
                meta: { title: '商品商店' },
            },
            {
                path: 'cart',
                name: 'ShopCart',
                component: () => import('@/views/shop/Cart.vue'),
                meta: { title: '购物车' },
            },
            {
                path: 'checkout',
                name: 'ShopCheckout',
                component: () => import('@/views/shop/Checkout.vue'),
                meta: { title: '订单结算' },
            },
            {
                path: 'payment-result/:id',
                name: 'PaymentResult',
                component: () => import('@/views/shop/PaymentResult.vue'),
                meta: { title: '支付结果' },
            },
            // ── 客户订单历史 (M1.4-66) ──
            {
                path: 'orders',
                name: 'PortalOrders',
                component: () => import('@/views/portal/Orders.vue'),
                meta: { title: '我的订单' },
            },
            {
                path: 'licenses',
                name: 'PortalLicenses',
                component: () => import('@/views/portal/Licenses.vue'),
                meta: { title: '我的 License' },
            },
            {
                path: 'licenses/:id',
                name: 'PortalLicenseDetail',
                component: () => import('@/views/portal/LicenseDetail.vue'),
                meta: { title: 'License 详情' },
            },
            {
                path: 'devices',
                name: 'PortalDevices',
                component: () => import('@/views/portal/Devices.vue'),
                meta: { title: '我的设备' },
            },
            {
                path: 'billing',
                name: 'PortalBilling',
                component: () => import('@/views/portal/Billing.vue'),
                meta: { title: '账单与发票' },
            },
            {
                path: 'invoices',
                name: 'PortalInvoices',
                component: () => import('@/views/portal/Invoices.vue'),
                meta: { title: '自助发票' },
            },
            {
                path: 'payment-methods',
                name: 'PortalPaymentMethods',
                component: () => import('@/views/portal/PaymentMethods.vue'),
                meta: { title: '支付方式' },
            },
            {
                path: 'usage',
                name: 'PortalUsage',
                component: () => import('@/views/portal/Usage.vue'),
                meta: { title: '用量看板' },
            },
            {
                path: 'analytics',
                name: 'PortalAnalytics',
                component: () => import('@/views/portal/AnalyticsDashboard.vue'),
                meta: { title: '分析仪表盘' },
            },
            // ── License 健康评分 (M2-110) ──
            {
                path: 'license-health',
                name: 'PortalLicenseHealth',
                component: () => import('@/views/portal/LicenseHealth.vue'),
                meta: { title: 'License 健康评分' },
            },
            {
                path: 'notification-preferences',
                name: 'PortalNotificationPreferences',
                component: () => import('@/views/portal/NotificationPreferences.vue'),
                meta: { title: '通知偏好' },
            },
            {
                path: 'audit-log',
                name: 'PortalAuditLog',
                component: () => import('@/views/portal/AuditLog.vue'),
                meta: { title: '审计日志' },
            },
            {
                path: 'tickets',
                name: 'PortalTickets',
                component: () => import('@/views/portal/Tickets.vue'),
                meta: { title: '我的工单' },
            },
            {
                path: 'tickets/:id',
                name: 'PortalTicketDetail',
                component: () => import('@/views/portal/TicketDetail.vue'),
                meta: { title: '工单详情' },
            },
            {
                path: 'settings',
                name: 'PortalSettings',
                component: () => import('@/views/portal/Settings.vue'),
                meta: { title: '个人设置' },
            },
            {
                path: 'data-exports',
                name: 'PortalDataExport',
                component: () => import('@/views/portal/DataExport.vue'),
                meta: { title: '数据导出' },
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
                meta: { title: '帮助中心' },
            },
            // ── 收益账户 ──
            {
                path: 'earnings',
                name: 'PortalEarnings',
                component: () => import('@/views/portal/Earnings.vue'),
                meta: { title: '收益账户' },
            },
            // ── 合作伙伴中心 ──
            {
                path: 'partner',
                name: 'PortalPartner',
                component: () => import('@/views/commission/MyPartnerPortal.vue'),
                meta: { title: '合作伙伴中心' },
            },
            // ── 联盟推广 ──
            {
                path: 'affiliate',
                name: 'PortalAffiliate',
                component: () => import('@/views/commission/MyAffiliate.vue'),
                meta: { title: '联盟推广' },
            },
            // ── 优惠促销 ──
            {
                path: 'promotions',
                name: 'PortalPromotions',
                component: () => import('@/views/portal/Promotions.vue'),
                meta: { title: '优惠促销' },
            },
            // ── License 转移 ──
            {
                path: 'transfers',
                name: 'PortalTransfers',
                component: () => import('@/views/portal/Transfers.vue'),
                meta: { title: 'License 转移' },
            },
            // ── 通知中心 ──
            {
                path: 'notifications',
                name: 'PortalNotifications',
                component: () => import('@/views/notifications/Index.vue'),
                meta: { title: '通知中心' },
            },
            // ── 客户反馈 ──
            {
                path: 'feedback',
                name: 'PortalFeedback',
                component: () => import('@/views/portal/MyFeedback.vue'),
                meta: { title: '反馈' },
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
                meta: { title: '团队协作' },
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
router.beforeEach((to, from, next) => {
    const auth = useAuthStore();

    // 设置页面标题
    if (to.meta?.title) {
        const isPublicPage = ['Login', 'Register', 'ForgotPassword', 'Appeal', 'StatusPage', 'Community', 'Channels', 'UserProfile', 'PlazaDetail', 'InteractiveDemo', 'FollowingFeed', 'OaEditor', 'OaArticleDetail', 'Checkout'].includes(to.name);
        document.title = `${to.meta.title} - ${isPublicPage ? '互物通' : 'HWT License 管理后台'}`;
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
            return next('/login');
        }
        return next();
    }

    // 检查认证
    if (!auth.isLoggedIn) {
        return next('/login');
    }

    // 如果是多租户用户且没有选择租户，跳转到租户选择页
    if (auth.isMultiTenant && !auth.activeTenantId && to.name !== 'TenantSelect') {
        return next('/tenant-select');
    }

    next();
});

export default router;
