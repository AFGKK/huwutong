<template>
    <div class="apm-page">
        <el-tabs v-model="activeTab">
            <!-- ═══════════════ 性能总览 ═══════════════ -->
            <el-tab-pane label="性能总览" name="overview">
                <div class="tab-actions">
                    <el-select v-model="period" @change="fetchAll" style="width: 150px">
                        <el-option :value="1" label="最近 1 小时" />
                        <el-option :value="6" label="最近 6 小时" />
                        <el-option :value="24" label="最近 24 小时" />
                        <el-option :value="72" label="最近 3 天" />
                        <el-option :value="168" label="最近 7 天" />
                    </el-select>
                    <el-button @click="fetchAll" :icon="Refresh" circle />
                </div>

                <!-- 总览指标 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">总请求数</div>
                                <div class="stat-value">{{ stats.total_requests ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">慢请求</div>
                                <div class="stat-value slow">{{ stats.slow_requests ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">平均耗时</div>
                                <div class="stat-value">{{ stats.avg_duration_ms ?? '-' }} <small>ms</small></div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">平均内存</div>
                                <div class="stat-value">{{ stats.avg_memory_mb ?? '-' }} <small>MB</small></div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" style="margin-bottom: 16px;">
                    <!-- 耗时分布 -->
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>请求耗时分布</span></template>
                            <div v-loading="loading" class="chart-area">
                                <div v-for="item in distribution" :key="item.label" class="bar-row">
                                    <span class="bar-label">{{ item.label }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill" :style="{ width: barWidth(item.count) + '%' }" />
                                    </div>
                                    <span class="bar-count">{{ item.count }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <!-- 慢请求原因 -->
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>慢请求原因分布</span></template>
                            <div v-loading="loading" class="chart-area">
                                <div v-for="item in slowReasons" :key="item.slow_reason" class="bar-row">
                                    <span class="bar-label reason-label">{{ item.slow_reason }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill fill-warning" :style="{ width: reasonBarWidth(item.count) + '%' }" />
                                    </div>
                                    <span class="bar-count">{{ item.count }}</span>
                                </div>
                                <el-empty v-if="!slowReasons.length" description="暂无慢请求" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 最慢路由 Top -->
                <el-card shadow="never" style="margin-bottom: 16px;">
                    <template #header><span>最慢路由 Top 10 (24h)</span></template>
                    <el-table :data="slowestRoutes" v-loading="loading" stripe>
                        <el-table-column label="方法" width="80">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.method }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="path" label="路径" min-width="250" />
                        <el-table-column label="平均耗时" width="120">
                            <template #default="{ row }">
                                <span :class="{ 'text-danger': row.avg_duration_ms > 500 }">
                                    {{ Math.round(row.avg_duration_ms) }} ms
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="最大耗时" width="120">
                            <template #default="{ row }">
                                {{ Math.round(row.max_duration_ms) }} ms
                            </template>
                        </el-table-column>
                        <el-table-column label="请求数" width="80" prop="request_count" />
                        <el-table-column label="慢请求" width="80" prop="slow_count" />
                    </el-table>
                </el-card>

                <!-- 最近慢请求 -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>最近慢请求</span>
                            <el-button @click="handlePrune" type="danger" plain size="small">清理过期数据</el-button>
                        </div>
                    </template>
                    <el-table :data="slowRequestsList" v-loading="loading" stripe>
                        <el-table-column label="方法" width="70">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.method }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="path" label="路径" min-width="200" />
                        <el-table-column prop="status_code" label="状态码" width="80" />
                        <el-table-column label="耗时" width="90">
                            <template #default="{ row }">
                                <span class="text-danger">{{ Math.round(row.duration_ms) }} ms</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="DB" width="80">
                            <template #default="{ row }">
                                {{ row.db_queries }}q / {{ Math.round(row.db_duration_ms) }}ms
                            </template>
                        </el-table-column>
                        <el-table-column label="内存" width="80">
                            <template #default="{ row }">
                                {{ row.memory_mb }} MB
                            </template>
                        </el-table-column>
                        <el-table-column prop="slow_reason" label="原因" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="时间" width="160" />
                    </el-table>
                    <el-empty v-if="!slowRequestsList.length" description="暂无慢请求" />
                </el-card>
            </el-tab-pane>

            <!-- ═══════════════ 统一监控仪表盘 ═══════════════ -->
            <el-tab-pane label="统一监控" name="dashboard">
                <div class="tab-actions">
                    <el-select v-model="dashPeriod" @change="fetchDashboard" style="width: 150px">
                        <el-option :value="1" label="最近 1 小时" />
                        <el-option :value="6" label="最近 6 小时" />
                        <el-option :value="24" label="最近 24 小时" />
                        <el-option :value="72" label="最近 3 天" />
                        <el-option :value="168" label="最近 7 天" />
                    </el-select>
                    <el-button @click="fetchDashboard" :icon="Refresh" circle />
                    <span style="font-size:12px;color:#909399;margin-left:8px;">
                        更新于: {{ dashGeneratedAt || '-' }}
                    </span>
                </div>

                <!-- 服务健康状态 -->
                <el-card shadow="never" style="margin-bottom:16px;">
                    <template #header>
                        <div class="card-header">
                            <span>服务健康状态</span>
                            <el-tag :type="healthOverall ? 'success' : 'danger'" size="small">
                                {{ healthOverall ? '健康' : '异常' }}
                            </el-tag>
                        </div>
                    </template>
                    <el-row :gutter="16">
                        <el-col :span="6" v-for="svc in healthServices" :key="svc.key">
                            <div class="health-item" :class="{ 'health-ok': svc.status, 'health-fail': !svc.status }">
                                <span class="health-icon">{{ svc.status ? '✓' : '✗' }}</span>
                                <span class="health-name">{{ svc.label }}</span>
                            </div>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- Telescope 聚合指标 -->
                <el-row :gutter="16" style="margin-bottom:16px;">
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">Telescope 异常</div>
                                <div class="stat-value danger">{{ telescopeStats.exceptions ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">失败任务</div>
                                <div class="stat-value warning">{{ telescopeStats.failed_jobs ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">慢查询</div>
                                <div class="stat-value">{{ telescopeStats.queries ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 请求趋势（按小时） -->
                <el-card shadow="never" style="margin-bottom:16px;">
                    <template #header><span>请求趋势（近 24 小时）</span></template>
                    <div v-loading="dashLoading" class="trend-chart">
                        <div v-for="(point, idx) in hourlyTrend" :key="idx" class="trend-col" :style="{ height: trendHeight(point) }" :title="`${point.hour}\n请求: ${point.total}\n平均: ${Math.round(point.avg_duration)}ms\n慢请求: ${point.slow_count}`">
                            <div class="trend-fill" :class="{ 'trend-slow': point.slow_count > 0 }" />
                        </div>
                        <el-empty v-if="!hourlyTrend.length" description="暂无趋势数据" />
                    </div>
                </el-card>

                <!-- Telescope 最近异常 -->
                <el-card shadow="never">
                    <template #header><span>最近异常</span></template>
                    <el-table :data="recentExceptions" v-loading="dashLoading" stripe size="small">
                        <el-table-column label="异常消息" min-width="350" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span class="text-danger">{{ row.message }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="类名" min-width="250" show-overflow-tooltip prop="class" />
                        <el-table-column label="位置" width="80">
                            <template #default="{ row }">{{ row.line }}</template>
                        </el-table-column>
                        <el-table-column label="时间" width="160" prop="created_at" />
                    </el-table>
                    <el-empty v-if="!recentExceptions.length" description="暂无异常记录" />
                </el-card>
            </el-tab-pane>

            <!-- ═══════════════ 追踪集成 ═══════════════ -->
            <el-tab-pane label="追踪集成" name="otel">
                <el-row :gutter="16">
                    <el-col :span="24">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>OpenTelemetry 集成状态</span>
                                    <el-tag :type="otelStatus.enabled ? 'success' : 'info'" size="small">
                                        {{ otelStatus.enabled ? '已启用' : '未启用' }}
                                    </el-tag>
                                </div>
                            </template>

                            <div v-if="loadingOtel" v-loading="true" class="loading-area" />

                            <template v-else>
                                <el-alert
                                    v-if="!otelStatus.enabled"
                                    title="OpenTelemetry 未启用"
                                    type="info"
                                    :description="otelStatus.message || '请设置 OTEL_ENABLED=true 环境变量并配置 OTLP 端点'"
                                    show-icon
                                    :closable="false"
                                />

                                <el-descriptions v-if="otelStatus.enabled" :column="2" border size="small">
                                    <el-descriptions-item label="服务名称">
                                        <el-tag size="small">{{ otelStatus.service_name }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="OTLP 端点">
                                        <code>{{ otelStatus.endpoint }}</code>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="采样率">
                                        {{ (otelStatus.sampling_ratio * 100).toFixed(1) }}%
                                    </el-descriptions-item>
                                    <el-descriptions-item label="连接状态">
                                        <el-tag :type="otelStatus.connected ? 'success' : 'danger'" size="small">
                                            {{ otelStatus.connected ? '已连接' : '未连接' }}
                                        </el-tag>
                                    </el-descriptions-item>
                                </el-descriptions>

                                <el-divider />
                                <h4>环境变量配置</h4>
                                <el-table :data="envConfigItems" stripe size="small" border>
                                    <el-table-column prop="key" label="变量名" width="280" />
                                    <el-table-column prop="default" label="默认值" width="280" />
                                    <el-table-column prop="desc" label="说明" min-width="200" />
                                </el-table>
                            </template>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mt-4">
                    <el-col :span="24">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>系统配置</span>
                                </div>
                            </template>

                            <el-descriptions v-if="apmConfig" :column="2" border size="small">
                                <el-descriptions-item label="慢请求阈值">
                                    {{ apmConfig.slow_threshold_ms }} ms
                                </el-descriptions-item>
                                <el-descriptions-item label="DB 慢查询阈值">
                                    {{ apmConfig.db_slow_threshold_ms }} ms
                                </el-descriptions-item>
                                <el-descriptions-item label="采样率">
                                    1/{{ apmConfig.sample_rate }}
                                </el-descriptions-item>
                                <el-descriptions-item label="数据保留期">
                                    {{ apmConfig.retention_days }} 天
                                </el-descriptions-item>
                                <el-descriptions-item label="追踪后端">
                                    <el-tag v-if="apmConfig.otel?.enabled" type="success" size="small">
                                        Jaeger/Tempo ({{ apmConfig.otel.endpoint }})
                                    </el-tag>
                                    <el-tag v-else type="info" size="small">本地数据库</el-tag>
                                </el-descriptions-item>
                            </el-descriptions>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Refresh } from '@element-plus/icons-vue';
import {
    getApmOverview,
    getSlowRequests,
    getSlowestRoutes,
    pruneApmData,
    getApmOtelStatus,
    getApmConfig,
    getApmDashboard,
} from '@/api/apm';
import { ElMessage, ElMessageBox } from 'element-plus';

// ─── Tab ───
const activeTab = ref('overview');

// ─── 性能总览 ───
const loading = ref(false);
const period = ref(24);
const stats = ref({});
const distribution = ref([]);
const slowReasons = ref([]);
const slowRequestsList = ref([]);
const slowestRoutes = ref([]);
const maxDistCount = ref(1);
const maxReasonCount = ref(1);

// ─── 统一监控 ───
const dashLoading = ref(false);
const dashPeriod = ref(24);
const dashGeneratedAt = ref(null);
const healthOverall = ref(false);
const healthServices = ref([]);
const telescopeStats = ref({});
const hourlyTrend = ref([]);
const recentExceptions = ref([]);
const maxTrendCount = ref(1);

// ─── OTEL ───
const loadingOtel = ref(false);
const otelStatus = ref({});
const apmConfig = ref(null);

const envConfigItems = [
    { key: 'OTEL_ENABLED', default: 'false', desc: '启用/禁用 OpenTelemetry 追踪' },
    { key: 'OTEL_SERVICE_NAME', default: 'huwutong-api', desc: '在 Jaeger/Tempo 中显示的服务名称' },
    { key: 'OTEL_EXPORTER_OTLP_ENDPOINT', default: 'http://localhost:4318', desc: 'OTLP HTTP 导出端点' },
    { key: 'OTEL_TRACES_SAMPLER_RATIO', default: '0.1', desc: '采样率（0.0~1.0，生产建议 0.1）' },
];

// ─── 方法: 性能总览 ───
async function fetchAll() {
    loading.value = true;
    try {
        const [overviewRes, slowRes, routesRes] = await Promise.all([
            getApmOverview(period.value),
            getSlowRequests(),
            getSlowestRoutes(10),
        ]);

        const overview = overviewRes.data || {};
        stats.value = overview.stats || {};
        distribution.value = overview.distribution || [];
        slowReasons.value = overview.slow_reasons || [];

        maxDistCount.value = Math.max(1, ...distribution.value.map(d => d.count));
        maxReasonCount.value = Math.max(1, ...slowReasons.value.map(r => r.count));

        slowRequestsList.value = slowRes.data || [];
        slowestRoutes.value = routesRes.data || [];
    } catch (e) {
        ElMessage.error('获取 APM 数据失败');
    } finally {
        loading.value = false;
    }
}

// ─── 方法: 统一监控 ───
async function fetchDashboard() {
    dashLoading.value = true;
    try {
        const res = await getApmDashboard(dashPeriod.value);
        const data = res.data || {};

        dashGeneratedAt.value = data.generated_at || null;

        // 服务健康
        const sh = data.service_health || {};
        healthOverall.value = sh.overall || false;
        healthServices.value = [
            { key: 'database', label: '数据库', status: !!sh.database },
            { key: 'cache', label: '缓存', status: !!sh.cache },
            { key: 'redis', label: 'Redis', status: !!sh.redis },
            { key: 'queue', label: '队列', status: !!sh.queue },
            { key: 'storage', label: '存储', status: !!sh.storage },
        ];

        // Telescope
        const tel = data.telescope || {};
        telescopeStats.value = tel.stats || {};
        recentExceptions.value = tel.recent_exceptions || [];

        // 趋势
        hourlyTrend.value = (data.apm?.hourly_trend || []).slice(-48);
        maxTrendCount.value = Math.max(1, ...hourlyTrend.value.map(t => t.total));
    } catch (e) {
        ElMessage.error('获取监控仪表盘数据失败');
    } finally {
        dashLoading.value = false;
    }
}

// ─── 方法: OTEL ───
async function fetchOtelStatus() {
    loadingOtel.value = true;
    try {
        const [statusRes, configRes] = await Promise.all([
            getApmOtelStatus(),
            getApmConfig(),
        ]);
        otelStatus.value = statusRes.data || {};
        apmConfig.value = configRes.data || null;
    } catch {
        otelStatus.value = {};
        apmConfig.value = null;
    } finally {
        loadingOtel.value = false;
    }
}

// ─── 辅助 ───
function barWidth(count) {
    return Math.max(2, (count / maxDistCount.value) * 100);
}

function reasonBarWidth(count) {
    return Math.max(2, (count / maxReasonCount.value) * 100);
}

function trendHeight(point) {
    const pct = (point.total / maxTrendCount.value) * 100;
    return Math.max(4, pct) + '%';
}

async function handlePrune() {
    try {
        await ElMessageBox.confirm('确定清理 7 天前的 APM 数据？', '确认清理', {
            confirmButtonText: '清理',
            cancelButtonText: '取消',
            type: 'warning',
        });
    } catch {
        return;
    }

    try {
        const res = await pruneApmData();
        ElMessage.success(res.message || '清理完成');
        await fetchAll();
    } catch (e) {
        ElMessage.error('清理失败');
    }
}

onMounted(() => {
    fetchAll();
    fetchDashboard();
    fetchOtelStatus();
});
</script>

<style scoped>
.apm-page {
    max-width: 1200px;
    margin: 0 auto;
}

.tab-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 16px;
}

.mb-4 {
    margin-bottom: 16px;
}

.mt-4 {
    margin-top: 16px;
}

.stat-item {
    text-align: center;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #303133;
}

.stat-value.slow {
    color: #E6A23C;
}

.stat-value.danger {
    color: #F56C6C;
}

.stat-value.warning {
    color: #E6A23C;
}

.stat-value small {
    font-size: 14px;
    font-weight: 400;
    color: #909399;
}

.chart-area {
    min-height: 100px;
}

.bar-row {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    gap: 8px;
}

.bar-label {
    width: 120px;
    font-size: 12px;
    color: #606266;
    flex-shrink: 0;
}

.bar-label.reason-label {
    width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bar-track {
    flex: 1;
    height: 20px;
    background: #f0f2f5;
    border-radius: 3px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: #409EFF;
    border-radius: 3px;
    min-width: 2px;
    transition: width 0.3s;
}

.bar-fill.fill-warning {
    background: #E6A23C;
}

.bar-count {
    width: 50px;
    font-size: 12px;
    color: #909399;
    text-align: right;
}

.text-danger {
    color: #F56C6C;
    font-weight: 600;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.loading-area {
    min-height: 200px;
}

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

h4 {
    margin: 0 0 8px;
    font-size: 14px;
}

/* 趋势图 */
.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 160px;
    padding: 8px 0;
}

.trend-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    cursor: pointer;
    position: relative;
}

.trend-fill {
    width: 100%;
    background: #409EFF;
    border-radius: 2px 2px 0 0;
    min-height: 3px;
    transition: height 0.3s;
}

.trend-fill.trend-slow {
    background: #E6A23C;
}

.trend-fill:hover {
    opacity: 0.8;
}

/* 健康状态 */
.health-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border-radius: 6px;
    background: var(--el-fill-color-light);
}

.health-ok {
    border-left: 3px solid #67C23A;
}

.health-fail {
    border-left: 3px solid #F56C6C;
}

.health-icon {
    font-size: 18px;
    font-weight: 700;
    width: 24px;
    text-align: center;
}

.health-ok .health-icon {
    color: #67C23A;
}

.health-fail .health-icon {
    color: #F56C6C;
}

.health-name {
    font-size: 14px;
    color: #303133;
}
</style>
