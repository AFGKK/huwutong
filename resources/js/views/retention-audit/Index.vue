<template>
    <div class="retention-audit-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('retention_audit_page.title') }}</h2>
                <span class="header-subtitle">{{ t('retention_audit_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> {{ t('retention_audit_page.refresh') }}
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('retention_audit_page.stats.sources') }}</div>
                        <div class="stat-value primary">{{ dashboard.by_source?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('retention_audit_page.stats.total_records') }}</div>
                        <div class="stat-value success">{{ formatNumber(dashboard.total_records) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('retention_audit_page.stats.storage') }}</div>
                        <div class="stat-value warning">{{ dashboard.total_storage_mb }} MB</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('retention_audit_page.stats.cleanups') }}</div>
                        <div class="stat-value info">{{ dashboard.recent_cleanups?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 保留策略管理 -->
            <el-tab-pane :label="tabLabels.policies" name="policies">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">{{ t('retention_audit_page.policies.toolbar_title') }}</span>
                    </div>
                </div>

                <el-table :data="dashboard.by_source" v-loading="loading" stripe size="small">
                    <el-table-column :label="t('retention_audit_page.policies.cols.data_source')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.data_source)" size="small">{{ row.display_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.policies.cols.count')" width="100" align="center" prop="count" />
                    <el-table-column :label="t('retention_audit_page.policies.cols.retention_days')" width="110" align="center" prop="retention_days" />
                    <el-table-column :label="t('retention_audit_page.policies.cols.to_prune')" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.to_prune > 0 ? 'danger' : 'success'" size="small" effect="plain">
                                {{ row.to_prune }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.policies.cols.storage_mb')" width="110" align="center" prop="storage_mb" />
                    <el-table-column :label="t('retention_audit_page.policies.cols.oldest')" width="150">
                        <template #default="{ row }">{{ formatTime(row.oldest) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.policies.cols.actions')" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openPolicyEdit(row)">{{ t('retention_audit_page.policies.edit_policy') }}</el-button>
                            <el-button
                                text
                                size="small"
                                type="danger"
                                :disabled="row.to_prune === 0"
                                :loading="cleaningSource === row.data_source"
                                @click="handleExtendedCleanup(row)"
                            >
                                {{ t('retention_audit_page.policies.cleanup_now') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 清理调度配置 -->
            <el-tab-pane :label="tabLabels.schedules" name="schedules">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">{{ t('retention_audit_page.schedules.toolbar_title') }}</span>
                    </div>
                </div>

                <el-table :data="schedules" v-loading="loadingSchedules" stripe size="small">
                    <el-table-column :label="t('retention_audit_page.schedules.cols.data_source')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.data_source)" size="small">{{ row.display_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.schedules.cols.frequency')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.frequency === 'manual' ? 'info' : 'primary'" size="small">
                                {{ freqLabel(row.frequency) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.schedules.cols.time_of_day')" width="100" align="center" prop="time_of_day" />
                    <el-table-column :label="t('retention_audit_page.schedules.cols.batch_size')" width="100" align="center" prop="batch_size" />
                    <el-table-column :label="t('retention_audit_page.schedules.cols.status')" width="90" align="center">
                        <template #default="{ row }">
                            <el-switch
                                :model-value="row.is_active"
                                @change="(val) => handleToggleSchedule(row, val)"
                                size="small"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.schedules.cols.last_run_at')" width="150" prop="last_run_at">
                        <template #default="{ row }">{{ formatTime(row.last_run_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.schedules.cols.actions')" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openScheduleEdit(row)">{{ t('actions.edit') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 合规报告导出 -->
            <el-tab-pane :label="tabLabels.exports" name="exports">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="exportFilter.report_id" :placeholder="t('retention_audit_page.exports.select_report')" clearable style="width: 300px" @change="fetchExports">
                            <el-option v-for="r in allReports" :key="r.id" :label="r.title" :value="r.id" />
                        </el-select>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openExportDialog">
                            <el-icon><Download /></el-icon> {{ t('retention_audit_page.exports.export_report') }}
                        </el-button>
                    </div>
                </div>

                <el-table :data="exports" v-loading="loadingExports" stripe size="small">
                    <el-table-column :label="t('retention_audit_page.exports.cols.title')" min-width="200">
                        <template #default="{ row }">{{ row.report?.title || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.exports.cols.format')" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.format === 'json' ? 'warning' : 'success'" size="small" effect="plain">
                                {{ row.format?.toUpperCase() }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.exports.cols.status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="exportStatusTag(row.status)" size="small">
                                {{ exportStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.exports.cols.file_size')" width="100" align="center">
                        <template #default="{ row }">{{ row.file_size ? formatBytes(row.file_size) : '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.exports.cols.generator')" width="120" prop="generator?.name" />
                    <el-table-column :label="t('retention_audit_page.exports.cols.generated_at')" width="170">
                        <template #default="{ row }">{{ formatTime(row.generated_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.exports.cols.actions')" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'completed'" text size="small" type="primary" @click="downloadExport(row)">
                                {{ t('actions.download') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 清理历史 -->
            <el-tab-pane :label="tabLabels.history" name="history">
                <el-table :data="cleanupHistory" v-loading="loadingHistory" stripe size="small">
                    <el-table-column :label="t('retention_audit_page.history.cols.data_source')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.type || row.data_source)" size="small">
                                {{ sourceLabel(row.type || row.data_source) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.history.cols.status')" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
                                {{ cleanupStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.history.cols.before')" width="90" align="center" prop="total_logs_before" />
                    <el-table-column :label="t('retention_audit_page.history.cols.pruned_count')" width="90" align="center" prop="pruned_count" />
                    <el-table-column :label="t('retention_audit_page.history.cols.retention_days')" width="100" align="center" prop="retention_days" />
                    <el-table-column :label="t('retention_audit_page.history.cols.notes')" min-width="200">
                        <template #default="{ row }">{{ row.notes || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('retention_audit_page.history.cols.initiator')" width="120" prop="initiator?.name" />
                    <el-table-column :label="t('retention_audit_page.history.cols.executed_at')" width="170">
                        <template #default="{ row }">{{ formatTime(row.executed_at) }}</template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <!-- 保留策略编辑对话框 -->
        <el-dialog v-model="showPolicyDialog" :title="t('retention_audit_page.dialogs.edit_policy')" width="480px">
            <el-form ref="policyFormRef" :model="policyForm" :rules="policyRules" label-width="100px">
                <el-form-item :label="t('retention_audit_page.form.data_source')">
                    <el-tag :type="sourceTag(policyForm.data_source)" size="small">{{ policyForm.display_name }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.retention_days')" prop="retention_days">
                    <el-input-number v-model="policyForm.retention_days" :min="1" :max="3650" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.status')">
                    <el-switch v-model="policyForm.is_active" :active-text="t('actions.enable')" :inactive-text="t('actions.disable')" />
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.description')" prop="description">
                    <el-input v-model="policyForm.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPolicyDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingPolicy" @click="handleSavePolicy">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 调度编辑对话框 -->
        <el-dialog v-model="showScheduleDialog" :title="t('retention_audit_page.dialogs.edit_schedule')" width="500px">
            <el-form ref="scheduleFormRef" :model="scheduleForm" :rules="scheduleRules" label-width="110px">
                <el-form-item :label="t('retention_audit_page.form.data_source')">
                    <el-tag :type="sourceTag(scheduleForm.data_source)" size="small">{{ scheduleForm.display_name }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.frequency')" prop="frequency">
                    <el-select v-model="scheduleForm.frequency" style="width: 100%">
                        <el-option v-for="opt in frequencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.time_of_day')" prop="time_of_day">
                    <el-time-picker v-model="scheduleTime" format="HH:mm" value-format="HH:mm" style="width: 100%" />
                </el-form-item>
                <el-form-item v-if="scheduleForm.frequency === 'weekly'" :label="t('retention_audit_page.form.day_of_week')">
                    <el-select v-model="scheduleForm.day_of_week" style="width: 100%">
                        <el-option v-for="opt in weekdayOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.batch_size')" prop="batch_size">
                    <el-input-number v-model="scheduleForm.batch_size" :min="100" :max="10000" :step="100" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.status')">
                    <el-switch v-model="scheduleForm.is_active" :active-text="t('actions.enable')" :inactive-text="t('actions.disable')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showScheduleDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingSchedule" @click="handleSaveSchedule">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 导出对话框 -->
        <el-dialog v-model="showExportDialog" :title="t('retention_audit_page.dialogs.export_report')" width="450px">
            <el-form ref="exportFormRef" :model="exportForm" :rules="exportRules" label-width="100px">
                <el-form-item :label="t('retention_audit_page.form.report')" prop="report_id">
                    <el-select v-model="exportForm.report_id" :placeholder="t('retention_audit_page.form.select_report')" style="width: 100%">
                        <el-option v-for="r in allReports" :key="r.id" :label="r.title" :value="r.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('retention_audit_page.form.format')" prop="format">
                    <el-radio-group v-model="exportForm.format">
                        <el-radio value="json">JSON</el-radio>
                        <el-radio value="csv">CSV</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showExportDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="exporting" @click="handleExport">{{ t('actions.export') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Download } from '@element-plus/icons-vue';
import auditGovernanceApi from '@/api/auditGovernance';

const { t, locale } = useI18n();

// ─── 标签 ───
const activeTab = ref('policies');

const tabLabels = computed(() => ({
    policies: t('retention_audit_page.tabs.policies'),
    schedules: t('retention_audit_page.tabs.schedules'),
    exports: t('retention_audit_page.tabs.exports'),
    history: t('retention_audit_page.tabs.history'),
}));

const frequencyLabels = computed(() => ({
    daily: t('retention_audit_page.frequency.daily'),
    weekly: t('retention_audit_page.frequency.weekly'),
    monthly: t('retention_audit_page.frequency.monthly'),
    manual: t('retention_audit_page.frequency.manual'),
}));

const frequencyOptions = computed(() => [
    { label: t('retention_audit_page.frequency.daily'), value: 'daily' },
    { label: t('retention_audit_page.frequency.weekly'), value: 'weekly' },
    { label: t('retention_audit_page.frequency.monthly'), value: 'monthly' },
    { label: t('retention_audit_page.frequency.manual'), value: 'manual' },
]);

const weekdayOptions = computed(() =>
    ['0', '1', '2', '3', '4', '5', '6'].map((value) => ({
        value,
        label: t(`retention_audit_page.weekdays.${value}`),
    })),
);

const sourceLabels = computed(() => ({
    audit_log: t('retention_audit_page.sources.audit_log'),
    security_log: t('retention_audit_page.sources.security_log'),
    error_log: t('retention_audit_page.sources.error_log'),
    system_log: t('retention_audit_page.sources.system_log'),
    apm_request: t('retention_audit_page.sources.apm_request'),
    webhook_event: t('retention_audit_page.sources.webhook_event'),
    webhook_delivery: t('retention_audit_page.sources.webhook_delivery'),
    license: t('retention_audit_page.sources.license'),
    api_endpoint: t('retention_audit_page.sources.api_endpoint'),
}));

const exportStatusLabels = computed(() => ({
    pending: t('retention_audit_page.export_status.pending'),
    processing: t('retention_audit_page.export_status.processing'),
    completed: t('retention_audit_page.export_status.completed'),
    failed: t('retention_audit_page.export_status.failed'),
}));

const cleanupStatusLabels = computed(() => ({
    completed: t('retention_audit_page.cleanup_status.completed'),
    partial: t('retention_audit_page.cleanup_status.partial'),
    failed: t('retention_audit_page.cleanup_status.failed'),
}));

const policyRules = computed(() => ({
    retention_days: [{ required: true, message: t('retention_audit_page.rules.retention_days_required'), trigger: 'blur' }],
}));

const scheduleRules = computed(() => ({
    frequency: [{ required: true, message: t('retention_audit_page.rules.frequency_required'), trigger: 'change' }],
    batch_size: [{ required: true, message: t('retention_audit_page.rules.batch_size_required'), trigger: 'blur' }],
}));

const exportRules = computed(() => ({
    report_id: [{ required: true, message: t('retention_audit_page.rules.report_required'), trigger: 'change' }],
    format: [{ required: true, message: t('retention_audit_page.rules.format_required'), trigger: 'change' }],
}));

// ─── 仪表盘数据 ───
const dashboard = reactive({
    by_source: [],
    recent_cleanups: [],
    total_records: 0,
    total_storage_mb: 0,
    policies: [],
});
const loading = ref(false);
const cleaningSource = ref('');

async function fetchDashboard() {
    loading.value = true;
    try {
        const res = await auditGovernanceApi.extendedRetentionDashboard();
        if (res.success) Object.assign(dashboard, res.data || {});
    } catch {
        ElMessage.error(t('retention_audit_page.messages.load_dashboard_failed'));
    } finally {
        loading.value = false;
    }
}

// ─── 保留策略管理 ───
const showPolicyDialog = ref(false);
const savingPolicy = ref(false);
const policyFormRef = ref(null);
const policyForm = reactive({
    data_source: '',
    display_name: '',
    retention_days: 365,
    is_active: true,
    description: '',
});

function openPolicyEdit(row) {
    policyForm.data_source = row.data_source;
    policyForm.display_name = row.display_name;
    policyForm.retention_days = row.retention_days;
    policyForm.is_active = row.is_active ?? true;
    policyForm.description = '';
    showPolicyDialog.value = true;
}

async function handleSavePolicy() {
    if (!policyFormRef.value) return;
    const valid = await policyFormRef.value.validate().catch(() => false);
    if (!valid) return;

    savingPolicy.value = true;
    try {
        const res = await auditGovernanceApi.saveRetentionPolicy({
            data_source: policyForm.data_source,
            retention_days: policyForm.retention_days,
            is_active: policyForm.is_active,
            description: policyForm.description,
        });
        if (res.success) {
            ElMessage.success(t('retention_audit_page.messages.policy_saved'));
            showPolicyDialog.value = false;
            await fetchDashboard();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('retention_audit_page.messages.save_failed'));
    } finally {
        savingPolicy.value = false;
    }
}

async function handleExtendedCleanup(row) {
    try {
        await ElMessageBox.confirm(
            t('retention_audit_page.confirm.cleanup', {
                name: row.display_name,
                days: row.retention_days,
                count: row.to_prune,
            }),
            t('retention_audit_page.confirm.cleanup_title'),
            {
                type: 'warning',
                confirmButtonText: t('retention_audit_page.confirm.cleanup_confirm'),
                cancelButtonText: t('actions.cancel'),
            },
        );
    } catch { return; }

    cleaningSource.value = row.data_source;
    try {
        const res = await auditGovernanceApi.executeExtendedCleanup({ data_source: row.data_source });
        if (res.success) {
            ElMessage.success(t('retention_audit_page.messages.cleanup_done', { count: res.data?.pruned_count || 0 }));
            await fetchDashboard();
            await fetchCleanupHistory();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('retention_audit_page.messages.cleanup_failed'));
    } finally {
        cleaningSource.value = '';
    }
}

// ─── 清理调度配置 ───
const schedules = ref([]);
const loadingSchedules = ref(false);
const showScheduleDialog = ref(false);
const savingSchedule = ref(false);
const scheduleFormRef = ref(null);
const scheduleTime = ref('02:00');
const scheduleForm = reactive({
    id: null,
    data_source: '',
    display_name: '',
    frequency: 'daily',
    time_of_day: '02:00',
    day_of_week: '0',
    batch_size: 1000,
    is_active: true,
});

async function fetchSchedules() {
    loadingSchedules.value = true;
    try {
        const res = await auditGovernanceApi.cleanupSchedules();
        if (res.success) schedules.value = res.data || [];
    } catch {
        ElMessage.error(t('retention_audit_page.messages.load_schedules_failed'));
    } finally {
        loadingSchedules.value = false;
    }
}

function openScheduleEdit(row) {
    scheduleForm.id = row.id;
    scheduleForm.data_source = row.data_source;
    scheduleForm.display_name = row.display_name;
    scheduleForm.frequency = row.frequency;
    scheduleForm.time_of_day = row.time_of_day || '02:00';
    scheduleForm.day_of_week = row.day_of_week || '0';
    scheduleForm.batch_size = row.batch_size;
    scheduleForm.is_active = row.is_active;
    scheduleTime.value = row.time_of_day || '02:00';
    showScheduleDialog.value = true;
}

async function handleSaveSchedule() {
    if (!scheduleFormRef.value) return;
    const valid = await scheduleFormRef.value.validate().catch(() => false);
    if (!valid) return;

    savingSchedule.value = true;
    try {
        const data = { ...scheduleForm };
        data.time_of_day = scheduleTime.value || '02:00';
        delete data.id;
        delete data.display_name;

        const res = await auditGovernanceApi.saveCleanupSchedule(data);
        if (res.success) {
            ElMessage.success(t('retention_audit_page.messages.schedule_saved'));
            showScheduleDialog.value = false;
            await fetchSchedules();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('retention_audit_page.messages.save_failed'));
    } finally {
        savingSchedule.value = false;
    }
}

async function handleToggleSchedule(row, val) {
    try {
        await auditGovernanceApi.saveCleanupSchedule({
            data_source: row.data_source,
            is_active: val,
        });
        ElMessage.success(val ? t('retention_audit_page.messages.schedule_enabled') : t('retention_audit_page.messages.schedule_disabled'));
    } catch {
        ElMessage.error(t('messages.failed'));
        await fetchSchedules();
    }
}

// ─── 合规报告导出 ───
const allReports = ref([]);
const exports = ref([]);
const loadingExports = ref(false);
const showExportDialog = ref(false);
const exporting = ref(false);
const exportFormRef = ref(null);
const exportFilter = reactive({ report_id: '' });
const exportForm = reactive({
    report_id: '',
    format: 'json',
});

async function fetchAllReports() {
    try {
        const res = await auditGovernanceApi.reports({ per_page: 200 });
        if (res.success) allReports.value = res.data || [];
    } catch { /* ignore */ }
}

async function fetchExports() {
    if (!exportFilter.report_id) {
        exports.value = [];
        return;
    }
    loadingExports.value = true;
    try {
        const res = await auditGovernanceApi.reportExports(exportFilter.report_id);
        if (res.success) exports.value = res.data || [];
    } catch {
        ElMessage.error(t('retention_audit_page.messages.load_exports_failed'));
    } finally {
        loadingExports.value = false;
    }
}

function openExportDialog() {
    exportForm.report_id = '';
    exportForm.format = 'json';
    showExportDialog.value = true;
}

async function handleExport() {
    if (!exportFormRef.value) return;
    const valid = await exportFormRef.value.validate().catch(() => false);
    if (!valid) return;

    exporting.value = true;
    try {
        const res = await auditGovernanceApi.exportReport(exportForm.report_id, exportForm.format);
        if (res.success) {
            ElMessage.success(t('retention_audit_page.messages.export_done'));
            showExportDialog.value = false;
            if (exportFilter.report_id === exportForm.report_id) {
                await fetchExports();
            }
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('retention_audit_page.messages.export_failed'));
    } finally {
        exporting.value = false;
    }
}

function downloadExport(row) {
    window.open(`/storage/${row.file_path}`, '_blank');
}

// ─── 清理历史 ───
const cleanupHistory = ref([]);
const loadingHistory = ref(false);

async function fetchCleanupHistory() {
    loadingHistory.value = true;
    try {
        const res = await auditGovernanceApi.cleanupHistory();
        if (res.success) cleanupHistory.value = res.data || [];
    } catch {
        ElMessage.error(t('retention_audit_page.messages.load_history_failed'));
    } finally {
        loadingHistory.value = false;
    }
}

// ─── 工具函数 ───
function sourceTag(source) {
    const map = {
        audit_log: 'primary', security_log: 'danger', error_log: 'warning', system_log: 'info',
        apm_request: '', webhook_event: 'success', webhook_delivery: 'success',
        license: 'primary', api_endpoint: 'info',
    };
    return map[source] || '';
}

function sourceLabel(source) {
    return sourceLabels.value[source] || source;
}

function freqLabel(freq) {
    return frequencyLabels.value[freq] || freq;
}

function exportStatusTag(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' };
    return map[status] || 'info';
}

function exportStatusLabel(status) {
    return exportStatusLabels.value[status] || status;
}

function cleanupStatusLabel(status) {
    return cleanupStatusLabels.value[status] || status;
}

function formatTime(time) {
    if (!time) return '—';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(loc);
}

function formatNumber(n) {
    if (!n && n !== 0) return '0';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return n.toLocaleString(loc);
}

function formatBytes(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function refreshAll() {
    await Promise.all([
        fetchDashboard(),
        fetchSchedules(),
        fetchCleanupHistory(),
        fetchAllReports(),
    ]);
}

onMounted(async () => {
    await Promise.all([
        fetchDashboard(),
        fetchSchedules(),
        fetchCleanupHistory(),
        fetchAllReports(),
    ]);
});
</script>

<style scoped>
.retention-audit-page { padding: 20px; }

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

:deep(.el-card__body) { padding: 16px; }
</style>
