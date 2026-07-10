<template>
    <div class="compliance-page">
        <div class="page-header"><div><h2>合规中心</h2><p class="header-desc text-sm text-gray-500">GDPR、PIPL、SOC2、AI 合规框架管理</p></div></div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📋 SOC2 / ISO27001" name="main"><ComplianceMain /></el-tab-pane>
            <el-tab-pane label="🇪🇺 GDPR" name="gdpr"><GdprCompliance /></el-tab-pane>
            <el-tab-pane label="🇪🇺 GDPR 增强" name="gdpr-enhance"><GdprEnhancement /></el-tab-pane>
            <el-tab-pane label="🇨🇳 PIPL" name="pipl"><PipCompliance /></el-tab-pane>
            <el-tab-pane label="🤖 AI 合规" name="ai"><AiCompliance /></el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref } from 'vue'; import { useRoute, useRouter } from 'vue-router';
const route = useRoute(); const router = useRouter();
const activeTab = ref(route.query.tab || 'main');
const ComplianceMain = () => import('@/views/compliance/Index.vue');
const GdprCompliance = () => import('@/views/gdpr-compliance/Index.vue');
const GdprEnhancement = () => import('@/views/gdpr-compliance/Enhancement.vue');
const PipCompliance = () => import('@/views/pipl-compliance/Index.vue');
const AiCompliance = () => import('@/views/ai-compliance/Index.vue');
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
