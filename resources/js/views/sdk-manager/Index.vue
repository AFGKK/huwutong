<template>
    <div class="sdk-page">
        <div class="page-header"><div><h2>SDK 管理</h2><p class="header-desc text-sm text-gray-500">SDK 开发工具包、版本兼容、完整性自检、本地缓存</p></div></div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📦 工具包" name="sdk"><SdkManager /></el-tab-pane>
            <el-tab-pane label="🔖 版本兼容" name="version"><SdkVersion /></el-tab-pane>
            <el-tab-pane label="🔐 完整性" name="integrity"><SdkIntegrity /></el-tab-pane>
            <el-tab-pane label="💾 本地缓存" name="cache"><SdkCache /></el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref } from 'vue'; import { useRoute, useRouter } from 'vue-router';
const route = useRoute(); const router = useRouter();
const activeTab = ref(route.query.tab || 'sdk');
const SdkManager = () => import('@/views/sdk/Index.vue');
const SdkVersion = () => import('@/views/sdk-version/Index.vue');
const SdkIntegrity = () => import('@/views/sdk-integrity/Index.vue');
const SdkCache = () => import('@/views/sdk-cache/Index.vue');
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
