<template>
    <div class="data-page">
        <div class="page-header"><div><h2>数据管理</h2><p class="header-desc text-sm text-gray-500">数据本地化、匿名化导出、协议管理、多区域合规</p></div></div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📄 协议管理" name="consents"><LegalConsents /></el-tab-pane>
            <el-tab-pane label="🌍 数据本地化" name="residency"><DataResidency /></el-tab-pane>
            <el-tab-pane label="🕶️ 数据匿名化" name="anonymization"><DataAnonymization /></el-tab-pane>
            <el-tab-pane label="🌐 多区域合规" name="regional"><RegionalCompliance /></el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref } from 'vue'; import { useRoute, useRouter } from 'vue-router';
const route = useRoute(); const router = useRouter();
const activeTab = ref(route.query.tab || 'consents');
const LegalConsents = () => import('@/views/legal-consent/Index.vue');
const DataResidency = () => import('@/views/data-residency/Index.vue');
const DataAnonymization = () => import('@/views/system/DataAnonymization.vue');
const RegionalCompliance = () => import('@/views/regional-compliance/Index.vue');
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
