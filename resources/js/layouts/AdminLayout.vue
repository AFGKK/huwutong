<template>
    <div class="app-container">
        <el-container class="h-screen">
            <!-- 侧边栏 -->
            <el-aside :width="sidebarStore.sidebarCollapsed ? '64px' : '240px'" class="app-sidebar">
                <div class="sidebar-header">
                    <div class="logo" v-if="!sidebarStore.sidebarCollapsed">
                        <el-icon :size="28" color="#409eff"><Key /></el-icon>
                        <span class="logo-text">HWT License</span>
                    </div>
                    <div class="logo collapsed" v-else>
                        <el-icon :size="28" color="#409eff"><Key /></el-icon>
                    </div>
                </div>

                <!-- 租户选择器（侧边栏顶部） -->
                <div v-if="authStore.isMultiTenant && !sidebarStore.sidebarCollapsed" class="tenant-switcher-sidebar">
                    <el-select
                        v-model="currentTenantId"
                        size="small"
                        placeholder="切换租户"
                        @change="handleTenantSwitch"
                        class="tenant-select"
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

                <el-menu
                    :default-active="route.path"
                    :collapse="sidebarStore.sidebarCollapsed"
                    :router="true"
                    background-color="#1d1e1f"
                    text-color="#bfcbd9"
                    active-text-color="#409eff"
                    class="sidebar-menu"
                >
                    <template v-for="group in menuGroups" :key="group.label">
                        <el-sub-menu v-if="!sidebarStore.sidebarCollapsed && group.items.length > 1" :index="group.label">
                            <template #title>
                                <el-icon><component :is="group.icon" /></el-icon>
                                <span>{{ group.label }}</span>
                            </template>
                            <el-menu-item
                                v-for="item in group.items"
                                :key="item.path"
                                :index="item.path"
                            >
                                <el-icon><component :is="item.icon" /></el-icon>
                                <template #title>{{ item.title }}</template>
                            </el-menu-item>
                        </el-sub-menu>
                        <template v-else>
                            <el-menu-item
                                v-for="item in group.items"
                                :key="item.path"
                                :index="item.path"
                            >
                                <el-icon><component :is="item.icon" /></el-icon>
                                <template #title>{{ item.title }}</template>
                            </el-menu-item>
                        </template>
                    </template>
                </el-menu>
            </el-aside>

            <!-- 主区域 -->
            <el-container>
                <!-- 顶部导航 -->
                <el-header class="app-header">
                    <div class="header-left">
                        <el-button text @click="sidebarStore.toggleSidebar">
                            <el-icon :size="20">
                                <Fold v-if="!sidebarStore.sidebarCollapsed" />
                                <Expand v-else />
                            </el-icon>
                        </el-button>
                        <el-breadcrumb separator="/" class="ml-4">
                            <el-breadcrumb-item :to="{ path: '/dashboard' }">首页</el-breadcrumb-item>
                            <el-breadcrumb-item v-if="appStore.currentTitle !== '仪表盘'">
                                {{ appStore.currentTitle }}
                            </el-breadcrumb-item>
                        </el-breadcrumb>
                    </div>
                    <div class="header-right">
                        <!-- 多租户快速切换（下拉） -->
                        <el-dropdown
                            v-if="authStore.isMultiTenant"
                            trigger="click"
                            class="mr-4"
                            @command="handleTenantSwitch"
                        >
                            <el-button text>
                                <el-icon><OfficeBuilding /></el-icon>
                                <span class="ml-1">{{ authStore.activeTenantName || '切换租户' }}</span>
                                <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="t in authStore.tenants"
                                        :key="t.id"
                                        :command="t.id"
                                        :class="{ 'is-active': t.id === currentTenantId }"
                                    >
                                        <el-icon v-if="t.id === currentTenantId" color="#409eff">
                                            <CircleCheck />
                                        </el-icon>
                                        <span>{{ t.name }}</span>
                                    </el-dropdown-item>
                                    <el-dropdown-item divided command="manage">
                                        <el-icon><Setting /></el-icon>管理租户
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <NotificationBell />
                        <el-dropdown trigger="click" @command="handleCommand">
                            <span class="user-info">
                                <el-avatar :size="32" icon="UserFilled" />
                                <span class="ml-2">{{ authStore.userName || '用户' }}</span>
                                <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-if="isImpersonating" command="stop-impersonate" divided>
                                        <el-icon color="#e6a23c"><WarnTriangleFilled /></el-icon>退出模拟模式
                                    </el-dropdown-item>
                                    <el-dropdown-item v-else command="mfa">
                                        <el-icon><Lock /></el-icon>MFA 设置
                                    </el-dropdown-item>
                                    <el-dropdown-item divided command="logout">
                                        <el-icon><SwitchButton /></el-icon>退出登录
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </el-header>

                <!-- 系统公告横幅 -->
                <AnnounceBanner />

                <!-- 模拟模式提示横幅 -->
                <div v-if="isImpersonating" class="impersonate-banner">
                    <el-icon><WarnTriangleFilled /></el-icon>
                    <span>
                        模拟模式 — 你正以 <strong>{{ impersonateTarget }}</strong> 身份操作
                    </span>
                    <el-button size="small" type="warning" plain @click="handleStopImpersonate">
                        退出模拟
                    </el-button>
                </div>

                <!-- 内容区域 -->
                <el-main class="app-main">
                    <router-view />
                </el-main>
            </el-container>
        </el-container>
        <LiveChat />
        <CookieConsent />
    </div>
