<template>
    <div class="portal-container" :style="portalStyle">
        <!-- 顶部导航 (WCAG: role=banner) -->
        <header class="portal-header" role="banner" aria-label="客户门户顶部导航">
            <div class="header-left">
                <el-button
                    text
                    @click="sidebarCollapsed = !sidebarCollapsed"
                    class="collapse-btn"
                    :aria-label="sidebarCollapsed ? '展开侧边栏' : '收起侧边栏'"
                    :aria-expanded="!sidebarCollapsed"
                >
                    <el-icon :size="20" aria-hidden="true"><Fold v-if="!sidebarCollapsed" /><Expand v-else /></el-icon>
                </el-button>
                <div class="brand">
                    <img v-if="branding.logo_url" :src="branding.logo_url" alt="Logo" class="brand-logo-img" />
                    <el-icon v-else :size="28" :color="branding.primary_color || '#409eff'" aria-hidden="true"><Key /></el-icon>
                    <span class="brand-text" v-if="!sidebarCollapsed">{{ branding.brand_name || '互物通 客户门户' }}</span>
                </div>
            </div>
            <div class="header-right">
                <!-- 通知铃铛 -->
                <el-badge :value="unreadCount" :hidden="unreadCount === 0" class="notification-badge">
                    <el-button text size="small" @click="router.push('/portal/notifications')" aria-label="通知">
                        <el-icon :size="20"><Bell /></el-icon>
                    </el-button>
                </el-badge>
                <el-dropdown @command="handleCommand" trigger="click" aria-label="用户菜单">
                    <span class="user-info" aria-haspopup="menu">
                        <el-avatar :size="32" :src="authStore.avatarUrl" class="user-avatar">
                            <span class="avatar-initial">{{ (authStore.userName || '?').charAt(0).toUpperCase() }}</span>
                            <template #error>
                                <span class="avatar-initial">{{ (authStore.userName || '?').charAt(0).toUpperCase() }}</span>
                            </template>
                        </el-avatar>
                        <span class="user-name">{{ authStore.userName || '客户用户' }}</span>
                        <el-icon aria-hidden="true"><ArrowDown /></el-icon>
                    </span>
                    <template #dropdown>
                        <el-dropdown-menu role="menu">
                            <el-dropdown-item command="settings" role="menuitem">
                                <el-icon aria-hidden="true"><Setting /></el-icon> 个人设置
                            </el-dropdown-item>
                            <el-dropdown-item command="logout" role="menuitem">
                                <el-icon aria-hidden="true"><SwitchButton /></el-icon> 退出登录
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </header>

        <div class="portal-body">
            <!-- 侧边栏 (WCAG: role=navigation) -->
            <aside
                class="portal-sidebar"
                :class="{ collapsed: sidebarCollapsed }"
                role="navigation"
                aria-label="客户门户导航"
                :style="{ background: branding.sidebar_bg_color || '#304156' }"
            >
                <el-menu
                    :default-active="activeMenu"
                    :collapse="sidebarCollapsed"
                    background-color="transparent"
                    :text-color="branding.sidebar_text_color || '#bfcbd9'"
                    :active-text-color="branding.primary_color || '#409eff'"
                    router
                >
                    <el-menu-item index="/portal/dashboard">
                        <el-icon><DataAnalysis /></el-icon>
                        <template #title>仪表盘</template>
                    </el-menu-item>
                    <!-- 🛒 商品商店 (M1.4-61) -->
                    <el-menu-item index="/portal/shop">
                        <el-icon><ShoppingCart /></el-icon>
                        <template #title>商品商店</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/cart">
                        <el-icon><ShoppingCart /></el-icon>
                        <template #title>购物车</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/checkout">
                        <el-icon><Coin /></el-icon>
                        <template #title>订单结算</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/orders">
                        <el-icon><List /></el-icon>
                        <template #title>我的订单</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/promotions">
                        <el-icon><Present /></el-icon>
                        <template #title>优惠促销</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/tickets">
                        <el-icon><Tickets /></el-icon>
                        <template #title>工单</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/licenses">
                        <el-icon><Key /></el-icon>
                        <template #title>我的 License</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/transfers">
                        <el-icon><Switch /></el-icon>
                        <template #title>License 转移</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/devices">
                        <el-icon><Monitor /></el-icon>
                        <template #title>设备管理</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/usage">
                        <el-icon><TrendCharts /></el-icon>
                        <template #title>用量统计</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/license-health">
                        <el-icon><CircleCheck /></el-icon>
                        <template #title>健康评分</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/billing">
                        <el-icon><Money /></el-icon>
                        <template #title>账单</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/invoices">
                        <el-icon><Document /></el-icon>
                        <template #title>发票</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/payment-methods">
                        <el-icon><CreditCard /></el-icon>
                        <template #title>支付方式</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/earnings">
                        <el-icon><Coin /></el-icon>
                        <template #title>收益</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/partner">
                        <el-icon><Connection /></el-icon>
                        <template #title>合作伙伴</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/affiliate">
                        <el-icon><Share /></el-icon>
                        <template #title>联盟推广</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/audit-log">
                        <el-icon><Document /></el-icon>
                        <template #title>操作日志</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/notifications">
                        <el-icon><Bell /></el-icon>
                        <template #title>通知</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/notification-preferences">
                        <el-icon><Setting /></el-icon>
                        <template #title>通知偏好</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/feedback">
                        <el-icon><ChatLineSquare /></el-icon>
                        <template #title>意见反馈</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/knowledge-base">
                        <el-icon><QuestionFilled /></el-icon>
                        <template #title>帮助</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/settings">
                        <el-icon><Setting /></el-icon>
                        <template #title>设置</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/data-exports">
                        <el-icon><Download /></el-icon>
                        <template #title>数据导出</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/team">
                        <el-icon><UserFilled /></el-icon>
                        <template #title>团队协作</template>
                    </el-menu-item>
                    <el-menu-item index="/portal/api-keys">
                        <el-icon><Key /></el-icon>
                        <template #title>API Keys</template>
                    </el-menu-item>
                </el-menu>
            </aside>

            <!-- 移动端侧栏遮罩 -->
            <div v-if="!sidebarCollapsed && isMobile" class="sidebar-overlay" @click="sidebarCollapsed = true"></div>

            <!-- 主内容区域 (WCAG: role=main) -->
            <main class="portal-main" id="main-content" role="main" aria-label="客户门户主内容" tabindex="-1">
                <div class="breadcrumb-bar" role="navigation" aria-label="当前位置">
                    <el-breadcrumb>
                        <el-breadcrumb-item :to="'/portal/dashboard'">首页</el-breadcrumb-item>
                        <el-breadcrumb-item v-if="route.meta?.title">{{ route.meta.title }}</el-breadcrumb-item>
                    </el-breadcrumb>
                </div>
                <div class="portal-content">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import {
    Fold, Expand, Key, ArrowDown, Setting, SwitchButton,
    DataAnalysis, Tickets, Monitor, TrendCharts, Money,
    CreditCard, Coin, Document, Bell, ChatLineSquare,
    QuestionFilled, CircleCheck, ShoppingCart, List,
    Present, Switch, Connection, Share, Download, UserFilled,
} from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const sidebarCollapsed = ref(false);
const isMobile = ref(window.innerWidth <= 768);

