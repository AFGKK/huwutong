<template>
    <div class="portal-container">
        <!-- 顶部导航 -->
        <header class="portal-header">
            <div class="header-left">
                <el-button text @click="sidebarCollapsed = !sidebarCollapsed" class="collapse-btn">
                    <el-icon :size="20"><Fold v-if="!sidebarCollapsed" /><Expand v-else /></el-icon>
                </el-button>
                <div class="brand">
                    <el-icon :size="28" color="#409eff"><Key /></el-icon>
                    <span class="brand-text" v-if="!sidebarCollapsed">互物通 客户门户</span>
                </div>
            </div>
            <div class="header-right">
                <el-dropdown @command="handleCommand" trigger="click">
                    <span class="user-info">
                        <el-avatar :size="32" icon="UserFilled" />
                        <span class="user-name">{{ authStore.userName || '客户用户' }}</span>
                        <el-icon><ArrowDown /></el-icon>
                    </span>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="settings">
                                <el-icon><Setting /></el-icon> 个人设置
                            </el-dropdown-item>
                            <el-dropdown-item divided command="logout">
                                <el-icon><SwitchButton /></el-icon> 退出登录
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </header>

        <div class="portal-body">
            <!-- 侧边栏 -->
            <aside class="portal-sidebar" :class="{ collapsed: sidebarCollapsed }">
                <el-menu
                    :default-active="route.path"
                    :collapse="sidebarCollapsed"
                    :router="true"
                    background-color="#fff"
                    text-color="#303133"
                    active-text-color="#409eff"
                >
                    <template v-for="item in menuItems" :key="item.path">
                        <el-menu-item v-if="!item.hidden" :index="item.path">
                            <el-icon><component :is="item.icon" /></el-icon>
                            <template #title>{{ item.title }}</template>
                        </el-menu-item>
                    </template>
                </el-menu>
            </aside>

            <!-- 主内容 -->
            <main class="portal-main">
                <div class="breadcrumb-bar">
                    <el-breadcrumb>
                        <el-breadcrumb-item :to="'/'">首页</el-breadcrumb-item>
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
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
    UserFilled, ArrowDown, Setting, SwitchButton,
    Fold, Expand, Key,
    Odometer, Tickets, Goods, Monitor, Bell,
    CreditCard, TrendCharts, Document, Notification, Reading,
} from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const sidebarCollapsed = ref(false);

const menuItems = [
    { path: '/portal', title: '我的仪表盘', icon: Odometer },
    { path: '/portal/tickets', title: '我的工单', icon: Tickets },
    { path: '/portal/licenses', title: '我的 License', icon: Key },
    { path: '/portal/devices', title: '我的设备', icon: Monitor },
    { path: '/portal/usage', title: '用量看板', icon: TrendCharts },
    { path: '/portal/billing', title: '账单与发票', icon: Goods },
    { path: '/portal/payment-methods', title: '支付方式', icon: CreditCard },
    { path: '/portal/audit-log', title: '审计日志', icon: Document },
    { path: '/portal/notification-preferences', title: '通知偏好', icon: Notification },
    { path: '/portal/knowledge-base', title: '帮助中心', icon: Reading },
    { path: '/portal/settings', title: '个人设置', icon: Setting },
];

function handleCommand(command) {
    if (command === 'logout') {
        authStore.logout().then(() => {
            router.push('/login');
        });
    } else if (command === 'settings') {
        router.push('/portal/settings');
    }
}
</script>

<style scoped>
.portal-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f7fa;
}

.portal-header {
    height: 56px;
    background: #fff;
    border-bottom: 1px solid #e4e7ed;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    flex-shrink: 0;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
}

.brand-text {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
}

.collapse-btn {
    padding: 8px;
}

.header-right {
    display: flex;
    align-items: center;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.user-info:hover {
    background: #f5f7fa;
}

.user-name {
    font-size: 14px;
    color: #303133;
}

.portal-body {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.portal-sidebar {
    width: 220px;
    background: #fff;
    border-right: 1px solid #e4e7ed;
    transition: width 0.3s;
    flex-shrink: 0;
    overflow-y: auto;
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
</style>
