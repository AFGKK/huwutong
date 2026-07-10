<template>
  <div class="analytics-dashboard">
    <!-- 页面标题 & 操作 -->
    <div class="page-header">
      <div>
        <h2>📊 分析仪表盘</h2>
        <p class="text-muted">深入了解您的 License、设备、消费和使用趋势</p>
      </div>
      <div class="header-actions">
        <el-select v-model="period" size="small" style="width:130px" @change="fetchAll">
          <el-option label="近30天" value="month" />
          <el-option label="近90天" value="quarter" />
          <el-option label="近一年" value="year" />
        </el-select>
        <el-button size="small" @click="exportCSV('licenses')">导出 CSV</el-button>
        <el-button size="small" @click="refreshAll">刷新</el-button>
      </div>
    </div>

    <!-- 健康评分 -->
    <el-card class="mb-4 health-card" :class="'level-' + (health.level || 'healthy')">
      <div class="health-content">
        <div class="health-score-section">
          <div class="health-score-value">{{ health.score }}</div>
          <div class="health-score-label">健康评分</div>
          <el-tag :type="health.level === 'healthy' ? 'success' : health.level === 'warning' ? 'warning' : 'danger'" size="small">
            {{ health.level === 'healthy' ? '健康' : health.level === 'warning' ? '警告' : '严重' }}
          </el-tag>
        </div>
        <div class="health-metrics">
          <div class="health-metric">
            <div class="hm-value">{{ health.activation_rate }}%</div>
            <div class="hm-label">激活率</div>
          </div>
          <div class="health-divider" />
          <div class="health-metric">
            <div class="hm-value">{{ health.device_usage_rate }}%</div>
            <div class="hm-label">设备使用率</div>
          </div>
          <div class="health-divider" />
          <div class="health-metric">
            <div class="hm-value">{{ health.recent_activity_rate }}%</div>
            <div class="hm-label">近期活跃率</div>
          </div>
        </div>
        <div class="health-issues" v-if="health.issues?.length">
          <div v-for="issue in health.issues" :key="issue" class="health-issue">
            ⚠️ {{ issue }}
          </div>
        </div>
      </div>
    </el-card>

    <!-- 概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6"><el-card shadow="hover" class="stat-card">
        <div class="stat-value" style="color:#409eff">{{ overview.licenses.active }}/{{ overview.licenses.total }}</div>
        <div class="stat-label">活跃 License</div>
        <div class="stat-trend" v-if="overview.licenses.new > 0">+{{ overview.licenses.new }} 本月新增</div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card">
        <div class="stat-value" style="color:#67c23a">{{ overview.devices.active }}/{{ overview.devices.total }}</div>
        <div class="stat-label">活跃设备</div>
        <div class="stat-trend" v-if="overview.devices.new > 0">+{{ overview.devices.new }} 本月新增</div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card">
        <div class="stat-value" style="color:#e6a23c">¥{{ formatMoney(overview.orders.period_spend) }}</div>
        <div class="stat-label">本期消费</div>
        <div class="stat-trend">{{ overview.orders.period }} 笔订单</div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card">
        <div class="stat-value" :style="{color: overview.api.trend >= 0 ? '#409eff' : '#f56c6c'}">{{ overview.api.period_calls }}</div>
        <div class="stat-label">API 调用</div>
        <div class="stat-trend" :style="{color: overview.api.trend >= 0 ? '#67c23a' : '#f56c6c'}">{{ overview.api.trend >= 0 ? '+' : '' }}{{ overview.api.trend }}% 较上期</div>
      </el-card></el-col>
    </el-row>

    <!-- 图表区域 -->
    <el-row :gutter="16">
      <!-- License 激活趋势 -->
      <el-col :span="12">
        <el-card class="mb-4">
          <template #header><div class="card-header"><span>📈 License 激活趋势</span></div></template>
          <div ref="licenseChartRef" style="width:100%;height:260px;"></div>
        </el-card>
      </el-col>
      <!-- 月度消费趋势 -->
      <el-col :span="12">
        <el-card class="mb-4">
          <template #header><div class="card-header"><span>💰 月度消费趋势</span></div></template>
          <div ref="spendChartRef" style="width:100%;height:260px;"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <!-- License 类型分布 -->
      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><div class="card-header"><span>🧩 License 分布</span></div></template>
          <div ref="distChartRef" style="width:100%;height:250px;"></div>
        </el-card>
      </el-col>
      <!-- 设备新增趋势 -->
      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><div class="card-header"><span>🖥️ 设备新增趋势</span></div></template>
          <div ref="deviceChartRef" style="width:100%;height:250px;"></div>
        </el-card>
      </el-col>
      <!-- 用量排行榜 -->
      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><div class="card-header"><span>🏆 License 用量排行</span></div></template>
          <div v-if="topLicenses.length" class="top-list">
            <div v-for="(l, i) in topLicenses" :key="l.license_key" class="top-item">
              <div class="top-rank" :class="'rank-' + (i+1)">{{ i + 1 }}</div>
              <div class="top-info">
                <div class="top-name">
                  <code class="small-text">{{ l.license_key.slice(0, 16) }}...</code>
                  <el-tag :type="l.status === 'active' ? 'success' : 'danger'" size="small">{{ l.status }}</el-tag>
                </div>
                <div class="top-product">{{ l.product_name }}</div>
              </div>
              <div class="top-usage">
                <el-progress :percentage="l.usage_percent" :stroke-width="8" :width="60" type="circle">
                  {{ l.device_count }}
                </el-progress>
              </div>
            </div>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, markRaw } from 'vue';
