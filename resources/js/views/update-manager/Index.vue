<template>
    <div class="update-page">
        <div class="page-header"><div><h2>更新管理</h2><p class="header-desc text-sm text-gray-500">更新包分发、签名与灰度、CDN 分发</p></div></div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📦 更新包" name="updates"><UpdatePackages /></el-tab-pane>
            <el-tab-pane label="✍️ 签名与灰度" name="signer"><UpdateSigner /></el-tab-pane>
            <el-tab-pane label="🌐 CDN 分发" name="cdn"><UpdateCdn /></el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref } from 'vue'; import { useRoute, useRouter } from 'vue-router';
const route = useRoute(); const router = useRouter();
const activeTab = ref(route.query.tab || 'updates');
const UpdatePackages = () => import('@/views/updates/Index.vue');
const UpdateSigner = () => import('@/views/update-signer/Index.vue');
const UpdateCdn = () => import('@/views/update-cdn/Index.vue');
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
