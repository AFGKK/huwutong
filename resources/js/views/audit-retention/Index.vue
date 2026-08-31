<template>

    <div class="audit-retention-page">

        <!-- 概览统计卡片 -->

        <el-row :gutter="16" class="mb-4">

            <el-col :span="6">

                <el-card shadow="never" :body-style="{ padding: '16px' }">

                    <div class="stat-card">

                        <div class="stat-value">{{ overviewData.total ?? '-' }}</div>

                        <div class="stat-label">{{ t('audit_retention_page.stats.total') }}</div>

                    </div>

                </el-card>

            </el-col>

            <el-col :span="6">

                <el-card shadow="never" :body-style="{ padding: '16px' }">

                    <div class="stat-card">

                        <div class="stat-value info">{{ overviewData.recent_30d ?? '-' }}</div>

                        <div class="stat-label">{{ t('audit_retention_page.stats.recent_30d') }}</div>

                    </div>

                </el-card>

            </el-col>

            <el-col :span="6">

                <el-card shadow="never" :body-style="{ padding: '16px' }">

                    <div class="stat-card">

                        <div class="stat-value warning">{{ overviewData.estimated_storage_mb ?? '-' }} MB</div>

                        <div class="stat-label">{{ t('audit_retention_page.stats.storage') }}</div>

                    </div>

                </el-card>

            </el-col>

            <el-col :span="6">

                <el-card shadow="never" :body-style="{ padding: '16px' }">

                    <div class="stat-card">

                        <div class="stat-value" :class="prunePreviewTotal > 0 ? 'danger' : 'success'">{{ prunePreviewTotal }}</div>

                        <div class="stat-label">{{ t('audit_retention_page.stats.pending_prune') }}</div>

                    </div>

                </el-card>

            </el-col>

        </el-row>



        <el-row :gutter="16" class="mb-4">

            <!-- 保留策略管理 -->

            <el-col :span="12">

                <el-card shadow="never">

                    <template #header>

                        <div class="card-header">

                            <span>{{ t('audit_retention_page.policies_title') }}</span>

                        </div>

                    </template>

                    <el-table :data="policies" v-loading="loading" stripe size="small">

                        <el-table-column :label="t('audit_logs_page.filter_type')" width="100">

                            <template #default="{ row }">

                                <el-tag size="small" :type="row.type === 'audit' ? '' : row.type === 'security' ? 'danger' : row.type === 'error' ? 'warning' : 'info'">

                                    {{ typeLabel(row.type) }}

                                </el-tag>

                            </template>

                        </el-table-column>

                        <el-table-column :label="t('retention_audit_page.form.retention_days')" width="100" align="center">

                            <template #default="{ row }">

                                <span class="retention-days" @click="openEdit(row)">{{ t('audit_retention_page.days', { n: row.retention_days }) }}</span>

                            </template>

                        </el-table-column>

                        <el-table-column :label="t('audit_retention_page.cols.log_count')" width="80" align="center">

                            <template #default="{ row }">{{ row.log_count }}</template>

                        </el-table-column>

                        <el-table-column :label="t('audit_retention_page.cols.oldest_log')" width="150">

                            <template #default="{ row }">{{ row.oldest_log_date || '-' }}</template>

                        </el-table-column>

                        <el-table-column :label="t('retention_audit_page.form.status')" width="70">

                            <template #default="{ row }">

                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">

                                    {{ row.is_active ? t('audit_retention_page.status_enabled') : t('audit_retention_page.status_disabled') }}

                                </el-tag>

                            </template>

                        </el-table-column>

                        <el-table-column :label="t('retention_audit_page.policies.cols.actions')" width="120">

                            <template #default="{ row }">

                                <el-button text size="small" type="primary" @click="openEdit(row)">{{ t('audit_retention_page.configure') }}</el-button>

                                <el-button v-if="row.is_custom" text size="small" type="danger" @click="handleReset(row)">{{ t('actions.reset') }}</el-button>

                            </template>

                        </el-table-column>

                    </el-table>

                </el-card>

            </el-col>



            <!-- 按类型分布 -->

            <el-col :span="12">

                <el-card shadow="never">

                    <template #header>

                        <div class="card-header">

                            <span>{{ t('audit_retention_page.trend_title') }}</span>

                        </div>

                    </template>

                    <div class="chart-placeholder">

                        <div class="chart-empty" v-if="!byDate.length">{{ t('messages.no_data') }}</div>

                        <div class="bar-chart" v-else>

                            <div class="bar-item" v-for="d in byDate" :key="d.date" :title="t('audit_retention_page.bar_tooltip', { date: d.date, count: d.count })">

                                <div class="bar-fill" :style="{ height: barHeight(d.count, maxByDate) + '%' }"></div>

                                <div class="bar-label">{{ d.date.slice(5) }}</div>

                            </div>

                        </div>

                    </div>

                </el-card>

            </el-col>

        </el-row>



        <!-- 策略编辑对话框 -->

        <el-dialog v-model="showEditDialog" :title="t('audit_retention_page.dialog_title', { type: editing?.type ? typeLabel(editing.type) : '' })" width="450px">

            <el-form label-width="100px" v-if="editing">

                <el-form-item :label="t('audit_logs_page.filter_type')">

                    <el-tag :type="editing.type === 'audit' ? '' : editing.type === 'security' ? 'danger' : editing.type === 'error' ? 'warning' : 'info'">

                        {{ typeLabel(editing.type) }}

                    </el-tag>

                </el-form-item>

                <el-form-item :label="t('retention_audit_page.form.retention_days')">

                    <el-input-number v-model="editDays" :min="1" :max="3650" :step="30" style="width: 200px" />

                    <div class="form-help">{{ t('audit_retention_page.form_current_value', { days: editing.retention_days, count: editing.log_count }) }}</div>

                </el-form-item>

                <el-form-item :label="t('retention_audit_page.form.description')">

                    <el-input v-model="editDescription" type="textarea" :rows="2" maxlength="500" show-word-limit />

                </el-form-item>

                <el-form-item :label="t('actions.enable')">

                    <el-switch v-model="editActive" />

                </el-form-item>



                <el-divider />

                <div class="prune-preview" v-if="editPreview !== null">

                    <p>{{ t('audit_retention_page.prune_preview', { count: editPreview.to_prune, date: editPreview.cutoff_date }) }}</p>

                </div>

            </el-form>

            <template #footer>

                <el-button @click="showEditDialog = false">{{ t('actions.cancel') }}</el-button>

                <el-button type="primary" :loading="saving" @click="confirmSave">

                    {{ editing?.is_custom ? t('audit_retention_page.btn_update_policy') : t('audit_retention_page.btn_create_policy') }}

                </el-button>

            </template>

        </el-dialog>



        <!-- 导出面板 -->

        <el-card shadow="never" class="mt-4">

            <template #header>

                <div class="card-header">

                    <span>{{ t('audit_retention_page.export.title') }}</span>

                    <el-button type="primary" @click="showExportPanel = !showExportPanel" size="small">

                        {{ showExportPanel ? t('audit_retention_page.export.collapse') : t('audit_retention_page.export.expand') }}

                    </el-button>

                </div>

            </template>

            <template v-if="showExportPanel">

                <el-form :inline="true" label-width="80px">

                    <el-form-item :label="t('audit_retention_page.export.date_range')">

                        <el-date-picker

                            v-model="exportDateRange"

                            type="daterange"

                            :range-separator="t('audit_retention_page.export.date_separator')"

                            :start-placeholder="t('audit_retention_page.export.start_date')"

                            :end-placeholder="t('audit_retention_page.export.end_date')"

                            value-format="YYYY-MM-DD"

                        />

                    </el-form-item>

                    <el-form-item :label="t('audit_logs_page.filter_type')">

                        <el-select v-model="exportFilterType" :placeholder="t('audit_logs_page.placeholder_all')" clearable style="width: 120px">

                            <el-option v-for="opt in exportTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />

                        </el-select>

                    </el-form-item>

                    <el-form-item :label="t('audit_retention_page.export.action_filter')">

                        <el-input v-model="exportFilterAction" :placeholder="t('audit_retention_page.export.action_placeholder')" style="width: 160px" />

                        <div class="form-help">{{ t('audit_retention_page.export.action_hint') }}</div>

                    </el-form-item>

                    <el-form-item :label="t('actions.search')">

                        <el-input v-model="exportSearch" :placeholder="t('audit_retention_page.export.keyword_placeholder')" style="width: 160px" />

                    </el-form-item>

                    <el-form-item :label="t('retention_audit_page.form.format')">

                        <el-radio-group v-model="exportFormat">

                            <el-radio value="csv">CSV</el-radio>

                            <el-radio value="json">JSON</el-radio>

                        </el-radio-group>

                    </el-form-item>

                    <el-form-item>

                        <el-button type="success" :loading="exporting" @click="handleExport">

                            <el-icon><Download /></el-icon> {{ t('actions.export') }}

                        </el-button>

                        <el-button text size="small" type="info" @click="handleExportPreview">{{ t('audit_retention_page.export.preview_count') }}</el-button>

                    </el-form-item>

                </el-form>

                <div v-if="exportPreviewCount !== null" class="export-preview">

                    {{ t('audit_retention_page.export.preview_result', { count: exportPreviewCount, max: exportMaxRows }) }}

                </div>

            </template>

        </el-card>

    </div>