import apiClient from '@/api/client';
import { ElMessage } from 'element-plus';
import * as echarts from 'echarts';

const period = ref('month');
const licenseChartRef = ref(null);
const spendChartRef = ref(null);
const distChartRef = ref(null);
const deviceChartRef = ref(null);

let licenseChart = null, spendChart = null, distChart = null, deviceChart = null;

const overview = reactive({
  licenses: { total: 0, active: 0, inactive: 0, new: 0, activation_rate: 0 },
  devices: { total: 0, active: 0, new: 0 },
  orders: { total: 0, period: 0, total_spend: 0, period_spend: 0 },
  api: { period_calls: 0, prev_period_calls: 0, trend: 0 },
});

const health = reactive({
  score: 0, level: 'healthy', activation_rate: 0, device_usage_rate: 0,
  recent_activity_rate: 0, issues: [],
});

const topLicenses = ref([]);

function formatMoney(v) {
  const n = Number(v);
  return n >= 10000 ? (n / 10000).toFixed(1) + '万' : n.toLocaleString('zh-CN', { minimumFractionDigits: 0 });
}

async function fetchOverview() {
  try {
    const { data: res } = await apiClient.get('/analytics/overview', { params: { period: period.value } });
    Object.assign(overview, res.data);
  } catch {}
}

async function fetchHealth() {
  try {
    const { data: res } = await apiClient.get('/analytics/health-score');
    Object.assign(health, res.data);
  } catch {}
}

async function fetchTopLicenses() {
  try {
    const { data: res } = await apiClient.get('/analytics/top-licenses', { params: { limit: 5 } });
    topLicenses.value = res.data || [];
  } catch {}
}

async function fetchLicenseTrend() {
  try {
    const { data: res } = await apiClient.get('/analytics/license-trend', { params: { days: 30 } });
    renderLicenseChart(res.data || []);
  } catch {}
}

async function fetchSpendTrend() {
  try {
    const { data: res } = await apiClient.get('/analytics/spend-trend', { params: { months: 12 } });
    renderSpendChart(res.data || []);
  } catch {}
}

async function fetchDistribution() {
  try {
    const { data: res } = await apiClient.get('/analytics/license-distribution');
    renderDistChart(res.data);
  } catch {}
}

async function fetchDeviceTrend() {
  try {
    const { data: res } = await apiClient.get('/analytics/device-trend', { params: { days: 30 } });
    renderDeviceChart(res.data || []);
  } catch {}
}

function renderLicenseChart(data) {
  if (!licenseChartRef.value) return;
  if (!licenseChart) licenseChart = echarts.init(licenseChartRef.value);
  licenseChart.setOption({
    tooltip: { trigger: 'axis' },
    grid: { left: 50, right: 16, top: 20, bottom: 28 },
    xAxis: { type: 'category', data: data.map(d => d.date.slice(5)), axisLabel: { fontSize: 11, color: '#909399' }, axisLine: { show: false }, axisTick: { show: false } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } } },
    series: [{
      type: 'bar', data: data.map(d => d.activated),
      itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#409eff' }, { offset: 1, color: 'rgba(64,158,255,0.3)' }]), borderRadius: [4, 4, 0, 0] },
    }],
  });
  licenseChart.resize();
}

function renderSpendChart(data) {
  if (!spendChartRef.value) return;
  if (!spendChart) spendChart = echarts.init(spendChartRef.value);
  const months = data.map(d => d.month.slice(5));
  const spends = data.map(d => d.spend);
  const orders = data.map(d => d.orders);
  spendChart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['消费金额', '订单数'], bottom: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { fontSize: 11 } },
    grid: { left: 60, right: 50, top: 20, bottom: 40 },
    xAxis: { type: 'category', data: months, axisLabel: { fontSize: 11, color: '#909399' }, axisLine: { show: false } },
    yAxis: [
      { type: 'value', name: '金额 (¥)', splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } }, axisLabel: { fontSize: 11, color: '#909399' } },
      { type: 'value', name: '订单数', axisLabel: { fontSize: 11, color: '#909399' } },
    ],
    series: [
      { name: '消费金额', type: 'bar', data: spends, itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#e6a23c' }, { offset: 1, color: 'rgba(230,162,60,0.3)' }]), borderRadius: [4, 4, 0, 0] } },
      { name: '订单数', type: 'line', yAxisIndex: 1, data: orders, smooth: true, lineStyle: { width: 2, color: '#67c23a' }, symbol: 'circle', symbolSize: 4, itemStyle: { color: '#67c23a' } },
    ],
  });
  spendChart.resize();
}

