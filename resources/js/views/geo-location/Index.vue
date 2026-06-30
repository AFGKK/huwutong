<template>
    <div class="geo-container">
        <el-page-header :content="'设备地理位置记录'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="记录设备 IP 归属地、展示地域分布热力图和管理黑名单区域。每次设备激活/验证时自动记录。" type="info" show-icon :closable="false" class="alert-info" />

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value">{{ dashboard.total_records }}</div>
                        <div class="stat-label">总记录数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value">{{ dashboard.covered_countries }}</div>
                        <div class="stat-label">覆盖国家/地区</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value">{{ dashboard.today_activations }}</div>
                        <div class="stat-label">今日激活</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #f56c6c;">{{ dashboard.blacklisted_count }}</div>
                        <div class="stat-label">黑名单拦截</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- 地域分布 -->
            <el-tab-pane label="地域分布" name="stats">
                <el-row :gutter="20">
                    <el-col :span="24">
                        <el-card>
                            <template #header>
                                <span>国家/地区分布 Top 20</span>
                                <div class="card-extra">
                                    <el-date-picker
                                        v-model="dateRange"
                                        type="daterange"
                                        range-separator="至"
                                        start-placeholder="开始日期"
                                        end-placeholder="结束日期"
                                        size="small"
                                        @change="loadStats"
                                    />
                                </div>
                            </template>
                            <div ref="barChartRef" style="height: 400px;"></div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 地图视图 -->
            <el-tab-pane label="地图视图" name="map">
                <el-card>
                    <template #header>
                        <span>全球设备分布</span>
                        <div class="card-extra">
                            <el-date-picker
                                v-model="mapDateRange"
                                type="daterange"
                                range-separator="至"
                                start-placeholder="开始日期"
                                end-placeholder="结束日期"
                                size="small"
                                @change="loadMapData"
                            />
                        </div>
                    </template>
                    <div ref="mapChartRef" style="height: 500px;"></div>
                </el-card>
            </el-tab-pane>

            <!-- 黑名单管理 -->
            <el-tab-pane label="黑名单管理" name="blacklist">
                <el-card>
                    <template #header><span>黑名单国家/地区</span></template>
                    <p class="tip-text">列入黑名单的国家/地区的设备请求将被标记。输入 ISO 3166-1 alpha-2 国家代码（如：CN, US, RU）。</p>
                    <el-select
                        v-model="blacklistCountries"
                        multiple
                        filterable
                        allow-create
                        default-first-option
                        placeholder="输入国家代码（如 CN, US）"
                        style="width: 100%;"
                    >
                        <el-option
                            v-for="code in commonCountryCodes"
                            :key="code.value"
                            :label="`${code.label} (${code.value})`"
                            :value="code.value"
                        />
                    </el-select>
                    <div class="actions">
                        <el-button type="primary" :loading="savingBlacklist" @click="handleSaveBlacklist">保存黑名单</el-button>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 记录列表 -->
            <el-tab-pane label="记录列表" name="records">
                <el-card>
                    <template #header>
                        <span>地理位置记录</span>
                        <div class="card-extra">
                            <el-input
                                v-model="recordSearch"
                                placeholder="搜索 IP / 国家 / 城市"
                                size="small"
                                clearable
                                style="width: 200px;"
                                @clear="loadRecords"
                                @keyup.enter="loadRecords"
                            />
                        </div>
                    </template>
                    <el-table :data="records" stripe size="small" v-loading="loadingRecords">
                        <el-table-column prop="ip_address" label="IP" width="140" />
                        <el-table-column prop="country" label="国家" width="120" />
                        <el-table-column prop="country_code" label="代码" width="70" />
                        <el-table-column prop="region" label="区域" width="120" />
                        <el-table-column prop="city" label="城市" width="120" />
                        <el-table-column prop="isp" label="ISP" width="120" />
                        <el-table-column prop="source" label="来源" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.source }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="黑名单" width="70">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_blacklisted" type="danger" size="small">是</el-tag>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" width="160" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { ElMessage } from 'element-plus';
import geoLocation from '@/api/geoLocation';
import * as echarts from 'echarts';

const activeTab = ref('stats');
const barChartRef = ref(null);
const mapChartRef = ref(null);
let barChartInstance = null;
let mapChartInstance = null;

const dateRange = ref(null);
const mapDateRange = ref(null);
const recordSearch = ref('');
const loadingRecords = ref(false);
const savingBlacklist = ref(false);

const dashboard = reactive({
    total_records: 0,
    covered_countries: 0,
    today_activations: 0,
    blacklisted_count: 0,
    top_countries: [],
});

const statsData = ref([]);
const mapData = ref([]);
const records = ref([]);
const blacklistCountries = ref([]);

