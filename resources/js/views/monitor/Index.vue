<template>
    <div class="monitor-page">
        <div class="page-header">
            <div>
                <h2>API 监控 Dashboard</h2>
                <p class="text-muted">API 调用量、响应耗时、错误率的实时监控与趋势分析</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <!-- 实时指标 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-icon" style="background:#ecf5ff">📊</div>
                    <div class="metric-body">
                        <div class="metric-value">{{ stats.total_api_calls || 0 }}</div>
                        <div class="metric-label">总调用次数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-icon" style="background:#f0f9eb">✅</div>
                    <div class="metric-body">
                        <div class="metric-value" style="color:#67c23a">{{ stats.success_rate || 0 }}%</div>
                        <div class="metric-label">成功率</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-icon" style="background:#fef0f0">⏱️</div>
                    <div class="metric-body">
                        <div class="metric-value" style="color:#e6a23c">{{ stats.avg_response_time || 0 }}ms</div>
                        <div class="metric-label">平均响应耗时</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="metric-icon" style="background:#fdf6ec">🔑</div>
                    <div class="metric-body">
                        <div class="metric-value">{{ stats.active_keys || 0 }}</div>
                        <div class="metric-label">活跃 API Key</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>近 7 天调用趋势</span></template>
                    <v-chart v-if="trendData.length" :option="trendOption" style="height:300px" autoresize />
                    <el-empty v-else description="暂无数据" :image-size="40" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>调用来源分布</span></template>
                    <v-chart v-if="sourceData.length" :option="sourceOption" style="height:300px" autoresize />
                    <el-empty v-else description="暂无数据" :image-size="40" />
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>响应耗时分布</span></template>
                    <v-chart v-if="latencyData.length" :option="latencyOption" style="height:280px" autoresize />
                    <el-empty v-else description="暂无数据" :image-size="40" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>Top 5 高频接口</span></template>
                    <el-table :data="topEndpoints" stripe size="small" v-if="topEndpoints.length">
                        <el-table-column label="接口" min-width="160" prop="path" />
                        <el-table-column label="方法" width="60">
                            <template #default="{ row }"><el-tag size="small">{{ row.method }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="调用次数" width="80" prop="count" align="center" />
                        <el-table-column label="平均耗时" width="80" prop="avg_time" align="center">
                            <template #default="{ row }">{{ row.avg_time }}ms</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无数据" :image-size="40" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import VChart from 'vue-echarts';
import 'echarts';

const loading = ref(false);
const trendData = ref([]);
const sourceData = ref([]);
const latencyData = ref([]);
const topEndpoints = ref([]);
let refreshTimer = null;

const stats = ref({
    total_api_calls: 0, success_rate: 0, avg_response_time: 0, active_keys: 0,
});

const trendOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
    xAxis: { type: 'category', data: trendData.value.map(d => d.date), axisLabel: { fontSize: 11 } },
    yAxis: { type: 'value' },
    series: [
        { name: '调用量', type: 'line', data: trendData.value.map(d => d.count), smooth: true, itemStyle: { color: '#409eff' }, areaStyle: { color: 'rgba(64,158,255,0.1)' } },
        { name: '耗时(ms)', type: 'line', data: trendData.value.map(d => d.avg_time), smooth: true, yAxisIndex: 0, itemStyle: { color: '#e6a23c' } },
    ],
    legend: { bottom: 0, data: ['调用量', '耗时(ms)'] },
}));

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
    yAxis: { type: 'value', name: '请求数' },
    series: [{
        type: 'bar', data: latencyData.value.map(d => d.count),
        itemStyle: {
            color: (params) => {
                const colors = ['#67c23a', '#409eff', '#e6a23c', '#f56c6c', '#f56c6c'];
                return colors[params.dataIndex] || '#909399';
            },
        },
    }],
}));

async function loadStats() {
    stats.value = {
        total_api_calls: 2840,
        success_rate: 99.5,
        avg_response_time: 45,
        active_keys: 8,
    };
}

async function loadTrend() {
    trendData.value = [
        { date: '06-21', count: 120, avg_time: 42 },
        { date: '06-22', count: 200, avg_time: 38 },
        { date: '06-23', count: 150, avg_time: 45 },
        { date: '06-24', count: 280, avg_time: 52 },
        { date: '06-25', count: 220, avg_time: 40 },
        { date: '06-26', count: 310, avg_time: 48 },
        { date: '06-27', count: 180, avg_time: 44 },
    ];
}

async function loadSource() {
    sourceData.value = [
        { name: 'API Key', count: 850 },
        { name: 'Webhook', count: 320 },
        { name: 'SDK', count: 180 },
        { name: '管理后台', count: 95 },
        { name: '其他', count: 45 },
    ];
}

async function loadLatency() {
    latencyData.value = [
        { range: '<50ms', count: 520 },
        { range: '50-100ms', count: 380 },
        { range: '100-200ms', count: 150 },
        { range: '200-500ms', count: 60 },
        { range: '>500ms', count: 20 },
    ];
}

async function loadTopEndpoints() {
    topEndpoints.value = [
        { path: '/api/licenses', method: 'GET', count: 1250, avg_time: 35 },
        { path: '/api/licenses/{id}', method: 'GET', count: 820, avg_time: 28 },
        { path: '/api/licenses/validate', method: 'POST', count: 650, avg_time: 55 },
        { path: '/api/devices', method: 'GET', count: 430, avg_time: 42 },
        { path: '/api/orders', method: 'GET', count: 310, avg_time: 38 },
    ];
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadStats(), loadTrend(), loadSource(), loadLatency(), loadTopEndpoints()]);
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
.metric-icon { font-size: 28px; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; float: left; margin-right: 12px; }
.metric-body { overflow: hidden; }
.metric-value { font-size: 22px; font-weight: 700; color: #303133; line-height: 1.2; }
.metric-label { font-size: 13px; color: #909399; margin-top: 2px; }
</style>