// 监听窗口变化
function checkMobile() {
    isMobile.value = window.innerWidth <= 768;
    if (!isMobile.value) sidebarCollapsed.value = false;
}
window.addEventListener('resize', checkMobile);

const branding = reactive({
    brand_name: '互物通 客户门户',
    primary_color: '#409eff',
    secondary_color: '#67c23a',
    sidebar_bg_color: '#304156',
    sidebar_text_color: '#bfcbd9',
    logo_url: '',
});

const portalStyle = computed(() => ({
    '--brand-primary': branding.primary_color || '#409eff',
    '--brand-sidebar-bg': branding.sidebar_bg_color || '#304156',
    '--brand-sidebar-text': branding.sidebar_text_color || '#bfcbd9',
}));

const activeMenu = computed(() => {
    return route.path.startsWith('/portal') ? route.path : '/portal/dashboard';
});

// 未读通知数
const unreadCount = ref(0);
let unreadTimer = null;

async function fetchUnreadCount() {
    try {
        const { data } = await apiClient.get('/notifications/unread-count');
        unreadCount.value = data?.data?.count || data?.count || 0;
    } catch { /* ignore */ }
}

onMounted(() => {
    loadBranding();
    authStore.fetchUser().catch(() => {});
    fetchUnreadCount();
    // 每30秒轮询未读数
    unreadTimer = setInterval(fetchUnreadCount, 30000);
});

onUnmounted(() => {
    if (unreadTimer) clearInterval(unreadTimer);
});

async function loadBranding() {
    try {
        const { data } = await apiClient.get('/branding', {
            params: { domain: window.location.hostname },
        });
        const config = data?.data?.config;
        if (config) {
            branding.brand_name = config.brand_name || '互物通 客户门户';
            branding.primary_color = config.primary_color || '#409eff';
            branding.secondary_color = config.secondary_color || '#67c23a';
            branding.sidebar_bg_color = config.sidebar_bg_color || '#304156';
            branding.sidebar_text_color = config.sidebar_text_color || '#bfcbd9';
            branding.logo_url = config.logo_url || '';

            // 更新页面标题
            if (config.brand_name) {
                document.title = config.brand_name;
            }

            // 更新 favicon
            if (config.favicon_url) {
                const link = document.querySelector('link[rel="icon"]') || document.createElement('link');
                link.rel = 'icon';
                link.href = config.favicon_url;
                document.head.appendChild(link);
            }

            // 应用 CSS 变量
            const cssVars = data?.data?.css_variables;
            if (cssVars) {
                const root = document.documentElement;
                Object.entries(cssVars).forEach(([key, val]) => {
                    root.style.setProperty(key, val);
                });
            }
        }
    } catch (e) {
        // 使用默认
    }
}

