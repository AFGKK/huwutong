<template>
    <div class="workflow-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('workflows_page.title') }}</h2>
                <span class="header-subtitle">{{ t('workflows_page.subtitle') }}</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="3" v-for="s in statCards" :key="s.key">
                <el-card shadow="hover" @click="filterByStatus(s.key)">
                    <div class="stat-box">
                        <div class="stat-num" :style="{ color: s.color }">{{ s.value }}</div>
                        <div class="stat-lbl">{{ s.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Worker 状态与 Temporal 引擎信息 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <template #header>{{ t('workflows_page.worker.title') }}</template>
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.driver') }}</span><span class="info-value">{{ workerConfig.driver }}</span></div>
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.namespace') }}</span><span class="info-value">{{ workerConfig.namespace }}</span></div>
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.task_queue') }}</span><span class="info-value">{{ workerConfig.task_queue }}</span></div>
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.max_concurrent') }}</span><span class="info-value">{{ workerConfig.max_concurrent }}</span></div>
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.heartbeat') }}</span><span class="info-value">{{ workerConfig.heartbeat_seconds }}s</span></div>
                        <div class="info-item"><span class="info-label">{{ t('workflows_page.worker.timeout') }}</span><span class="info-value">{{ workerConfig.timeout_minutes }}min</span></div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="9">
                <el-card shadow="never">
                    <template #header>{{ t('workflows_page.definitions.title') }}</template>
                    <el-table :data="definitions" v-loading="loadingDefinitions" stripe size="small">
                        <el-table-column prop="name" :label="t('workflows_page.definitions.col_name')" width="150" />
                        <el-table-column prop="description" :label="t('workflows_page.definitions.col_description')" min-width="180" />
                        <el-table-column :label="t('workflows_page.definitions.col_steps')" width="70">
                            <template #default="{ row }">{{ row.steps_definition?.length || 0 }}</template>
                        </el-table-column>
                        <el-table-column :label="t('workflows_page.definitions.col_status')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('workflows_page.definitions.active') : t('workflows_page.definitions.inactive') }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :span="9">
                <el-card shadow="never">
                    <template #header>{{ t('workflows_page.by_workflow.title') }}</template>
                    <el-table :data="byWorkflowStats" v-loading="loadingStats" stripe size="small">
                        <el-table-column prop="workflow_name" :label="t('workflows_page.by_workflow.col_workflow')" width="150" />
                        <el-table-column :label="t('workflows_page.by_workflow.col_total')" width="60" prop="total" />
                        <el-table-column :label="t('workflows_page.by_workflow.col_running')" width="65">
                            <template #default="{ row }"><el-tag type="warning" size="small">{{ row.running }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('workflows_page.by_workflow.col_completed')" width="65">
                            <template #default="{ row }"><el-tag type="success" size="small">{{ row.completed }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('workflows_page.by_workflow.col_failed')" width="55">
                            <template #default="{ row }"><el-tag type="danger" size="small">{{ row.failed }}</el-tag></template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <!-- 今日动态 + 近期失败 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header>{{ t('workflows_page.today.title') }}</template>
                    <div v-if="todayStats" class="today-grid">
                        <div class="today-item"><span class="today-num">{{ todayStats.started }}</span><span class="today-lbl">{{ t('workflows_page.today.started') }}</span></div>
                        <div class="today-item"><span class="today-num success">{{ todayStats.completed }}</span><span class="today-lbl">{{ t('workflows_page.today.completed') }}</span></div>
                        <div class="today-item"><span class="today-num danger">{{ todayStats.failed }}</span><span class="today-lbl">{{ t('workflows_page.today.failed') }}</span></div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>{{ t('workflows_page.recent_failures.title') }}</template>
                    <div v-if="recentFailures.length === 0" class="empty-text">{{ t('workflows_page.recent_failures.empty') }}</div>
                    <el-timeline v-else>
                        <el-timeline-item v-for="f in recentFailures" :key="f.id" timestamp="" type="danger">
                            <div class="fail-item">
                                <strong>#{{ f.id }} {{ f.workflow_name }}</strong>
                                <el-tag type="danger" size="small">{{ formatTime(f.updated_at) }}</el-tag>
                                <div class="fail-msg">{{ truncate(f.error_message, 80) }}</div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </el-card>
            </el-col>
        </el-row>

        <!-- 实例列表 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('workflows_page.instances.title') }}</span>
                    <div class="card-actions">
                        <el-select v-model="filters.workflow_name" clearable :placeholder="t('workflows_page.instances.all_types')" style="width: 150px" @change="fetchInstances">
                            <el-option v-for="d in definitions" :key="d.name" :label="d.name" :value="d.name" />
                        </el-select>
                        <el-select v-model="filters.status" clearable :placeholder="t('workflows_page.instances.all_status')" style="width: 110px" @change="fetchInstances">
                            <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-button type="danger" plain @click="handleBatchRetry" :disabled="failedCount === 0">{{ t('workflows_page.instances.batch_retry', { n: failedCount }) }}</el-button>
                        <el-button @click="fetchInstances">{{ t('workflows_page.instances.refresh') }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="instances" v-loading="loadingInstances" stripe @row-click="showDetail">
                <el-table-column type="index" label="#" width="45" />
                <el-table-column prop="workflow_name" :label="t('workflows_page.cols.workflow')" width="130" />
                <el-table-column :label="t('workflows_page.cols.status')" width="85">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="current_step" :label="t('workflows_page.cols.current_step')" width="130" />
                <el-table-column :label="t('workflows_page.cols.progress')" width="160">
                    <template #default="{ row }">
                        <el-progress :percentage="stepProgress(row)" :stroke-width="14" :status="row.status === 'failed' ? 'exception' : ''" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('workflows_page.cols.retry')" width="55" align="center">
                    <template #default="{ row }">{{ row.retry_count }}/{{ row.max_retries }}</template>
                </el-table-column>
                <el-table-column prop="error_message" :label="t('workflows_page.cols.error')" min-width="160" show-overflow-tooltip />
                <el-table-column :label="t('workflows_page.cols.created_at')" width="150">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('workflows_page.cols.actions')" width="140" fixed="right">
                    <template #default="{ row }">
                        <el-button link size="small" @click.stop="showDetail(row)">{{ t('workflows_page.row_actions.detail') }}</el-button>
                        <el-button link size="small" type="warning" @click.stop="showSaga(row)" v-if="row.status === 'compensating' || row.status === 'failed'">{{ t('workflows_page.row_actions.saga') }}</el-button>
                        <el-button link size="small" type="danger" @click.stop="handleCancel(row)" v-if="row.status === 'running'">{{ t('actions.cancel') }}</el-button>
                        <el-button link size="small" type="warning" @click.stop="handleRetry(row)" v-if="row.status === 'failed'">{{ t('actions.retry') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="total > 0">
                <el-pagination v-model:current-page="page" :page-size="perPage" :total="total"
                    layout="total, sizes, prev, pager, next" :page-sizes="[10, 20, 50]"
                    @size-change="fetchInstances" @current-change="fetchInstances" />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailDialog" :title="t('workflows_page.detail_dialog.title', { id: detail?.id || '' })" width="800px">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('workflows_page.cols.workflow')">{{ detail.workflow_name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.cols.status')">
                        <el-tag :type="statusTagType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.detail_dialog.started_at')">{{ detail.started_at ? formatTime(detail.started_at) : '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.detail_dialog.elapsed')">{{ detail.elapsed_seconds ? detail.elapsed_seconds + 's' : '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.detail_dialog.retry_count')">{{ detail.retry_count }}/{{ detail.max_retries }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.detail_dialog.error')" v-if="detail.error_message">
                        <el-tag type="danger">{{ detail.error_message }}</el-tag>
                    </el-descriptions-item>
                </el-descriptions>

                <!-- 步骤进度条 -->
                <h4 class="steps-title">{{ t('workflows_page.detail_dialog.steps_title') }}</h4>
                <el-steps :active="detail.steps?.filter(s => s.status === 'completed').length || 0" align-center>
                    <el-step v-for="s in detail.steps" :key="s.id" :title="s.step_name"
                        :status="s.status === 'completed' ? 'finish' : s.status === 'failed' ? 'error' : s.status === 'running' ? 'process' : 'wait'" />
                </el-steps>

                <h4 class="steps-title">{{ t('workflows_page.detail_dialog.timeline_title') }}</h4>
                <el-timeline>
                    <el-timeline-item v-for="s in detail.steps" :key="s.id"
                        :timestamp="s.completed_at ? formatTime(s.completed_at) : s.started_at ? formatTime(s.started_at) : '-'"
                        :type="s.status === 'completed' ? 'success' : s.status === 'failed' ? 'danger' : s.status === 'compensated' ? 'warning' : 'primary'">
                        <div class="step-item">
                            <strong>{{ s.step_name }}</strong>
                            <el-tag :type="stepStatusTag(s.status)" size="small" class="ml-2">{{ stepStatusLabel(s.status) }}</el-tag>
                            <span class="step-duration" v-if="s.started_at && s.completed_at">
                                {{ Math.round((new Date(s.completed_at) - new Date(s.started_at)) / 1000) }}s
                            </span>
                            <div v-if="s.error_message" class="step-error"><el-icon><WarningFilled /></el-icon> {{ s.error_message }}</div>
                        </div>
                    </el-timeline-item>
                </el-timeline>
            </template>
        </el-dialog>

        <!-- Saga 状态对话框 -->
        <el-dialog v-model="showSagaDialog" :title="t('workflows_page.saga_dialog.title', { id: sagaData?.instance_id || '' })" width="650px">
            <template v-if="sagaData">
                <el-alert :title="sagaData.is_compensating ? t('workflows_page.saga_dialog.compensating') : sagaData.compensation_completed ? t('workflows_page.saga_dialog.compensation_done') : t('workflows_page.saga_dialog.committed')"
                    :type="sagaData.is_compensating ? 'warning' : sagaData.compensation_completed ? 'success' : 'info'" show-icon :closable="false" class="mb-4" />
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item :label="t('workflows_page.saga_dialog.saga_status')">{{ sagaData.status }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.saga_dialog.committed_steps')">{{ sagaData.committed_steps?.length || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.saga_dialog.compensated_steps')">{{ sagaData.compensated_steps?.length || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('workflows_page.saga_dialog.failed_step')" v-if="sagaData.failed_step">
                        <el-tag type="danger">{{ sagaData.failed_step.name }}: {{ sagaData.failed_step.error }}</el-tag>
                    </el-descriptions-item>
                </el-descriptions>

                <h4 class="steps-title">{{ t('workflows_page.saga_dialog.committed_title') }}</h4>
                <div v-if="sagaData.committed_steps?.length">
                    <el-tag v-for="s in sagaData.committed_steps" :key="s.name" type="success" class="saga-step-tag">{{ s.name }}</el-tag>
                </div>
                <div v-else class="empty-text">{{ t('workflows_page.saga_dialog.no_committed') }}</div>

                <h4 class="steps-title" v-if="sagaData.compensated_steps?.length">{{ t('workflows_page.saga_dialog.compensated_title') }}</h4>
                <div v-if="sagaData.compensated_steps?.length">
                    <el-tag v-for="s in sagaData.compensated_steps" :key="s.name" type="warning" class="saga-step-tag">{{ s.name }} ↩</el-tag>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { WarningFilled } from '@element-plus/icons-vue';
import workflowApi from '@/api/workflow';

const { t, locale } = useI18n();

const loadingDefinitions = ref(false);
const loadingStats = ref(false);
const loadingInstances = ref(false);

const definitions = ref([]);
const instances = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const detail = ref(null);
const showDetailDialog = ref(false);
const byWorkflowStats = ref([]);
const todayStats = ref(null);
const recentFailures = ref([]);
const workerConfig = ref({ driver: 'temporal', namespace: '-', task_queue: '-', max_concurrent: 10, heartbeat_seconds: 30, timeout_minutes: 60 });
const sagaData = ref(null);
const showSagaDialog = ref(false);

const filters = reactive({ workflow_name: '', status: '' });

const statData = reactive({
    total: '0',
    running: '0',
    completed: '0',
    failed: '0',
    compensating: '0',
    cancelled: '0',
});

const statCardMeta = [
    { key: 'total', color: '#0f172a', labelKey: 'workflows_page.stats.total' },
    { key: 'running', color: '#e6a23c', labelKey: 'workflows_page.stats.running' },
    { key: 'completed', color: '#67c23a', labelKey: 'workflows_page.stats.completed' },
    { key: 'failed', color: '#f56c6c', labelKey: 'workflows_page.stats.failed' },
    { key: 'compensating', color: '#f56c6c', labelKey: 'workflows_page.stats.compensating' },
    { key: 'cancelled', color: '#909399', labelKey: 'workflows_page.stats.cancelled' },
];

const statCards = computed(() => statCardMeta.map((m) => ({
    key: m.key,
    color: m.color,
    label: t(m.labelKey),
    value: statData[m.key],
})));

const failedCount = computed(() => parseInt(statData.failed, 10) || 0);

const statusFilterOptions = computed(() => [
    { label: t('workflows_page.status.running'), value: 'running' },
    { label: t('workflows_page.status.completed'), value: 'completed' },
    { label: t('workflows_page.status.failed'), value: 'failed' },
    { label: t('workflows_page.status.compensating'), value: 'compensating' },
    { label: t('workflows_page.status.cancelled'), value: 'cancelled' },
]);

const statusLabels = computed(() => ({
    running: t('workflows_page.status.running'),
    completed: t('workflows_page.status.completed'),
    failed: t('workflows_page.status.failed'),
    cancelled: t('workflows_page.status.cancelled'),
    compensating: t('workflows_page.status.compensating'),
    pending: t('workflows_page.status.pending'),
}));

const stepStatusLabels = computed(() => ({
    completed: t('workflows_page.step_status.completed'),
    failed: t('workflows_page.step_status.failed'),
    compensated: t('workflows_page.step_status.compensated'),
    running: t('workflows_page.step_status.running'),
    pending: t('workflows_page.step_status.pending'),
}));

function filterByStatus(key) {
    if (key === 'total') { filters.status = ''; } else { filters.status = key; }
    fetchInstances();
}

function statusTagType(status) {
    return { running: 'warning', completed: 'success', failed: 'danger', cancelled: 'info', compensating: 'warning', pending: 'info' }[status] || 'info';
}
function statusLabel(status) {
    return statusLabels.value[status] || status;
}
function stepStatusTag(status) {
    return { completed: 'success', failed: 'danger', compensated: 'warning', running: 'primary', pending: 'info' }[status] || 'info';
}
function stepStatusLabel(status) {
    return stepStatusLabels.value[status] || status;
}
function formatTime(time) {
    if (!time) return '';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(time).toLocaleString(loc);
}
function truncate(s, n) {
    return s?.length > n ? s.substring(0, n) + '...' : s || '';
}
function stepProgress(row) {
    const steps = row.step_executions_count;
    if (!steps || steps === 0) return row.status === 'completed' ? 100 : 0;
    return row.status === 'completed' ? 100 : row.status === 'running' ? 50 : row.status === 'failed' ? 30 : 0;
}

async function fetchDefinitions() {
    loadingDefinitions.value = true;
    try {
        const res = await workflowApi.definitions();
        definitions.value = res.data?.data || [];
    } catch { /* ignore */ }
    finally { loadingDefinitions.value = false; }
}

async function fetchDashboard() {
    try {
        const res = await workflowApi.dashboard();
        const d = res.data?.data || {};
        const stats = d.stats || {};
        statData.total = String(stats.total || 0);
        statData.running = String(stats.running || 0);
        statData.completed = String(stats.completed || 0);
        statData.failed = String(stats.failed || 0);
        statData.compensating = String(stats.compensating || 0);
        statData.cancelled = String(stats.cancelled || 0);
        todayStats.value = d.today_stats || null;
        recentFailures.value = d.recent_failures || [];
        byWorkflowStats.value = d.by_workflow || [];
        if (d.worker_status) workerConfig.value = d.worker_status;
    } catch { /* ignore */ }
}

async function fetchInstances() {
    loadingInstances.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (filters.workflow_name) params.workflow_name = filters.workflow_name;
        if (filters.status) params.status = filters.status;
        const res = await workflowApi.instances(params);
        instances.value = res.data?.data?.data || [];
        total.value = res.data?.data?.total || 0;
    } catch { /* ignore */ }
    finally { loadingInstances.value = false; }
}

async function showDetail(row) {
    try {
        const res = await workflowApi.show(row.id);
        detail.value = res.data?.data || null;
        showDetailDialog.value = true;
    } catch { ElMessage.error(t('workflows_page.messages.detail_load_fail')); }
}

async function showSaga(row) {
    try {
        const res = await workflowApi.sagaStatus(row.id);
        sagaData.value = res.data?.data || null;
        showSagaDialog.value = true;
    } catch { ElMessage.error(t('workflows_page.messages.saga_load_fail')); }
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(t('workflows_page.messages.cancel_confirm', { id: row.id }), t('actions.confirm'), { type: 'warning' });
        await workflowApi.cancel(row.id);
        ElMessage.success(t('workflows_page.messages.cancelled_ok'));
        await fetchInstances();
    } catch { /* cancelled */ }
}

async function handleRetry(row) {
    try {
        await ElMessageBox.confirm(t('workflows_page.messages.retry_confirm', { id: row.id }), t('actions.confirm'), { type: 'info' });
        await workflowApi.retry(row.id);
        ElMessage.success(t('workflows_page.messages.retried_ok'));
        await fetchInstances();
    } catch { /* cancelled */ }
}

async function handleBatchRetry() {
    try {
        await ElMessageBox.confirm(t('workflows_page.messages.batch_retry_confirm', { n: failedCount.value }), t('workflows_page.messages.batch_retry_title'), { type: 'info' });
        const res = await workflowApi.batchRetry({ workflow_name: filters.workflow_name || undefined });
        const data = res.data?.data || [];
        const success = data.filter(r => r.status === 'retried').length;
        ElMessage.success(t('workflows_page.messages.batch_retry_ok', { n: success }));
        await fetchDashboard();
        await fetchInstances();
    } catch { /* cancelled */ }
}

onMounted(() => {
    fetchDefinitions();
    fetchDashboard();
    fetchInstances();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0 0 4px; }
.header-subtitle { font-size: 14px; color: #909399; }
.mb-4 { margin-bottom: 16px; }
.stat-box { text-align: center; padding: 4px; cursor: pointer; }
.stat-num { font-size: 26px; font-weight: 700; }
.stat-num.success { color: #67c23a; }
.stat-num.danger { color: #f56c6c; }
.stat-lbl { font-size: 13px; color: #909399; margin-top: 2px; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.card-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
.steps-title { margin: 16px 0 12px; font-size: 15px; }
.step-item { line-height: 1.6; }
.step-error { font-size: 12px; color: #f56c6c; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
.step-duration { font-size: 12px; color: #909399; margin-left: 8px; }
.saga-step-tag { margin: 4px; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.info-item { display: flex; flex-direction: column; }
.info-label { font-size: 11px; color: #909399; }
.info-value { font-size: 14px; font-weight: 600; }
.today-grid { display: flex; justify-content: space-around; text-align: center; padding: 8px 0; }
.today-item { display: flex; flex-direction: column; }
.today-num { font-size: 32px; font-weight: 700; color: #0f172a; }
.today-num.success { color: #67c23a; }
.today-num.danger { color: #f56c6c; }
.today-lbl { font-size: 12px; color: #909399; }
.fail-item { line-height: 1.5; }
.fail-msg { font-size: 12px; color: #909399; margin-top: 2px; }
.empty-text { text-align: center; color: #909399; padding: 20px; font-size: 13px; }
.ml-2 { margin-left: 8px; }
</style>
