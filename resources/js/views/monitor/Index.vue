<template>
    <div class="monitor-page">
        <div class="page-header">
            <div>
                <h2>{{ t('monitor_page.title') }}</h2>
                <p class="text-muted">{{ t('monitor_page.subtitle') }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('monitor_page.refresh') }}</el-button>
        </div>

        <!-- 实时指标 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-body">
                        <div class="metric-value">{{ stats.total_api_calls || 0 }}</div>
                        <div class="metric-label">{{ t('monitor_page.stats.total_calls') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-body">
                        <div class="metric-value" style="color:#67c23a">{{ stats.success_rate || 0 }}%</div>
                        <div class="metric-label">{{ t('monitor_page.stats.success_rate') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-body">
                        <div class="metric-value" style="color:#e6a23c">{{ stats.avg_response_time || 0 }}ms</div>
                        <div class="metric-label">{{ t('monitor_page.stats.avg_response_time') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-body">
                        <div class="metric-value">{{ stats.active_keys || 0 }}</div>
                        <div class="metric-label">{{ t('monitor_page.stats.active_keys') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ t('monitor_page.sections.trend_7d') }}</span></template>
                    <v-chart v-if="trendData.length" :option="trendOption" style="height:300px" autoresize />
                    <el-empty v-else :description="t('messages.no_data')" :image-size="40" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ t('monitor_page.sections.source_distribution') }}</span></template>
                    <v-chart v-if="sourceData.length" :option="sourceOption" style="height:300px" autoresize />
                    <el-empty v-else :description="t('messages.no_data')" :image-size="40" />
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ t('monitor_page.sections.latency_distribution') }}</span></template>
                    <v-chart v-if="latencyData.length" :option="latencyOption" style="height:280px" autoresize />
                    <el-empty v-else :description="t('messages.no_data')" :image-size="40" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ t('monitor_page.sections.top_endpoints') }}</span></template>
                    <el-table :data="topEndpoints" stripe size="small" v-if="topEndpoints.length">
                        <el-table-column :label="t('monitor_page.cols.endpoint')" min-width="160" prop="path" />
                        <el-table-column :label="t('monitor_page.cols.method')" width="60">
                            <template #default="{ row }"><el-tag size="small">{{ row.method }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('monitor_page.cols.call_count')" width="80" prop="count" align="center" />
                        <el-table-column :label="t('monitor_page.cols.avg_time')" width="80" prop="avg_time" align="center">
                            <template #default="{ row }">{{ row.avg_time }}ms</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="t('messages.no_data')" :image-size="40" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import VChart from 'vue-echarts';
import 'echarts';
import endpointUsage from '@/api/endpointUsage';

const { t } = useI18n();

const loading = ref(false);
const trendData = ref([]);
const sourceData = ref([]);
const latencyData = ref([]);
const topEndpoints = ref([]);
let refreshTimer = null;

const stats = ref({
    total_api_calls: 0, success_rate: 0, avg_response_time: 0, active_keys: 0,
});

const trendOption = computed(() => {
    const callVolume = t('monitor_page.charts.call_volume');
    const latencyMs = t('monitor_page.charts.latency_ms');
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: trendData.value.map(d => d.date), axisLabel: { fontSize: 11 } },
        yAxis: { type: 'value' },
        series: [
            { name: callVolume, type: 'line', data: trendData.value.map(d => d.count), smooth: true, itemStyle: { color: '#0f172a' }, areaStyle: { color: 'rgba(15,23,42,0.1)' } },
            { name: latencyMs, type: 'line', data: trendData.value.map(d => d.avg_time), smooth: true, yAxisIndex: 0, itemStyle: { color: '#e6a23c' } },
        ],
        legend: { bottom: 0, data: [callVolume, latencyMs] },
    };
});

const sourceOption = computed(() => ({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    series: [{
        type: 'pie', radius: ['40%', '65%'], center: ['50%', '45%'],
        data: sourceData.value.map(d => ({ name: d.name, value: d.count })),
        emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.5)' } },
        label: { fontSize: 12 },
    }],
}));

const latencyOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
    xAxis: { type: 'category', data: latencyData.value.map(d => d.range) },
    yAxis: { type: 'value', name: t('monitor_page.charts.request_count') },
    series: [{
        type: 'bar', data: latencyData.value.map(d => d.count),
        itemStyle: {
            color: (params) => {
                const colors = ['#67c23a', '#0f172a', '#e6a23c', '#f56c6c', '#f56c6c'];
                return colors[params.dataIndex] || '#909399';
            },
        },
    }],
}));

async function loadStats() {
    try {
        const res = await endpointUsage.overview();
        const data = res.data?.data || {};
        const endpoints = data.endpoints || [];
        stats.value = {
            total_api_calls: data.total_today ?? data.total_month ?? 0,
            success_rate: endpoints.length
                ? Math.round(endpoints.reduce((s, e) => s + (e.success_rate ?? 100), 0) / endpoints.length * 10) / 10
                : 0,
            avg_response_time: endpoints.length
                ? Math.round(endpoints.reduce((s, e) => s + (e.avg_latency_ms ?? 0), 0) / endpoints.length)
                : 0,
            active_keys: endpoints.length,
        };
    } catch {
        stats.value = { total_api_calls: 0, success_rate: 0, avg_response_time: 0, active_keys: 0 };
    }
}

async function loadTrend() {
    try {
        const res = await endpointUsage.trend({ days: 7 });
        const trend = res.data?.data?.trend || [];
        trendData.value = trend.map((row) => ({
            date: row.date?.slice(5) || row.period || '',
            count: row.total ?? row.count ?? 0,
            avg_time: row.avg_latency_ms ?? row.avg_time ?? 0,
        }));
    } catch {
        trendData.value = [];
    }
}

async function loadSource() {
    try {
        const res = await endpointUsage.overview();
        const endpoints = res.data?.data?.endpoints || [];
        sourceData.value = endpoints.slice(0, 5).map((e) => ({
            name: e.endpoint || e.name || 'unknown',
            count: e.today_quantity ?? e.count ?? 0,
        }));
    } catch {
        sourceData.value = [];
    }
}

async function loadLatency() {
    try {
        const res = await endpointUsage.latency({ days: 7 });
        const latency = res.data?.data?.latency || [];
        latencyData.value = latency.map((row) => ({
            range: row.bucket || row.range || row.label || '-',
            count: row.count ?? 0,
        }));
    } catch {
        latencyData.value = [];
    }
}

async function loadTopEndpoints() {
    try {
        const res = await endpointUsage.overview();
        const endpoints = res.data?.data?.endpoints || [];
        topEndpoints.value = [...endpoints]
            .sort((a, b) => (b.today_quantity ?? 0) - (a.today_quantity ?? 0))
            .slice(0, 5)
            .map((e) => ({
                path: e.endpoint || e.path || '-',
                method: e.method || 'GET',
                count: e.today_quantity ?? e.count ?? 0,
                avg_time: e.avg_latency_ms ?? e.avg_time ?? 0,
            }));
    } catch {
        topEndpoints.value = [];
    }
}

async function refreshAll() {
    loading.value = true;
    try {
        await Promise.all([loadStats(), loadTrend(), loadSource(), loadLatency(), loadTopEndpoints()]);
    } catch {
        ElMessage.error(t('messages.load_failed'));
    }
    loading.value = false;
}

onMounted(() => {
    refreshAll();
    refreshTimer = setInterval(refreshAll, 30000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<style scoped>
.monitor-page { padding: 20px; }
.page-header {
    display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.metric-body { padding: 4px 0; }
.metric-value { font-size: 22px; font-weight: 700; color: #303133; line-height: 1.2; }
.metric-label { font-size: 13px; color: #909399; margin-top: 2px; }
</style>
