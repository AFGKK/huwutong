<template>
    <div class="audit-governance-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('audit_governance_page.title') }}</h2>
                <span class="header-subtitle">{{ t('audit_governance_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> {{ t('audit_governance_page.refresh') }}
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('audit_governance_page.stats.frameworks') }}</div>
                        <div class="stat-value primary">{{ dashboard.frameworks?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('audit_governance_page.stats.log_tags') }}</div>
                        <div class="stat-value success">{{ dashboard.tag_stats?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('audit_governance_page.stats.annotations') }}</div>
                        <div class="stat-value warning">{{ dashboard.total_annotations || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('audit_governance_page.stats.cleanups') }}</div>
                        <div class="stat-value info">{{ dashboard.total_cleanups || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 主标签页 -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- 合规报告 -->
            <el-tab-pane :label="tabLabels.compliance" name="compliance">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="complianceFilter.framework_id" :placeholder="t('audit_governance_page.compliance.select_framework')" clearable style="width: 200px" @change="fetchReports">
                            <el-option v-for="fw in dashboard.frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                        </el-select>
                        <el-select v-model="complianceFilter.status" :placeholder="t('audit_governance_page.compliance.report_status')" clearable style="width: 140px" @change="fetchReports">
                            <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openGenerateDialog">
                            <el-icon><Plus /></el-icon> {{ t('audit_governance_page.compliance.generate_report') }}
                        </el-button>
                        <el-button @click="handleSeedFrameworks" :loading="seeding">
                            {{ t('audit_governance_page.compliance.seed_frameworks') }}
                        </el-button>
                    </div>
                </div>

                <el-table :data="reports" v-loading="loadingReports" stripe>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.title')" min-width="200">
                        <template #default="{ row }">
                            <el-button text @click="showReportDetail(row)">{{ row.title }}</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.framework')" width="140">
                        <template #default="{ row }">{{ row.framework?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.period')" width="200">
                        <template #default="{ row }">{{ row.period_start }} ~ {{ row.period_end }}</template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.pass_rate')" width="120" align="center">
                        <template #default="{ row }">
                            <el-progress
                                :percentage="calcPassRate(row)"
                                :status="calcPassRate(row) >= 80 ? 'success' : calcPassRate(row) >= 50 ? 'warning' : 'exception'"
                                :stroke-width="16"
                                :text-inside="true"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.risk_level')" width="110">
                        <template #default="{ row }">
                            <el-tag :type="riskTag(row.risk_level)" size="small">
                                {{ riskLabel(row.risk_level) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'generated' ? 'success' : row.status === 'draft' ? 'info' : 'danger'" size="small">
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.generated_at')" width="170">
                        <template #default="{ row }">{{ formatTime(row.generated_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.compliance.cols.actions')" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="showReportDetail(row)">{{ t('actions.view_details') }}</el-button>
                            <el-popconfirm :title="t('audit_governance_page.compliance.delete_confirm')" @confirm="handleDeleteReport(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="reportMeta.total > reportMeta.per_page">
                    <el-pagination
                        v-model:current-page="reportPage"
                        :page-size="reportMeta.per_page"
                        :total="reportMeta.total"
                        layout="total, prev, pager, next"
                        @current-change="fetchReports"
                    />
                </div>

                <!-- 生成报告对话框 -->
                <el-dialog v-model="showGenerateDialog" :title="t('audit_governance_page.compliance.generate_dialog_title')" width="550px">
                    <el-form ref="genFormRef" :model="genForm" :rules="genRules" label-width="120px">
                        <el-form-item :label="t('audit_governance_page.compliance.form.framework')" prop="framework_id">
                            <el-select v-model="genForm.framework_id" :placeholder="t('audit_governance_page.compliance.select_framework')" style="width: 100%">
                                <el-option v-for="fw in dashboard.frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('audit_governance_page.compliance.form.title')">
                            <el-input v-model="genForm.title" :placeholder="t('audit_governance_page.compliance.form.title_auto')" />
                        </el-form-item>
                        <el-form-item :label="t('audit_governance_page.compliance.form.type')">
                            <el-select v-model="genForm.type" style="width: 100%">
                                <el-option v-for="opt in reportTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('audit_governance_page.compliance.form.period_start')">
                            <el-date-picker v-model="genForm.period_start" type="date" :placeholder="t('audit_governance_page.compliance.form.period_start')" style="width: 100%" value-format="YYYY-MM-DD" />
                        </el-form-item>
                        <el-form-item :label="t('audit_governance_page.compliance.form.period_end')">
                            <el-date-picker v-model="genForm.period_end" type="date" :placeholder="t('audit_governance_page.compliance.form.period_end')" style="width: 100%" value-format="YYYY-MM-DD" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showGenerateDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="generating" @click="handleGenerate">{{ t('audit_governance_page.compliance.generate') }}</el-button>
                    </template>
                </el-dialog>

                <!-- 报告详情对话框 -->
                <el-dialog v-model="showReportDialog" :title="reportDetail?.title || t('audit_governance_page.compliance.report_detail')" width="800px">
                    <template v-if="reportDetail">
                        <el-descriptions :column="2" border size="small">
                            <el-descriptions-item :label="t('audit_governance_page.compliance.form.framework')">{{ reportDetail.framework?.name }}</el-descriptions-item>
                            <el-descriptions-item :label="t('audit_governance_page.compliance.cols.risk_level')">
                                <el-tag :type="riskTag(reportDetail.risk_level)" size="small">{{ riskLabel(reportDetail.risk_level) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('audit_governance_page.compliance.cols.period')">{{ reportDetail.period_start }} ~ {{ reportDetail.period_end }}</el-descriptions-item>
                            <el-descriptions-item :label="t('audit_governance_page.compliance.cols.status')">
                                <el-tag :type="reportDetail.status === 'generated' ? 'success' : 'info'" size="small">
                                    {{ statusLabel(reportDetail.status) }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('audit_governance_page.compliance.detail.pass_fail_na')">
                                {{ reportDetail.passed_count }} / {{ reportDetail.failed_count }} / {{ reportDetail.na_count }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('audit_governance_page.compliance.cols.generated_at')">{{ formatTime(reportDetail.generated_at) }}</el-descriptions-item>
                        </el-descriptions>

                        <div class="detail-section">
                            <h4>{{ t('audit_governance_page.compliance.detail.summary') }}</h4>
                            <p>{{ reportDetail.summary || t('audit_governance_page.compliance.detail.no_summary') }}</p>
                        </div>

                        <div v-if="reportDetail.findings?.length" class="detail-section">
                            <h4>{{ t('audit_governance_page.compliance.detail.findings', { n: reportDetail.findings.length }) }}</h4>
                            <el-table :data="reportDetail.findings" stripe size="small">
                                <el-table-column :label="t('audit_governance_page.compliance.detail.domain')" prop="domain" width="150" />
                                <el-table-column :label="t('audit_governance_page.compliance.cols.status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'pass' ? 'success' : row.status === 'warn' ? 'warning' : 'danger'" size="small">
                                            {{ findingStatusLabel(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('audit_governance_page.compliance.detail.description')" prop="description" min-width="200" />
                                <el-table-column :label="t('audit_governance_page.compliance.detail.related_events')" width="120">
                                    <template #default="{ row }">{{ row.details?.total_events || 0 }}</template>
                                </el-table-column>
                            </el-table>
                        </div>

                        <div v-if="reportDetail.evidence_refs" class="detail-section">
                            <h4>{{ t('audit_governance_page.compliance.detail.evidence') }}</h4>
                            <el-descriptions :column="3" border size="small">
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.total_logs')">{{ reportDetail.evidence_refs.total_logs }}</el-descriptions-item>
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.audit_logs')">{{ reportDetail.evidence_refs.audit_logs }}</el-descriptions-item>
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.security_logs')">{{ reportDetail.evidence_refs.security_logs }}</el-descriptions-item>
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.error_logs')">{{ reportDetail.evidence_refs.error_logs }}</el-descriptions-item>
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.merkle_anchors')">{{ reportDetail.evidence_refs.merkle_anchors }}</el-descriptions-item>
                                <el-descriptions-item :label="t('audit_governance_page.compliance.detail.date_range')">{{ reportDetail.evidence_refs.date_range?.from }} ~ {{ reportDetail.evidence_refs.date_range?.to }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 审计标签 -->
            <el-tab-pane :label="tabLabels.tags" name="tags">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">{{ t('audit_governance_page.tags.toolbar_title') }}</span>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openTagDialog()">
                            <el-icon><Plus /></el-icon> {{ t('audit_governance_page.tags.new_tag') }}
                        </el-button>
                    </div>
                </div>

                <el-table :data="tags" v-loading="loadingTags" stripe>
                    <el-table-column :label="t('audit_governance_page.tags.cols.tag')" min-width="200">
                        <template #default="{ row }">
                            <el-tag :color="row.color" :style="{ color: isLightColor(row.color) ? '#333' : '#fff' }" effect="dark">
                                {{ row.name }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.tags.cols.color')" width="100">
                        <template #default="{ row }">
                            <el-color-picker v-model="row.color" :predefine="predefineColors" size="small" @change="(val) => handleUpdateTag(row, { color: val })" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('audit_governance_page.tags.cols.usage')" width="100" align="center" prop="logs_count" />
                    <el-table-column :label="t('audit_governance_page.tags.cols.actions')" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openTagDialog(row)">{{ t('actions.edit') }}</el-button>
                            <el-popconfirm :title="t('audit_governance_page.tags.delete_confirm')" @confirm="handleDeleteTag(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 标签编辑对话框 -->
                <el-dialog v-model="showTagDialog" :title="editingTagId ? t('audit_governance_page.tags.edit_tag') : t('audit_governance_page.tags.new_tag')" width="420px">
                    <el-form ref="tagFormRef" :model="tagForm" :rules="tagRules" label-width="80px">
                        <el-form-item :label="t('audit_governance_page.tags.form.name')" prop="name">
                            <el-input v-model="tagForm.name" maxlength="100" />
                        </el-form-item>
                        <el-form-item :label="t('audit_governance_page.tags.form.color')">
                            <el-color-picker v-model="tagForm.color" :predefine="predefineColors" show-alpha />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showTagDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="savingTag" @click="handleSaveTag">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 数据保留治理 -->
            <el-tab-pane :label="tabLabels.retention" name="retention">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('audit_governance_page.stats.total_logs') }}</div>
                                <div class="stat-value primary">{{ retentionDashboard.total_logs || 0 }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('audit_governance_page.stats.storage') }}</div>
                                <div class="stat-value warning">{{ retentionDashboard.total_storage_mb || 0 }} MB</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <div class="stat-row">
                                <span class="stat-label-sm">{{ t('audit_governance_page.retention.pending_by_type') }}</span>
                                <span v-for="item in retentionDashboard.by_type" :key="item.type" class="type-badge">
                                    <el-tag
                                        size="small"
                                        :type="item.to_prune > 0 ? 'danger' : 'success'"
                                        effect="plain"
                                        style="margin-right:4px"
                                    >
                                        {{ t('audit_governance_page.retention.type_pending', { type: typeLabel(item.type), count: item.to_prune }) }}
                                    </el-tag>
                                </span>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="16">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('audit_governance_page.retention.distribution_title') }}</span>
                                </div>
                            </template>
                            <el-table :data="retentionDashboard.by_type" v-loading="loadingRetention" stripe size="small">
                                <el-table-column :label="t('audit_governance_page.retention.cols.type')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('audit_governance_page.retention.cols.count')" width="90" align="center" prop="count" />
                                <el-table-column :label="t('audit_governance_page.retention.cols.retention_days')" width="100" align="center" prop="retention_days" />
                                <el-table-column :label="t('audit_governance_page.retention.cols.to_prune')" width="90" align="center">
                                    <template #default="{ row }">
                                        <span :class="{ 'text-danger': row.to_prune > 0 }">{{ row.to_prune }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('audit_governance_page.retention.cols.storage_mb')" width="100" align="center" prop="storage_mb" />
                                <el-table-column :label="t('audit_governance_page.retention.cols.oldest')" width="150" prop="oldest" />
                                <el-table-column :label="t('audit_governance_page.retention.cols.actions')" width="140">
                                    <template #default="{ row }">
                                        <el-button
                                            text
                                            size="small"
                                            type="danger"
                                            :loading="cleaningType === row.type"
                                            @click="handleCleanup(row.type)"
                                        >
                                            {{ t('audit_governance_page.retention.cleanup_now') }}
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('audit_governance_page.retention.history_title') }}</span>
                                </div>
                            </template>
                            <div v-if="!cleanupHistory.length" class="empty-state">{{ t('audit_governance_page.retention.no_history') }}</div>
                            <div v-for="item in cleanupHistory" :key="item.id" class="history-item">
                                <div class="history-header">
                                    <el-tag :type="typeTag(item.type)" size="small">{{ typeLabel(item.type) }}</el-tag>
                                    <el-tag :type="item.status === 'completed' ? 'success' : item.status === 'partial' ? 'warning' : 'danger'" size="small">
                                        {{ cleanupStatusLabel(item.status) }}
                                    </el-tag>
                                </div>
                                <div class="history-body">
                                    {{ t('audit_governance_page.retention.history_body', { count: item.pruned_count, before: item.total_logs_before, after: item.total_logs_after }) }}
                                </div>
                                <div class="history-time">{{ formatTime(item.executed_at) }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import auditGovernanceApi from '@/api/auditGovernance';

const { t, locale } = useI18n();

// ─── 标签 ───
const activeTab = ref('compliance');

const tabLabels = computed(() => ({
    compliance: t('audit_governance_page.tabs.compliance'),
    tags: t('audit_governance_page.tabs.tags'),
    retention: t('audit_governance_page.tabs.retention'),
}));

const statusFilterOptions = computed(() => [
    { label: t('audit_governance_page.status.draft'), value: 'draft' },
    { label: t('audit_governance_page.status.generated'), value: 'generated' },
    { label: t('audit_governance_page.status.failed'), value: 'failed' },
]);

const reportTypeOptions = computed(() => [
    { label: t('audit_governance_page.report_types.on_demand'), value: 'on_demand' },
    { label: t('audit_governance_page.report_types.scheduled'), value: 'scheduled' },
]);

const riskLabels = computed(() => ({
    low: t('audit_governance_page.risk.low'),
    medium: t('audit_governance_page.risk.medium'),
    high: t('audit_governance_page.risk.high'),
    critical: t('audit_governance_page.risk.critical'),
}));

const statusLabels = computed(() => ({
    draft: t('audit_governance_page.status.draft'),
    generated: t('audit_governance_page.status.generated'),
    failed: t('audit_governance_page.status.failed'),
}));

const findingStatusLabels = computed(() => ({
    pass: t('audit_governance_page.finding_status.pass'),
    warn: t('audit_governance_page.finding_status.warn'),
    fail: t('audit_governance_page.finding_status.fail'),
}));

const typeLabels = computed(() => ({
    audit: t('audit_governance_page.log_types.audit'),
    security: t('audit_governance_page.log_types.security'),
    error: t('audit_governance_page.log_types.error'),
    system: t('audit_governance_page.log_types.system'),
}));

const cleanupStatusLabels = computed(() => ({
    completed: t('audit_governance_page.cleanup_status.completed'),
    partial: t('audit_governance_page.cleanup_status.partial'),
    failed: t('audit_governance_page.cleanup_status.failed'),
}));

const genRules = computed(() => ({
    framework_id: [{ required: true, message: t('audit_governance_page.rules.framework_required'), trigger: 'change' }],
}));

const tagRules = computed(() => ({
    name: [{ required: true, message: t('audit_governance_page.rules.tag_name_required'), trigger: 'blur' }],
}));

// ─── 概览仪表盘 ───
const dashboard = reactive({
    frameworks: [],
    tag_stats: [],
    total_annotations: 0,
    total_batch_ops: 0,
    total_cleanups: 0,
});

async function fetchDashboard() {
    try {
        const res = await auditGovernanceApi.governanceDashboard();
        if (res.success) Object.assign(dashboard, res.data || {});
    } catch { /* ignore */ }
}

// ─── 合规报告 ───
const reports = ref([]);
const reportMeta = reactive({ total: 0, per_page: 20, current_page: 1 });
const reportPage = ref(1);
const loadingReports = ref(false);
const seeding = ref(false);
const showGenerateDialog = ref(false);
const generating = ref(false);
const showReportDialog = ref(false);
const reportDetail = ref(null);
const genFormRef = ref(null);
const complianceFilter = reactive({
    framework_id: '',
    status: '',
});
const genForm = reactive({
    framework_id: '',
    title: '',
    type: 'on_demand',
    period_start: '',
    period_end: '',
});

async function fetchReports() {
    loadingReports.value = true;
    try {
        const params = { page: reportPage.value, per_page: 20 };
        if (complianceFilter.framework_id) params.framework_id = complianceFilter.framework_id;
        if (complianceFilter.status) params.status = complianceFilter.status;
        const res = await auditGovernanceApi.reports(params);
        if (res.success) {
            reports.value = res.data || [];
            Object.assign(reportMeta, res.meta || {});
        }
    } catch {
        ElMessage.error(t('audit_governance_page.messages.load_reports_failed'));
    } finally {
        loadingReports.value = false;
    }
}

async function handleSeedFrameworks() {
    seeding.value = true;
    try {
        const res = await auditGovernanceApi.seedFrameworks();
        if (res.success) {
            ElMessage.success(t('audit_governance_page.messages.frameworks_seeded'));
            await fetchDashboard();
        }
    } catch {
        ElMessage.error(t('audit_governance_page.messages.seed_failed'));
    } finally {
        seeding.value = false;
    }
}

function openGenerateDialog() {
    genForm.framework_id = '';
    genForm.title = '';
    genForm.type = 'on_demand';
    genForm.period_start = '';
    genForm.period_end = '';
    showGenerateDialog.value = true;
}

async function handleGenerate() {
    if (!genFormRef.value) return;
    const valid = await genFormRef.value.validate().catch(() => false);
    if (!valid) return;

    generating.value = true;
    try {
        const data = { ...genForm };
        // 过滤空值
        Object.keys(data).forEach(k => { if (!data[k]) delete data[k]; });
        const res = await auditGovernanceApi.generateReport(data);
        if (res.success) {
            ElMessage.success(t('audit_governance_page.messages.report_generated'));
            showGenerateDialog.value = false;
            await fetchReports();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('audit_governance_page.messages.generate_failed'));
    } finally {
        generating.value = false;
    }
}

function showReportDetail(row) {
    if (row.findings || row.evidence_refs) {
        reportDetail.value = row;
    } else {
        // 如果列表数据不完整，从 API 获取详情
        auditGovernanceApi.showReport(row.id).then(res => {
            reportDetail.value = res.data || row;
        }).catch(() => {
            reportDetail.value = row;
        });
    }
    showReportDialog.value = true;
}

async function handleDeleteReport(row) {
    try {
        const res = await auditGovernanceApi.deleteReport(row.id);
        if (res.success) {
            ElMessage.success(t('audit_governance_page.messages.report_deleted'));
            await fetchReports();
        }
    } catch {
        ElMessage.error(t('audit_governance_page.messages.delete_failed'));
    }
}

function calcPassRate(row) {
    const total = row.passed_count + row.failed_count;
    if (!total) return 0;
    return Math.round((row.passed_count / total) * 100);
}

// ─── 标签管理 ───
const tags = ref([]);
const loadingTags = ref(false);
const showTagDialog = ref(false);
const editingTagId = ref(null);
const savingTag = ref(false);
const tagFormRef = ref(null);
const tagForm = reactive({ name: '', color: '#0f172a' });
const predefineColors = [
    '#0f172a', '#67C23A', '#E6A23C', '#F56C6C', '#909399',
    '#B37FEB', '#36CFC9', '#FF85C0', '#FF9F43', '#2E86DE',
];

async function fetchTags() {
    loadingTags.value = true;
    try {
        const res = await auditGovernanceApi.tags();
        if (res.success) tags.value = res.data || [];
    } catch {
        ElMessage.error(t('audit_governance_page.messages.load_tags_failed'));
    } finally {
        loadingTags.value = false;
    }
}

function openTagDialog(row) {
    if (row) {
        editingTagId.value = row.id;
        tagForm.name = row.name;
        tagForm.color = row.color || '#0f172a';
    } else {
        editingTagId.value = null;
        tagForm.name = '';
        tagForm.color = '#0f172a';
    }
    showTagDialog.value = true;
}

async function handleSaveTag() {
    if (!tagFormRef.value) return;
    const valid = await tagFormRef.value.validate().catch(() => false);
    if (!valid) return;

    savingTag.value = true;
    try {
        if (editingTagId.value) {
            await auditGovernanceApi.updateTag(editingTagId.value, { name: tagForm.name, color: tagForm.color });
            ElMessage.success(t('audit_governance_page.messages.tag_updated'));
        } else {
            await auditGovernanceApi.createTag({ name: tagForm.name, color: tagForm.color });
            ElMessage.success(t('audit_governance_page.messages.tag_created'));
        }
        showTagDialog.value = false;
        await fetchTags();
        await fetchDashboard();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        savingTag.value = false;
    }
}

async function handleDeleteTag(row) {
    try {
        await auditGovernanceApi.deleteTag(row.id);
        ElMessage.success(t('audit_governance_page.messages.tag_deleted'));
        await fetchTags();
        await fetchDashboard();
    } catch {
        ElMessage.error(t('audit_governance_page.messages.delete_failed'));
    }
}

async function handleUpdateTag(row, patch) {
    try {
        await auditGovernanceApi.updateTag(row.id, patch);
    } catch {
        ElMessage.error(t('audit_governance_page.messages.update_failed'));
    }
}

// ─── 数据保留治理 ───
const retentionDashboard = reactive({
    by_type: [], recent_cleanups: [], storage_trend: {}, total_logs: 0, total_storage_mb: 0,
});
const loadingRetention = ref(false);
const cleanupHistory = ref([]);
const cleaningType = ref('');

async function fetchRetentionDashboard() {
    loadingRetention.value = true;
    try {
        const res = await auditGovernanceApi.retentionDashboard();
        if (res.success) {
            Object.assign(retentionDashboard, res.data || {});
        }
    } catch {
        ElMessage.error(t('audit_governance_page.messages.load_retention_failed'));
    } finally {
        loadingRetention.value = false;
    }
}

async function fetchCleanupHistory() {
    try {
        const res = await auditGovernanceApi.cleanupHistory();
        if (res.success) cleanupHistory.value = res.data || [];
    } catch { /* ignore */ }
}

async function handleCleanup(type) {
    try {
        await ElMessageBox.confirm(
            t('audit_governance_page.confirm.cleanup', { type: typeLabel(type) }),
            t('audit_governance_page.confirm.cleanup_title'),
            {
                type: 'warning',
                confirmButtonText: t('audit_governance_page.confirm.cleanup_confirm'),
                cancelButtonText: t('actions.cancel'),
            },
        );
    } catch { return; }

    cleaningType.value = type;
    try {
        const res = await auditGovernanceApi.executeCleanup({ type });
        if (res.success) {
            ElMessage.success(t('audit_governance_page.messages.cleanup_done', { count: res.data?.pruned_count || 0 }));
            await fetchRetentionDashboard();
            await fetchCleanupHistory();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('audit_governance_page.messages.cleanup_failed'));
    } finally {
        cleaningType.value = '';
    }
}

// ─── 工具函数 ───
function riskLabel(level) {
    return riskLabels.value[level] || level || t('audit_governance_page.risk.unknown');
}

function riskTag(level) {
    const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
    return map[level] || 'info';
}

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function findingStatusLabel(status) {
    return findingStatusLabels.value[status] || status;
}

function typeLabel(type) {
    return typeLabels.value[type] || type;
}

function cleanupStatusLabel(status) {
    return cleanupStatusLabels.value[status] || status;
}

function typeTag(type) {
    const map = { audit: 'primary', security: 'danger', error: 'warning', system: 'info' };
    return map[type] || 'info';
}

function formatTime(time) {
    if (!time) return '—';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(loc);
}

function isLightColor(hex) {
    if (!hex) return true;
    const c = hex.replace('#', '');
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 > 155;
}

async function refreshAll() {
    await Promise.all([
        fetchDashboard(),
        fetchReports(),
        fetchTags(),
        fetchRetentionDashboard(),
        fetchCleanupHistory(),
    ]);
}

onMounted(async () => {
    await fetchDashboard();
    await fetchReports();
    await fetchTags();
    await fetchRetentionDashboard();
    await fetchCleanupHistory();
});
</script>

<style scoped>
.audit-governance-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.primary { color: var(--el-color-primary); }
.stat-value.success { color: var(--el-color-success); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.info { color: var(--el-color-info); }

.stat-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
}
.stat-label-sm {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    white-space: nowrap;
}
.type-badge { display: inline-flex; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header span { font-weight: 600; font-size: 14px; }

.tab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 12px;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}
.toolbar-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--el-text-color-regular);
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.detail-section { margin-top: 20px; }
.detail-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px;
}
.detail-section p {
    font-size: 14px;
    line-height: 1.6;
    color: var(--el-text-color-regular);
}

.text-danger { color: var(--el-color-danger); font-weight: 600; }

.history-item {
    padding: 10px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
}
.history-item:last-child { border-bottom: none; }
.history-header {
    display: flex;
    gap: 6px;
    margin-bottom: 4px;
}
.history-body { font-size: 13px; color: var(--el-text-color-regular); }
.history-time { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 2px; }

.empty-state {
    text-align: center;
    padding: 30px 0;
    color: var(--el-text-color-placeholder);
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