</template>

<script setup>
import LiveChat from '@/components/LiveChat.vue';
import { ref, computed, onErrorCaptured } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import NotificationBell from '@/components/NotificationBell.vue';
import AnnounceBanner from '@/components/AnnounceBanner.vue';
import CookieConsent from '@/components/CookieConsent.vue';
import errorReporter from '@/utils/errorReporter';
import { getImpersonateSession, stopImpersonate } from '@/api/impersonate';
import { ElMessage } from 'element-plus';
import { markRaw } from 'vue';
import {
    Fold, Expand, ArrowDown, SwitchButton,
    Key, Lock, UserFilled, OfficeBuilding,
    CircleCheck, Setting, Odometer, User, Goods,
    Monitor, Tickets, Coin, Document, Upload,
    MagicStick, EditPen, Connection, Reading,
    Bell, Link, Promotion, Refresh,
    TrendCharts, Message, Timer, ChatDotSquare,
    WarnTriangleFilled, DataBoard, List, Odometer,
} from '@element-plus/icons-vue';

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
    error.value = err?.message || '组件渲染异常';
    ElMessage.error({
        message: '页面部分组件加载异常，已自动恢复',
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

async function handleStopImpersonate() {
    if (!impersonateToken.value) return;
    try {
        await stopImpersonate(impersonateToken.value);
        clearImpersonateState();
        ElMessage.success('已退出模拟模式');
        // 刷新页面恢复原始身份
        window.location.reload();
    } catch (e) {
        clearImpersonateState();
        ElMessage.error('退出模拟失败');
    }
}

const menuGroups = [
    {
        label: '核心业务', icon: 'Odometer',
        items: [
            { path: '/dashboard', title: '仪表盘', icon: Odometer },
            { path: '/licenses', title: 'License 管理', icon: Key },
            { path: '/customers', title: '客户管理', icon: User },
            { path: '/products', title: '产品管理', icon: Goods },
            { path: '/devices', title: '设备管理', icon: Monitor },
            { path: '/tickets', title: '工单管理', icon: Tickets },
        ],
    },
    {
        label: '计费与订阅', icon: 'Coin',
        items: [
            { path: '/billing', title: '订阅计费', icon: Coin },
            { path: '/usage-meter', title: '用量计量', icon: DataBoard },
            { path: '/currency', title: '多币种定价', icon: Coin },
            { path: '/billing/retention', title: '续费流水线', icon: Connection },
            { path: '/tax', title: '税务管理', icon: Document },
            { path: '/license-files', title: 'License 文件分发', icon: Upload },
        ],
    },
    {
        label: '运营分析', icon: 'DataBoard',
        items: [
            { path: '/batch', title: '批量操作', icon: List },
            { path: '/sla', title: 'SLA 等级', icon: Odometer },
            { path: '/health-score', title: '客户健康度', icon: DataBoard },
        ],
    },
    {
        label: '开发者生态', icon: 'MagicStick',
        items: [
            { path: '/wizard', title: 'AI 集成向导', icon: MagicStick },
            { path: '/diagnostic', title: 'AI 错误诊断', icon: MagicStick },
            { path: '/ai-chat', title: 'AI 智能客服', icon: ChatDotSquare },
            { path: '/api-keys', title: 'API 密钥管理', icon: Key },
            { path: '/updates', title: '更新包分发', icon: Upload },
            { path: '/playground', title: 'API Playground', icon: Monitor },
            { path: '/sandbox', title: '开发者沙箱', icon: EditPen },
            { path: '/staging', title: 'Staging 环境', icon: Connection },
            { path: '/knowledge-base', title: '帮助中心', icon: Reading },
        ],
    },
    {
        label: '系统管理', icon: 'Setting',
        items: [
            { path: '/rbac', title: '权限管理', icon: Setting },
            { path: '/notifications', title: '通知中心', icon: Bell },
            { path: '/settings', title: '系统设置', icon: Setting },
            { path: '/pages', title: '页面管理', icon: Document },
            { path: '/feature-flags', title: '功能开关', icon: SwitchButton },
            { path: '/announce-banners', title: '系统公告', icon: Bell },
            { path: '/cookie-consent', title: 'Cookie 管理', icon: Bell },
            { path: '/sso', title: '单点登录', icon: Link },
            { path: '/sessions', title: '活跃会话', icon: Monitor },
            { path: '/device-trust', title: '信任设备', icon: Monitor },
            { path: '/password-policy', title: '密码策略', icon: Lock },
            { path: '/invite-codes', title: '邀请码管理', icon: Key },
            { path: '/legal-consents', title: '协议管理', icon: Document },
            { path: '/account-deletions', title: '注销审核', icon: Delete },
            { path: '/mfa', title: 'MFA 设置', icon: Lock },
            { path: '/domains', title: '自定义域名', icon: Link },
            { path: '/account/binding', title: '账号绑定', icon: Link },
            { path: '/trials', title: '试用管理', icon: Timer },
            { path: '/offline', title: '离线 License', icon: Connection },
        ],
    },
    {
        label: '安全与监控', icon: 'Lock',
        items: [
            { path: '/audit-logs', title: '审计日志', icon: Document },
            { path: '/merkle-chain', title: 'Merkle 验证链', icon: Connection },
            { path: '/webhook-endpoints', title: 'Webhook 端点', icon: Link },
            { path: '/webhook-events', title: 'Webhook 事件', icon: Promotion },
            { path: '/webhook-replay', title: 'Webhook 回放', icon: Refresh },
            { path: '/health', title: '系统健康', icon: Monitor },
            { path: '/deps-security', title: '依赖安全', icon: Lock },
            { path: '/account/login-history', title: '登录历史', icon: TrendCharts },
            { path: '/email-tracking', title: '邮件追踪', icon: TrendCharts },
            { path: '/email-templates', title: '邮件模板', icon: Message },
            { path: '/circuit-breaker', title: '断路器监控', icon: Monitor },
            { path: '/impersonate', title: '模拟登录', icon: Key },
        ],
    },
    {
        label: 'AI 与基础设施', icon: 'Connection',
        items: [
            { path: '/llm', title: '大模型管理', icon: Connection },
            { path: '/rag', title: 'RAG 知识库管理', icon: Reading },
            { path: '/openfeature', title: 'OpenFeature 标志', icon: SwitchButton },
            { path: '/global-resources', title: '全局资源白名单', icon: Key },
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
    } else if (command === 'mfa') {
        router.push('/mfa');
    } else if (command === 'stop-impersonate') {
        handleStopImpersonate();
    }
}
</script>

<style scoped>
.app-container {
    height: 100vh;
    overflow: hidden;
}

.app-sidebar {
    background: #1d1e1f;
    overflow-y: auto;
    transition: width 0.3s;
}

.sidebar-header {
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
    padding: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tenant-select {
    width: 100%;
}

.sidebar-menu {
    border-right: none;
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
</style>
