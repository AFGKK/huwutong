<template>
    <div class="compliance-ai-page">
        <h2>{{ t('compliance_ai_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">{{ t('compliance_ai_page.stats.total_reports') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.completed || 0 }}</div><div class="stat-label">{{ t('compliance_ai_page.stats.completed') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.failed || 0 }}</div><div class="stat-label">{{ t('compliance_ai_page.stats.failed') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card">
                <el-select v-model="genFramework" style="width:100%">
                    <el-option v-for="opt in frameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button type="primary" size="small" style="margin-top:8px;width:100%" @click="handleGenerate" :loading="generating">{{ t('compliance_ai_page.btn_generate') }}</el-button>
            </div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('compliance_ai_page.tabs.list')" name="list">
                <el-table :data="reports" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" :label="t('compliance_ai_page.cols.id')" width="60" />
                    <el-table-column :label="t('compliance_ai_page.cols.framework')" width="100"><template #default="{row}"><el-tag size="small">{{ row.framework?.toUpperCase() }}</el-tag></template></el-table-column>
                    <el-table-column prop="title" :label="t('compliance_ai_page.cols.title')" min-width="250" show-overflow-tooltip />
                    <el-table-column :label="t('compliance_ai_page.cols.status')" width="100"><template #default="{row}"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('compliance_ai_page.cols.generator')" width="120"><template #default="{row}">{{ row.generator?.name }}</template></el-table-column>
                    <el-table-column prop="created_at" :label="t('compliance_ai_page.cols.created_at')" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="t('compliance_ai_page.tabs.detail')" name="detail" :disabled="!selectedReport">
                <div v-if="selectedReport">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('compliance_ai_page.detail.title')" :span="2">{{ selectedReport.title }}</el-descriptions-item>
                        <el-descriptions-item :label="t('compliance_ai_page.detail.framework')">{{ selectedReport.framework?.toUpperCase() }}</el-descriptions-item>
                        <el-descriptions-item :label="t('compliance_ai_page.detail.status')">{{ statusLabel(selectedReport.status) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('compliance_ai_page.detail.language')">{{ selectedReport.language }}</el-descriptions-item>
                        <el-descriptions-item :label="t('compliance_ai_page.detail.generated_at')">{{ selectedReport.generated_at }}</el-descriptions-item>
                    </el-descriptions>

                    <!-- 证据统计 -->
                    <el-card shadow="never" style="margin-top:16px">
                        <template #header><span>{{ t('compliance_ai_page.evidence.title') }}</span></template>
                        <div v-if="selectedReport.evidence_summary">
                            <el-tag style="margin-right:6px">{{ t('compliance_ai_page.evidence.total') }}: {{ selectedReport.evidence_summary.total_items }}</el-tag>
                            <el-tag type="success" style="margin-right:6px">{{ t('compliance_ai_page.evidence.compliant') }}: {{ selectedReport.evidence_summary.compliant }}</el-tag>
                            <el-tag type="warning" style="margin-right:6px">{{ t('compliance_ai_page.evidence.partial') }}: {{ selectedReport.evidence_summary.partial }}</el-tag>
                            <el-tag type="danger" style="margin-right:6px">{{ t('compliance_ai_page.evidence.non_compliant') }}: {{ selectedReport.evidence_summary.non_compliant }}</el-tag>
                            <el-tag type="info">{{ t('compliance_ai_page.evidence.not_applicable') }}: {{ selectedReport.evidence_summary.not_applicable }}</el-tag>
                        </div>
                    </el-card>

                    <!-- 章节内容 -->
                    <el-card shadow="never" style="margin-top:16px">
                        <template #header><span>{{ t('compliance_ai_page.sections.title') }}</span></template>
                        <el-timeline v-if="selectedReport.sections?.length">
                            <el-timeline-item
                                v-for="sec in selectedReport.sections"
                                :key="sec.section"
                                :color="sec.status==='compliant'?'#67c23a':sec.status==='partial'?'#e6a23c':'#f56c6c'"
                            >
                                <div style="font-weight:600">{{ sec.section }}</div>
                                <div style="font-size:13px;color:#606266;margin-top:4px">{{ sec.description }}</div>
                            </el-timeline-item>
                        </el-timeline>
                        <el-empty v-else :description="t('compliance_ai_page.sections.empty')" />
                    </el-card>

                    <!-- 差距分析 -->
                    <el-card v-if="selectedReport.gap_analysis" shadow="never" style="margin-top:16px">
                        <template #header><span>{{ t('compliance_ai_page.gap_analysis.title') }}</span></template>
                        <p>{{ selectedReport.gap_analysis.summary }}</p>
                    </el-card>

                    <!-- 改进建议 -->
                    <el-card v-if="selectedReport.recommendations?.length" shadow="never" style="margin-top:16px">
                        <template #header><span>{{ t('compliance_ai_page.recommendations.title') }}</span></template>
                        <el-timeline>
                            <el-timeline-item
                                v-for="(rec, i) in selectedReport.recommendations"
                                :key="i"
                                :color="rec.priority==='high'?'#f56c6c':rec.priority==='medium'?'#e6a23c':'#909399'"
                            >
                                <div style="font-weight:600">{{ rec.action }}</div>
                                <el-tag :type="rec.priority==='high'?'danger':rec.priority==='medium'?'warning':'info'" size="small" style="margin-top:4px">{{ priorityLabel(rec.priority) }}</el-tag>
                            </el-timeline-item>
                        </el-timeline>
                    </el-card>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getComplianceReportDashboard, getComplianceReports, generateComplianceReport, getComplianceReportDetail } from '@/api/complianceReportAi';

const { t } = useI18n();

const frameworkKeys = ['gdpr', 'soc2', 'iso27001'];
const statusKeys = ['generating', 'completed', 'failed'];
const priorityKeys = ['high', 'medium', 'low'];

const frameworkOptions = computed(() =>
    frameworkKeys.map((value) => ({
        value,
        label: t(`compliance_ai_page.frameworks.${value}`),
    }))
);

const statusMap = computed(() =>
    Object.fromEntries(statusKeys.map((key) => [key, t(`compliance_ai_page.status.${key}`)]))
);

const priorityMap = computed(() =>
    Object.fromEntries(priorityKeys.map((key) => [key, t(`compliance_ai_page.priority.${key}`)]))
);

const statusTag = (s) => ({ generating: 'warning', completed: 'success', failed: 'danger' }[s] || 'info');

function statusLabel(status) {
    return statusMap.value[status] || status;
}

function priorityLabel(priority) {
    return priorityMap.value[priority] || priority;
}

const activeTab = ref('list');
const stats = ref({});
const reports = ref([]);
const loading = ref(false);
const generating = ref(false);
const selectedReport = ref(null);
const genFramework = ref('gdpr');

async function loadDashboard() {
    try { stats.value = await getComplianceReportDashboard(); } catch (e) { console.error(e); }
}
async function loadReports() {
    loading.value = true;
    try { const r = await getComplianceReports({ per_page: 50 }); reports.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleGenerate() {
    generating.value = true;
    try {
        const r = await generateComplianceReport(genFramework.value, 'zh-CN');
        ElMessage.success(t('compliance_ai_page.messages.generate_done'));
        loadReports();
        loadDashboard();
        selectedReport.value = r;
        activeTab.value = 'detail';
    } catch (e) { ElMessage.error(t('compliance_ai_page.messages.generate_failed')); } finally { generating.value = false; }
}
async function showDetail(row) {
    try { const r = await getComplianceReportDetail(row.id); selectedReport.value = r; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error(t('messages.load_failed')); }
}

onMounted(() => { loadDashboard(); loadReports(); });
</script>

<style scoped>
.compliance-ai-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
