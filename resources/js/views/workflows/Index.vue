<template>
    <div class="workflow-page">
        <div class="page-header">
            <div class="header-left">
                <h2>Temporal 工作流引擎</h2>
                <span class="header-subtitle">M2-137 · 异步业务工作流监控 · Saga 分布式事务</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="3" v-for="s in statCards" :key="s.label">
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
                    <template #header>⚡ Temporal Worker</template>
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">驱动</span><span class="info-value">{{ workerConfig.driver }}</span></div>
                        <div class="info-item"><span class="info-label">命名空间</span><span class="info-value">{{ workerConfig.namespace }}</span></div>
                        <div class="info-item"><span class="info-label">任务队列</span><span class="info-value">{{ workerConfig.task_queue }}</span></div>
                        <div class="info-item"><span class="info-label">并发上限</span><span class="info-value">{{ workerConfig.max_concurrent }}</span></div>
                        <div class="info-item"><span class="info-label">心跳间隔</span><span class="info-value">{{ workerConfig.heartbeat_seconds }}s</span></div>
                        <div class="info-item"><span class="info-label">超时时间</span><span class="info-value">{{ workerConfig.timeout_minutes }}min</span></div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="9">
                <el-card shadow="never">
                    <template #header>📋 工作流定义</template>
                    <el-table :data="definitions" v-loading="loadingDefinitions" stripe size="small">
                        <el-table-column prop="name" label="名称" width="150" />
                        <el-table-column prop="description" label="描述" min-width="180" />
                        <el-table-column label="步骤数" width="70">
                            <template #default="{ row }">{{ row.steps_definition?.length || 0 }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :span="9">
                <el-card shadow="never">
                    <template #header>📊 按工作流统计</template>
                    <el-table :data="byWorkflowStats" v-loading="loadingStats" stripe size="small">
                        <el-table-column prop="workflow_name" label="工作流" width="150" />
                        <el-table-column label="总数" width="60" prop="total" />
                        <el-table-column label="运行中" width="65">
                            <template #default="{ row }"><el-tag type="warning" size="small">{{ row.running }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="已完成" width="65">
                            <template #default="{ row }"><el-tag type="success" size="small">{{ row.completed }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="失败" width="55">
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
                    <template #header>🔄 今日动态</template>
                    <div v-if="todayStats" class="today-grid">
                        <div class="today-item"><span class="today-num">{{ todayStats.started }}</span><span class="today-lbl">已启动</span></div>
                        <div class="today-item"><span class="today-num success">{{ todayStats.completed }}</span><span class="today-lbl">已完成</span></div>
                        <div class="today-item"><span class="today-num danger">{{ todayStats.failed }}</span><span class="today-lbl">已失败</span></div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>⚠️ 近期失败</template>
                    <div v-if="recentFailures.length === 0" class="empty-text">暂无失败记录</div>
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
                    <span>工作流实例</span>
                    <div class="card-actions">
                        <el-select v-model="filters.workflow_name" clearable placeholder="全部类型" style="width: 150px" @change="fetchInstances">
                            <el-option v-for="d in definitions" :key="d.name" :label="d.name" :value="d.name" />
                        </el-select>
                        <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 110px" @change="fetchInstances">
                            <el-option label="运行中" value="running" />
                            <el-option label="已完成" value="completed" />
                            <el-option label="失败" value="failed" />
                            <el-option label="补偿中" value="compensating" />
                            <el-option label="已取消" value="cancelled" />
                        </el-select>
                        <el-button type="danger" plain @click="handleBatchRetry" :disabled="failedCount === 0">批量重试 ({{ failedCount }})</el-button>
                        <el-button @click="fetchInstances">刷新</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="instances" v-loading="loadingInstances" stripe @row-click="showDetail">
                <el-table-column type="index" label="#" width="45" />
                <el-table-column prop="workflow_name" label="工作流" width="130" />
                <el-table-column label="状态" width="85">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="current_step" label="当前步骤" width="130" />
                <el-table-column label="进度" width="160">
                    <template #default="{ row }">
                        <el-progress :percentage="stepProgress(row)" :stroke-width="14" :status="row.status === 'failed' ? 'exception' : ''" />
                    </template>
                </el-table-column>
                <el-table-column label="重试" width="55" align="center">
                    <template #default="{ row }">{{ row.retry_count }}/{{ row.max_retries }}</template>
                </el-table-column>
                <el-table-column prop="error_message" label="错误" min-width="160" show-overflow-tooltip />
                <el-table-column label="创建时间" width="150">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="140" fixed="right">
                    <template #default="{ row }">
                        <el-button link size="small" @click.stop="showDetail(row)">详情</el-button>
                        <el-button link size="small" type="warning" @click.stop="showSaga(row)" v-if="row.status === 'compensating' || row.status === 'failed'">Saga</el-button>
                        <el-button link size="small" type="danger" @click.stop="handleCancel(row)" v-if="row.status === 'running'">取消</el-button>
                        <el-button link size="small" type="warning" @click.stop="handleRetry(row)" v-if="row.status === 'failed'">重试</el-button>
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
        <el-dialog v-model="showDetailDialog" :title="'工作流 #' + (detail?.id || '')" width="800px">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="工作流">{{ detail.workflow_name }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTagType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="开始时间">{{ detail.started_at ? formatTime(detail.started_at) : '-' }}</el-descriptions-item>
                    <el-descriptions-item label="耗时">{{ detail.elapsed_seconds ? detail.elapsed_seconds + 's' : '-' }}</el-descriptions-item>
                    <el-descriptions-item label="重试次数">{{ detail.retry_count }}/{{ detail.max_retries }}</el-descriptions-item>
                    <el-descriptions-item label="错误" v-if="detail.error_message">
                        <el-tag type="danger">{{ detail.error_message }}</el-tag>
                    </el-descriptions-item>
                </el-descriptions>

                <!-- 步骤进度条 -->
                <h4 class="steps-title">执行步骤</h4>
                <el-steps :active="detail.steps?.filter(s => s.status === 'completed').length || 0" align-center>
                    <el-step v-for="s in detail.steps" :key="s.id" :title="s.step_name"
                        :status="s.status === 'completed' ? 'finish' : s.status === 'failed' ? 'error' : s.status === 'running' ? 'process' : 'wait'" />
                </el-steps>

                <h4 class="steps-title">步骤时间线</h4>
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
        <el-dialog v-model="showSagaDialog" :title="'Saga 事务状态 #' + (sagaData?.instance_id || '')" width="650px">
            <template v-if="sagaData">
                <el-alert :title="sagaData.is_compensating ? '⚠️ 正在执行补偿' : sagaData.compensation_completed ? '✅ 补偿已完成' : '✔ 事务已提交'" 
                    :type="sagaData.is_compensating ? 'warning' : sagaData.compensation_completed ? 'success' : 'info'" show-icon :closable="false" class="mb-4" />
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="Saga 状态">{{ sagaData.status }}</el-descriptions-item>
                    <el-descriptions-item label="已提交步骤">{{ sagaData.committed_steps?.length || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="已补偿步骤">{{ sagaData.compensated_steps?.length || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="失败步骤" v-if="sagaData.failed_step">
                        <el-tag type="danger">{{ sagaData.failed_step.name }}: {{ sagaData.failed_step.error }}</el-tag>
                    </el-descriptions-item>
                </el-descriptions>

                <h4 class="steps-title">已提交步骤</h4>
                <div v-if="sagaData.committed_steps?.length">
                    <el-tag v-for="s in sagaData.committed_steps" :key="s.name" type="success" class="saga-step-tag">{{ s.name }}</el-tag>
                </div>
                <div v-else class="empty-text">无已提交步骤</div>

                <h4 class="steps-title" v-if="sagaData.compensated_steps?.length">已补偿步骤</h4>
                <div v-if="sagaData.compensated_steps?.length">
                    <el-tag v-for="s in sagaData.compensated_steps" :key="s.name" type="warning" class="saga-step-tag">{{ s.name }} ↩</el-tag>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { WarningFilled } from '@element-plus/icons-vue';
import workflowApi from '@/api/workflow';

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

const statCards = reactive([
    { label: '总计', key: 'total', color: '#409eff', value: '0' },
    { label: '运行中', key: 'running', color: '#e6a23c', value: '0' },
    { label: '已完成', key: 'completed', color: '#67c23a', value: '0' },
    { label: '失败', key: 'failed', color: '#f56c6c', value: '0' },
    { label: '补偿中', key: 'compensating', color: '#f56c6c', value: '0' },
    { label: '已取消', key: 'cancelled', color: '#909399', value: '0' },
]);

const failedCount = computed(() => parseInt(statCards[3].value) || 0);

function filterByStatus(key) {
    if (key === 'total') { filters.status = ''; } else { filters.status = key; }
    fetchInstances();
}

function statusTagType(status) {
    return { running: 'warning', completed: 'success', failed: 'danger', cancelled: 'info', compensating: 'warning', pending: 'info' }[status] || 'info';
}
function statusLabel(status) {
    return { running: '运行中', completed: '已完成', failed: '失败', cancelled: '已取消', compensating: '补偿中', pending: '待执行' }[status] || status;
}
function stepStatusTag(status) {
    return { completed: 'success', failed: 'danger', compensated: 'warning', running: 'primary', pending: 'info' }[status] || 'info';
}
function stepStatusLabel(status) {
    return { completed: '成功', failed: '失败', compensated: '已补偿', running: '运行中', pending: '等待中' }[status] || status;
}
function formatTime(t) {
    if (!t) return '';
    return new Date(t).toLocaleString('zh-CN');
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
        statCards[0].value = String(stats.total || 0);
        statCards[1].value = String(stats.running || 0);
        statCards[2].value = String(stats.completed || 0);
        statCards[3].value = String(stats.failed || 0);
        statCards[4].value = String(stats.compensating || 0);
        statCards[5].value = String(stats.cancelled || 0);
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
    } catch { ElMessage.error('获取详情失败'); }
}

async function showSaga(row) {
    try {
        const res = await workflowApi.sagaStatus(row.id);
        sagaData.value = res.data?.data || null;
        showSagaDialog.value = true;
    } catch { ElMessage.error('获取 Saga 状态失败'); }
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(`确认取消工作流 #${row.id}？将执行 Saga 补偿。`, '确认', { type: 'warning' });
        await workflowApi.cancel(row.id);
        ElMessage.success('已取消');
        await fetchInstances();
    } catch { /* cancelled */ }
}

async function handleRetry(row) {
    try {
        await ElMessageBox.confirm(`确认重试失败的工作流 #${row.id}？`, '确认', { type: 'info' });
        await workflowApi.retry(row.id);
        ElMessage.success('已重试');
        await fetchInstances();
    } catch { /* cancelled */ }
}

async function handleBatchRetry() {
    try {
        await ElMessageBox.confirm(`确认重试全部 ${failedCount.value} 个失败工作流？`, '批量重试', { type: 'info' });
        const res = await workflowApi.batchRetry({ workflow_name: filters.workflow_name || undefined });
        const data = res.data?.data || [];
        const success = data.filter(r => r.status === 'retried').length;
        ElMessage.success(`批量重试完成: ${success} 成功`);
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
.today-num { font-size: 32px; font-weight: 700; color: #409eff; }
.today-num.success { color: #67c23a; }
.today-num.danger { color: #f56c6c; }
.today-lbl { font-size: 12px; color: #909399; }
.fail-item { line-height: 1.5; }
.fail-msg { font-size: 12px; color: #909399; margin-top: 2px; }
.empty-text { text-align: center; color: #909399; padding: 20px; font-size: 13px; }
.ml-2 { margin-left: 8px; }
</style>
