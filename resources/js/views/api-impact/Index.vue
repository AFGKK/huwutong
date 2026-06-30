<template>
    <div class="api-impact-page">
        <div class="page-header">
            <div>
                <h2>API 变更影响分析</h2>
                <p class="text-muted">废弃 API 版本影响分析 · 客户调用统计 · 迁移通知 · 报表导出</p>
            </div>
            <div class="header-actions">
                <el-radio-group v-model="days" size="small" @change="loadAll">
                    <el-radio-button :value="7">7天</el-radio-button>
                    <el-radio-button :value="30">30天</el-radio-button>
                    <el-radio-button :value="90">90天</el-radio-button>
                </el-radio-group>
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">废弃/停用版本</div><div class="metric-value warning">{{ dash.deprecated_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">已停用版本</div><div class="metric-value text-muted">{{ dash.retired_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">今日调用量</div><div class="metric-value">{{ dash.total_calls_today }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">待通知</div><div class="metric-value danger">{{ dash.pending_notifications }}</div></el-card></el-col>
        </el-row>

        <!-- 影响概览卡片 -->
        <el-row :gutter="16" class="mb-4" v-if="dash.impact_summary?.length">
            <el-col v-for="v in dash.impact_summary" :key="v.version_id" :xs="24" :sm="8">
                <el-card :shadow="'hover'" :class="['impact-card', v.days_until_sunset !== null && v.days_until_sunset < 30 ? 'border-danger' : 'border-warning']">
                    <div class="impact-header">
                        <span class="impact-version">{{ v.version }}</span>
                        <el-tag :type="v.status === 'deprecated' ? 'warning' : 'danger'" size="small">{{ v.status === 'deprecated' ? '已废弃' : '即将停用' }}</el-tag>
                    </div>
                    <div class="impact-stats">
                        <div><span class="stat-l">30d 调用:</span><span class="stat-v">{{ v.call_count_30d?.toLocaleString() }}</span></div>
                        <div><span class="stat-l">影响客户:</span><span class="stat-v">{{ v.affected_tenants }}</span></div>
                        <div v-if="v.deprecated_at"><span class="stat-l">废弃日期:</span><span class="stat-v">{{ v.deprecated_at }}</span></div>
                        <div v-if="v.days_until_sunset !== null"><span class="stat-l">距停用:</span><span :class="v.days_until_sunset < 30 ? 'danger' : 'warning'">{{ v.days_until_sunset }}天</span></div>
                    </div>
                    <el-button size="small" type="primary" style="margin-top:8px;width:100%" @click="analyzeVersion(v.version_id)">详细分析</el-button>
                </el-card>
            </el-col>
        </el-row>

        <!-- 综合报告 -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header"><span><el-icon><DataBoard /></el-icon> 版本影响综合报告</span></div>
            </template>
            <el-table :data="report.versions" stripe v-loading="reportLoading" size="small">
                <el-table-column prop="version" label="版本" width="80" />
                <el-table-column label="状态" width="90"><template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : (row.status === 'deprecated' ? 'warning' : 'danger')" size="small">{{ {active:'活跃', deprecated:'废弃', sunset:'停用'}[row.status] || row.status }}</el-tag></template></el-table-column>
                <el-table-column label="默认" width="60"><template #default="{row}"><el-icon v-if="row.is_default" color="#67c23a"><CircleCheck /></el-icon></template></el-table-column>
                <el-table-column label="废弃日期" width="110"><template #default="{row}">{{ row.deprecated_at || '—' }}</template></el-table-column>
                <el-table-column label="停用日期" width="110"><template #default="{row}">{{ row.sunset_at || '—' }}</template></el-table-column>
                <el-table-column label="调用量(30d)" width="120" prop="call_count" />
                <el-table-column label="客户数" width="80" prop="tenant_count" />
                <el-table-column label="影响等级" width="100"><template #default="{row}">
                    <el-tag :type="row.impact_level === 'critical' ? 'danger' : (row.impact_level === 'high' ? 'warning' : (row.impact_level === 'medium' ? 'info' : 'success'))" size="small">{{ {critical:'严重', high:'高', medium:'中', low:'低', none:'无', retired:'已停用'}[row.impact_level] || row.impact_level }}</el-tag>
                </template></el-table-column>
            </el-table>
        </el-card>

        <!-- 版本分析详情对话框 -->
        <el-dialog v-model="showAnalysis" :title="'影响分析: ' + (analysis?.version?.version || '')" width="850px" top="5vh">
            <template v-if="analysis">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">总调用量</div><div class="metric-value">{{ analysis.total_calls?.toLocaleString() }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">受影响客户</div><div class="metric-value warning">{{ analysis.total_tenants }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">状态</div><div class="metric-value"><el-tag :type="analysis.version?.status === 'deprecated' ? 'warning' : 'danger'" size="small">{{ analysis.version?.status }}</el-tag></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">分析周期</div><div class="metric-value">{{ analysis.analysis_period_days }}天</div></el-card></el-col>
                </el-row>
                <el-tabs type="border-card">
                    <el-tab-pane label="受影响客户">
                        <el-table :data="analysis.affected_tenants" stripe size="small" max-height="300">
                            <el-table-column prop="name" label="客户名称" min-width="160" />
                            <el-table-column prop="email" label="邮箱" min-width="180" />
                            <el-table-column label="调用量({{ days }}d)" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="路径分布 TOP10">
                        <el-table :data="analysis.by_path" stripe size="small" max-height="300">
                            <el-table-column prop="path" label="路径" min-width="300" />
                            <el-table-column label="调用量" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="月度趋势">
                        <el-table :data="analysis.monthly_trend" stripe size="small" max-height="300">
                            <el-table-column prop="month" label="月份" width="100" />
                            <el-table-column label="调用量" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="迁移指南">
                        <pre v-if="analysis.version?.migration_guide" class="guide-block">{{ analysis.version.migration_guide }}</pre>
                        <el-empty v-else description="未配置迁移指南" />
                    </el-tab-pane>
                </el-tabs>
                <div style="margin-top:12px;display:flex;gap:8px">
                    <el-button type="primary" @click="notifyCustomers(analysis.version?.id)" :loading="notifyLoading">📧 通知受影响客户</el-button>
                    <el-button @click="exportCsv(analysis.version?.id)">📥 导出报表</el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, DataBoard, CircleCheck } from '@element-plus/icons-vue';
import apiImpactApi from '@/api/apiImpact';

const loading = ref(false);
const reportLoading = ref(false);
const notifyLoading = ref(false);
const days = ref(30);
const dash = reactive({ deprecated_versions: 0, retired_versions: 0, total_calls_today: 0, impact_summary: [], pending_notifications: 0 });
const report = reactive({ versions: [], generated_at: '', period_days: 30, total_deprecated_calls: 0 });
const showAnalysis = ref(false);
const analysis = ref(null);

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadDashboard(), loadReport()]); } finally { loading.value = false; }
}
async function loadDashboard() {
    try { const r = await apiImpactApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadReport() {
    reportLoading.value = true;
    try { const r = await apiImpactApi.overallReport({ days: days.value }); Object.assign(report, r.data?.data || {}); } finally { reportLoading.value = false; }
}
async function analyzeVersion(versionId) {
    try {
        const r = await apiImpactApi.analyzeVersion(versionId, { days: days.value });
        analysis.value = r.data?.data; showAnalysis.value = true;
    } catch { ElMessage.error('加载分析失败'); }
}
async function notifyCustomers(versionId) {
    if (!versionId) return;
    try {
        await ElMessageBox.confirm('确认向所有受影响客户发送版本迁移通知？', '确认发送');
        notifyLoading.value = true;
        const r = await apiImpactApi.sendNotifications(versionId);
        ElMessage.success(`已向 ${r.data?.data?.sent} 个客户发送通知`);
        loadDashboard();
    } catch (e) { if (e !== 'cancel') ElMessage.error('发送失败'); } finally { notifyLoading.value = false; }
}
async function exportCsv(versionId) {
    if (!versionId) return;
    try {
        const r = await apiImpactApi.exportReport(versionId, { days: days.value });
        const data = r.data?.data || [];
        if (!data.length) { ElMessage.warning('暂无数据'); return; }
        const csv = data.map(row => row.map(c => typeof c === 'string' && c.includes(',') ? `"${c}"` : c).join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'api-impact-report.csv'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success('已导出');
    } catch { ElMessage.error('导出失败'); }
}
</script>

<style scoped>
.api-impact-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.impact-card { margin-bottom: 8px; }
.impact-card.border-danger { border-left: 3px solid #f56c6c; }
.impact-card.border-warning { border-left: 3px solid #e6a23c; }
.impact-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.impact-version { font-size: 18px; font-weight: 700; font-family: monospace; }
.impact-stats { font-size: 13px; }
.impact-stats > div { display: flex; justify-content: space-between; padding: 2px 0; }
.stat-l { color: #909399; }
.stat-v { font-weight: 600; }
.guide-block { background: #f5f7fa; padding: 12px; border-radius: 4px; white-space: pre-wrap; font-size: 13px; max-height: 300px; overflow-y: auto; }
</style>
