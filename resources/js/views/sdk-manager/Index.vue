<template>
    <div class="sdk-page">
        <div class="page-header">
            <div>
                <h2>{{ t('sdk_manager_page.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('sdk_manager_page.subtitle') }}</p>
            </div>
        </div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('sdk_manager_page.tabs.sdk')" name="sdk"><SdkManager /></el-tab-pane>
            <el-tab-pane :label="t('sdk_manager_page.tabs.version')" name="version"><SdkVersion /></el-tab-pane>
            <el-tab-pane :label="t('sdk_manager_page.tabs.integrity')" name="integrity"><SdkIntegrity /></el-tab-pane>
            <el-tab-pane :label="t('sdk_manager_page.tabs.cache')" name="cache"><SdkCache /></el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref, defineAsyncComponent } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const activeTab = ref(route.query.tab || 'sdk');
const SdkManager = defineAsyncComponent(() => import('@/views/sdk/Index.vue'));
const SdkVersion = defineAsyncComponent(() => import('@/views/sdk-version/Index.vue'));
const SdkIntegrity = defineAsyncComponent(() => import('@/views/sdk-integrity/Index.vue'));
const SdkCache = defineAsyncComponent(() => import('@/views/sdk-cache/Index.vue'));
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
