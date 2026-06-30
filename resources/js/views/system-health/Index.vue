<template>
    <div class="system-health">
        <div class="page-header">
            <h2>系统健康监控</h2>
            <div class="header-actions">
                <el-button size="small" :icon="Refresh" @click="refreshAll" :loading="refreshing">刷新</el-button>
                <el-button size="small" :icon="Camera" @click="takeSnapshot" :loading="snapshotting">记录快照</el-button>
            </div>
        </div>

        <!-- ── 全局状态横幅 ── -->
        <el-alert
            v-if="currentStatus === 'degraded'"
            title="系统降级运行"
            type="warning"
            :description="degradedDescription"
            show-icon
            :closable="false"
            class="mb-4"
        />
        <el-alert
            v-else-if="currentStatus === 'down'"
            title="系统不可用"
            type="error"
            description="关键服务（数据库或Redis）异常，请立即处理"
            show-icon
            :closable="false"
            class="mb-4"
        />

        <!-- ── 顶部分数 + 可用率 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" :class="scoreColor(currentScore)">{{ currentScore }}</div>
                    <div class="stat-label">当前健康分</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value success">{{ uptime24h }}%</div>
                    <div class="stat-label">24h 可用率</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value success">{{ uptime7d }}%</div>
                    <div class="stat-label">7天可用率</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" :class="queueSizeColor">{{ queueSize }}</div>
                    <div class="stat-label">队列积压</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- ── 左列: 服务状态 ── -->
            <el-col :span="14">
                <el-card shadow="never" class="mb-4">
                    <template #header><span>服务状态</span></template>
                    <div class="service-grid">
                        <div v-for="svc in services" :key="svc.name" class="service-item" :class="svc.status">
                            <div class="service-indicator">
                                <span class="status-dot" :class="svc.status" />
                            </div>
                            <div class="service-info">
                                <div class="service-name">{{ svc.name }}</div>
                                <div class="service-detail">
                                    <template v-if="svc.latency_ms !== undefined">
                                        {{ svc.latency_ms }}ms
                                    </template>
                                    <template v-else-if="svc.size !== undefined">
                                        {{ svc.size }} 条待处理
                                    </template>
                                    <template v-else-if="svc.driver">
                                        {{ svc.driver }}
                                    </template>
                                </div>
                            </div>
                            <el-tag :type="serviceTagType(svc.status)" size="small">
                                {{ serviceStatusLabel(svc.status) }}
                            </el-tag>
                        </div>
                    </div>
                </el-card>

                <!-- ── 资源使用 ── -->
                <el-card shadow="never" class="mb-4">
                    <template #header><span>资源使用</span></template>
                    <el-row :gutter="16">
                        <el-col :span="8">
                            <div class="resource-card">
                                <div class="resource-label">磁盘使用</div>
                                <el-progress :percentage="diskPercent" :stroke-width="16"
                                    :status="diskPercent > 90 ? 'exception' : diskPercent > 75 ? 'warning' : 'success'"
                                    :format="() => diskPercent + '%'" />
                                <div class="resource-meta">{{ diskFree }} GB 剩余 / {{ diskTotal }} GB 总计</div>
                            </div>
                        </el-col>
                        <el-col :span="8">
                            <div class="resource-card">
                                <div class="resource-label">PHP 内存</div>
                                <el-progress :percentage="memoryPercent" :stroke-width="16"
                                    :status="memoryPercent > 80 ? 'exception' : memoryPercent > 50 ? 'warning' : 'success'"
                                    :format="() => memoryCurrent + ' MB'" />
                                <div class="resource-meta">峰值 {{ memoryPeak }} MB</div>
                            </div>
                        </el-col>
                        <el-col :span="8">
                            <div class="resource-card">
                                <div class="resource-label">DB 连接数</div>
                                <div class="resource-value">{{ dbConnections }}</div>
                                <div class="resource-meta">活跃连接</div>
                            </div>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- ── 健康趋势图 ── -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>健康趋势</span>
                            <el-radio-group v-model="trendPeriod" size="small" @change="fetchTrend">
                                <el-radio-button value="24h">24小时</el-radio-button>
                                <el-radio-button value="7d">7天</el-radio-button>
                                <el-radio-button value="30d">30天</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div ref="trendChart" class="trend-chart" />
                    <div v-if="!trendData.labels?.length" class="empty-chart">暂无趋势数据</div>
                </el-card>
            </el-col>

            <!-- ── 右列: 告警 + 阈值 + 失败任务 ── -->
            <el-col :span="10">
                <!-- ── 告警事件 ── -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>最近告警</span>
                            <el-tag size="small">{{ recentAlerts.length }}</el-tag>
                        </div>
                    </template>
                    <div v-if="recentAlerts.length === 0" class="empty-state">
                        <el-empty description="暂无告警" :image-size="60" />
                    </div>
                    <div v-else class="alert-list">
                        <div v-for="a in recentAlerts" :key="a.id" class="alert-item">
                            <el-tag :type="severityType(a.severity)" size="small" class="alert-severity">
                                {{ severityLabel(a.severity) }}
                            </el-tag>
                            <div class="alert-info">
                                <div class="alert-title">{{ a.title }}</div>
                                <div class="alert-meta">
                                    {{ a.rule?.name || '' }} · {{ formatTime(a.fired_at) }}
                                </div>
                            </div>
                            <el-tag v-if="a.status === 'firing'" type="danger" size="small">进行中</el-tag>
                            <el-tag v-else size="small">{{ a.status === 'acknowledged' ? '已确认' : '已解决' }}</el-tag>
                        </div>
                    </div>
                </el-card>

                <!-- ── 健康阈值配置 ── -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>阈值配置</span>
                            <el-button text size="small" @click="loadThresholds">刷新</el-button>
                        </div>
                    </template>
                    <div v-for="t in thresholds" :key="t.id" class="threshold-item">
                        <div class="threshold-header">
                            <span class="threshold-label">{{ t.label }}</span>
                            <el-tag v-if="!t.is_active" size="small" type="info">已禁用</el-tag>
                        </div>
                        <div class="threshold-values">
                            <span class="threshold-warn">⚠ {{ t.warning_threshold }}{{ t.unit }}</span>
                            <span class="threshold-crit">⛔ {{ t.critical_threshold }}{{ t.unit }}</span>
                        </div>
                        <el-button text size="small" @click="editThreshold(t)">编辑</el-button>
                    </div>
                </el-card>

                <!-- ── 失败任务 ── -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>失败任务</span>
                            <el-tag :type="failedJobs.length > 0 ? 'danger' : 'success'" size="small">
                                {{ failedJobs.length }}
                            </el-tag>
                        </div>
                    </template>
                    <div v-if="failedJobs.length === 0" class="empty-state">
                        <el-empty description="无失败任务" :image-size="60" />
                    </div>
                    <div v-else class="failed-jobs-list">
                        <div v-for="job in failedJobs.slice(0, 10)" :key="job.id" class="failed-job-item">
                            <div class="failed-job-name">{{ job.display_name || job.command || job.payload?.displayName || '未知' }}</div>
                            <div class="failed-job-meta">{{ formatTime(job.failed_at) }}</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 编辑阈值对话框 ── -->
        <el-dialog v-model="thresholdDialogVisible" title="编辑阈值" width="400px">
            <el-form label-position="top" size="small">
                <el-form-item :label="`${editThresholdForm.label} - 警告阈值`">
                    <el-input-number v-model="editThresholdForm.warning_threshold" :min="0" :step="10" style="width:100%" />
                </el-form-item>
                <el-form-item :label="`${editThresholdForm.label} - 严重阈值`">
                    <el-input-number v-model="editThresholdForm.critical_threshold" :min="0" :step="10" style="width:100%" />
                </el-form-item>
                <el-form-item>
                    <el-switch v-model="editThresholdForm.is_active" active-text="启用" inactive-text="禁用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="thresholdDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="thresholdSaving" @click="saveThreshold">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, onBeforeUnmount } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Camera } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import systemHealthApi from '@/api/systemHealth';

