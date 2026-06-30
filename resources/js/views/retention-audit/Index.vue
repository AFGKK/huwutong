<template>
    <div class="retention-audit-page">
        <div class="page-header">
            <div class="header-left">
                <h2>数据保留审计</h2>
                <span class="header-subtitle">多数据源保留策略管理、自动清理调度、合规报告导出</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">数据源总数</div>
                        <div class="stat-value primary">{{ dashboard.by_source?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总记录数</div>
                        <div class="stat-value success">{{ formatNumber(dashboard.total_records) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">存储占用</div>
                        <div class="stat-value warning">{{ dashboard.total_storage_mb }} MB</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">清理记录</div>
                        <div class="stat-value info">{{ dashboard.recent_cleanups?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 保留策略管理 -->
            <el-tab-pane label="保留策略管理" name="policies">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">所有数据源保留策略</span>
                    </div>
                </div>

                <el-table :data="dashboard.by_source" v-loading="loading" stripe size="small">
                    <el-table-column label="数据源" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.data_source)" size="small">{{ row.display_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="记录数" width="100" align="center" prop="count" />
                    <el-table-column label="保留天数" width="110" align="center" prop="retention_days" />
                    <el-table-column label="待清理" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.to_prune > 0 ? 'danger' : 'success'" size="small" effect="plain">
                                {{ row.to_prune }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="存储 (MB)" width="110" align="center" prop="storage_mb" />
                    <el-table-column label="最早数据" width="150">
                        <template #default="{ row }">{{ formatTime(row.oldest) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openPolicyEdit(row)">修改策略</el-button>
                            <el-button
                                text
                                size="small"
                                type="danger"
                                :disabled="row.to_prune === 0"
                                :loading="cleaningSource === row.data_source"
                                @click="handleExtendedCleanup(row)"
                            >
                                立即清理
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 清理调度配置 -->
            <el-tab-pane label="清理调度" name="schedules">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">自动清理调度配置</span>
                    </div>
                </div>

                <el-table :data="schedules" v-loading="loadingSchedules" stripe size="small">
                    <el-table-column label="数据源" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.data_source)" size="small">{{ row.display_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="频率" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.frequency === 'manual' ? 'info' : 'primary'" size="small">
                                {{ freqLabel(row.frequency) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="执行时间" width="100" align="center" prop="time_of_day" />
                    <el-table-column label="每批条数" width="100" align="center" prop="batch_size" />
                    <el-table-column label="状态" width="90" align="center">
                        <template #default="{ row }">
                            <el-switch
                                :model-value="row.is_active"
                                @change="(val) => handleToggleSchedule(row, val)"
                                size="small"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="上次执行" width="150" prop="last_run_at">
                        <template #default="{ row }">{{ formatTime(row.last_run_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openScheduleEdit(row)">编辑</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 合规报告导出 -->
            <el-tab-pane label="合规报告导出" name="exports">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="exportFilter.report_id" placeholder="选择报告" clearable style="width: 300px" @change="fetchExports">
                            <el-option v-for="r in allReports" :key="r.id" :label="r.title" :value="r.id" />
                        </el-select>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openExportDialog">
                            <el-icon><Download /></el-icon> 导出报告
                        </el-button>
                    </div>
                </div>

                <el-table :data="exports" v-loading="loadingExports" stripe size="small">
                    <el-table-column label="报告标题" min-width="200">
                        <template #default="{ row }">{{ row.report?.title || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="格式" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.format === 'json' ? 'warning' : 'success'" size="small" effect="plain">
                                {{ row.format?.toUpperCase() }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="exportStatusTag(row.status)" size="small">
                                {{ exportStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="文件大小" width="100" align="center">
                        <template #default="{ row }">{{ row.file_size ? formatBytes(row.file_size) : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="生成人" width="120" prop="generator?.name" />
                    <el-table-column label="生成时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.generated_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'completed'" text size="small" type="primary" @click="downloadExport(row)">
                                下载
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 清理历史 -->
            <el-tab-pane label="清理历史" name="history">
                <el-table :data="cleanupHistory" v-loading="loadingHistory" stripe size="small">
                    <el-table-column label="数据源" width="140">
                        <template #default="{ row }">
                            <el-tag :type="sourceTag(row.type || row.data_source)" size="small">
                                {{ sourceLabel(row.type || row.data_source) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
                                {{ row.status === 'completed' ? '完成' : row.status === 'partial' ? '部分' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="清理前" width="90" align="center" prop="total_logs_before" />
                    <el-table-column label="清理数量" width="90" align="center" prop="pruned_count" />
                    <el-table-column label="保留天数" width="100" align="center" prop="retention_days" />
                    <el-table-column label="说明" min-width="200">
                        <template #default="{ row }">{{ row.notes || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="执行人" width="120" prop="initiator?.name" />
                    <el-table-column label="执行时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.executed_at) }}</template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <!-- 保留策略编辑对话框 -->
        <el-dialog v-model="showPolicyDialog" title="编辑保留策略" width="480px">
            <el-form ref="policyFormRef" :model="policyForm" :rules="policyRules" label-width="100px">
                <el-form-item label="数据源">
                    <el-tag :type="sourceTag(policyForm.data_source)" size="small">{{ policyForm.display_name }}</el-tag>
                </el-form-item>
                <el-form-item label="保留天数" prop="retention_days">
                    <el-input-number v-model="policyForm.retention_days" :min="1" :max="3650" style="width: 100%" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="policyForm.is_active" active-text="启用" inactive-text="停用" />
                </el-form-item>
                <el-form-item label="说明" prop="description">
                    <el-input v-model="policyForm.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPolicyDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingPolicy" @click="handleSavePolicy">保存</el-button>
            </template>
        </el-dialog>

        <!-- 调度编辑对话框 -->
        <el-dialog v-model="showScheduleDialog" title="编辑清理调度" width="500px">
            <el-form ref="scheduleFormRef" :model="scheduleForm" :rules="scheduleRules" label-width="110px">
                <el-form-item label="数据源">
                    <el-tag :type="sourceTag(scheduleForm.data_source)" size="small">{{ scheduleForm.display_name }}</el-tag>
                </el-form-item>
                <el-form-item label="频率" prop="frequency">
                    <el-select v-model="scheduleForm.frequency" style="width: 100%">
                        <el-option label="每天" value="daily" />
                        <el-option label="每周" value="weekly" />
                        <el-option label="每月" value="monthly" />
                        <el-option label="手动" value="manual" />
                    </el-select>
                </el-form-item>
                <el-form-item label="执行时间" prop="time_of_day">
                    <el-time-picker v-model="scheduleTime" format="HH:mm" value-format="HH:mm" style="width: 100%" />
                </el-form-item>
                <el-form-item v-if="scheduleForm.frequency === 'weekly'" label="星期几">
                    <el-select v-model="scheduleForm.day_of_week" style="width: 100%">
                        <el-option label="周日" value="0" />
                        <el-option label="周一" value="1" />
                        <el-option label="周二" value="2" />
                        <el-option label="周三" value="3" />
                        <el-option label="周四" value="4" />
                        <el-option label="周五" value="5" />
                        <el-option label="周六" value="6" />
                    </el-select>
                </el-form-item>
                <el-form-item label="每批条数" prop="batch_size">
                    <el-input-number v-model="scheduleForm.batch_size" :min="100" :max="10000" :step="100" style="width: 100%" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="scheduleForm.is_active" active-text="启用" inactive-text="停用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showScheduleDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingSchedule" @click="handleSaveSchedule">保存</el-button>
            </template>
        </el-dialog>

        <!-- 导出对话框 -->
        <el-dialog v-model="showExportDialog" title="导出合规报告" width="450px">
            <el-form ref="exportFormRef" :model="exportForm" :rules="exportRules" label-width="100px">
                <el-form-item label="合规报告" prop="report_id">
                    <el-select v-model="exportForm.report_id" placeholder="选择要导出的报告" style="width: 100%">
                        <el-option v-for="r in allReports" :key="r.id" :label="r.title" :value="r.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="导出格式" prop="format">
                    <el-radio-group v-model="exportForm.format">
                        <el-radio value="json">JSON</el-radio>
                        <el-radio value="csv">CSV</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showExportDialog = false">取消</el-button>
                <el-button type="primary" :loading="exporting" @click="handleExport">导出</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Download } from '@element-plus/icons-vue';
import auditGovernanceApi from '@/api/auditGovernance';

// ─── 标签 ───
const activeTab = ref('policies');

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
        ElMessage.error('加载仪表盘失败');
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
const policyRules = {
    retention_days: [{ required: true, message: '请设置保留天数', trigger: 'blur' }],
};

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
            ElMessage.success('策略已保存');
            showPolicyDialog.value = false;
            await fetchDashboard();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        savingPolicy.value = false;
    }
}

async function handleExtendedCleanup(row) {
    try {
        await ElMessageBox.confirm(
            `确定要清理「${row.display_name}」中超过 ${row.retention_days} 天的 ${row.to_prune} 条数据吗？此操作不可撤销。`,
            '确认清理',
            { type: 'warning', confirmButtonText: '确认清理', cancelButtonText: '取消' }
        );
    } catch { return; }

    cleaningSource.value = row.data_source;
    try {
        const res = await auditGovernanceApi.executeExtendedCleanup({ data_source: row.data_source });
        if (res.success) {
            ElMessage.success(`已清理 ${res.data?.pruned_count || 0} 条数据`);
            await fetchDashboard();
            await fetchCleanupHistory();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '清理失败');
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
const scheduleRules = {
    frequency: [{ required: true, message: '请选择频率', trigger: 'change' }],
    batch_size: [{ required: true, message: '请设置批处理大小', trigger: 'blur' }],
};

async function fetchSchedules() {
    loadingSchedules.value = true;
    try {
        const res = await auditGovernanceApi.cleanupSchedules();
        if (res.success) schedules.value = res.data || [];
    } catch {
        ElMessage.error('加载调度配置失败');
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
            ElMessage.success('调度配置已保存');
            showScheduleDialog.value = false;
            await fetchSchedules();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
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
        ElMessage.success(val ? '调度已启用' : '调度已停用');
    } catch {
        ElMessage.error('操作失败');
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
const exportRules = {
    report_id: [{ required: true, message: '请选择报告', trigger: 'change' }],
    format: [{ required: true, message: '请选择格式', trigger: 'change' }],
};

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
        ElMessage.error('加载导出记录失败');
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
            ElMessage.success('报告已导出');
            showExportDialog.value = false;
            if (exportFilter.report_id === exportForm.report_id) {
                await fetchExports();
            }
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '导出失败');
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
        ElMessage.error('加载清理历史失败');
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
    const map = {
        audit_log: '审计日志', security_log: '安全日志', error_log: '错误日志', system_log: '系统日志',
        apm_request: 'APM 请求', webhook_event: 'Webhook 事件', webhook_delivery: 'Webhook 投递',
        license: 'License', api_endpoint: 'API 端点',
    };
    return map[source] || source;
}

function freqLabel(freq) {
    const map = { daily: '每天', weekly: '每周', monthly: '每月', manual: '手动' };
    return map[freq] || freq;
}

function exportStatusTag(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' };
    return map[status] || 'info';
}

function exportStatusLabel(status) {
    const map = { pending: '等待中', processing: '处理中', completed: '已完成', failed: '失败' };
    return map[status] || status;
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

function formatNumber(n) {
    if (!n && n !== 0) return '0';
    return n.toLocaleString();
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
