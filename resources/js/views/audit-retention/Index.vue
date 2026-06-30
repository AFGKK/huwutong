<template>
    <div class="audit-retention-page">
        <!-- 概览统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ overviewData.total ?? '-' }}</div>
                        <div class="stat-label">审计日志总量</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value info">{{ overviewData.recent_30d ?? '-' }}</div>
                        <div class="stat-label">近30天新增</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value warning">{{ overviewData.estimated_storage_mb ?? '-' }} MB</div>
                        <div class="stat-label">存储占用（估算）</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value" :class="prunePreviewTotal > 0 ? 'danger' : 'success'">{{ prunePreviewTotal }}</div>
                        <div class="stat-label">待清理记录</div>
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
                            <span>日志保留策略</span>
                        </div>
                    </template>
                    <el-table :data="policies" v-loading="loading" stripe size="small">
                        <el-table-column label="日志类型" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.type === 'audit' ? '' : row.type === 'security' ? 'danger' : row.type === 'error' ? 'warning' : 'info'">
                                    {{ row.type }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="保留天数" width="100" align="center">
                            <template #default="{ row }">
                                <span class="retention-days" @click="openEdit(row)">{{ row.retention_days }} 天</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="日志量" width="80" align="center">
                            <template #default="{ row }">{{ row.log_count }}</template>
                        </el-table-column>
                        <el-table-column label="最早日志" width="150">
                            <template #default="{ row }">{{ row.oldest_log_date || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openEdit(row)">设置</el-button>
                                <el-button v-if="row.is_custom" text size="small" type="danger" @click="handleReset(row)">重置</el-button>
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
                            <span>近30天趋势</span>
                        </div>
                    </template>
                    <div class="chart-placeholder">
                        <div class="chart-empty" v-if="!byDate.length">暂无数据</div>
                        <div class="bar-chart" v-else>
                            <div class="bar-item" v-for="d in byDate" :key="d.date" :title="`${d.date}: ${d.count} 条`">
                                <div class="bar-fill" :style="{ height: barHeight(d.count, maxByDate) + '%' }"></div>
                                <div class="bar-label">{{ d.date.slice(5) }}</div>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 策略编辑对话框 -->
        <el-dialog v-model="showEditDialog" :title="'设置保留策略 - ' + (editing?.type || '')" width="450px">
            <el-form label-width="100px" v-if="editing">
                <el-form-item label="日志类型">
                    <el-tag :type="editing.type === 'audit' ? '' : editing.type === 'security' ? 'danger' : editing.type === 'error' ? 'warning' : 'info'">
                        {{ editing.type }}
                    </el-tag>
                </el-form-item>
                <el-form-item label="保留天数">
                    <el-input-number v-model="editDays" :min="1" :max="3650" :step="30" style="width: 200px" />
                    <div class="form-help">当前值：{{ editing.retention_days }} 天，日志量：{{ editing.log_count }} 条</div>
                </el-form-item>
                <el-form-item label="说明">
                    <el-input v-model="editDescription" type="textarea" :rows="2" maxlength="500" show-word-limit />
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="editActive" />
                </el-form-item>

                <el-divider />
                <div class="prune-preview" v-if="editPreview !== null">
                    <p>按此策略，将清理 <strong>{{ editPreview.to_prune }}</strong> 条 < {{ editPreview.cutoff_date }} 的日志</p>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="confirmSave">
                    {{ editing?.is_custom ? '更新策略' : '创建策略' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 导出面板 -->
        <el-card shadow="never" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>导出审计日志</span>
                    <el-button type="primary" @click="showExportPanel = !showExportPanel" size="small">
                        {{ showExportPanel ? '收起' : '展开导出面板' }}
                    </el-button>
                </div>
            </template>
            <template v-if="showExportPanel">
                <el-form :inline="true" label-width="80px">
                    <el-form-item label="时间范围">
                        <el-date-picker
                            v-model="exportDateRange"
                            type="daterange"
                            range-separator="至"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            value-format="YYYY-MM-DD"
                        />
                    </el-form-item>
                    <el-form-item label="日志类型">
                        <el-select v-model="exportFilterType" placeholder="全部" clearable style="width: 120px">
                            <el-option label="审计" value="audit" />
                            <el-option label="安全" value="security" />
                            <el-option label="错误" value="error" />
                            <el-option label="系统" value="system" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="动作筛选">
                        <el-input v-model="exportFilterAction" placeholder="如：license." style="width: 160px" />
                        <div class="form-help">支持前缀匹配</div>
                    </el-form-item>
                    <el-form-item label="搜索">
                        <el-input v-model="exportSearch" placeholder="关键词" style="width: 160px" />
                    </el-form-item>
                    <el-form-item label="格式">
                        <el-radio-group v-model="exportFormat">
                            <el-radio value="csv">CSV</el-radio>
                            <el-radio value="json">JSON</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="success" :loading="exporting" @click="handleExport">
                            <el-icon><Download /></el-icon> 导出
                        </el-button>
                        <el-button text size="small" type="info" @click="handleExportPreview">预览数量</el-button>
                    </el-form-item>
                </el-form>
                <div v-if="exportPreviewCount !== null" class="export-preview">
                    符合条件的日志数：<strong>{{ exportPreviewCount }}</strong>（最大导出 {{ exportMaxRows }} 条）
                </div>
            </template>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Download } from '@element-plus/icons-vue';
import auditRetentionApi from '@/api/auditRetention';
import apiClient from '@/api/client';

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
        ElMessage.error('获取概览失败');
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
            ElMessage.success('策略已更新');
        } else {
            await auditRetentionApi.create({
                type: editing.value.type,
                retention_days: editDays.value,
                description: editDescription.value || undefined,
            });
            ElMessage.success('自定义策略已创建');
        }
        showEditDialog.value = false;
        await fetchPolicies();
        await fetchOverview();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleReset(row) {
    try {
        await ElMessageBox.confirm(`确定将「${row.type}」保留策略恢复为默认值吗？`, '确认', { type: 'warning' });
        await auditRetentionApi.destroy(row.id);
        ElMessage.success('已恢复默认策略');
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
            if (!response.ok) throw new Error('导出失败');
            return response.blob();
        })
        .then(blob => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            const ext = exportFormat.value === 'csv' ? '.csv' : '.json';
            link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}${ext}`;
            link.click();
            URL.revokeObjectURL(link.href);
            ElMessage.success('导出成功');
        })
        .catch(() => ElMessage.error('导出失败'))
        .finally(() => { exporting.value = false; });
    } catch {
        exporting.value = false;
        ElMessage.error('导出失败');
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
        ElMessage.error('预览失败');
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
