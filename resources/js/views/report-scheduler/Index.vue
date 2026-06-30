<template>
    <div class="report-scheduler">
        <div class="page-header">
            <h2>报表调度器</h2>
            <p class="text-muted">定时生成并邮件投递报表，支持多格式导出</p>
        </div>

        <!-- ── 统计概览 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashboard.stats.total_schedules }}</div>
                    <div class="stat-label">总调度数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value active">{{ dashboard.stats.active_schedules }}</div>
                    <div class="stat-label">活跃中</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" :class="dashboard.stats.due_count > 0 ? 'warn' : ''">{{ dashboard.stats.due_count }}</div>
                    <div class="stat-label">待处理</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value success">{{ dashboard.stats.success_rate }}%</div>
                    <div class="stat-label">投递成功率</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- ── 调度列表 ── -->
            <el-tab-pane label="调度列表" name="schedules">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>报表调度</span>
                            <el-button type="primary" size="small" :icon="Plus" @click="showCreateDialog">新建调度</el-button>
                        </div>
                    </template>

                    <el-table :data="schedules" border stripe v-loading="loading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="报表" min-width="180">
                            <template #default="{ row }">
                                <div>
                                    <strong>{{ row.report?.name || '未知' }}</strong>
                                    <el-tag size="small" type="info" class="ml-2">{{ row.report?.data_source }}</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="调度计划" width="160">
                            <template #default="{ row }">
                                <code class="cron-badge">{{ row.cron_expression }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column label="格式" width="80">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.export_format }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-switch
                                    :model-value="row.is_active"
                                    :loading="togglingId === row.id"
                                    @change="toggleSchedule(row)"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column label="运行次数" width="80" align="center">
                            <template #default="{ row }">
                                <div class="run-stats">
                                    <el-tooltip :content="`成功: ${row.success_count}, 失败: ${row.failure_count}`">
                                        <span>
                                            {{ row.run_count }}
                                            <span v-if="row.failure_count > 0" class="text-danger">({{ row.failure_count }})</span>
                                        </span>
                                    </el-tooltip>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="下次运行" width="160">
                            <template #default="{ row }">
                                <span v-if="row.next_run_at">{{ formatDate(row.next_run_at) }}</span>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="上次运行" width="160">
                            <template #default="{ row }">
                                <span v-if="row.last_run_at">{{ formatDate(row.last_run_at) }}</span>
                                <span v-else class="text-muted">从未</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editSchedule(row)">编辑</el-button>
                                <el-button size="small" type="primary" plain @click="triggerSchedule(row)">触发</el-button>
                                <el-popconfirm title="确认删除此调度?" @confirm="deleteSchedule(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger" plain>删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- ── 投递日志 ── -->
            <el-tab-pane label="投递日志" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>投递记录</span>
                            <div class="filter-bar">
                                <el-select v-model="logFilters.status" clearable placeholder="状态" size="small" style="width:120px" @change="fetchLogs">
                                    <el-option label="全部" value="" />
                                    <el-option label="处理中" value="processing" />
                                    <el-option label="成功" value="completed" />
                                    <el-option label="失败" value="failed" />
                                </el-select>
                                <el-date-picker
                                    v-model="logDateRange"
                                    type="daterange"
                                    range-separator="至"
                                    start-placeholder="开始日期"
                                    end-placeholder="结束日期"
                                    size="small"
                                    value-format="YYYY-MM-DD"
                                    @change="fetchLogs"
                                />
                            </div>
                        </div>
                    </template>

                    <el-table :data="deliveryLogs" border stripe v-loading="logLoading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="报表" min-width="150">
                            <template #default="{ row }">
                                {{ row.report?.name || '未知' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="logStatusType(row.status)" size="small">
                                    {{ logStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="格式" width="80">
                            <template #default="{ row }">{{ row.export_format }}</template>
                        </el-table-column>
                        <el-table-column label="接收人" min-width="200">
                            <template #default="{ row }">
                                <div v-if="row.recipients && row.recipients.length > 0" class="recipient-list">
                                    <el-tag v-for="r in row.recipients.slice(0, 3)" :key="r.email" size="small" type="info" class="recipient-tag">
                                        {{ r.email }}
                                    </el-tag>
                                    <span v-if="row.recipients.length > 3" class="text-muted">+{{ row.recipients.length - 3 }}</span>
                                </div>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="尝试次数" width="80" align="center">
                            <template #default="{ row }">{{ row.attempts }}</template>
                        </el-table-column>
                        <el-table-column label="文件大小" width="100">
                            <template #default="{ row }">{{ formatFileSize(row.file_size) }}</template>
                        </el-table-column>
                        <el-table-column label="时间" width="160">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="错误信息" min-width="200">
                            <template #default="{ row }">
                                <el-tooltip v-if="row.error_message" :content="row.error_message">
                                    <el-tag type="danger" size="small" effect="dark" class="error-msg">
                                        {{ truncate(row.error_message, 50) }}
                                    </el-tag>
                                </el-tooltip>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div v-if="logTotal > logPerPage" class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="logPage"
                            :page-size="logPerPage"
                            :total="logTotal"
                            layout="prev, pager, next, total"
                            @current-change="fetchLogs"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ── 最近投递 ── -->
            <el-tab-pane label="最近活动" name="recent">
                <el-card shadow="never">
                    <template #header><span>最近投递活动</span></template>
                    <el-timeline>
                        <el-timeline-item
                            v-for="log in recentDeliveries"
                            :key="log.id"
                            :type="logStatusType(log.status)"
                            :timestamp="formatDate(log.created_at)"
                        >
                            <div class="timeline-content">
                                <strong>{{ log.report?.name || '报表' }}</strong>
                                <el-tag :type="logStatusType(log.status)" size="small" class="ml-2">
                                    {{ logStatusLabel(log.status) }}
                                </el-tag>
                            </div>
                            <div class="timeline-meta">
                                {{ log.export_format }} · {{ log.recipients?.length || 0 }} 个接收人
                            </div>
                            <div v-if="log.error_message" class="timeline-error">
                                {{ log.error_message }}
                            </div>
                        </el-timeline-item>
                        <el-timeline-item v-if="recentDeliveries.length === 0" type="info">
                            暂无投递记录
                        </el-timeline-item>
                    </el-timeline>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ── 新建/编辑调度对话框 ── -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? '编辑调度' : '新建调度'"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form label-position="top" size="small" :model="form">
                <el-row :gutter="16">
                    <el-col :span="24">
                        <el-form-item label="选择报表" required>
                            <el-select
                                v-model="form.report_id"
                                filterable
                                placeholder="选择要调度的报表"
                                style="width:100%"
                                :disabled="isEditing"
                            >
                                <el-option
                                    v-for="r in schedulableReports"
                                    :key="r.id"
                                    :label="`${r.name} (${r.data_source})`"
                                    :value="r.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="Cron 表达式" required>
                            <el-input v-model="form.cron_expression" placeholder="例如: 0 8 * * * (每天8点)">
                                <template #append>
                                    <el-tooltip content="常用: 0 8 * * *(每天8点), 0 */2 * * *(每2小时), 0 9 * * 1(每周一9点)">
                                        <el-icon><QuestionFilled /></el-icon>
                                    </el-tooltip>
                                </template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="导出格式">
                            <el-select v-model="form.export_format">
                                <el-option label="CSV" value="csv" />
                                <el-option label="JSON" value="json" />
                                <el-option label="XLSX" value="xlsx" />
                                <el-option label="PDF" value="pdf" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="邮件主题">
                            <el-input v-model="form.subject" placeholder="留空使用默认主题" maxlength="200" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="最大重试次数">
                            <el-input-number v-model="form.max_retries" :min="0" :max="10" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="邮件正文">
                    <el-input v-model="form.message" type="textarea" :rows="3" placeholder="可选：自定义邮件正文内容" maxlength="2000" />
                </el-form-item>
                <el-form-item label="接收人">
                    <div class="recipient-editor">
                        <div v-for="(r, i) in form.recipients" :key="i" class="recipient-row">
                            <el-input v-model="r.email" placeholder="邮箱地址" style="width:240px" />
                            <el-input v-model="r.name" placeholder="姓名(可选)" style="width:160px" class="ml-2" />
                            <el-button type="danger" :icon="Delete" text @click="removeRecipient(i)" />
                        </div>
                        <el-button size="small" @click="addRecipient" :icon="Plus">添加接收人</el-button>
                    </div>
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="form.include_chart">邮件中包含图表摘要</el-checkbox>
                    <el-checkbox v-model="form.is_active" class="ml-2">创建后立即启用</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveSchedule">
                    {{ isEditing ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    Plus, Delete, QuestionFilled,
} from '@element-plus/icons-vue';
import reportSchedulerApi from '@/api/reportScheduler';

const activeTab = ref('schedules');
const loading = ref(false);
const logLoading = ref(false);
const saving = ref(false);
const togglingId = ref(null);
const dialogVisible = ref(false);
const isEditing = ref(false);

const schedules = ref([]);
const schedulableReports = ref([]);
const deliveryLogs = ref([]);
const recentDeliveries = ref([]);
const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);
const logDateRange = ref(null);

const logFilters = reactive({
    status: '',
});

const dashboard = reactive({
    stats: {
        total_schedules: 0,
        active_schedules: 0,
        due_count: 0,
        total_deliveries: 0,
        success_rate: 100,
    },
});

const form = reactive({
    report_id: null,
    cron_expression: '0 8 * * *',
    export_format: 'csv',
    recipients: [],
    subject: '',
    message: '',
    include_chart: true,
    is_active: true,
    max_retries: 3,
});

// ─── 加载数据 ───

async function loadDashboard() {
    try {
        const { data: res } = await reportSchedulerApi.getDashboard();
        if (res.success) {
            Object.assign(dashboard.stats, res.data.stats);
            recentDeliveries.value = res.data.recent_deliveries || [];
        }
    } catch { /* ignore */ }
}

async function loadSchedules() {
    loading.value = true;
    try {
        const { data: res } = await reportSchedulerApi.getSchedules({ per_page: 100 });
        if (res.success) {
            schedules.value = res.data.items || [];
        }
    } catch { /* ignore */ }
    finally { loading.value = false; }
}

async function loadSchedulableReports() {
    try {
        const { data: res } = await reportSchedulerApi.getSchedulableReports();
        if (res.success) {
            schedulableReports.value = res.data || [];
        }
    } catch { /* ignore */ }
}

async function fetchLogs() {
    logLoading.value = true;
    try {
        const params = {
            page: logPage.value,
            per_page: logPerPage.value,
            status: logFilters.status || undefined,
        };
        if (logDateRange.value) {
            params.date_from = logDateRange.value[0];
            params.date_to = logDateRange.value[1];
        }
        const { data: res } = await reportSchedulerApi.getDeliveryLogs(params);
        if (res.success) {
            deliveryLogs.value = res.data.items || [];
            logTotal.value = res.data.total || 0;
        }
    } catch { /* ignore */ }
    finally { logLoading.value = false; }
}

// ─── CRUD ───

function showCreateDialog() {
    isEditing.value = false;
    form.report_id = null;
    form.cron_expression = '0 8 * * *';
    form.export_format = 'csv';
    form.recipients = [];
    form.subject = '';
    form.message = '';
    form.include_chart = true;
    form.is_active = true;
    form.max_retries = 3;
    loadSchedulableReports();
    dialogVisible.value = true;
}

function editSchedule(schedule) {
    isEditing.value = true;
    form.report_id = schedule.report_id;
    form.cron_expression = schedule.cron_expression;
    form.export_format = schedule.export_format;
    form.recipients = schedule.recipients ? JSON.parse(JSON.stringify(schedule.recipients)) : [];
    form.subject = schedule.subject || '';
    form.message = schedule.message || '';
    form.include_chart = schedule.include_chart !== false;
    form.is_active = schedule.is_active;
    form.max_retries = schedule.max_retries;
    editingId.value = schedule.id;
    dialogVisible.value = true;
}

const editingId = ref(null);

async function saveSchedule() {
    if (!form.report_id) {
        ElMessage.warning('请选择报表');
        return;
    }
    if (!form.cron_expression?.trim()) {
        ElMessage.warning('请输入 Cron 表达式');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            report_id: form.report_id,
            cron_expression: form.cron_expression.trim(),
            export_format: form.export_format,
            recipients: form.recipients.filter(r => r.email),
            subject: form.subject || null,
            message: form.message || null,
            include_chart: form.include_chart,
            is_active: form.is_active,
            max_retries: form.max_retries,
        };

        let res;
        if (isEditing.value) {
            res = await reportSchedulerApi.updateSchedule(editingId.value, payload);
        } else {
            res = await reportSchedulerApi.createSchedule(payload);
        }

        if (res.data.success) {
            ElMessage.success(isEditing.value ? '调度已更新' : '调度已创建');
            dialogVisible.value = false;
            await loadSchedules();
            await loadDashboard();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '操作失败');
    } finally {
        saving.value = false;
    }
}

async function deleteSchedule(schedule) {
    try {
        const { data: res } = await reportSchedulerApi.deleteSchedule(schedule.id);
        if (res.success) {
            ElMessage.success('调度已删除');
            await loadSchedules();
            await loadDashboard();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '删除失败');
    }
}

async function toggleSchedule(schedule) {
    togglingId.value = schedule.id;
    try {
        const { data: res } = await reportSchedulerApi.toggleSchedule(schedule.id);
        if (res.success) {
            schedule.is_active = res.data.is_active;
            ElMessage.success(res.data.is_active ? '调度已启用' : '调度已暂停');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '操作失败');
    } finally {
        togglingId.value = null;
    }
}

async function triggerSchedule(schedule) {
    try {
        const { data: res } = await reportSchedulerApi.triggerSchedule(schedule.id);
        if (res.success) {
            ElMessage.success('调度已触发，请查看投递日志');
            await loadSchedules();
            await loadDashboard();
            await fetchLogs();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '触发失败');
    }
}

// ─── 接收人管理 ───

function addRecipient() {
    form.recipients.push({ email: '', name: '' });
}

function removeRecipient(index) {
    form.recipients.splice(index, 1);
}

// ─── 格式化 ───

function formatDate(date) {
    if (!date) return '-';
    const d = new Date(date);
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function logStatusType(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' };
    return map[status] || 'info';
}

function logStatusLabel(status) {
    const map = { pending: '待处理', processing: '处理中', completed: '成功', failed: '失败' };
    return map[status] || status;
}

// ─── 初始化 ───

onMounted(async () => {
    await Promise.all([
        loadDashboard(),
        loadSchedules(),
        fetchLogs(),
    ]);
});
</script>

<style scoped>
.report-scheduler {
    padding: 16px 24px;
}

.page-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
}
.page-header .text-muted {
    margin: 0 0 16px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}

.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }

.stat-card {
    text-align: center;
    padding: 8px 0;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.stat-value.active { color: var(--el-color-success); }
.stat-value.warn { color: var(--el-color-warning); }
.stat-value.success { color: var(--el-color-success); }
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-bar {
    display: flex;
    gap: 8px;
    align-items: center;
}

.cron-badge {
    background: var(--el-fill-color);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    color: var(--el-color-primary);
}

.run-stats {
    font-size: 13px;
}
.text-danger {
    color: var(--el-color-danger);
    font-size: 11px;
}

.recipient-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.recipient-tag {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.error-msg {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recipient-editor {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.recipient-row {
    display: flex;
    align-items: center;
    gap: 4px;
}

.timeline-content {
    display: flex;
    align-items: center;
    gap: 8px;
}
.timeline-meta {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}
.timeline-error {
    font-size: 12px;
    color: var(--el-color-danger);
    margin-top: 2px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.text-muted {
    color: var(--el-text-color-placeholder);
}
</style>
