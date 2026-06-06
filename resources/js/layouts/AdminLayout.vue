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
                                    <el-dropdown-item command="mfa">
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

                <!-- 内容区域 -->
                <el-main class="app-main">
                    <router-view />
                </el-main>
            </el-container>
        </el-container>
        <LiveChat />
    </div>
</template>

<script setup>
import LiveChat from '@/components/LiveChat.vue';
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import NotificationBell from '@/components/NotificationBell.vue';
import {
    Fold, Expand, ArrowDown, SwitchButton,
    Key, Lock, UserFilled, OfficeBuilding,
    CircleCheck, Setting, Odometer, User, Goods,
    Monitor, Tickets, Coin, Document, Upload,
    MagicStick, EditPen, Connection, Reading,
    Bell, Link, Promotion, Refresh,
    TrendCharts, Message, Timer, ChatDotSquare,
} from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const appStore = useAppStore();
const sidebarStore = appStore;

const currentTenantId = computed({
    get: () => authStore.activeTenantId,
    set: (val) => handleTenantSwitch(val),
});

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
            { path: '/billing/retention', title: '续费流水线', icon: Connection },
            { path: '/tax', title: '税务管理', icon: Document },
            { path: '/license-files', title: 'License 文件分发', icon: Upload },
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
            { path: '/sso', title: '单点登录', icon: Link },
            { path: '/sessions', title: '活跃会话', icon: Monitor },
            { path: '/invite-codes', title: '邀请码管理', icon: Key },
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
            { path: '/webhook-events', title: 'Webhook 事件', icon: Promotion },
            { path: '/webhook-replay', title: 'Webhook 回放', icon: Refresh },
            { path: '/health', title: '系统健康', icon: Monitor },
            { path: '/deps-security', title: '依赖安全', icon: Lock },
            { path: '/account/login-history', title: '登录历史', icon: TrendCharts },
            { path: '/email-tracking', title: '邮件追踪', icon: TrendCharts },
            { path: '/email-templates', title: '邮件模板', icon: Message },
        ],
    },
    {
        label: 'AI 与基础设施', icon: 'Connection',
        items: [
            { path: '/llm', title: '大模型管理', icon: Connection },
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

.ml-1 {
    margin-left: 4px;
}
</style>
