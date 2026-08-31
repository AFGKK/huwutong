<template>
    <div class="apm-page">
        <el-tabs v-model="activeTab">
            <!-- 性能总览 -->
            <el-tab-pane :label="t('apm_page.tabs.overview')" name="overview">
                <div class="tab-actions">
                    <el-select v-model="period" @change="fetchAll" style="width: 150px">
                        <el-option
                            v-for="opt in periodOptions"
                            :key="opt.value"
                            :value="opt.value"
                            :label="opt.label"
                        />
                    </el-select>
                    <el-button @click="fetchAll" :icon="Refresh" circle />
                </div>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.stats.total_requests') }}</div>
                                <div class="stat-value">{{ stats.total_requests ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.stats.slow_requests') }}</div>
                                <div class="stat-value slow">{{ stats.slow_requests ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.stats.avg_duration') }}</div>
                                <div class="stat-value">{{ stats.avg_duration_ms ?? '-' }} <small>ms</small></div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.stats.avg_memory') }}</div>
                                <div class="stat-value">{{ stats.avg_memory_mb ?? '-' }} <small>MB</small></div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" style="margin-bottom: 16px;">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('apm_page.charts.duration_distribution') }}</span></template>
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

                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('apm_page.charts.slow_reason_distribution') }}</span></template>
                            <div v-loading="loading" class="chart-area">
                                <div v-for="item in slowReasons" :key="item.slow_reason" class="bar-row">
                                    <span class="bar-label reason-label">{{ item.slow_reason }}</span>
                                    <div class="bar-track">
                                        <div class="bar-fill fill-warning" :style="{ width: reasonBarWidth(item.count) + '%' }" />
                                    </div>
                                    <span class="bar-count">{{ item.count }}</span>
                                </div>
                                <el-empty v-if="!slowReasons.length" :description="t('apm_page.empty.no_slow_requests')" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" style="margin-bottom: 16px;">
                    <template #header><span>{{ t('apm_page.charts.slowest_routes') }}</span></template>
                    <el-table :data="slowestRoutes" v-loading="loading" stripe>
                        <el-table-column :label="t('apm_page.columns.method')" width="80">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.method }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="path" :label="t('apm_page.columns.path')" min-width="250" />
                        <el-table-column :label="t('apm_page.columns.avg_duration')" width="120">
                            <template #default="{ row }">
                                <span :class="{ 'text-danger': row.avg_duration_ms > 500 }">
                                    {{ Math.round(row.avg_duration_ms) }} ms
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.max_duration')" width="120">
                            <template #default="{ row }">
                                {{ Math.round(row.max_duration_ms) }} ms
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.request_count')" width="80" prop="request_count" />
                        <el-table-column :label="t('apm_page.columns.slow_count')" width="80" prop="slow_count" />
                    </el-table>
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('apm_page.charts.recent_slow_requests') }}</span>
                            <el-button @click="handlePrune" type="danger" plain size="small">
                                {{ t('apm_page.actions.prune_expired') }}
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="slowRequestsList" v-loading="loading" stripe>
                        <el-table-column :label="t('apm_page.columns.method')" width="70">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.method }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="path" :label="t('apm_page.columns.path')" min-width="200" />
                        <el-table-column prop="status_code" :label="t('apm_page.columns.status_code')" width="80" />
                        <el-table-column :label="t('apm_page.columns.duration')" width="90">
                            <template #default="{ row }">
                                <span class="text-danger">{{ Math.round(row.duration_ms) }} ms</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.db')" width="80">
                            <template #default="{ row }">
                                {{ formatDbQueries(row) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.memory')" width="80">
                            <template #default="{ row }">
                                {{ row.memory_mb }} MB
                            </template>
                        </el-table-column>
                        <el-table-column prop="slow_reason" :label="t('apm_page.columns.reason')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="created_at" :label="t('apm_page.columns.time')" width="160" />
                    </el-table>
                    <el-empty v-if="!slowRequestsList.length" :description="t('apm_page.empty.no_slow_requests')" />
                </el-card>
            </el-tab-pane>

            <!-- 统一监控仪表盘 -->
            <el-tab-pane :label="t('apm_page.tabs.dashboard')" name="dashboard">
                <div class="tab-actions">
                    <el-select v-model="dashPeriod" @change="fetchDashboard" style="width: 150px">
                        <el-option
                            v-for="opt in periodOptions"
                            :key="opt.value"
                            :value="opt.value"
                            :label="opt.label"
                        />
                    </el-select>
                    <el-button @click="fetchDashboard" :icon="Refresh" circle />
                    <span style="font-size:12px;color:#909399;margin-left:8px;">
                        {{ t('apm_page.dashboard.updated_at') }} {{ dashGeneratedAt || '-' }}
                    </span>
                </div>

                <el-card shadow="never" style="margin-bottom:16px;">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('apm_page.dashboard.service_health') }}</span>
                            <el-tag :type="healthOverall ? 'success' : 'danger'" size="small">
                                {{ healthOverall ? t('apm_page.health.healthy') : t('apm_page.health.unhealthy') }}
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

                <el-row :gutter="16" style="margin-bottom:16px;">
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.dashboard.telescope_exceptions') }}</div>
                                <div class="stat-value danger">{{ telescopeStats.exceptions ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.dashboard.failed_jobs') }}</div>
                                <div class="stat-value warning">{{ telescopeStats.failed_jobs ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">{{ t('apm_page.dashboard.slow_queries') }}</div>
                                <div class="stat-value">{{ telescopeStats.queries ?? '-' }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" style="margin-bottom:16px;">
                    <template #header><span>{{ t('apm_page.charts.request_trend') }}</span></template>
                    <div v-loading="dashLoading" class="trend-chart">
                        <div
                            v-for="(point, idx) in hourlyTrend"
                            :key="idx"
                            class="trend-col"
                            :style="{ height: trendHeight(point) }"
                            :title="trendTooltip(point)"
                        >
                            <div class="trend-fill" :class="{ 'trend-slow': point.slow_count > 0 }" />
                        </div>
                        <el-empty v-if="!hourlyTrend.length" :description="t('apm_page.empty.no_trend_data')" />
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header><span>{{ t('apm_page.dashboard.recent_exceptions') }}</span></template>
                    <el-table :data="recentExceptions" v-loading="dashLoading" stripe size="small">
                        <el-table-column :label="t('apm_page.columns.exception_message')" min-width="350" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span class="text-danger">{{ row.message }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.class_name')" min-width="250" show-overflow-tooltip prop="class" />
                        <el-table-column :label="t('apm_page.columns.location')" width="80">
                            <template #default="{ row }">{{ row.line }}</template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.time')" width="160" prop="created_at" />
                    </el-table>
                    <el-empty v-if="!recentExceptions.length" :description="t('apm_page.empty.no_exceptions')" />
                </el-card>
            </el-tab-pane>

            <!-- 追踪集成 -->
            <el-tab-pane :label="t('apm_page.tabs.otel')" name="otel">
                <el-row :gutter="16">
                    <el-col :span="24">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('apm_page.otel.integration_status') }}</span>
                                    <el-tag :type="otelStatus.enabled ? 'success' : 'info'" size="small">
                                        {{ otelStatus.enabled ? t('apm_page.status.enabled') : t('apm_page.status.disabled') }}
                                    </el-tag>
                                </div>
                            </template>

                            <div v-if="loadingOtel" v-loading="true" class="loading-area" />

                            <template v-else>
                                <el-alert
                                    v-if="!otelStatus.enabled"
                                    :title="t('apm_page.otel.disabled_title')"
                                    type="info"
                                    :description="otelStatus.message || t('apm_page.otel.disabled_hint')"
                                    show-icon
                                    :closable="false"
                                />

                                <el-descriptions v-if="otelStatus.enabled" :column="2" border size="small">
                                    <el-descriptions-item :label="t('apm_page.otel.service_name')">
                                        <el-tag size="small">{{ otelStatus.service_name }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('apm_page.otel.otlp_endpoint')">
                                        <code>{{ otelStatus.endpoint }}</code>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('apm_page.otel.sampling_ratio')">
                                        {{ (otelStatus.sampling_ratio * 100).toFixed(1) }}%
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('apm_page.otel.connection_status')">
                                        <el-tag :type="otelStatus.connected ? 'success' : 'danger'" size="small">
                                            {{ otelStatus.connected ? t('apm_page.status.connected') : t('apm_page.status.disconnected') }}
                                        </el-tag>
                                    </el-descriptions-item>
                                </el-descriptions>

                                <el-divider />
                                <h4>{{ t('apm_page.otel.env_config') }}</h4>
                                <el-table :data="envConfigItems" stripe size="small" border>
                                    <el-table-column prop="key" :label="t('apm_page.columns.var_name')" width="280" />
                                    <el-table-column prop="default" :label="t('apm_page.columns.default_value')" width="280" />
                                    <el-table-column prop="desc" :label="t('apm_page.columns.description')" min-width="200" />
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
                                    <span>{{ t('apm_page.otel.system_config') }}</span>
                                </div>
                            </template>

                            <el-descriptions v-if="apmConfig" :column="2" border size="small">
                                <el-descriptions-item :label="t('apm_page.otel.slow_threshold')">
                                    {{ apmConfig.slow_threshold_ms }} ms
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('apm_page.otel.db_slow_threshold')">
                                    {{ apmConfig.db_slow_threshold_ms }} ms
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('apm_page.otel.sample_rate')">
                                    {{ t('apm_page.units.sample_ratio', { rate: apmConfig.sample_rate }) }}
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('apm_page.otel.retention')">
                                    {{ t('apm_page.units.days', { n: apmConfig.retention_days }) }}
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('apm_page.otel.trace_backend')">
                                    <el-tag v-if="apmConfig.otel?.enabled" type="success" size="small">
                                        {{ t('apm_page.otel.trace_backend_remote', { endpoint: apmConfig.otel.endpoint }) }}
                                    </el-tag>
                                    <el-tag v-else type="info" size="small">{{ t('apm_page.otel.trace_backend_local') }}</el-tag>
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
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();

const activeTab = ref('overview');

const loading = ref(false);
const period = ref(24);
const stats = ref({});
const distribution = ref([]);
const slowReasons = ref([]);
const slowRequestsList = ref([]);
const slowestRoutes = ref([]);
const maxDistCount = ref(1);
const maxReasonCount = ref(1);

const dashLoading = ref(false);
const dashPeriod = ref(24);
const dashGeneratedAt = ref(null);
const healthOverall = ref(false);
const healthRaw = ref({});
const telescopeStats = ref({});
const hourlyTrend = ref([]);
const recentExceptions = ref([]);
const maxTrendCount = ref(1);

const loadingOtel = ref(false);
const otelStatus = ref({});
const apmConfig = ref(null);

const periodSpec = [
    { value: 1, key: 'h1' },
    { value: 6, key: 'h6' },
    { value: 24, key: 'h24' },
    { value: 72, key: 'd3' },
    { value: 168, key: 'd7' },
];

const periodOptions = computed(() =>
    periodSpec.map(({ value, key }) => ({
        value,
        label: t(`apm_page.periods.${key}`),
    }))
);

const healthServiceKeys = ['database', 'cache', 'redis', 'queue', 'storage'];

const healthServices = computed(() =>
    healthServiceKeys.map((key) => ({
        key,
        label: t(`apm_page.health_services.${key}`),
        status: !!healthRaw.value[key],
    }))
);

const envConfigSpec = [
    { key: 'OTEL_ENABLED', default: 'false', descKey: 'otel_enabled' },
    { key: 'OTEL_SERVICE_NAME', default: 'huwutong-api', descKey: 'otel_service_name' },
    { key: 'OTEL_EXPORTER_OTLP_ENDPOINT', default: 'http://localhost:4318', descKey: 'otel_endpoint' },
    { key: 'OTEL_TRACES_SAMPLER_RATIO', default: '0.1', descKey: 'otel_sampler' },
];

const envConfigItems = computed(() =>
    envConfigSpec.map((item) => ({
        key: item.key,
        default: item.default,
        desc: t(`apm_page.env.${item.descKey}`),
    }))
);

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
        ElMessage.error(t('apm_page.messages.fetch_overview_failed'));
    } finally {
        loading.value = false;
    }
}

async function fetchDashboard() {
    dashLoading.value = true;
    try {
        const res = await getApmDashboard(dashPeriod.value);
        const data = res.data || {};

        dashGeneratedAt.value = data.generated_at || null;

        const sh = data.service_health || {};
        healthOverall.value = sh.overall || false;
        healthRaw.value = {
            database: !!sh.database,
            cache: !!sh.cache,
            redis: !!sh.redis,
            queue: !!sh.queue,
            storage: !!sh.storage,
        };

        const tel = data.telescope || {};
        telescopeStats.value = tel.stats || {};
        recentExceptions.value = tel.recent_exceptions || [];

        hourlyTrend.value = (data.apm?.hourly_trend || []).slice(-48);
        maxTrendCount.value = Math.max(1, ...hourlyTrend.value.map(p => p.total));
    } catch (e) {
        ElMessage.error(t('apm_page.messages.fetch_dashboard_failed'));
    } finally {
        dashLoading.value = false;
    }
}

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

function trendTooltip(point) {
    return t('apm_page.trend_tooltip', {
        hour: point.hour,
        total: point.total,
        avg: Math.round(point.avg_duration),
        slow: point.slow_count,
    });
}

function formatDbQueries(row) {
    return t('apm_page.units.db_queries', {
        queries: row.db_queries,
        ms: Math.round(row.db_duration_ms),
    });
}

async function handlePrune() {
    try {
        await ElMessageBox.confirm(t('apm_page.confirm.prune_message'), t('apm_page.confirm.prune_title'), {
            confirmButtonText: t('apm_page.actions.prune'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        });
    } catch {
        return;
    }

    try {
        const res = await pruneApmData();
        ElMessage.success(res.message || t('apm_page.messages.prune_done'));
        await fetchAll();
    } catch (e) {
        ElMessage.error(t('apm_page.messages.prune_failed'));
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
    background: #0f172a;
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
    background: #0f172a;
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
