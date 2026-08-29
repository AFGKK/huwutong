<template>
    <div class="app-container">
        <el-container class="h-screen">
            <!-- 📱 移动端侧边栏遮罩层 -->
            <div
                v-if="isMobile && mobileDrawerOpen"
                class="mobile-overlay"
                @click="closeMobileDrawer"
                role="presentation"
            />

            <!-- 侧边栏 (WCAG: role=navigation, aria-label) -->
            <el-aside
                :width="sidebarStore.sidebarCollapsed ? '64px' : '240px'"
                :class="['app-sidebar', { 'mobile-sidebar': isMobile, 'mobile-sidebar-open': mobileDrawerOpen }]"
                role="navigation"
                :aria-label="$t('admin.main_nav')"
            >
                <div class="sidebar-header">
                    <div class="logo" v-if="!sidebarStore.sidebarCollapsed">
                        <el-icon :size="28" color="#409eff" aria-hidden="true"><Key /></el-icon>
                        <span class="logo-text">HWT License</span>
                    </div>
                    <div class="logo collapsed" v-else>
                        <el-icon :size="28" color="#409eff" aria-hidden="true"><Key /></el-icon>
                    </div>
                </div>

                <!-- 租户选择器（侧边栏顶部） -->
                <div v-if="authStore.isMultiTenant && !sidebarStore.sidebarCollapsed" class="tenant-switcher-sidebar">
                    <el-select
                        v-model="currentTenantId"
                        size="small"
                        :placeholder="$t('admin.switch_tenant')"
                        @change="handleTenantSwitch"
                        class="tenant-select"
                        :aria-label="$t('admin.switch_tenant')"
                    >
                        <el-option
                            v-for="t in authStore.tenants"
                            :key="t.id"
                            :label="t.name"
                            :value="t.id"
                        >
                            <span>{{ t.name }}</span>
                        </el-option>
                    </el-select>
                </div>

                <div class="sidebar-menu-wrapper">
                    <el-menu
                        :default-active="route.path"
                        :collapse="sidebarStore.sidebarCollapsed"
                        background-color="#1d1e1f"
                        text-color="#bfcbd9"
                        active-text-color="#409eff"
                        class="sidebar-menu"
                        role="menubar"
                        aria-orientation="vertical"
                    >
                    <template v-for="group in menuGroups" :key="group.label">
                        <!-- 自定义分组头 — 替代 el-sub-menu，消除路由 bug -->
                        <li
                            v-if="!sidebarStore.sidebarCollapsed && visibleItems(group.items).length > 1"
                            class="sidebar-group-header"
                            role="none"
                            @click="toggleGroup(group.label)"
                        >
                            <el-icon aria-hidden="true"><component :is="group.icon" /></el-icon>
                            <span>{{ group.label }}</span>
                            <el-icon class="group-arrow" :class="{ collapsed: collapsedGroups[group.label] }" aria-hidden="true">
                                <ArrowDown />
                            </el-icon>
                        </li>
                        <!-- 菜单项（图标须在默认插槽，#title 仅放文字，collapse 模式才能显示图标）-->
                        <el-menu-item
                            v-for="item in visibleGroupItems(group)"
                            :key="item.path"
                            :index="item.path"
                            role="menuitem"
                            :aria-label="item.title"
                            @click="handleMenuClick(item.path)"
                        >
                            <el-badge
                                v-if="item.path === '/user-chat' && userChatUnread > 0 && sidebarStore.sidebarCollapsed"
                                :value="userChatUnread > 99 ? '99+' : userChatUnread"
                                class="sidebar-icon-badge"
                            >
                                <el-icon aria-hidden="true"><component :is="item.icon" /></el-icon>
                            </el-badge>
                            <el-icon v-else aria-hidden="true"><component :is="item.icon" /></el-icon>
                            <template #title>
                                <span>{{ item.title }}</span>
                                <el-badge
                                    v-if="item.path === '/user-chat' && userChatUnread > 0 && !sidebarStore.sidebarCollapsed"
                                    :value="userChatUnread > 99 ? '99+' : userChatUnread"
                                    class="sidebar-menu-badge"
                                />
                            </template>
                        </el-menu-item>
                    </template>
                </el-menu>
                </div><!-- sidebar-menu-wrapper -->
            </el-aside>

            <!-- 主区域 -->
            <el-container>
                <!-- 顶部导航 (WCAG: role=banner, aria-label) -->
                <el-header class="app-header" role="banner" :aria-label="$t('admin.top_toolbar')">
                    <div class="header-left">
                        <!-- 📱 移动端菜单按钮 -->
                        <el-button
                            v-if="isMobile"
                            text
                            @click="toggleMobileDrawer"
                            :aria-label="$t('admin.toggle_menu')"
                            class="mobile-menu-btn"
                        >
                            <el-icon :size="22" aria-hidden="true">
                                <Fold v-if="!mobileDrawerOpen" />
                                <Expand v-else />
                            </el-icon>
                        </el-button>
                        <!-- 💻 桌面端折叠按钮 -->
                        <el-button
                            v-else
                            text
                            @click="sidebarStore.toggleSidebar"
                            :aria-label="sidebarStore.sidebarCollapsed ? $t('admin.expand_sidebar') : $t('admin.collapse_sidebar')"
                            :aria-expanded="!sidebarStore.sidebarCollapsed"
                        >
                            <el-icon :size="20" aria-hidden="true">
                                <Fold v-if="!sidebarStore.sidebarCollapsed" />
                                <Expand v-else />
                            </el-icon>
                        </el-button>
                        <el-breadcrumb separator="/" class="ml-4" :aria-label="$t('admin.breadcrumb')">
                            <el-breadcrumb-item :to="{ path: '/dashboard' }">{{ $t('admin.home') }}</el-breadcrumb-item>
                            <el-breadcrumb-item v-if="appStore.currentTitle !== $t('admin.menu.dashboard')">
                                {{ appStore.currentTitle }}
                            </el-breadcrumb-item>
                        </el-breadcrumb>
                    </div>
                    <div class="header-right">
                        <!-- 全局搜索 -->
                        <GlobalSearchBar class="mr-4" />
                        <!-- 多租户快速切换（下拉） -->
                        <el-dropdown
                            v-if="authStore.isMultiTenant"
                            trigger="click"
                            class="mr-4"
                            @command="handleTenantSwitch"
                            :aria-label="$t('admin.switch_tenant')"
                        >
                            <el-button text aria-haspopup="menu">
                                <el-icon aria-hidden="true"><OfficeBuilding /></el-icon>
                                <span class="ml-1">{{ authStore.activeTenantName || $t('admin.switch_tenant') }}</span>
                                <el-icon class="el-icon--right" aria-hidden="true"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu role="menu">
                                    <el-dropdown-item
                                        v-for="t in authStore.tenants"
                                        :key="t.id"
                                        :command="t.id"
                                        :class="{ 'is-active': t.id === currentTenantId }"
                                        role="menuitemradio"
                                        :aria-checked="t.id === currentTenantId"
                                    >
                                        <el-icon v-if="t.id === currentTenantId" color="#409eff" aria-hidden="true">
                                            <CircleCheck />
                                        </el-icon>
                                        <span>{{ t.name }}</span>
                                    </el-dropdown-item>
                                    <el-dropdown-item divided command="manage" role="menuitem">
                                        <el-icon aria-hidden="true"><Setting /></el-icon>{{ $t("admin.manage_tenants") }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <NotificationBell />
        <CriticalNotificationDialog />
                        <el-dropdown trigger="click" @command="handleCommand" :aria-label="$t('admin.user_menu')">
                            <span class="user-info" aria-haspopup="menu">
                                <el-avatar :size="32" :src="authStore.avatarUrl" class="user-avatar">
                                    <span class="avatar-initial">{{ (authStore.userName || '?').charAt(0).toUpperCase() }}</span>
                                    <template #error>
                                        <span class="avatar-initial">{{ (authStore.userName || '?').charAt(0).toUpperCase() }}</span>
                                    </template>
                                </el-avatar>
                                <span class="ml-2">{{ authStore.userName || $t('admin.user_fallback') }}</span>
                                <el-icon class="el-icon--right" aria-hidden="true"><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu role="menu">
                                    <el-dropdown-item command="profile" role="menuitem">
                                        <el-icon aria-hidden="true"><User /></el-icon>{{ $t("admin.profile") }}
                                    </el-dropdown-item>
                                    <el-dropdown-item command="mfa" role="menuitem">
                                        <el-icon aria-hidden="true"><Lock /></el-icon>{{ $t("admin.mfa") }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="isImpersonating" command="stop-impersonate" divided role="menuitem">
                                        <el-icon color="#e6a23c" aria-hidden="true"><WarnTriangleFilled /></el-icon>{{ $t("admin.stop_impersonate") }}
                                    </el-dropdown-item>
                                    <el-dropdown-item divided command="logout" role="menuitem">
                                        <el-icon aria-hidden="true"><SwitchButton /></el-icon>{{ $t("admin.logout") }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </el-header>

                <!-- 系统公告横幅 -->
                <AnnounceBanner />

                <!-- 模拟模式提示横幅 -->
                <div v-if="isImpersonating" class="impersonate-banner" role="alert" aria-live="polite">
                    <el-icon aria-hidden="true"><WarnTriangleFilled /></el-icon>
                    <span>
                        {{ $t('admin.impersonating', { name: impersonateTarget }) }}
                    </span>
                    <el-button size="small" type="warning" plain @click="handleStopImpersonate" :aria-label="$t('admin.stop_impersonate')">
                        {{ $t("admin.exit_impersonate") }}
                    </el-button>
                </div>

                <!-- 内容区域 (WCAG: role=main, id for skip-link) -->
                <el-main
                    class="app-main"
                    id="main-content"
                    role="main"
                    :aria-label="$t('admin.main_content')"
                    tabindex="-1"
                >
                    <router-view />
                </el-main>
            </el-container>
        </el-container>
        <FeedbackButton v-if="false" />
        <CookieConsent v-if="false" />
        <PwaInstallPrompt />
    </div>
</template>

<script setup>
import FeedbackButton from '@/components/FeedbackButton.vue';
import GlobalSearchBar from '@/components/GlobalSearchBar.vue';
import PwaInstallPrompt from '@/components/PwaInstallPrompt.vue';
import { ref, reactive, computed, watch, onErrorCaptured, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import NotificationBell from '@/components/NotificationBell.vue';
import CriticalNotificationDialog from '@/components/CriticalNotificationDialog.vue';
import AnnounceBanner from '@/components/AnnounceBanner.vue';
import CookieConsent from '@/components/CookieConsent.vue';
import errorReporter from '@/utils/errorReporter';
import { getImpersonateSession, stopImpersonate } from '@/api/impersonate';
import apiClient from '@/utils/request';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { markRaw } from 'vue';
import {
    Fold, Expand, ArrowDown, SwitchButton,
    Key, Lock, UserFilled, OfficeBuilding,
    CircleCheck, Setting, Odometer, User, Goods,
    Monitor, Tickets, Coin, Document, Upload,
    MagicStick, EditPen, Connection, Reading,
    Bell, Link, Promotion, Refresh,
    TrendCharts, Message, Timer, ChatDotSquare, ChatDotRound, AlarmClock, Search,
    WarnTriangleFilled, DataBoard, DataAnalysis, List, WarningFilled, PriceTag, Money, SwitchFilled,
    ScaleToOriginal, SetUp, Delete, Histogram, ChatRound, Download, DocumentChecked, Wallet,
    Service, UploadFilled, CreditCard, Star, Collection, ShoppingCart, RemoveFilled,
    Notification, CollectionTag, Cpu, Mug,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const appStore = useAppStore();
const sidebarStore = appStore;

// 错误边界 — 捕获子组件渲染错误
const error = ref(null);
onErrorCaptured((err, instance, info) => {
    errorReporter.vueError(err, instance, info);

    // 轻度错误只通知，不崩溃
    error.value = err?.message || t('admin.render_error');
    ElMessage.error({
        message: t('admin.render_recovered'),
        duration: 3000,
    });

    // 返回 false 阻止错误向上传播（不会导致整个应用崩溃）
    return false;
});

const currentTenantId = computed({
    get: () => authStore.activeTenantId,
    set: (val) => handleTenantSwitch(val),
});

// 模拟登录状态
const impersonateToken = ref(localStorage.getItem('impersonate_token') || '');
const impersonateTarget = ref(localStorage.getItem('impersonate_target') || '');
const isImpersonating = computed(() => !!impersonateToken.value);

// 启动时检查模拟会话是否仍然有效
(async function checkImpersonateSession() {
    if (!impersonateToken.value) return;
    try {
        const res = await getImpersonateSession();
        // 确保 token 通过请求头发送（需要 apiClient 拦截器支持）
        // 如果 session 返回 false，则清除本地存储
        if (!res.data?.data?.is_impersonating) {
            clearImpersonateState();
        }
    } catch {
        clearImpersonateState();
    }
})();

function clearImpersonateState() {
    impersonateToken.value = '';
    impersonateTarget.value = '';
    localStorage.removeItem('impersonate_token');
    localStorage.removeItem('impersonate_target');
}

/** 根据用户角色过滤菜单项 */
function visibleItems(items) {
    if (authStore.isAdmin) return items
    return items.filter(item => !item.adminOnly)
}

/** 分组展开时才显示子项；侧边栏收起时始终显示图标列表 */
function visibleGroupItems(group) {
    const items = visibleItems(group.items)
    if (sidebarStore.sidebarCollapsed || items.length <= 1) return items
    if (collapsedGroups[group.label]) return []
    return items
}

/** 菜单点击导航 */
function handleMenuClick(path) {
    if (path && path !== router.currentRoute.value.path) {
        router.push(path)
    }
}

/** 分组折叠状态 — 默认全部展开 */
const collapsedGroups = reactive({})
function toggleGroup(label) {
    collapsedGroups[label] = !collapsedGroups[label]
}

async function handleStopImpersonate() {
    if (!impersonateToken.value) return;
    try {
        await stopImpersonate(impersonateToken.value);
        clearImpersonateState();
        ElMessage.success(t('admin.impersonate_ok'));
        // 刷新页面恢复原始身份
        window.location.reload();
    } catch (e) {
        clearImpersonateState();
        ElMessage.error(t('admin.impersonate_fail'));
    }
}

const menuGroups = [
    {
        label: '核心业务', icon: Odometer,
        items: [
            { path: '/dashboard', title: '仪表盘', icon: Odometer },
            { path: '/licenses', title: 'License 管理', icon: Key },
            { path: '/license-analytics', title: 'License 分析引擎', icon: DataBoard },
            { path: '/license-key-prefix', title: 'License Key 前缀', icon: EditPen },
            { path: '/license-templates', title: 'License 模板', icon: List },
            { path: '/license-trash', title: 'License 回收站', icon: Delete },
            { path: '/license-snapshot', title: 'License 快照', icon: Timer },
            { path: '/license-approval', title: 'License 审批', icon: CircleCheck },
            { path: '/license-merge', title: 'License 继承/合并', icon: Connection },
            { path: '/license-marketplace', title: 'License 二级市场', icon: ShoppingCart },
            { path: '/blockchain-license', title: '区块链 License', icon: Coin },
            { path: '/transfers', title: 'License 转移', icon: Connection },
            { path: '/customers', title: '客户管理', icon: User },
            { path: '/customer-merge', title: '客户合并', icon: Connection },
            { path: '/products', title: '产品管理中心', icon: Goods },
            { path: '/devices', title: '设备管理', icon: Monitor },
            { path: '/license-files', title: 'License 文件分发', icon: Upload },
        ],
    },
    {
        label: 'IM 即时通讯', icon: ChatDotSquare,
        items: [
            { path: '/im', title: 'IM中心', icon: ChatDotSquare },
            { path: '/user-chat', title: '我的消息', icon: ChatRound },
            { path: '/sensitive-words', title: '敏感词管理', icon: WarningFilled },
            { path: '/custom-emoji', title: '自定义 Emoji', icon: Mug },
            { path: '/auto-reply', title: '自动回复', icon: ChatDotRound },
            { path: '/bot-manager', title: 'Bot 管理', icon: Cpu },
            { path: '/im-integration', title: 'IM 通知集成', icon: ChatDotSquare },
            { path: '/tickets', title: '工单管理', icon: Tickets },
        ],
    },
    {
        label: '计费与订阅', icon: Coin,
        items: [
            { path: '/billing', title: '订阅计费', icon: Coin },
            { path: '/payment', title: '支付管理', icon: Wallet },
            { path: '/payment/transactions', title: '交易流水', icon: List },
            { path: '/payment/webhook-logs', title: 'Webhook 日志', icon: Notification },
            { path: '/payment-methods', title: '支付方式管理', icon: CreditCard },
            { path: '/pricing/dynamic', title: '动态定价引擎', icon: TrendCharts },
            { path: '/plans', title: '套餐系统', icon: Coin },
            { path: '/promotions', title: '促销管理', icon: Promotion },
            { path: '/refunds', title: '退款售后中心', icon: Money },
            { path: '/points', title: '积分管理', icon: Coin },
            { path: '/usage-meter', title: '用量计量', icon: DataBoard },
            { path: '/metered-billing', title: '按量计费深度', icon: TrendCharts },
            { path: '/currency', title: '多币种定价', icon: Coin },
            { path: '/billing/retention', title: '续费流水线', icon: Connection },
            { path: '/tax', title: '税务管理', icon: Document },
            { path: '/china-invoice', title: '中国电子发票', icon: Document },
            { path: '/commission', title: '佣金结算', icon: Money },
            { path: '/settlement', title: '财务结算', icon: DataBoard },
            { path: '/accounting', title: '会计系统集成', icon: Coin },
            { path: '/payment-callbacks', title: '支付回调', icon: Coin },
            { path: '/token-meter', title: 'Token 计费追踪', icon: Money },
            { path: '/renewal', title: '续费管理中心', icon: Refresh },
        ],
    },
    {
        label: '运营分析', icon: DataBoard,
        items: [
            { path: '/reports', title: '报表中心', icon: TrendCharts },
            { path: '/revenue', title: '平台收益总览', icon: DataBoard },
            { path: '/business-metrics', title: '业务指标看板', icon: DataBoard },
            { path: '/batch', title: '批量操作', icon: List },
            { path: '/orders', title: '订单管理', icon: List },
            { path: '/sla', title: 'SLA 管理中心', icon: Timer },
            { path: '/slo', title: 'SLO / 调用链', icon: Coin },
            { path: '/health-score', title: '健康度监控', icon: DataBoard },
            { path: '/crm', title: 'CRM 客户分析', icon: DataBoard },
            { path: '/product-analytics', title: '产品使用分析', icon: DataBoard },
            { path: '/conversion-funnel', title: '转化漏斗', icon: TrendCharts },
            { path: '/email', title: '邮件管理', icon: Message },
            { path: '/data-lineage', title: '数据血缘', icon: Connection },
            { path: '/bi-export', title: 'BI 数据仓库导出', icon: DataBoard },
        ],
    },
    {
        label: '电商运营', icon: ShoppingCart,
        items: [
            { path: '/ecommerce-dashboard', title: '电商数据中心', icon: TrendCharts },
            { path: '/auto-invoice', title: '自动开票', icon: PriceTag },
            { path: '/affiliate', title: '联盟推广', icon: Connection, adminOnly: true },
            { path: '/inventory', title: '库存管理', icon: Goods },
            { path: '/product-sku', title: 'SKU 商品规格', icon: Goods },
            { path: '/billing-cycles', title: '计费周期', icon: Timer },
            { path: '/coupons', title: '优惠券管理', icon: Coin },
            { path: '/reviews', title: '商品评论', icon: Star },
            { path: '/product-comparison', title: '规格对比', icon: Connection },
            { path: '/wishlist', title: '收藏夹 / 心愿单', icon: Collection },
            { path: '/bundles', title: '组合套餐', icon: Goods },
            { path: '/pre-sale', title: '限时销售管理', icon: Timer },
            { path: '/resale', title: '二级市场转售', icon: ShoppingCart },
            { path: '/product-localization', title: '多语言商品', icon: ChatRound },
            { path: '/anomaly-detection', title: '异常检测', icon: WarningFilled },
            { path: '/payment-security', title: '支付安全', icon: Lock },
            { path: '/reconciliation', title: '电商对账系统', icon: DataBoard },
            { path: '/auto-delivery', title: '多渠道送达', icon: ShoppingCart },
        ],
    },
    {
        label: '应用生态', icon: MagicStick,
        items: [
            { path: '/wizard', title: 'AI 集成向导', icon: MagicStick },
            { path: '/dev-portal', title: '开发者门户', icon: Monitor },
            { path: '/open-platform', title: '开放平台', icon: Connection },
            { path: '/app-marketplace', title: '应用市场', icon: ShoppingCart },
            { path: '/developer-earnings', title: '开发者收益', icon: Money },
            { path: '/marketplace-push', title: '市场推送', icon: Bell },
            { path: '/marketplace-security', title: '内容安全', icon: WarningFilled },
            { path: '/moments', title: '社区管理', icon: ChatDotSquare },
            { path: '/official-accounts', title: '互物号管理', icon: Monitor },
            { path: '/articles/manage', title: '文章审核', icon: Document },
            { path: '/blog', title: '变更日志', icon: Document },
            { path: '/certification', title: '开发者认证', icon: CollectionTag },
            { path: '/knowledge-base', title: '帮助文档中心', icon: Reading },
            { path: '/crm-integration', title: 'CRM 集成', icon: Connection },
            { path: '/cloud-marketplace', title: '云市场集成', icon: Connection },
        ],
    },
    {
        label: '开发工具链', icon: Connection,
        items: [
            { path: '/api-key-center', title: 'API Key 管理中心', icon: Key },
            { path: '/mock-server', title: 'API Mock Server', icon: Connection },
            { path: '/quota', title: '限流配额管理', icon: TrendCharts },
            { path: '/rate-limits', title: '限流规则', icon: Monitor },
            { path: '/monitor', title: 'API 监控', icon: DataAnalysis },
            { path: '/postman', title: 'Postman Collection', icon: Connection },
            { path: '/cache-invalidation', title: 'SDK 缓存推送', icon: Connection },
            { path: '/public-key', title: '公钥版本管理', icon: Key },
            { path: '/text-to-sql', title: 'Text-to-SQL 安全', icon: Monitor },
            { path: '/diagnostic', title: 'AI 错误诊断', icon: MagicStick },
            { path: '/sdk-manager', title: 'SDK 管理', icon: Connection },
            { path: '/update-manager', title: '更新管理', icon: Upload },
            { path: '/prompt-templates', title: 'Prompt 模板管理', icon: EditPen },
            { path: '/meilisearch', title: 'Meilisearch 搜索', icon: Search },
            { path: '/playground', title: 'API Playground', icon: Monitor },
            { path: '/telemetry', title: 'SDK Telemetry', icon: Monitor },
            { path: '/error-codes', title: '错误码参考', icon: WarningFilled },
            { path: '/ci-cd', title: 'CI/CD 自动授权', icon: Connection },
            { path: '/compat-test', title: '兼容性测试', icon: Connection },
            { path: '/migration-assistant', title: '迁移工具中心', icon: Connection },
            { path: '/sandbox', title: '测试环境管理', icon: Monitor },
            { path: '/api-versions', title: 'API 版本管理中心', icon: Connection },
            { path: '/updates', title: '自动更新', icon: Upload },
        ],
    },
    {
        label: '个人中心', icon: User,
        items: [
            { path: '/account/profile', title: '账户中心', icon: User },
            { path: '/account/binding', title: '账号绑定', icon: Link },
            { path: '/account/login-history', title: '登录历史', icon: TrendCharts },
            { path: '/account/passkey', title: 'Passkey 管理', icon: Key },
            { path: '/invite-codes', title: '邀请码', icon: Key },
            { path: '/mfa', title: 'MFA 设置', icon: Lock },
        ],
    },
    {
        label: '系统管理', icon: Setting,
        items: [
            { path: '/rbac', title: '权限管理', icon: Setting },
            { path: '/users', title: '用户管理', icon: User },
            { path: '/notifications', title: '通知中心', icon: Bell },
            { path: '/settings', title: '系统设置', icon: Setting },
            { path: '/tenants', title: '租户管理', icon: OfficeBuilding },
            { path: '/pages', title: '页面管理', icon: Document },
            { path: '/feature-flags', title: '功能开关', icon: SwitchButton },
            { path: '/announce-banners', title: '系统公告', icon: Bell },
            { path: '/cookie-consent', title: 'Cookie 管理', icon: Bell },
            { path: '/sso', title: '单点登录 (SSO)', icon: Link },
            { path: '/oauth', title: 'OAuth 登录', icon: Link },
            { path: '/backups', title: '自动备份', icon: Timer },
            { path: '/tags', title: '标签管理', icon: PriceTag },
            { path: '/data-management', title: '数据管理', icon: Connection },
            { path: '/account-deletions', title: '注销审核', icon: Delete },
            { path: '/data-exports', title: '数据导出管理', icon: Download },
            { path: '/scim', title: 'SCIM 用户同步', icon: Refresh },
            { path: '/workflows', title: '工作流引擎', icon: Timer },
            { path: '/marketplace-rollout', title: '灰度发布', icon: SwitchFilled },
        ],
    },
    {
        label: '门户与运营', icon: OfficeBuilding,
        items: [
            { path: '/embedded-widget', title: '嵌入式 Widget', icon: Monitor },
            { path: '/feedback', title: '客户反馈', icon: ChatDotSquare },
            { path: '/agent-manager', title: '合作伙伴管理', icon: UserFilled },
            { path: '/portal-branding', title: '门户品牌化', icon: EditPen },
            { path: '/compliance-center', title: '合规中心', icon: DocumentChecked },
            { path: '/demo-booking', title: 'Demo 管理', icon: Monitor },
            { path: '/case-studies', title: '客户案例', icon: DataBoard },
            { path: '/compare-page', title: '竞品对比', icon: TrendCharts },
            { path: '/domains', title: '域名管理', icon: Link },
            { path: '/trials', title: '试用管理', icon: Timer },
            { path: '/offline', title: '离线部署管理', icon: Lock },
            { path: '/pwa', title: 'PWA 移动端', icon: Monitor },
            { path: '/a11y', title: '无障碍帮助', icon: Reading },
        ],
    },
    {
        label: '安全中心', icon: Lock,
        items: [
            { path: '/sessions', title: '活跃会话', icon: Monitor },
            { path: '/device-trust', title: '信任设备', icon: Monitor },
            { path: '/geo-location', title: '地理位置', icon: Monitor },
            { path: '/password-policy', title: '密码策略', icon: Lock },
            { path: '/audit', title: '审计中心', icon: DataBoard },
            { path: '/security', title: '安全中心', icon: Lock },
            { path: '/waf', title: 'WAF 基础防护', icon: WarningFilled },
            { path: '/deps-security', title: '依赖安全', icon: Lock },
            { path: '/attack-detection', title: '攻击模式识别', icon: WarningFilled },
            { path: '/honeypot', title: '主动蜜罐防御', icon: WarningFilled },
            { path: '/auto-pentest', title: '自动渗透测试', icon: Monitor },
            { path: '/tenant-isolation', title: '租户隔离管理', icon: Lock },
            { path: '/watermark-tamper', title: '暗水印与防篡改', icon: Lock },
            { path: '/fraud-risk', title: 'AI 风控中心', icon: WarnTriangleFilled },
            { path: '/secrets', title: '密钥管理', icon: Lock },
            { path: '/hsm', title: 'HSM 签名', icon: Key },
            { path: '/ssl-certificates', title: 'SSL 证书', icon: Lock },
            { path: '/crl', title: 'CRL 吊销列表', icon: RemoveFilled },
            { path: '/cors-configs', title: 'CORS 配置', icon: Connection },
            { path: '/domain-whitelist', title: '域名白名单验证', icon: Link },
            { path: '/license-restrictions', title: 'License 访问限制', icon: Lock },
            { path: '/global-resources', title: '全局资源白名单', icon: Key },
            { path: '/impersonate', title: '模拟登录', icon: Key },
            { path: '/admin-appeals', title: '账号申诉审核', icon: WarningFilled },
            { path: '/merkle-chain', title: 'Merkle 验证链', icon: Connection },
            { path: '/siem-export', title: 'SIEM 日志导出', icon: DataBoard },
            { path: '/fingerprint-drift', title: '指纹漂移', icon: Monitor },
            { path: '/tpm-binding', title: 'TPM 硬件绑定', icon: Key },
            { path: '/secret-scan', title: '密钥泄露扫描', icon: WarningFilled },
        ],
    },
    {
        label: '监控与运维', icon: Monitor,
        items: [
            { path: '/system-health', title: '系统健康监控', icon: Monitor },
            { path: '/apm', title: 'APM 监控', icon: DataAnalysis },
            { path: '/slow-query-monitor', title: '慢查询监控', icon: Monitor },
            { path: '/synthetic-monitor', title: '拨测监控', icon: Monitor },
            { path: '/log-archiver', title: '日志管理中心', icon: Coin },
            { path: '/alerts', title: '告警中心', icon: WarningFilled },
            { path: '/chaos-engineering', title: '混沌工程', icon: WarnTriangleFilled },
            { path: '/circuit-breaker', title: '断路器监控', icon: Monitor },
            { path: '/db-read-write', title: '读写分离', icon: Connection },
            { path: '/redis-ha', title: 'Redis 高可用', icon: DataAnalysis },
            { path: '/vm-detection', title: '虚拟环境检测', icon: Monitor },
            { path: '/incident-alerting', title: 'PagerDuty/OpsGenie', icon: Bell },
            { path: '/quota-alert', title: '用量配额预警', icon: WarningFilled },
            { path: '/scheduled-notification', title: '批量通知定时发送', icon: Timer },
            { path: '/seat-pool', title: '并发License浮动', icon: Connection },
            { path: '/api-gateway', title: 'API 网关', icon: Connection },
            { path: '/grpc', title: 'gRPC 服务通信', icon: Connection },
            { path: '/istio', title: 'Istio 服务网格', icon: Connection },
            { path: '/blue-green', title: '蓝绿部署', icon: Connection },
            { path: '/edge-verifier', title: 'Edge 授权验证', icon: Connection },
            { path: '/data-retention', title: '数据留存策略', icon: Timer },
            { path: '/on-call', title: '值班轮换', icon: AlarmClock },
            { path: '/status-page', title: '状态页管理', icon: Monitor },
        ],
    },
    {
        label: '平台工具', icon: Setting,
        items: [
            { path: '/search-center', title: '搜索管理', icon: Search },
            { path: '/webhooks', title: 'Webhook 管理', icon: Link },
            { path: '/automation', title: '自动化规则引擎', icon: Setting },
            { path: '/collaboration', title: '团队协作中心', icon: ChatDotSquare },
            { path: '/custom-fields', title: '自定义字段', icon: EditPen },
            { path: '/cloud-upload', title: '云文件存储', icon: UploadFilled },
            { path: '/i18n', title: '国际化管理', icon: ChatRound },
            { path: '/api-docs', title: 'API 文档门户', icon: Document },
            { path: '/data-import', title: '批量数据导入', icon: Upload },
            { path: '/footer-nav', title: '页脚导航配置', icon: Link },
            { path: '/zapier', title: 'Zapier/Make 集成', icon: Connection },
            { path: '/budget-guard', title: '消费预警+预算上限', icon: Coin },
            { path: '/utm-tracker', title: 'UTM/渠道归因', icon: TrendCharts },
            { path: '/nps-survey', title: 'NPS 满意度调查', icon: WarningFilled },
            { path: '/feature-adoption', title: '功能使用率追踪', icon: TrendCharts },
        ],
    },
    {
        label: 'AI 与基础设施', icon: Connection,
        items: [
            { path: '/ai', title: 'AI 智能套件', icon: Monitor },
            { path: '/ai-memory', title: 'AI 长期记忆', icon: Collection },
            { path: '/ai-proactive', title: 'AI 主动洞察', icon: DataAnalysis },
            { path: '/llm', title: '大模型管理', icon: Connection },
            { path: '/rag', title: 'RAG 知识库管理', icon: Reading },
            { path: '/ai-operations', title: 'AI 运营中心', icon: SetUp },
            { path: '/usage-dashboard', title: '客户用量看板', icon: DataBoard },
            { path: '/mcp-auth', title: 'MCP / AI Agent 授权', icon: Connection },
            { path: '/serverless-auth', title: 'Serverless 授权', icon: Monitor },
            { path: '/edge-auth', title: '边缘计算授权', icon: Connection },
            { path: '/openfeature', title: 'OpenFeature 标志', icon: SwitchButton },
        ],
    },
];

async function handleTenantSwitch(tenantId) {
    if (!tenantId || tenantId === 'manage') {
        router.push('/tenant-select');
        return;
    }
    await authStore.switchTenant(tenantId);
    // 刷新页面数据（切换租户后刷新用户信息）
    await authStore.fetchUser();
}

function handleCommand(command) {
    if (command === 'logout') {
        authStore.logout();
        router.push('/login');
    } else if (command === 'profile') {
        router.push('/account/profile');
    } else if (command === 'mfa') {
        router.push('/mfa');
    } else if (command === 'stop-impersonate') {
        handleStopImpersonate();
    }
}

// ─── 📱 移动端响应式 ──────────────────────────────────────────
const isMobile = ref(window.innerWidth < 768);
const mobileDrawerOpen = ref(false);

function onResize() {
    const mobile = window.innerWidth < 768;
    if (mobile !== isMobile.value) {
        isMobile.value = mobile;
        if (mobile) {
            sidebarStore.sidebarCollapsed = true;
            mobileDrawerOpen.value = false;
        }
    }
}

const userChatUnread = ref(0);
let userChatUnreadTimer = null;
let userChatEchoChannel = null;
let userChatUnreadDebounce = null;

function applyUnreadSummary(d) {
    const n = Number(d?.total_unread);
    if (Number.isFinite(n) && n >= 0) {
        userChatUnread.value = n;
        return;
    }
    if (Array.isArray(d?.conversations)) {
        userChatUnread.value = d.conversations.reduce((sum, c) => sum + (Number(c.unread_count) || 0), 0);
        return;
    }
    userChatUnread.value = d?.has_unread ? 1 : 0;
}

async function refreshUserChatUnread() {
    try {
        const res = await apiClient.get('/user-chat/unread-summary', { params: { lite: 1 } });
        applyUnreadSummary(res.data?.data || {});
    } catch {
        /* ignore badge refresh errors */
    }
}

function onUserChatMessageEvent(payload) {
    const myId = authStore.user?.id;
    if (!payload || payload.sender_id === myId) return;
    if (route.path === '/user-chat' || route.path.startsWith('/user-chat')) {
        if (userChatUnreadDebounce) clearTimeout(userChatUnreadDebounce);
        userChatUnreadDebounce = setTimeout(refreshUserChatUnread, 400);
        return;
    }
    userChatUnread.value += 1;
}

function subscribeUserChatUnreadEcho() {
    const userId = authStore.user?.id;
    if (!userId || typeof window.Echo !== 'object' || !window.Echo) return;
    try {
        userChatEchoChannel = window.Echo.private(`chat.${userId}`);
        userChatEchoChannel.listen('.chat.message', onUserChatMessageEvent);
    } catch {
        userChatEchoChannel = null;
    }
}

function unsubscribeUserChatUnreadEcho() {
    if (!userChatEchoChannel) return;
    try {
        userChatEchoChannel.stopListening('.chat.message');
    } catch {
        /* ignore */
    }
    userChatEchoChannel = null;
}

function startUserChatUnreadPolling() {
    refreshUserChatUnread();
    subscribeUserChatUnreadEcho();
    if (userChatUnreadTimer) clearInterval(userChatUnreadTimer);
    userChatUnreadTimer = setInterval(refreshUserChatUnread, 60000);
}

onMounted(() => {
    window.addEventListener('resize', onResize);
    startUserChatUnreadPolling();
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    unsubscribeUserChatUnreadEcho();
    if (userChatUnreadDebounce) {
        clearTimeout(userChatUnreadDebounce);
        userChatUnreadDebounce = null;
    }
    if (userChatUnreadTimer) {
        clearInterval(userChatUnreadTimer);
        userChatUnreadTimer = null;
    }
});

watch(() => route.path, (path, prev) => {
    if (path === '/user-chat' || prev === '/user-chat' || path.startsWith('/user-chat')) {
        refreshUserChatUnread();
    }
});

function toggleMobileDrawer() {
    mobileDrawerOpen.value = !mobileDrawerOpen.value;
    if (mobileDrawerOpen.value) {
        sidebarStore.sidebarCollapsed = false;
    } else {
        sidebarStore.sidebarCollapsed = true;
    }
}

function closeMobileDrawer() {
    mobileDrawerOpen.value = false;
    sidebarStore.sidebarCollapsed = true;
}
</script>

<style scoped>
.app-container {
    height: 100vh;
    overflow: hidden;
}

.el-container.h-screen {
    height: 100%;
}

.app-sidebar {
    background: #1d1e1f;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    transition: width 0.3s;
}

.sidebar-header {
    flex-shrink: 0;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
    display: flex;
    align-items: center;
    gap: 8px;
}

.logo-text {
    color: #fff;
    font-size: 18px;
    font-weight: 600;
    white-space: nowrap;
}

.tenant-switcher-sidebar {
    flex-shrink: 0;
    padding: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tenant-select {
    width: 100%;
}

.sidebar-menu {
    border-right: none;
}

.sidebar-menu-wrapper {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

/* ─── 自定义分组头（替代 el-sub-menu） ─── */
.sidebar-group-header {
    display: flex;
    align-items: center;
    height: 56px;
    padding: 0 20px;
    margin-top: 4px;
    color: #909399;
    font-size: 14px;
    cursor: pointer;
    user-select: none;
    transition: color 0.2s;
    gap: 8px;
}
.sidebar-group-header:hover {
    color: #bfcbd9;
}
.sidebar-group-header span {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.group-arrow {
    font-size: 12px;
    transition: transform 0.2s;
}
.group-arrow.collapsed {
    transform: rotate(-90deg);
}

/* 点击导航包裹 — 绕过 el-menu-item 对内部 click 的吞没 */
.menu-item-inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border-bottom: 1px solid #e4e7ed;
    padding: 0 20px;
    height: 60px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-right {
    display: flex;
    align-items: center;
}

.user-info {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.user-info:hover {
    background: #f5f7fa;
}

.app-main {
    background: #f5f7fa;
    overflow-y: auto;
    padding: 20px;
}

.mr-4 {
    margin-right: 16px;
}

.impersonate-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 8px 16px;
    background: #fdf6ec;
    border-bottom: 1px solid #e6a23c;
    color: #e6a23c;
    font-size: 14px;
}

.impersonate-banner strong {
    font-weight: 600;
}

.ml-1 {
    margin-left: 4px;
}

/* ─── 📱 移动端响应式 ──────────────────────────────────────── */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.mobile-sidebar {
    position: fixed !important;
    top: 0;
    left: -280px;
    z-index: 1000;
    transition: left 0.3s ease !important;
    height: 100vh !important;
}

.mobile-sidebar-open {
    left: 0 !important;
}

.mobile-menu-btn {
    margin-left: 0 !important;
}

.sidebar-menu-badge {
    margin-left: 8px;
}
.sidebar-menu-badge :deep(.el-badge__content) {
    position: static;
    transform: none;
}

.sidebar-icon-badge :deep(.el-badge__content) {
    transform: scale(0.85);
}

@media (max-width: 768px) {
    .app-main {
        padding: 12px !important;
    }

    .app-header {
        padding: 0 12px !important;
    }

    .header-right .mr-4 {
        margin-right: 8px !important;
    }

    .user-info .ml-2 {
        display: none;
    }
}
</style>