</template>



<script setup>

import { ref, reactive, computed, onMounted } from 'vue';

import { useI18n } from 'vue-i18n';

import { ElMessage, ElMessageBox } from 'element-plus';

import { Download } from '@element-plus/icons-vue';

import auditRetentionApi from '@/api/auditRetention';

import apiClient from '@/api/client';



const { t } = useI18n();



const typeLabels = computed(() => ({

    audit: t('audit_logs_page.type_audit'),

    security: t('audit_logs_page.type_security'),

    error: t('audit_logs_page.type_error'),

    system: t('audit_logs_page.type_system'),

}));



function typeLabel(type) {

    return typeLabels.value[type] || type;

}



const exportTypeOptions = computed(() => [

    { value: 'audit', label: t('audit_logs_page.type_audit') },

    { value: 'security', label: t('audit_logs_page.type_security') },

    { value: 'error', label: t('audit_logs_page.type_error') },

    { value: 'system', label: t('audit_logs_page.type_system') },

]);



// ─── 概览 ───

const overviewData = reactive({

    total: 0, recent_30d: 0, estimated_storage_mb: 0,

});

const byDate = ref([]);

const loading = ref(false);



async function fetchOverview() {

    loading.value = true;

    try {

        const res = await auditRetentionApi.overview();

        const data = res.data?.data || {};

        Object.assign(overviewData, data);

        byDate.value = data.by_date || [];

    } catch {

        ElMessage.error(t('audit_retention_page.messages.overview_load_failed'));

    } finally {

        loading.value = false;

    }

}