function handleCommand(command) {
    if (command === 'logout') {
        authStore.logout();
        router.push('/login');
    } else if (command === 'settings') {
        router.push('/portal/settings');
    }
}

onMounted(() => {
    loadBranding();
    // 刷新用户数据确保头像等信息最新
    authStore.fetchUser().catch(() => {});
});
</script>

<style scoped>
/* ── Portal Layout ── */
.portal-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: var(--brand-background, #f5f7fa);
}

.portal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
    padding: 0 24px;
    background: var(--brand-header-bg, #fff);
    border-bottom: 1px solid #ebeef5;
    flex-shrink: 0;
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.collapse-btn {
    padding: 6px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-logo-img {
    height: 32px;
    max-width: 120px;
    object-fit: contain;
}

.brand-text {
    font-size: 18px;
    font-weight: 600;
    color: var(--brand-text, #303133);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.2s;
}

.user-info:hover {
    background: var(--el-fill-color-light);
}

.user-name {
    font-size: 14px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.portal-body {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.portal-sidebar {
    width: 200px;
    flex-shrink: 0;
    overflow-y: auto;
    transition: width 0.3s;
}

.portal-sidebar.collapsed {
    width: 64px;
}

.portal-sidebar .el-menu {
    border-right: none;
}

.portal-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.breadcrumb-bar {
    padding: 12px 24px;
    background: #fff;
    border-bottom: 1px solid #ebeef5;
    flex-shrink: 0;
}

.portal-content {
    flex: 1;
    padding: 20px 24px;
    overflow-y: auto;
}

/* 通知铃铛 */
.notification-badge {
    margin-right: 8px;
}
.notification-badge :deep(.el-badge__content) {
    font-size: 10px;
    height: 16px;
    line-height: 16px;
    padding: 0 4px;
    border-width: 1px;
}

/* ── 移动端响应式适配 ── */
/* 移动端侧栏遮罩 */
.sidebar-overlay {
    display: none;
}
@media (max-width: 768px) {
    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.3);
        z-index: 98;
    }
    .portal-header {
        padding: 0 12px;
    }
    .brand-text {
        display: none;
    }
    .user-name {
        display: none;
    }
    .portal-sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        bottom: 0;
        z-index: 99;
        width: 200px;
        transform: translateX(0);
        transition: transform 0.3s ease;
        box-shadow: 2px 0 12px rgba(0,0,0,0.1);
    }
    .portal-sidebar.collapsed {
        transform: translateX(-100%);
        width: 200px;
    }
    .portal-sidebar:not(.collapsed) {
        /* overlay when expanded on mobile */
    }
    /* 点击内容区关闭侧栏的遮罩 */
    .portal-sidebar.collapsed + .portal-main::before {
        display: none;
    }
    .portal-main {
        margin-left: 0 !important;
    }
    .portal-content {
        padding: 12px;
    }
    .breadcrumb-bar {
        padding: 8px 12px;
    }
    /* Element Plus 表格在移动端可横向滚动 */
    .portal-content :deep(.el-table) {
        overflow-x: auto;
        display: block;
    }
    /* 卡片内边距缩减 */
    .portal-content :deep(.el-card__body) {
        padding: 12px;
    }
    /* 统计卡片在移动端改为两列 */
    .portal-content :deep(.el-row) {
        margin-left: -4px !important;
        margin-right: -4px !important;
    }
    .portal-content :deep(.el-col) {
        padding-left: 4px !important;
        padding-right: 4px !important;
    }
    /* 按钮文字适配 */
    .portal-content :deep(.el-button) {
        font-size: 12px;
    }
    /* 图表容器适配 */
    .portal-content :deep(.chart-container) {
        min-height: 180px !important;
    }
    /* 描述列表适配 */
    .portal-content :deep(.el-descriptions__cell) {
        padding: 6px 8px !important;
    }
}

/* 超小屏额外优化 */
@media (max-width: 480px) {
    .portal-content {
        padding: 8px;
    }
    .portal-content :deep(.el-card__body) {
        padding: 8px;
    }
    .portal-content :deep(.el-table .el-table__cell) {
        padding: 4px 6px !important;
    }
    .portal-content :deep(.el-table-column--selection .el-table__cell) {
        padding: 0 4px !important;
    }
    .portal-content :deep(.stat-value) {
        font-size: 20px !important;
    }
    .portal-content :deep(.mini-stat .mini-value) {
        font-size: 20px !important;
    }
}
</style>
