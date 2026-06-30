<template>
    <div class="portal-usage">
        <div class="page-header">
            <div>
                <h2>用量看板</h2>
                <p class="text-muted">查看本月 API 调用、设备活跃度和功能使用情况。</p>
            </div>
            <el-select v-model="selectedPeriod" size="small" style="width: 140px" @change="fetchAll">
                <el-option label="本月" value="month" />
                <el-option label="上月" value="last_month" />
                <el-option label="近90天" value="quarter" />
            </el-select>
        </div>

        <!-- 概览卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="card in overviewCards" :key="card.label">
                <el-card shadow="hover" class="usage-stat-card">
                    <div class="usage-stat-content">
                        <div class="usage-stat-info">
                            <div class="usage-stat-value" :style="{ color: card.color }">{{ card.value }}</div>
                            <div class="usage-stat-label">{{ card.label }}</div>
                        </div>
                        <el-icon :size="32" :color="card.color + '33'">
                            <component :is="card.icon" />
                        </el-icon>
                    </div>
                    <div class="usage-stat-trend" v-if="card.trend !== undefined">
                        <el-tag :type="card.trend >= 0 ? 'success' : 'danger'" size="small" effect="plain">
                            {{ card.trend >= 0 ? '+' : '' }}{{ card.trend }}%
                        </el-tag>
                        <span class="trend-label">较上期</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- 左侧：API 调用趋势 -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>API 调用趋势</span>
                            <el-radio-group v-model="apiChartRange" size="small" @change="fetchApiUsage">
                                <el-radio-button value="7d">7天</el-radio-button>
                                <el-radio-button value="30d">30天</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div class="chart-container" v-loading="loadingApi">
                        <div ref="apiChartRef" style="width:100%;height:260px;"></div>
                        <el-empty v-if="!apiDailyData.length" description="暂无调用数据" :image-size="60" style="display:none" />
                    </div>
                </el-card>

                <!-- API 分端点统计 -->
                <el-card>
                    <template #header>
                        <span>API 调用分布（按端点）</span>
                    </template>
                    <div v-if="endpointStats.length" class="endpoint-list">
                        <div v-for="ep in endpointStats" :key="ep.endpoint" class="endpoint-row">
                            <div class="endpoint-info">
                                <el-tag :type="methodType(ep.method)" size="small" class="method-tag">
                                    {{ ep.method }}
                                </el-tag>
                                <span class="endpoint-path">{{ ep.endpoint }}</span>
                            </div>
                            <div class="endpoint-metrics">
                                <span class="endpoint-count">{{ ep.count }}</span>
                                <el-progress
                                    :percentage="calcEndpointPercent(ep.count)"
                                    :stroke-width="8"
                                    :show-text="false"
                                />
                            </div>
                        </div>
                    </div>
                    <el-empty v-else description="暂无数据" :image-size="60" />
                </el-card>
            </el-col>

            <!-- 右侧：设备 & 功能 -->
            <el-col :span="12">
                <!-- 设备活跃趋势 -->
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>设备活跃趋势</span>
                            <el-radio-group v-model="deviceChartRange" size="small" @change="fetchDeviceTrend">
                                <el-radio-button value="7d">7天</el-radio-button>
                                <el-radio-button value="30d">30天</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div class="device-metrics" style="margin-bottom:12px;">
                        <div class="metric-row">
                            <span class="metric-label">当前在线</span>
                            <span class="metric-value" style="color:#67c23a">{{ deviceMetrics.active }}</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">本月新增</span>
                            <span class="metric-value" style="color:#409eff">{{ deviceMetrics.new_this_month }}</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">设备总数</span>
                            <span class="metric-value">{{ deviceMetrics.total }}</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">激活成功率</span>
                            <span class="metric-value" style="color:#67c23a">{{ deviceMetrics.activation_success_rate }}%</span>
                        </div>
                    </div>
                    <div class="chart-container" v-loading="loadingDevice">
                        <div ref="deviceChartRef" style="width:100%;height:220px;"></div>
                        <el-empty v-if="!deviceTrendData.length" description="暂无设备活跃数据" :image-size="60" style="display:none" />
                    </div>
                </el-card>

                <!-- 功能使用排行 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>功能使用排行</span>
                    </template>
                    <div v-if="featureUsage.length" class="feature-list">
                        <div v-for="(feat, idx) in featureUsage" :key="feat.name" class="feature-row">
                            <div class="feature-rank">#{{ idx + 1 }}</div>
                            <div class="feature-info">
                                <span class="feature-name">{{ feat.name }}</span>
                                <span class="feature-count">{{ feat.count }} 次</span>
                            </div>
                            <el-progress
                                :percentage="calcFeaturePercent(feat.count)"
                                :stroke-width="8"
                                :color="featureColors[idx % featureColors.length]"
                            />
                        </div>
                    </div>
                    <el-empty v-else description="暂无数据" :image-size="60" />
                </el-card>

                <!-- License 配额使用率 -->
                <el-card>
                    <template #header>
                        <span>License 配额使用率</span>
                    </template>
                    <div v-if="quotaData.length" class="quota-list">
                        <div v-for="q in quotaData" :key="q.license_key" class="quota-row">
                            <div class="quota-header">
                                <code class="small-text">{{ q.license_key }}</code>
                                <span class="quota-percent">{{ q.usage_percent }}%</span>
                            </div>
                            <el-progress
                                :percentage="q.usage_percent"
                                :status="q.usage_percent >= 80 ? 'exception' : q.usage_percent >= 60 ? 'warning' : 'success'"
                                :stroke-width="12"
                                :text-inside="true"
                            >
                                {{ q.used }}/{{ q.total }}
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-else description="暂无数据" :image-size="60" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, markRaw, onMounted, nextTick, watch } from 'vue';
import apiClient from '@/api/client';
import deviceApi from '@/api/device';
import licenseApi from '@/api/license';
import { ElMessage } from 'element-plus';
import * as echarts from 'echarts';
import {
    Odometer, Monitor, Connection, TrendCharts,
} from '@element-plus/icons-vue';