const refreshing = ref(false);
const snapshotting = ref(false);

// ─── 当前健康数据 ───
const currentStatus = ref('ok');
const currentScore = ref(100);
const uptime24h = ref(100);
const uptime7d = ref(100);
const queueSize = ref(0);
const services = ref([]);
const recentAlerts = ref([]);
const thresholds = ref([]);
const failedJobs = ref([]);

const diskPercent = ref(0);
const diskFree = ref(0);
const diskTotal = ref(0);
const memoryCurrent = ref(0);
const memoryPeak = ref(0);
const memoryLimit = ref(-1);
const dbConnections = ref(0);

const queueSizeColor = computed(() => {
    if (queueSize.value > 500) return 'danger';
    if (queueSize.value > 100) return 'warn';
    return '';
});

const degradedDescription = computed(() => {
    const down = services.value.filter(s => s.status !== 'healthy').map(s => s.name);
    return `以下服务异常: ${down.join(', ')}`;
});

// ─── 阈值对话 ───
const thresholdDialogVisible = ref(false);
const thresholdSaving = ref(false);
const editThresholdForm = reactive({
    id: null, label: '', warning_threshold: 0, critical_threshold: 0, is_active: true,
});

// ─── 趋势 ───
const trendPeriod = ref('24h');
const trendData = reactive({ labels: [], scores: [], db_latency: [], redis_latency: [], disk_usage: [], memory: [], queue_sizes: [] });
const trendChart = ref(null);
let chartInstance = null;