// ─── 保留策略 ───

const policies = ref([]);

const showEditDialog = ref(false);

const editing = ref(null);

const editDays = ref(365);

const editDescription = ref('');

const editActive = ref(true);

const saving = ref(false);

const editPreview = ref(null);



async function fetchPolicies() {

    try {

        const res = await auditRetentionApi.list();

        policies.value = res.data?.data || [];

    } catch { /* silent */ }

}



function openEdit(row) {

    editing.value = row;

    editDays.value = row.retention_days;

    editDescription.value = row.description || '';

    editActive.value = row.is_active;

    editPreview.value = null;

    showEditDialog.value = true;



    // 预览清理量

    auditRetentionApi.previewPrune({ type: row.type }).then(res => {

        editPreview.value = res.data?.data;

    }).catch(() => {});

}



async function confirmSave() {

    saving.value = true;

    try {

        if (editing.value.is_custom) {

            await auditRetentionApi.update(editing.value.id, {

                retention_days: editDays.value,

                description: editDescription.value || undefined,

                is_active: editActive.value,

            });

            ElMessage.success(t('audit_retention_page.messages.policy_updated'));

        } else {

            await auditRetentionApi.create({

                type: editing.value.type,

                retention_days: editDays.value,

                description: editDescription.value || undefined,

            });

            ElMessage.success(t('audit_retention_page.messages.policy_created'));

        }

        showEditDialog.value = false;

        await fetchPolicies();

        await fetchOverview();

    } catch (err) {

        ElMessage.error(err.response?.data?.message || t('retention_audit_page.messages.save_failed'));

    } finally {

        saving.value = false;

    }

}



async function handleReset(row) {

    try {

        await ElMessageBox.confirm(

            t('audit_retention_page.messages.reset_confirm', { type: typeLabel(row.type) }),

            t('actions.confirm'),

            { type: 'warning' },

        );

        await auditRetentionApi.destroy(row.id);

        ElMessage.success(t('audit_retention_page.messages.reset_success'));

        await fetchPolicies();

        await fetchOverview();

    } catch { /* cancelled */ }

}



// ─── 清理预览 ───