const loadingApi = ref(false);
const loadingDevice = ref(false);
const selectedPeriod = ref('month');
const apiChartRange = ref('7d');
const deviceChartRange = ref('7d');
const apiDailyData = ref([]);
const deviceTrendData = ref([]);
const maxApiCount = ref(1);
const endpointStats = ref([]);
const featureUsage = ref([]);
const quotaData = ref([]);

const apiChartRef = ref(null);
const deviceChartRef = ref(null);
let apiChartInstance = null;
let deviceChartInstance = null;

const deviceMetrics = reactive({
    active: 0,
    total: 0,
    new_this_month: 0,
    activation_success_rate: 100,
});

const overviewCards = reactive([
    { label: 'API 调用次数', value: '0', icon: markRaw(Odometer), color: '#409eff', trend: undefined },
    { label: '活跃设备', value: '0', icon: markRaw(Monitor), color: '#67c23a', trend: undefined },
    { label: '本月新增设备', value: '0', icon: markRaw(Connection), color: '#e6a23c', trend: undefined },
    { label: '配额使用率', value: '0%', icon: markRaw(TrendCharts), color: '#f56c6c', trend: undefined },
]);

const featureColors = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#b37feb'];

function methodType(method) {
    const map = { GET: 'success', POST: 'primary', PUT: 'warning', DELETE: 'danger', PATCH: 'info' };
    return map[method] || 'info';
}

function calcEndpointPercent(count) {
    const max = Math.max(...endpointStats.value.map(e => e.count), 1);
    return Math.round((count / max) * 100);
}

function calcFeaturePercent(count) {
    const max = Math.max(...featureUsage.value.map(f => f.count), 1);
    return Math.round((count / max) * 100);
}