// ─── 加载数据 ───

async function refreshAll() {
    refreshing.value = true;
    try {
        await Promise.all([
            loadDashboard(),
            loadThresholds(),
            loadFailedJobs(),
            fetchTrend(),
        ]);
    } finally {
        refreshing.value = false;
    }
}

async function loadDashboard() {
    try {
        const { data: res } = await systemHealthApi.getDashboard();
        if (res.success) {
            const d = res.data;
            currentStatus.value = d.current?.status || 'ok';
            currentScore.value = d.current?.overall_score || 100;
            services.value = Object.values(d.services || {});
            uptime24h.value = d.uptime?.last_24h || 100;
            uptime7d.value = d.uptime?.last_7d || 100;
            recentAlerts.value = d.recent_alerts || [];

            const resources = d.current?.resources || {};
            diskPercent.value = resources.disk?.percent || 0;
            diskFree.value = resources.disk?.free_gb || 0;
            diskTotal.value = resources.disk?.total_gb || 0;
            memoryCurrent.value = resources.memory?.current_mb || 0;
            memoryPeak.value = resources.memory?.peak_mb || 0;
            memoryLimit.value = resources.memory?.limit_mb || -1;
            dbConnections.value = resources.db_connections || 0;
            queueSize.value = d.current?.checks?.queue?.size || 0;
        }
    } catch { /* ignore */ }
}

async function loadThresholds() {
    try {
        const { data: res } = await systemHealthApi.getThresholds();
        if (res.success) thresholds.value = res.data || [];
    } catch { /* ignore */ }
}

async function loadFailedJobs() {
    try {
        const { data: res } = await systemHealthApi.getFailedJobs();
        if (res.success) failedJobs.value = res.data || [];
    } catch { /* ignore */ }
}

async function fetchTrend() {
    try {
        const { data: res } = await systemHealthApi.getTrend(trendPeriod.value);
        if (res.success) {
            Object.assign(trendData, res.data);
            nextTick(renderChart);
        }
    } catch { /* ignore */ }
}

async function takeSnapshot() {
    snapshotting.value = true;
    try {
        const { data: res } = await systemHealthApi.takeSnapshot();
        if (res.success) {
            ElMessage.success('健康快照已记录');
            await loadDashboard();
        }
    } catch (e) {
        ElMessage.error('快照记录失败');
    } finally {
        snapshotting.value = false;
    }
}

// ─── 阈值编辑 ───

function editThreshold(t) {
    editThresholdForm.id = t.id;
    editThresholdForm.label = t.label;
    editThresholdForm.warning_threshold = t.warning_threshold;
    editThresholdForm.critical_threshold = t.critical_threshold;
    editThresholdForm.is_active = t.is_active;
    thresholdDialogVisible.value = true;
}

async function saveThreshold() {
    thresholdSaving.value = true;
    try {
        const { data: res } = await systemHealthApi.updateThreshold(editThresholdForm.id, {
            warning_threshold: editThresholdForm.warning_threshold,
            critical_threshold: editThresholdForm.critical_threshold,
            is_active: editThresholdForm.is_active,
        });
        if (res.success) {
            ElMessage.success('阈值已更新');
            thresholdDialogVisible.value = false;
            await loadThresholds();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '保存失败');
    } finally {
        thresholdSaving.value = false;
    }
}

// ─── 图表 ───

