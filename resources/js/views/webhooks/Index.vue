<template>
    <div class="webhooks-page">
        <div class="page-header">
            <div>
                <h2>{{ t('webhooks_page.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('webhooks_page.subtitle') }}</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="tabLabels.monitor" name="monitor">
                <WebhookMonitor />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.endpoints" name="endpoints">
                <WebhookEndpoints />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.events" name="events">
                <WebhookEvents />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.replay" name="replay">
                <WebhookReplay />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.simulator" name="simulator">
                <WebhookSimulator />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.filter" name="filter">
                <WebhookFilter />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const activeTab = ref(route.query.tab || 'monitor');

const WebhookMonitor = defineAsyncComponent(() => import('@/views/webhook-monitor/Index.vue'));
const WebhookEndpoints = defineAsyncComponent(() => import('@/views/webhook/Endpoints.vue'));
const WebhookEvents = defineAsyncComponent(() => import('@/views/webhooks/Events.vue'));
const WebhookReplay = defineAsyncComponent(() => import('@/views/webhook/Index.vue'));
const WebhookSimulator = defineAsyncComponent(() => import('@/views/webhook/simulator/Index.vue'));
const WebhookFilter = defineAsyncComponent(() => import('@/views/webhook-filter/Index.vue'));

const tabKeys = ['monitor', 'endpoints', 'events', 'replay', 'simulator', 'filter'];

const tabLabels = computed(() =>
    Object.fromEntries(tabKeys.map((key) => [key, t(`webhooks_page.tabs.${key}`)]))
);

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
