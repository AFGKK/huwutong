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
        path: '/status',
        name: 'StatusPage',
        component: () => import('@/views/status/Index.vue'),
        meta: { layout: 'blank', title: '系统状态' },
    },
    {
        path: '/tenant-select',
        name: 'TenantSelect',
        component: () => import('@/views/tenants/Select.vue'),
        meta: { layout: 'blank', title: '选择租户' },
    },
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
                path: 'devices',
                name: 'Devices',
                component: () => import('@/views/devices/Index.vue'),
                meta: { title: '设备管理', icon: 'Monitor' },
            },
            {
                path: 'rbac',
                name: 'Rbac',
                component: () => import('@/views/rbac/Index.vue'),
                meta: { title: '权限管理', icon: 'Setting' },
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
            {
                path: 'settings',
                name: 'SiteSettings',
                component: () => import('@/views/settings/Index.vue'),
                meta: { title: '系统设置', icon: 'Setting' },
            },
            {
                path: 'pages',
                name: 'PageManager',
                component: () => import('@/views/pages/Index.vue'),
                meta: { title: '页面管理', icon: 'Document' },
            },
            {
                path: 'email-templates',
                name: 'EmailTemplates',
                component: () => import('@/views/email-templates/Index.vue'),
                meta: { title: '邮件模板', icon: 'Message' },
            },
            {
                path: 'playground',
                name: 'ApiPlayground',
                component: () => import('@/views/playground/Index.vue'),
                meta: { title: 'API Playground', icon: 'Monitor' },
            },
            {
                path: 'email-tracking',
                name: 'EmailTracking',
                component: () => import('@/views/email-tracking/Index.vue'),
                meta: { title: '邮件追踪', icon: 'TrendCharts' },
            },
            {
                path: 'webhook-endpoints',
                name: 'WebhookEndpoints',
                component: () => import('@/views/webhook/Endpoints.vue'),
                meta: { title: 'Webhook 端点', icon: 'Link' },
            },
            {
                path: 'webhook-replay',
                name: 'WebhookReplay',
                component: () => import('@/views/webhook/Index.vue'),
                meta: { title: 'Webhook 回放', icon: 'Refresh' },
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
                path: 'webhook-events',
                name: 'WebhookEvents',
                component: () => import('@/views/webhooks/Events.vue'),
                meta: { title: 'Webhook 事件', icon: 'Promotion' },
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
                path: 'knowledge-base',
                name: 'KnowledgeBase',
                component: () => import('@/views/kb/Index.vue'),
                meta: { title: '帮助中心', icon: 'Reading' },
            },
            {
                path: 'license-files',
                name: 'LicenseFileCdn',
                component: () => import('@/views/license-files/Index.vue'),
                meta: { title: 'License 文件分发', icon: 'Upload' },
            },
            {
                path: 'llm',
                name: 'LlmProvider',
                component: () => import('@/views/llm/Index.vue'),
                meta: { title: '大模型管理', icon: 'Connection' },
            },
            {
                path: 'tax',
                name: 'TaxCalculator',
                component: () => import('@/views/tax/Index.vue'),
                meta: { title: '税务管理', icon: 'Document' },
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
            // ── 隐私协议管理 ──
            {
                path: 'legal-consents',
                name: 'LegalConsents',
                component: () => import('@/views/legal-consent/Index.vue'),
                meta: { title: '协议管理', icon: 'Document' },
            },
            // ── 账号注销审核 ──
            {
                path: 'account-deletions',
                name: 'AccountDeletions',
                component: () => import('@/views/account-deletion/Index.vue'),
                meta: { title: '注销审核', icon: 'Delete' },
            },
            {
                path: 'account/binding',
                name: 'AccountBinding',
                component: () => import('@/views/account/Binding.vue'),
                meta: { title: '账号绑定', icon: 'Link' },
            },
            {
                path: 'account/login-history',
                name: 'LoginHistory',
                component: () => import('@/views/account/LoginHistory.vue'),
                meta: { title: '登录历史', icon: 'Time' },
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
            // ── 用量计量系统 (M2-10) ──
            {
                path: 'usage-meter',
                name: 'UsageMeter',
                component: () => import('@/views/usage-meter/Index.vue'),
                meta: { title: '用量计量', icon: 'DataBoard' },
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
            // ── 批量操作工具 (M2-08) ──
            {
                path: 'batch',
                name: 'Batch',
                component: () => import('@/views/batch/Index.vue'),
                meta: { title: '批量操作', icon: 'List' },
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
            // ── SSO ──
            {
                path: 'sso',
                name: 'SsoSettings',
                component: () => import('@/views/sso/Index.vue'),
                meta: { title: '单点登录', icon: 'Link' },
            },
            // ── OpenFeature ──
            {
                path: 'openfeature',
                name: 'OpenFeature',
                component: () => import('@/views/openfeature/Index.vue'),
                meta: { title: 'OpenFeature 标志', icon: 'Switch' },
            },
            // ── AI 客服 ──
            {
                path: 'ai-chat',
                name: 'AiChat',
                component: () => import('@/views/ai-chat/Index.vue'),
                meta: { title: 'AI 智能客服', icon: 'ChatDotSquare' },
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
                path: 'updates',
                name: 'UpdatePackages',
                component: () => import('@/views/updates/Index.vue'),
                meta: { title: '更新包管理', icon: 'Upload' },
            },
            // ── 审计日志 ──
            {
                path: 'audit-logs',
                name: 'AuditLogs',
                component: () => import('@/views/audit-logs/Index.vue'),
                meta: { title: '审计日志', icon: 'Document' },
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
            // ── 帮助中心 ──
            {
                path: 'knowledge-base',
                name: 'PortalKnowledgeBase',
                component: () => import('@/views/portal/KnowledgeBase.vue'),
                meta: { title: '帮助中心' },
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
        document.title = `${to.meta.title} - HWT License`;
    }

    // 登录页面不需要认证
    if (to.name === 'Login') {
        if (auth.isLoggedIn) {
            return next('/dashboard');
        }
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