const prunePreviewTotal = computed(() => {

    return policies.value.reduce((sum, p) => sum + (p.estimated_prune || 0), 0);

});



// ─── 图表辅助 ───

const maxByDate = computed(() => {

    if (!byDate.value.length) return 1;

    return Math.max(...byDate.value.map(d => d.count));

});



function barHeight(val, max) {

    if (!max || max === 0) return 0;

    return Math.max(2, (val / max) * 100);

}



// ─── 导出 ───

const showExportPanel = ref(false);

const exportDateRange = ref(null);

const exportFilterType = ref('');

const exportFilterAction = ref('');

const exportSearch = ref('');

const exportFormat = ref('csv');

const exporting = ref(false);

const exportPreviewCount = ref(null);

const exportMaxRows = 50000;



function handleExport() {

    exporting.value = true;

    try {

        const params = new URLSearchParams();

        params.set('format', exportFormat.value);



        if (exportFilterType.value) {

            params.set('filter[type]', exportFilterType.value);

        }

        if (exportFilterAction.value) {

            params.set('filter[action_prefix]', exportFilterAction.value);

        }

        if (exportDateRange.value) {

            params.set('date_from', exportDateRange.value[0]);

            params.set('date_to', exportDateRange.value[1]);

        }

        if (exportSearch.value) {

            params.set('search', exportSearch.value);

        }



        // 使用 fetch 直接下载

        const token = localStorage.getItem('token') || '';

        const url = `/api/admin/audit-logs/export?${params.toString()}`;



        fetch(url, {

            headers: {

                'Authorization': `Bearer ${token}`,

                'Accept': exportFormat.value === 'csv' ? 'text/csv' : 'application/json',

            },

        })

        .then(response => {

            if (!response.ok) throw new Error('export failed');

            return response.blob();

        })

        .then(blob => {

            const link = document.createElement('a');

            link.href = URL.createObjectURL(blob);

            const ext = exportFormat.value === 'csv' ? '.csv' : '.json';

            link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}${ext}`;

            link.click();

            URL.revokeObjectURL(link.href);

            ElMessage.success(t('audit_retention_page.messages.export_success'));

        })

        .catch(() => ElMessage.error(t('audit_retention_page.messages.export_failed')))

        .finally(() => { exporting.value = false; });

    } catch {

        exporting.value = false;

        ElMessage.error(t('audit_retention_page.messages.export_failed'));

    }

}



async function handleExportPreview() {

    try {

        const params = {};

        if (exportFilterType.value) params['filter[type]'] = exportFilterType.value;

        if (exportDateRange.value) {

            params.date_from = exportDateRange.value[0];

            params.date_to = exportDateRange.value[1];

        }

        const res = await apiClient.get('/admin/audit-logs', { params: { ...params, per_page: 1 } });

        exportPreviewCount.value = res.data?.meta?.total || 0;

    } catch {

        ElMessage.error(t('audit_retention_page.messages.preview_failed'));

    }

}



onMounted(async () => {

    await fetchOverview();

    await fetchPolicies();

});

</script>



<style scoped>

.mb-4 { margin-bottom: 16px; }

.mt-4 { margin-top: 16px; }



.stat-card { text-align: center; }

.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }

.stat-value.success { color: var(--el-color-success); }

.stat-value.danger { color: var(--el-color-danger); }

.stat-value.warning { color: var(--el-color-warning); }

.stat-value.info { color: var(--el-color-info); }

.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }



.card-header { display: flex; justify-content: space-between; align-items: center; }



.chart-placeholder { height: 200px; }

.chart-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--el-text-color-secondary); }



.bar-chart {

    display: flex; align-items: flex-end; gap: 3px; height: 180px;

}

.bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; }

.bar-fill {

    width: 100%; max-width: 24px; min-height: 2px;

    background: var(--el-color-primary-light-5);

    border-radius: 2px 2px 0 0;

}

.bar-label { font-size: 10px; color: var(--el-text-color-secondary); white-space: nowrap; }



.retention-days {

    cursor: pointer; color: var(--el-color-primary); text-decoration: underline dashed;

}



.form-help { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }



.prune-preview {

    font-size: 13px; color: var(--el-color-danger); padding: 8px;

    background: var(--el-color-danger-light-9); border-radius: 4px;

}



.export-preview {

    font-size: 13px; color: var(--el-text-color-secondary);

}

</style>

