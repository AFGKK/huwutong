<template>
    <div class="piracy-trace-page">
        <h2>{{ t('piracy_trace_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalScans || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.total_scans') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.openCases || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.open_cases') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.confirmedLeaks || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.confirmed_leaks') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.resolvedCases || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.resolved_cases') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.falsePositives || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.false_positives') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.totalEvidence || 0 }}</div><div class="stat-label">{{ t('piracy_trace_page.stats.total_evidence') }}</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 扫描任务 -->
            <el-tab-pane :label="tabLabels.scans" name="scans">
                <div class="toolbar">
                    <el-button type="primary" @click="showScanDialog = true">{{ t('piracy_trace_page.scans.new_scan') }}</el-button>
                    <el-button @click="loadScans">{{ t('piracy_trace_page.scans.refresh') }}</el-button>
                </div>
                <el-table :data="scans" v-loading="scansLoading" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" :label="t('piracy_trace_page.scans.cols.source')" width="100">
                        <template #default="{row}"><el-tag size="small">{{ row.source }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="query" :label="t('piracy_trace_page.scans.cols.query')" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="status" :label="t('piracy_trace_page.scans.cols.status')" width="100">
                        <template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='running'?'warning':'info'" size="small">{{ scanStatusLabel(row.status) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="urls_found" :label="t('piracy_trace_page.scans.cols.urls_found')" width="90" align="center" />
                    <el-table-column prop="matches_found" :label="t('piracy_trace_page.scans.cols.matches')" width="70" align="center" />
                    <el-table-column prop="confirmed" :label="t('piracy_trace_page.scans.cols.confirmed')" width="70" align="center" />
                    <el-table-column :label="t('piracy_trace_page.scans.cols.actions')" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="primary" @click="handleRunScan(row)" :disabled="row.status==='running'">{{ t('piracy_trace_page.scans.run') }}</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t('piracy_trace_page.scans.cols.created_at')" width="170" />
                </el-table>

                <el-dialog v-model="showScanDialog" :title="t('piracy_trace_page.scans.dialog_title')" width="500px">
                    <el-form :model="scanForm" label-width="100px">
                        <el-form-item :label="t('piracy_trace_page.scans.source')"><el-select v-model="scanForm.source" style="width:100%"><el-option v-for="opt in scanSourceOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('piracy_trace_page.scans.query')"><el-input v-model="scanForm.query" type="textarea" :rows="4" :placeholder="t('piracy_trace_page.scans.query_ph')" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showScanDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleCreateScan">{{ t('actions.create') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 证据列表 -->
            <el-tab-pane :label="tabLabels.evidence" name="evidence">
                <div class="toolbar">
                    <el-select v-model="evFilter.status" :placeholder="t('piracy_trace_page.evidence.filter_status')" clearable style="width:130px;margin-right:8px" @change="loadEvidence"><el-option v-for="opt in evidenceStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select>
                    <el-select v-model="evFilter.confidence_level" :placeholder="t('piracy_trace_page.evidence.filter_confidence')" clearable style="width:120px;margin-right:8px" @change="loadEvidence"><el-option v-for="opt in confidenceOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select>
                    <el-button @click="loadEvidence">{{ t('piracy_trace_page.scans.refresh') }}</el-button>
                </div>
                <el-table :data="evidenceList" v-loading="evLoading" stripe @row-click="showEvidenceDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" :label="t('piracy_trace_page.evidence.cols.source')" width="90" />
                    <el-table-column prop="source_url" :label="t('piracy_trace_page.evidence.cols.source_url')" min-width="300" show-overflow-tooltip />
                    <el-table-column :label="t('piracy_trace_page.evidence.cols.confidence')" width="100">
                        <template #default="{row}"><el-tag :type="confidenceTag(row.confidence_level)" size="small">{{ confidenceLabel(row.confidence_level) }}({{ row.confidence }}%)</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="status" :label="t('piracy_trace_page.evidence.cols.status')" width="100">
                        <template #default="{row}"><el-tag :type="statusTag(row.status)" size="small">{{ evidenceStatusLabel(row.status) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="license_key" :label="t('piracy_trace_page.evidence.cols.license_key')" width="200" show-overflow-tooltip />
                    <el-table-column prop="detected_at" :label="t('piracy_trace_page.evidence.cols.detected_at')" width="170" />
                </el-table>
            </el-tab-pane>

            <!-- 证据详情 -->
            <el-tab-pane :label="tabLabels.detail" name="detail" :disabled="!selectedEvidence">
                <div v-if="selectedEvidence">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.source_url')" :span="2">{{ selectedEvidence.source_url }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.source')">{{ selectedEvidence.source }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.confidence')">{{ confidenceLabel(selectedEvidence.confidence_level) }}({{ selectedEvidence.confidence }}%)</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.status')">{{ evidenceStatusLabel(selectedEvidence.status) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.license_key')">{{ selectedEvidence.license_key || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.detected_at')">{{ selectedEvidence.detected_at }}</el-descriptions-item>
                    </el-descriptions>

                    <el-form :model="evUpdateForm" label-width="100px" style="margin-top:16px;max-width:600px">
                        <el-form-item :label="t('piracy_trace_page.detail.status')"><el-select v-model="evUpdateForm.status" style="width:100%"><el-option v-for="opt in evidenceStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('piracy_trace_page.detail.notes')"><el-input v-model="evUpdateForm.notes" type="textarea" :rows="3" /></el-form-item>
                        <el-form-item><el-button type="primary" @click="handleUpdateEvidence">{{ t('actions.update') }}</el-button></el-form-item>
                    </el-form>

                    <div style="margin-top:12px">
                        <el-button type="danger" @click="handleAutoRemediate" :disabled="selectedEvidence.status==='resolved'">{{ t('piracy_trace_page.detail.auto_remediate') }}</el-button>
                        <el-button type="warning" @click="handleGenerateReport">{{ t('piracy_trace_page.detail.generate_report') }}</el-button>
                    </div>
                </div>
                <el-empty v-else :description="t('piracy_trace_page.detail.empty_select')" />
            </el-tab-pane>

            <!-- 取证报告 -->
            <el-tab-pane :label="tabLabels.reports" name="reports">
                <el-table :data="reports" v-loading="reportsLoading" stripe @row-click="showReportDetail">
                    <el-table-column prop="title" :label="t('piracy_trace_page.reports.cols.title')" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="report_type" :label="t('piracy_trace_page.reports.cols.type')" width="100" />
                    <el-table-column prop="status" :label="t('piracy_trace_page.reports.cols.status')" width="90" />
                    <el-table-column :label="t('piracy_trace_page.reports.cols.generator')" width="120"><template #default="{row}">{{ row.generator?.name }}</template></el-table-column>
                    <el-table-column prop="generated_at" :label="t('piracy_trace_page.reports.cols.generated_at')" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="reportDialog.visible" :title="reportDialog.data?.title || t('piracy_trace_page.reports.dialog_title')" width="760px" top="5vh">
            <div v-loading="reportDialog.loading">
                <template v-if="reportDialog.data">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.report_type')">{{ reportDialog.data.report_type || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.status')">{{ reportDialog.data.status || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.generator')">{{ reportDialog.data.generator?.name || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.generated_at')">{{ reportDialog.data.generated_at || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.recommended_action')" :span="2">{{ reportDialog.data.recommended_action || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.analysis')" :span="2">
                            <pre class="report-pre">{{ formatJsonish(reportDialog.data.analysis) }}</pre>
                        </el-descriptions-item>
                    </el-descriptions>

                    <el-divider content-position="left">{{ t('piracy_trace_page.reports.linked_evidence') }}</el-divider>
                    <el-descriptions v-if="reportDialog.data.evidence" :column="2" border size="small">
                        <el-descriptions-item :label="t('piracy_trace_page.detail.source_url')" :span="2">{{ reportDialog.data.evidence.source_url || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.source')">{{ reportDialog.data.evidence.source || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.detail.status')">{{ evidenceStatusLabel(reportDialog.data.evidence.status) || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.license')">{{ reportDialog.data.evidence.license?.key || reportDialog.data.evidence.license_key || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('piracy_trace_page.reports.customer')">{{ reportDialog.data.evidence.license?.customer?.name || '-' }}</el-descriptions-item>
                    </el-descriptions>
                    <el-empty v-else :description="t('piracy_trace_page.reports.no_linked')" :image-size="64" />

                    <el-divider content-position="left">{{ t('piracy_trace_page.reports.evidence_items') }}</el-divider>
                    <el-table :data="asArray(reportDialog.data.evidence_items)" size="small" stripe :empty-text="t('piracy_trace_page.reports.empty_items')">
                        <el-table-column v-for="col in evidenceItemColumns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                    </el-table>

                    <el-divider content-position="left">{{ t('piracy_trace_page.reports.timeline') }}</el-divider>
                    <el-timeline v-if="asArray(reportDialog.data.timeline).length">
                        <el-timeline-item
                            v-for="(item, idx) in asArray(reportDialog.data.timeline)"
                            :key="idx"
                            :timestamp="item.at || item.time || item.timestamp || ''"
                        >
                            {{ item.event || item.description || formatJsonish(item) }}
                        </el-timeline-item>
                    </el-timeline>
                    <el-empty v-else :description="t('piracy_trace_page.reports.no_timeline')" :image-size="48" />

                    <el-divider content-position="left">{{ t('piracy_trace_page.reports.affected_licenses') }}</el-divider>
                    <el-tag
                        v-for="(lic, idx) in asArray(reportDialog.data.affected_licenses)"
                        :key="idx"
                        class="mr-2 mb-2"
                        type="warning"
                    >{{ typeof lic === 'object' ? (lic.key || lic.id || JSON.stringify(lic)) : lic }}</el-tag>
                    <span v-if="!asArray(reportDialog.data.affected_licenses).length" class="text-muted">{{ t('piracy_trace_page.reports.none') }}</span>
                </template>
            </div>
            <template #footer>
                <el-button @click="reportDialog.visible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getPiracyDashboard, getScanTasks, createScanTask, runScanTask, getEvidence, getEvidenceDetail, updateEvidence, autoRemediate, generateReport, getForensicReports, getReportDetail } from '@/api/piracyTrace';

const { t } = useI18n();

const activeTab = ref('scans');
const stats = ref({});
const reportDialog = reactive({ visible: false, loading: false, data: null });

const tabLabels = computed(() => ({
    scans: t('piracy_trace_page.tabs.scans'),
    evidence: t('piracy_trace_page.tabs.evidence'),
    detail: t('piracy_trace_page.tabs.detail'),
    reports: t('piracy_trace_page.tabs.reports'),
}));

const scanSourceOptions = computed(() => [
    { label: t('piracy_trace_page.scans.source_github'), value: 'github' },
    { label: t('piracy_trace_page.scans.source_manual'), value: 'manual' },
]);

const evidenceStatusOptions = computed(() => [
    { label: t('piracy_trace_page.evidence.status.open'), value: 'open' },
    { label: t('piracy_trace_page.evidence.status.investigating'), value: 'investigating' },
    { label: t('piracy_trace_page.evidence.status.confirmed'), value: 'confirmed' },
    { label: t('piracy_trace_page.evidence.status.false_positive'), value: 'false_positive' },
    { label: t('piracy_trace_page.evidence.status.resolved'), value: 'resolved' },
]);

const confidenceOptions = computed(() => [
    { label: t('piracy_trace_page.evidence.confidence.confirmed'), value: 'confirmed' },
    { label: t('piracy_trace_page.evidence.confidence.high'), value: 'high' },
    { label: t('piracy_trace_page.evidence.confidence.medium'), value: 'medium' },
    { label: t('piracy_trace_page.evidence.confidence.low'), value: 'low' },
]);

const scanStatusLabels = computed(() => ({
    completed: t('piracy_trace_page.scans.status.completed'),
    running: t('piracy_trace_page.scans.status.running'),
    pending: t('piracy_trace_page.scans.status.pending'),
    failed: t('piracy_trace_page.scans.status.failed'),
}));

const evidenceStatusLabels = computed(() => ({
    open: t('piracy_trace_page.evidence.status.open'),
    investigating: t('piracy_trace_page.evidence.status.investigating'),
    confirmed: t('piracy_trace_page.evidence.status.confirmed'),
    false_positive: t('piracy_trace_page.evidence.status.false_positive'),
    resolved: t('piracy_trace_page.evidence.status.resolved'),
}));

const confidenceLabels = computed(() => ({
    confirmed: t('piracy_trace_page.evidence.confidence.confirmed'),
    high: t('piracy_trace_page.evidence.confidence.high'),
    medium: t('piracy_trace_page.evidence.confidence.medium'),
    low: t('piracy_trace_page.evidence.confidence.low'),
}));

function scanStatusLabel(status) {
    return scanStatusLabels.value[status] || status;
}
function evidenceStatusLabel(status) {
    return evidenceStatusLabels.value[status] || status;
}
function confidenceLabel(level) {
    return confidenceLabels.value[level] || level;
}

function asArray(v) {
    if (!v) return [];
    if (Array.isArray(v)) return v;
    if (typeof v === 'object') return Object.values(v);
    return [];
}
function formatJsonish(v) {
    if (v == null || v === '') return '-';
    if (typeof v === 'string') return v;
    try { return JSON.stringify(v, null, 2); } catch { return String(v); }
}
const evidenceItemColumns = computed(() => {
    const rows = asArray(reportDialog.data?.evidence_items);
    if (!rows.length || typeof rows[0] !== 'object') return ['value'];
    return Object.keys(rows[0]).slice(0, 6);
});

// Scans
const scans = ref([]);
const scansLoading = ref(false);
const showScanDialog = ref(false);
const scanForm = reactive({ source: 'github', query: '' });

// Evidence
const evidenceList = ref([]);
const evLoading = ref(false);
const evFilter = reactive({ status: 'open', confidence_level: '' });
const selectedEvidence = ref(null);
const evUpdateForm = reactive({ status: 'open', notes: '' });

// Reports
const reports = ref([]);
const reportsLoading = ref(false);

const confidenceTag = (l) => ({ confirmed: 'danger', high: 'warning', medium: 'info', low: 'success' }[l] || 'info');
const statusTag = (s) => ({ open: 'danger', investigating: 'warning', confirmed: 'danger', resolved: 'success', false_positive: 'info' }[s] || 'info');

async function loadDashboard() {
    try { stats.value = await getPiracyDashboard(); } catch (e) { console.error(e); }
}
async function loadScans() {
    scansLoading.value = true;
    try { const r = await getScanTasks({ per_page: 50 }); scans.value = r.data || []; }
    catch (e) { console.error(e); } finally { scansLoading.value = false; }
}
async function loadEvidence() {
    evLoading.value = true;
    try { const r = await getEvidence({ status: evFilter.status || undefined, confidence_level: evFilter.confidence_level || undefined, per_page: 50 }); evidenceList.value = r.data || []; }
    catch (e) { console.error(e); } finally { evLoading.value = false; }
}
async function loadReports() {
    reportsLoading.value = true;
    try { const r = await getForensicReports({ per_page: 50 }); reports.value = r.data || []; }
    catch (e) { console.error(e); } finally { reportsLoading.value = false; }
}
async function handleCreateScan() {
    try { await createScanTask(scanForm); ElMessage.success(t('piracy_trace_page.messages.scan_created')); showScanDialog.value = false; loadScans(); }
    catch (e) { ElMessage.error(t('piracy_trace_page.messages.create_failed')); }
}
async function handleRunScan(row) {
    try { await runScanTask(row.id); ElMessage.success(t('piracy_trace_page.messages.scan_completed')); loadScans(); loadDashboard(); loadEvidence(); }
    catch (e) { ElMessage.error(t('piracy_trace_page.messages.scan_failed')); }
}
async function showEvidenceDetail(row) {
    try { const r = await getEvidenceDetail(row.id); selectedEvidence.value = r; evUpdateForm.status = r.status; evUpdateForm.notes = r.notes || ''; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error(t('piracy_trace_page.messages.load_detail_failed')); }
}
async function handleUpdateEvidence() {
    try { await updateEvidence(selectedEvidence.value.id, evUpdateForm); ElMessage.success(t('piracy_trace_page.messages.updated')); loadEvidence(); }
    catch (e) { ElMessage.error(t('piracy_trace_page.messages.update_failed')); }
}
async function handleAutoRemediate() {
    try {
        await ElMessageBox.confirm(
            t('piracy_trace_page.confirm.auto_remediate'),
            t('piracy_trace_page.confirm.auto_remediate_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await autoRemediate(selectedEvidence.value.id);
        ElMessage.success(t('piracy_trace_page.messages.auto_remediate_done'));
        loadDashboard();
        loadEvidence();
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('piracy_trace_page.messages.remediate_failed')); }
}
async function handleGenerateReport() {
    try { await generateReport(selectedEvidence.value.id); ElMessage.success(t('piracy_trace_page.messages.report_generated')); loadReports(); }
    catch (e) { ElMessage.error(t('piracy_trace_page.messages.generate_failed')); }
}
async function showReportDetail(row) {
    reportDialog.visible = true;
    reportDialog.loading = true;
    reportDialog.data = null;
    try {
        const r = await getReportDetail(row.id);
        const body = r?.data?.data ?? r?.data ?? r;
        reportDialog.data = body || row;
    } catch (e) {
        reportDialog.data = row;
        ElMessage.error(t('piracy_trace_page.messages.report_detail_fallback'));
    } finally {
        reportDialog.loading = false;
    }
}

onMounted(() => { loadDashboard(); loadScans(); loadEvidence(); loadReports(); });
</script>

<style scoped>
.piracy-trace-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
.report-pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: inherit; font-size: 13px; }
.mr-2 { margin-right: 8px; }
.mb-2 { margin-bottom: 8px; }
.text-muted { color: #909399; font-size: 13px; }
</style>