async function fetchApiUsage() {
    loadingApi.value = true;
    try {
        const days = apiChartRange.value === '7d' ? 7 : 30;
        const { data: res } = await apiClient.get('/usage/api-calls', {
            params: { period: selectedPeriod.value, days },
        });
        apiDailyData.value = res.data?.daily || [];
        maxApiCount.value = Math.max(...apiDailyData.value.map(d => d.count), 1);
        await nextTick();
        renderApiChart();
    } catch {
        apiDailyData.value = [];
    } finally {
        loadingApi.value = false;
    }
}

async function fetchDeviceTrend() {
    loadingDevice.value = true;
    try {
        const days = deviceChartRange.value === '7d' ? 7 : 30;
        const { data: res } = await apiClient.get('/usage/device-trend', {
            params: { days },
        });
        deviceTrendData.value = res.data?.daily || [];
        await nextTick();
        renderDeviceChart();
    } catch {
        deviceTrendData.value = [];
    } finally {
        loadingDevice.value = false;
    }
}

function renderApiChart() {
    if (!apiChartRef.value) return;
    if (!apiChartInstance) {
        apiChartInstance = echarts.init(apiChartRef.value);
    }
    const days = apiDailyData.value.map(d => d.date?.slice(5) || '');
    const counts = apiDailyData.value.map(d => d.count);
    apiChartInstance.setOption({
        tooltip: { trigger: 'axis', backgroundColor: 'rgba(255,255,255,0.95)', borderWidth: 0 },
        grid: { left: 50, right: 16, top: 20, bottom: 28 },
        xAxis: { type: 'category', data: days, axisLabel: { fontSize: 11, color: '#909399' }, axisLine: { show: false }, axisTick: { show: false } },
        yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } }, axisLabel: { fontSize: 11, color: '#909399' } },
        series: [{
            type: 'line', data: counts, smooth: true,
            lineStyle: { width: 2.5, color: '#409eff' },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(64,158,255,0.3)' }, { offset: 1, color: 'rgba(64,158,255,0.02)' }]) },
            symbol: 'circle', symbolSize: 5, itemStyle: { color: '#409eff' },
        }],
    });
    apiChartInstance.resize();
}

function renderDeviceChart() {
    if (!deviceChartRef.value) return;
    if (!deviceChartInstance) {
        deviceChartInstance = echarts.init(deviceChartRef.value);
    }
    const days = deviceTrendData.value.map(d => d.date?.slice(5) || '');
    const activeCounts = deviceTrendData.value.map(d => d.active || 0);
    const newCounts = deviceTrendData.value.map(d => d.new_devices || 0);
    deviceChartInstance.setOption({
        tooltip: { trigger: 'axis', backgroundColor: 'rgba(255,255,255,0.95)', borderWidth: 0 },
        legend: { data: ['活跃设备', '新增设备'], bottom: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { fontSize: 11, color: '#909399' } },
        grid: { left: 50, right: 16, top: 20, bottom: 40 },
        xAxis: { type: 'category', data: days, axisLabel: { fontSize: 11, color: '#909399' }, axisLine: { show: false }, axisTick: { show: false } },
        yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } }, axisLabel: { fontSize: 11, color: '#909399' } },
        series: [
            { name: '活跃设备', type: 'line', data: activeCounts, smooth: true, lineStyle: { width: 2, color: '#67c23a' }, areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(103,194,58,0.25)' }, { offset: 1, color: 'rgba(103,194,58,0.02)' }]) }, symbol: 'circle', symbolSize: 4, itemStyle: { color: '#67c23a' } },
            { name: '新增设备', type: 'line', data: newCounts, smooth: true, lineStyle: { width: 2, color: '#409eff', type: 'dashed' }, symbol: 'diamond', symbolSize: 4, itemStyle: { color: '#409eff' } },
        ],
    });
    deviceChartInstance.resize();
}

async function fetchEndpointStats() {
    try {
        const { data: res } = await apiClient.get('/usage/endpoint-stats', {
            params: { period: selectedPeriod.value },
        });
        endpointStats.value = res.data || [];
    } catch {
        endpointStats.value = [];
    }
}