function renderDistChart(data) {
  if (!distChartRef.value) return;
  if (!distChart) distChart = echarts.init(distChartRef.value);
  const byStatus = data?.by_status || [];
  const byProduct = data?.by_product || [];
  const statusColors = { active: '#67c23a', expired: '#909399', suspended: '#f56c6c', revoked: '#e6a23c' };
  distChart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    series: [{
      type: 'pie', radius: ['40%', '65%'], center: ['50%', '50%'],
      data: byStatus.map(d => ({ name: d.status, value: d.count, itemStyle: { color: statusColors[d.status] || '#409eff' } })),
      label: { formatter: '{b}\n{d}%', fontSize: 11 },
      emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0,0,0,0.2)' } },
    }],
  });
  distChart.resize();
}

function renderDeviceChart(data) {
  if (!deviceChartRef.value) return;
  if (!deviceChart) deviceChart = echarts.init(deviceChartRef.value);
  deviceChart.setOption({
    tooltip: { trigger: 'axis' },
    grid: { left: 50, right: 16, top: 20, bottom: 28 },
    xAxis: { type: 'category', data: data.map(d => d.date.slice(5)), axisLabel: { fontSize: 11, color: '#909399' }, axisLine: { show: false }, axisTick: { show: false } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } } },
    series: [{
      type: 'line', data: data.map(d => d.count), smooth: true, areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(103,194,58,0.3)' }, { offset: 1, color: 'rgba(103,194,58,0.02)' }]) },
      lineStyle: { width: 2.5, color: '#67c23a' }, symbol: 'circle', symbolSize: 5, itemStyle: { color: '#67c23a' },
    }],
  });
  deviceChart.resize();
}

async function exportCSV(type) {
  try {
    const url = `/analytics/export/${type}`;
    const resp = await apiClient.get(url, { responseType: 'blob' });
    const blob = new Blob([resp.data], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${type}_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
    ElMessage.success('导出成功');
  } catch {
    ElMessage.error('导出失败');
  }
}

function refreshAll() {
  fetchOverview(); fetchHealth(); fetchTopLicenses();
  fetchLicenseTrend(); fetchSpendTrend(); fetchDistribution(); fetchDeviceTrend();
}

function fetchAll() {
  refreshAll();
}

onMounted(() => {
  refreshAll();
  window.addEventListener('resize', () => {
    [licenseChart, spendChart, distChart, deviceChart].forEach(c => c?.resize());
  });
});
</script>

<style scoped>
.analytics-dashboard { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 0; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }

/* 健康评分 */
.health-card { border-left: 4px solid #67c23a; }
.health-card.level-warning { border-left-color: #e6a23c; }
.health-card.level-critical { border-left-color: #f56c6c; }
.health-content { display: flex; align-items: center; gap: 32px; flex-wrap: wrap; }
.health-score-section { text-align: center; min-width: 100px; }
.health-score-value { font-size: 42px; font-weight: 700; color: var(--el-color-primary); line-height: 1; }
.health-score-label { font-size: 13px; color: #909399; margin: 4px 0; }
.health-metrics { display: flex; align-items: center; gap: 24px; }
.health-metric { text-align: center; }
.hm-value { font-size: 22px; font-weight: 600; }
.hm-label { font-size: 12px; color: #909399; margin-top: 2px; }
.health-divider { width: 1px; height: 40px; background: var(--el-border-color-light); }
.health-issues { flex: 1; min-width: 200px; }
.health-issue { font-size: 13px; color: #e6a23c; padding: 2px 0; }

/* 统计卡片 */
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-trend { font-size: 12px; color: #67c23a; margin-top: 2px; }

/* 排行榜 */
.top-list { display: flex; flex-direction: column; gap: 8px; }
.top-item { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; background: var(--el-fill-color-lighter); }
.top-rank { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #fff; background: #909399; }
.top-rank.rank-1 { background: #f56c6c; }
.top-rank.rank-2 { background: #e6a23c; }
.top-rank.rank-3 { background: #409eff; }
.top-info { flex: 1; min-width: 0; }
.top-name { display: flex; align-items: center; gap: 6px; }
.top-product { font-size: 11px; color: #909399; margin-top: 2px; }
.small-text { font-size: 11px; }
</style>
