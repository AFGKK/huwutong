<template>
    <div class="webhooks-page">
        <div class="page-header">
            <div>
                <h2>Webhook 管理</h2>
                <p class="header-desc text-sm text-gray-500">管理 Webhook 端点、事件、回放、监控、模拟器和过滤器</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <!-- 📊 监控 -->
            <el-tab-pane label="📊 监控" name="monitor">
                <WebhookMonitor />
            </el-tab-pane>

            <!-- 🔗 端点 -->
            <el-tab-pane label="🔗 端点" name="endpoints">
                <WebhookEndpoints />
            </el-tab-pane>

            <!-- 📋 事件 -->
            <el-tab-pane label="📋 事件" name="events">
                <WebhookEvents />
            </el-tab-pane>

            <!-- 🔄 回放 -->
            <el-tab-pane label="🔄 回放" name="replay">
                <WebhookReplay />
            </el-tab-pane>

            <!-- 🧪 模拟器 -->
            <el-tab-pane label="🧪 模拟器" name="simulator">
                <WebhookSimulator />
            </el-tab-pane>

            <!-- 🔍 过滤器 -->
            <el-tab-pane label="🔍 过滤器" name="filter">
                <WebhookFilter />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const activeTab = ref(route.query.tab || 'monitor');

const WebhookMonitor = () => import('@/views/webhook-monitor/Index.vue');
const WebhookEndpoints = () => import('@/views/webhook/Endpoints.vue');
const WebhookEvents = () => import('@/views/webhooks/Events.vue');
const WebhookReplay = () => import('@/views/webhook/Index.vue');
const WebhookSimulator = () => import('@/views/webhook/simulator/Index.vue');
const WebhookFilter = () => import('@/views/webhook-filter/Index.vue');

function onTabChange(tab) {
    router.replace({ query: { tab } });
}
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 {
    margin: 0;
}
</style>