async function fetchDeviceMetrics() {
    try {
        const { data: res } = await deviceApi.stats();
        const s = res.data || {};
        deviceMetrics.total = s.total || 0;
        deviceMetrics.active = s.active || 0;
        deviceMetrics.new_this_month = s.new_this_month || 0;
        deviceMetrics.activation_success_rate = s.activation_success_rate || 100;
    } catch {
        // ignore
    }
}

async function fetchFeatureUsage() {
    try {
        const { data: res } = await apiClient.get('/usage/features', {
            params: { period: selectedPeriod.value },
        });
        featureUsage.value = (res.data || []).slice(0, 10);
    } catch {
        featureUsage.value = [];
    }
}

async function fetchQuotaUsage() {
    try {
        const { data: res } = await licenseApi.list({ per_page: 10, sort: '-created_at' });
        const licenses = res.data?.data || [];
        quotaData.value = licenses.map(l => ({
            license_key: l.license_key,
            usage_percent: l.max_devices ? Math.min(Math.round((l.active_devices_count || 0) / l.max_devices * 100), 100) : 0,
            used: l.active_devices_count || 0,
            total: l.max_devices || 1,
        }));
    } catch {
        quotaData.value = [];
    }
}

async function fetchOverview() {
    try {
        const { data: res } = await apiClient.get('/usage/overview', {
            params: { period: selectedPeriod.value },
        });
        const ov = res.data || {};
        overviewCards[0].value = String(ov.api_calls || 0);
        overviewCards[0].trend = ov.api_calls_trend;
        overviewCards[1].value = String(ov.active_devices || 0);
        overviewCards[1].trend = ov.devices_trend;
        overviewCards[2].value = String(ov.new_devices || 0);
        overviewCards[2].trend = ov.new_devices_trend;
        overviewCards[3].value = (ov.quota_usage_percent || 0) + '%';
        overviewCards[3].trend = ov.quota_trend;
    } catch {
        // fallback to device stats
        overviewCards[1].value = String(deviceMetrics.active);
        overviewCards[2].value = String(deviceMetrics.new_this_month);
    }
}

async function fetchAll() {
    await Promise.all([
        fetchDeviceMetrics(),
        fetchApiUsage(),
        fetchDeviceTrend(),
        fetchEndpointStats(),
        fetchFeatureUsage(),
        fetchQuotaUsage(),
    ]);
    await fetchOverview();
}

onMounted(fetchAll);

// 窗口缩放时重绘图表
let resizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        apiChartInstance?.resize();
        deviceChartInstance?.resize();
    }, 200);
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0 0 4px; }

.text-muted { color: #909399; font-size: 14px; margin: 0; }
.mb-4 { margin-bottom: 16px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.usage-stat-card {
    cursor: default;
    transition: transform 0.2s;
}

.usage-stat-card:hover {
    transform: translateY(-2px);
}

.usage-stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.usage-stat-value {
    font-size: 28px;
    font-weight: 700;
}

.usage-stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.usage-stat-trend {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.trend-label {
    font-size: 12px;
    color: #909399;
}

.chart-container {
    height: 200px;
    display: flex;
    align-items: flex-end;
}

.chart-container {
    min-height: 260px;
}

.endpoint-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.endpoint-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.endpoint-info {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1;
}

.method-tag {
    flex-shrink: 0;
}

.endpoint-path {
    font-family: monospace;
    font-size: 12px;
    color: #303133;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.endpoint-metrics {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    width: 160px;
}

.endpoint-count {
    font-weight: 600;
    font-size: 14px;
    color: #303133;
    min-width: 40px;
    text-align: right;
}

.device-metrics {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.metric-label { color: #606266; }
.metric-value { font-weight: 600; }

.feature-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.feature-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.feature-rank {
    font-size: 14px;
    font-weight: 600;
    color: #909399;
    min-width: 24px;
}

.feature-info {
    display: flex;
    flex-direction: column;
    min-width: 120px;
}

.feature-name { font-size: 13px; color: #303133; }
.feature-count { font-size: 11px; color: #909399; }

.quota-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.quota-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.quota-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.quota-percent {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
}

.small-text { font-size: 11px; }
</style>