const commonCountryCodes = [
    { value: 'CN', label: '中国' },
    { value: 'US', label: '美国' },
    { value: 'JP', label: '日本' },
    { value: 'KR', label: '韩国' },
    { value: 'GB', label: '英国' },
    { value: 'DE', label: '德国' },
    { value: 'FR', label: '法国' },
    { value: 'SG', label: '新加坡' },
    { value: 'HK', label: '香港' },
    { value: 'TW', label: '台湾' },
    { value: 'RU', label: '俄罗斯' },
    { value: 'IN', label: '印度' },
    { value: 'AU', label: '澳大利亚' },
    { value: 'CA', label: '加拿大' },
    { value: 'BR', label: '巴西' },
    { value: 'NL', label: '荷兰' },
    { value: 'SE', label: '瑞典' },
    { value: 'NO', label: '挪威' },
    { value: 'FI', label: '芬兰' },
    { value: 'DK', label: '丹麦' },
    { value: 'CH', label: '瑞士' },
    { value: 'IT', label: '意大利' },
    { value: 'ES', label: '西班牙' },
    { value: 'KR', label: '韩国' },
    { value: 'TH', label: '泰国' },
    { value: 'VN', label: '越南' },
    { value: 'ID', label: '印度尼西亚' },
    { value: 'PH', label: '菲律宾' },
    { value: 'MY', label: '马来西亚' },
];

async function loadDashboard() {
    try {
        const res = await geoLocation.dashboard();
        Object.assign(dashboard, res.data.data);
    } catch {}
}

async function loadStats() {
    try {
        const params = {};
        if (dateRange.value) {
            params.start_date = dateRange.value[0].toISOString().split('T')[0];
            params.end_date = dateRange.value[1].toISOString().split('T')[0];
        }
        const res = await geoLocation.stats(params);
        statsData.value = res.data.data || [];
        renderBarChart();
    } catch {}
}

async function loadMapData() {
    try {
        const params = {};
        if (mapDateRange.value) {
            params.start_date = mapDateRange.value[0].toISOString().split('T')[0];
            params.end_date = mapDateRange.value[1].toISOString().split('T')[0];
        }
        const res = await geoLocation.mapData(params);
        mapData.value = res.data.data || [];
        renderMapChart();
    } catch {}
}

async function loadRecords() {
    loadingRecords.value = true;
    try {
        const params = {};
        if (recordSearch.value) params.search = recordSearch.value;
        const res = await geoLocation.records(params);
        records.value = res.data.data || [];
    } catch {} finally {
        loadingRecords.value = false;
    }
}

async function loadBlacklist() {
    try {
        const res = await geoLocation.blacklist();
        blacklistCountries.value = res.data.data?.countries || [];
    } catch {}
}

// ── ECharts ──

function renderBarChart() {
    if (!barChartRef.value) return;
    if (!barChartInstance) {
        barChartInstance = echarts.init(barChartRef.value);
    }

    const top = statsData.value.slice(0, 20);
    const names = top.map(i => i.country_code || i.country);
    const values = top.map(i => i.total || i.device_count || 0);

    barChartInstance.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: names, axisLabel: { rotate: 45, fontSize: 11 } },
        yAxis: { type: 'value' },
        series: [{
            type: 'bar',
            data: values,
            itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#409eff' },
                    { offset: 1, color: '#79bbff' },
                ]),
                borderRadius: [4, 4, 0, 0],
            },
        }],
    });
}

function renderMapChart() {
    if (!mapChartRef.value) return;
    if (!mapChartInstance) {
        mapChartInstance = echarts.init(mapChartRef.value);
    }

    const scatterData = (mapData.value || []).map(d => ({
        name: `${d.city || d.country} (${d.total})`,
        value: [parseFloat(d.longitude), parseFloat(d.latitude), d.total],
    }));

    mapChartInstance.setOption({
        tooltip: {
            trigger: 'item',
            formatter: (p) => `${p.name}<br/>数量: ${p.value?.[2] || 0}`,
        },
        geo: {
            map: 'world',
            roam: true,
            label: { show: false },
            itemStyle: {
                areaColor: '#e6f0ff',
                borderColor: '#a0c4ff',
            },
            emphasis: {
                itemStyle: { areaColor: '#409eff' },
            },
        },
        series: [{
            type: 'scatter',
            coordinateSystem: 'geo',
            data: scatterData,
            symbolSize: (val) => Math.max(4, Math.min(30, val[2] * 0.8)),
            itemStyle: {
                color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.5, [
                    { offset: 0, color: 'rgba(64, 158, 255, 0.9)' },
                    { offset: 1, color: 'rgba(64, 158, 255, 0.2)' },
                ]),
            },
            emphasis: {
                scale: 1.5,
                label: { show: true, formatter: (p) => p.name },
            },
        }],
    });
}

async function handleSaveBlacklist() {
    savingBlacklist.value = true;
    try {
        await geoLocation.updateBlacklist({ countries: blacklistCountries.value });
        ElMessage.success('黑名单已更新');
    } catch {} finally {
        savingBlacklist.value = false;
    }
}

// 标签页切换时重新渲染图表
watch(activeTab, (tab) => {
    if (tab === 'stats') {
        nextTick(() => { if (statsData.value.length) renderBarChart(); });
    } else if (tab === 'map') {
        nextTick(() => { if (mapData.value.length) renderMapChart(); });
    } else if (tab === 'records') {
        loadRecords();
    } else if (tab === 'blacklist') {
        loadBlacklist();
    }
});

onMounted(() => {
    loadDashboard();
    loadStats();
    loadMapData();
});

// 窗口大小变化时自适应
window.addEventListener('resize', () => {
    barChartInstance?.resize();
    mapChartInstance?.resize();
});
</script>

<style scoped>
.geo-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.card-extra { float: right; }
.actions { margin-top: 16px; }
.tip-text { color: #909399; font-size: 13px; margin-bottom: 12px; }
</style>
