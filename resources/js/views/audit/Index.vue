<template>
    <div class="audit-page">
        <div class="page-header">
            <div>
                <h2>审计中心</h2>
                <p class="header-desc text-sm text-gray-500">审计日志、治理、导出、归档、可视化、保留策略</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📊 治理概览" name="governance">
                <AuditGovernance />
            </el-tab-pane>
            <el-tab-pane label="📋 审计日志" name="logs">
                <AuditLogs />
            </el-tab-pane>
            <el-tab-pane label="📈 可视化" name="visualization">
                <AuditVisualization />
            </el-tab-pane>
            <el-tab-pane label="📤 导出" name="export">
                <AuditExport />
            </el-tab-pane>
            <el-tab-pane label="🗄️ 归档" name="archive">
                <AuditArchive />
            </el-tab-pane>
            <el-tab-pane label="📐 保留策略" name="retention">
                <AuditRetention />
            </el-tab-pane>
            <el-tab-pane label="⏱ 保留审计" name="retention-audit">
                <RetentionAudit />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const activeTab = ref(route.query.tab || 'governance');

const AuditGovernance = () => import('@/views/audit-governance/Index.vue');
const AuditLogs = () => import('@/views/audit-logs/Index.vue');
const AuditVisualization = () => import('@/views/audit-visualization/Index.vue');
const AuditExport = () => import('@/views/audit-export/Index.vue');
const AuditArchive = () => import('@/views/audit-archive/Index.vue');
const AuditRetention = () => import('@/views/audit-retention/Index.vue');
const RetentionAudit = () => import('@/views/retention-audit/Index.vue');

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
.page-header h2 { margin: 0; }
</style>