function renderChart() {
    if (!trendChart.value) return;
    if (!chartInstance) {
        chartInstance = echarts.init(trendChart.value);
    }

    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' },
        },
        legend: {
            data: ['健康分', 'DB延迟(ms)', '磁盘使用率(%)'],
            bottom: 0,
            textStyle: { fontSize: 11 },
        },
        grid: { left: 50, right: 20, bottom: 40, top: 10 },
        xAxis: {
            type: 'category',
            data: trendData.labels || [],
            axisLabel: { fontSize: 10, rotate: 45 },
        },
        yAxis: [
            { type: 'value', name: '分数', min: 0, max: 100 },
            { type: 'value', name: '延迟/使用率', min: 0 },
        ],
        series: [
            {
                name: '健康分',
                type: 'line',
                data: trendData.scores,
                smooth: true,
                lineStyle: { width: 2, color: '#67c23a' },
                itemStyle: { color: '#67c23a' },
                areaStyle: { color: 'rgba(103, 194, 58, 0.1)' },
            },
            {
                name: 'DB延迟(ms)',
                type: 'line',
                yAxisIndex: 1,
                data: trendData.db_latency,
                smooth: true,
                lineStyle: { width: 2, color: '#e6a23c' },
                itemStyle: { color: '#e6a23c' },
            },
            {
                name: '磁盘使用率(%)',
                type: 'line',
                yAxisIndex: 1,
                data: trendData.disk_usage,
                smooth: true,
                lineStyle: { width: 2, color: '#f56c6c' },
                itemStyle: { color: '#f56c6c' },
            },
        ],
        dataZoom: trendData.labels?.length > 50 ? [{ type: 'inside', start: 0, end: 100 }] : undefined,
    };

    chartInstance.setOption(option, true);
}

function handleResize() {
    chartInstance?.resize();
}

// ─── 辅助 ───

function scoreColor(score) {
    if (score >= 80) return 'success';
    if (score >= 50) return 'warn';
    return 'danger';
}

function serviceTagType(status) {
    return { healthy: 'success', degraded: 'warning', down: 'danger' }[status] || 'info';
}

function serviceStatusLabel(status) {
    return { healthy: '正常', degraded: '降级', down: '异常' }[status] || status;
}

function severityType(severity) {
    return { critical: 'danger', warning: 'warning', info: 'info' }[severity] || 'info';
}

function severityLabel(severity) {
    return { critical: '严重', warning: '警告', info: '信息' }[severity] || severity;
}

function formatTime(date) {
    if (!date) return '';
    const d = new Date(date);
    const pad = n => String(n).padStart(2, '0');
    return `${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// ─── 初始化 ───

onMounted(async () => {
    window.addEventListener('resize', handleResize);
    await refreshAll();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    chartInstance?.dispose();
});
</script>

<style scoped>
.system-health {
    padding: 16px 24px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0;
    font-size: 20px;
}
.header-actions {
    display: flex;
    gap: 8px;
}

.mb-4 { margin-bottom: 16px; }

.stat-card {
    text-align: center;
    padding: 8px 0;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.stat-value.success { color: var(--el-color-success); }
.stat-value.warn { color: var(--el-color-warning); }
.stat-value.danger { color: var(--el-color-danger); }
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

/* ── 服务网格 ── */
.service-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.service-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    transition: background 0.2s;
}
.service-item:hover {
    background: var(--el-fill-color-light);
}
.service-indicator {
    flex-shrink: 0;
}
.status-dot {
    display: block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.status-dot.healthy { background: var(--el-color-success); box-shadow: 0 0 6px rgba(103,194,58,0.5); }
.status-dot.degraded { background: var(--el-color-warning); box-shadow: 0 0 6px rgba(230,162,60,0.5); }
.status-dot.down { background: var(--el-color-danger); box-shadow: 0 0 6px rgba(245,108,108,0.5); }
.service-info {
    flex: 1;
    min-width: 0;
}
.service-name {
    font-weight: 600;
    font-size: 14px;
}
.service-detail {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

/* ── 资源 ── */
.resource-card {
    text-align: center;
    padding: 8px 0;
}
.resource-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}
.resource-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.resource-meta {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}

/* ── 趋势 ── */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.trend-chart {
    height: 280px;
}
.empty-chart {
    text-align: center;
    padding: 40px;
    color: var(--el-text-color-placeholder);
}

/* ── 告警 ── */
.alert-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.alert-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
}
.alert-severity {
    flex-shrink: 0;
}
.alert-info {
    flex: 1;
    min-width: 0;
}
.alert-title {
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.alert-meta {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 2px;
}

/* ── 阈值 ── */
.threshold-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--el-border-color-light);
}
.threshold-item:last-child { border-bottom: none; }
.threshold-header {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 100px;
}
.threshold-label {
    font-size: 13px;
    font-weight: 500;
}
.threshold-values {
    display: flex;
    gap: 8px;
    font-size: 12px;
}
.threshold-warn { color: var(--el-color-warning); }
.threshold-crit { color: var(--el-color-danger); }

/* ── 失败任务 ── */
.failed-jobs-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.failed-job-item {
    padding: 6px 8px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
}
.failed-job-name {
    font-size: 12px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.failed-job-meta {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 2px;
}

.empty-state {
    padding: 8px 0;
}
</style>
