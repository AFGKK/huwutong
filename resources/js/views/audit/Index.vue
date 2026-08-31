<template>
    <div class="audit-page">
        <div class="page-header">
            <div>
                <h2>{{ t('audit_center.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('audit_center.desc') }}</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('audit_center.tabs.governance')" name="governance">
                <AuditGovernance />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.logs')" name="logs">
                <AuditLogs />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.visualization')" name="visualization">
                <AuditVisualization />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.export')" name="export">
                <AuditExport />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.archive')" name="archive">
                <AuditArchive />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.retention')" name="retention">
                <AuditRetention />
            </el-tab-pane>
            <el-tab-pane :label="t('audit_center.tabs.retention_audit')" name="retention-audit">
                <RetentionAudit />
            </el-tab-pane>
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

const activeTab = ref(route.query.tab || 'governance');

const AuditGovernance = defineAsyncComponent(() => import('@/views/audit-governance/Index.vue'));
const AuditLogs = defineAsyncComponent(() => import('@/views/audit-logs/Index.vue'));
const AuditVisualization = defineAsyncComponent(() => import('@/views/audit-visualization/Index.vue'));
const AuditExport = defineAsyncComponent(() => import('@/views/audit-export/Index.vue'));
const AuditArchive = defineAsyncComponent(() => import('@/views/audit-archive/Index.vue'));
const AuditRetention = defineAsyncComponent(() => import('@/views/audit-retention/Index.vue'));
const RetentionAudit = defineAsyncComponent(() => import('@/views/retention-audit/Index.vue'));

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
