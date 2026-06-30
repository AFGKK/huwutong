<template>
  <div class="endpoint-usage">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>API 用量统计</h2>
        <span class="header-subtitle">按端点查看 API 调用量、延迟和错误率</span>
      </div>
      <div class="header-right">
        <el-radio-group v-model="trendDays" size="small" @change="fetchTrend">
          <el-radio-button :value="7">7天</el-radio-button>
          <el-radio-button :value="14">14天</el-radio-button>
          <el-radio-button :value="30">30天</el-radio-button>
        </el-radio-group>
        <el-button @click="refreshData" class="ml-2">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 预警横幅 -->
    <div v-if="alertsData.critical_count > 0" class="alert-banner critical">
      <el-icon><WarningFilled /></el-icon>
      <span>检测到 {{ alertsData.critical_count }} 个端点用量激增，请关注详情</span>
    </div>
    <div v-else-if="alertsData.warning_count > 0" class="alert-banner warning">
      <el-icon><WarningFilled /></el-icon>
      <span>{{ alertsData.warning_count }} 个端点用量增长明显</span>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ totalCallsToday }}</div>
            <div class="stat-label">今日 API 调用</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ totalCallsMonth }}</div>
            <div class="stat-label">本月 API 调用</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">4</div>
            <div class="stat-label">API 端点</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value" :class="overallErrorRate > 5 ? 'danger' : 'success'">{{ overallErrorRate }}%</div>
            <div class="stat-label">总体错误率（近7天）</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 端点概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6" v-for="ep in endpoints" :key="ep.metric_key" style="margin-bottom: 16px">
        <el-card shadow="hover" class="endpoint-card" :style="{ borderTop: `3px solid ${ep.color || '#409eff'}` }">
          <div class="endpoint-header">
            <span class="endpoint-method" :style="{ color: ep.color }">{{ ep.method }}</span>
            <span class="endpoint-name">{{ ep.name }}</span>
          </div>
          <div class="endpoint-path">{{ ep.path }}</div>
          <el-divider />
          <div class="endpoint-metrics">
            <div class="metric-row">
              <span class="metric-label">今日调用</span>
              <span class="metric-value">{{ ep.today_quantity }}</span>
            </div>
            <div class="metric-row">
              <span class="metric-label">本月调用</span>
              <span class="metric-value">{{ ep.this_month_quantity }}</span>
            </div>
            <div class="metric-row" v-if="ep.monthly_change_percent !== 0">
              <span class="metric-label">环比上月</span>
              <span class="metric-value" :class="ep.monthly_change_percent > 0 ? 'up' : 'down'">
                {{ ep.monthly_change_percent > 0 ? '+' : '' }}{{ ep.monthly_change_percent }}%
              </span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 用量趋势图 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <div class="card-header">
          <span>用量趋势</span>
        </div>
      </template>
      <div class="trend-container" ref="trendChartRef"></div>
    </el-card>

    <!-- 延迟和错误率 -->
    <el-row :gutter="16" class="mb-4">
      <!-- 延迟统计 -->
      <el-col :span="12">
        <el-card shadow="never">
          <template #header>
            <span>延迟统计（P50 / P99，近7天）</span>
          </template>
          <el-table :data="latencyTableData" stripe size="small">
            <el-table-column label="端点" min-width="120">
              <template #default="{ row }">
                <span :style="{ color: row.color }">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column label="P50" width="80" align="right">
              <template #default="{ row }">{{ row.p50 }}ms</template>
            </el-table-column>
            <el-table-column label="P90" width="80" align="right">
              <template #default="{ row }">{{ row.p90 }}ms</template>
            </el-table-column>
            <el-table-column label="P99" width="80" align="right">
              <template #default="{ row }">{{ row.p99 }}ms</template>
            </el-table-column>
            <el-table-column label="平均" width="80" align="right">
              <template #default="{ row }">{{ row.avg }}ms</template>
            </el-table-column>
            <el-table-column label="样本" width="70" align="right">
              <template #default="{ row }">{{ row.sample_count }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <!-- 错误率统计 -->
      <el-col :span="12">
        <el-card shadow="never">
          <template #header>
            <span>错误率统计（近7天）</span>
          </template>
          <el-table :data="errorTableData" stripe size="small">
            <el-table-column label="端点" min-width="120">
              <template #default="{ row }">
                <span :style="{ color: row.color }">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column label="请求数" width="80" align="right">
              <template #default="{ row }">{{ row.total_requests }}</template>
            </el-table-column>
            <el-table-column label="错误数" width="80" align="right">
              <template #default="{ row }">{{ row.error_count }}</template>
            </el-table-column>
            <el-table-column label="错误率" width="90" align="right">
              <template #default="{ row }">
                <el-tag :type="row.error_rate > 5 ? 'danger' : row.error_rate > 1 ? 'warning' : 'success'" size="small">
                  {{ row.error_rate }}%
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <!-- 错误详情 -->
          <el-divider />
          <div class="error-detail-title">错误码详情</div>
          <div v-if="!hasErrorDetail" class="empty-state">近7天无错误记录</div>
          <div v-else v-for="(errors, metricKey) in errorDetailData" :key="metricKey">
            <div class="error-metric-label" v-if="errors.length">{{ getEndpointName(metricKey) }}</div>
            <div v-for="err in errors" :key="err.error_code" class="error-item">
              <el-tag size="small" type="danger">{{ err.error_code }}</el-tag>
              <span class="error-msg">{{ err.error_message }}</span>
              <span class="error-count">{{ err.count }}次</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 超额预警 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>用量预警</span>
          <div>
            <el-tag v-if="alertsData.critical_count" type="danger" size="small" class="mr-1">
              严重 {{ alertsData.critical_count }}
            </el-tag>
            <el-tag v-if="alertsData.warning_count" type="warning" size="small">
              警告 {{ alertsData.warning_count }}
            </el-tag>
          </div>
        </div>
      </template>
      <div v-if="!alertsData.alerts?.length" class="empty-state">暂无预警</div>
      <div v-else>
        <div v-for="alert in alertsData.alerts" :key="alert.metric_key" class="alert-item">
          <el-tag :type="alert.level === 'critical' ? 'danger' : alert.level === 'warning' ? 'warning' : 'info'" size="small" effect="plain">
            {{ alert.level === 'critical' ? '严重' : alert.level === 'warning' ? '警告' : '正常' }}
          </el-tag>
          <div class="alert-content">
            <span class="alert-name">{{ alert.name }}</span>
            <span class="alert-change" v-if="alert.message">{{ alert.message }}</span>
            <span class="alert-numbers" v-else>
              本月 {{ alert.this_month }} / 上月 {{ alert.last_month }}
            </span>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, WarningFilled } from '@element-plus/icons-vue';
import endpointUsageApi from '../../api/endpointUsage';

export default {
  name: 'EndpointUsageAnalytics',
  components: { Refresh, WarningFilled },
  setup() {
    const trendDays = ref(14);
    const trendChartRef = ref(null);
    const endpoints = ref([]);
    const alertsData = reactive({ alerts: [], critical_count: 0, warning_count: 0 });
    const latencyData = ref({});
    const errorData = ref({});
    const errorDetailData = ref({});

    const totalCallsToday = computed(() => {
      return endpoints.value.reduce((sum, ep) => sum + (ep.today_quantity || 0), 0);
    });

    const totalCallsMonth = computed(() => {
      return endpoints.value.reduce((sum, ep) => sum + (ep.this_month_quantity || 0), 0);
    });

    const overallErrorRate = computed(() => {
      const errors = Object.values(errorData.value);
      if (!errors.length) return 0;
      const total = errors.reduce((s, e) => s + (e.total_requests || 0), 0);
      const errs = errors.reduce((s, e) => s + (e.error_count || 0), 0);
      return total > 0 ? Number(((errs / total) * 100).toFixed(2)) : 0;
    });

    const hasErrorDetail = computed(() => {
      return Object.values(errorDetailData.value).some(errors => errors.length > 0);
    });

    const latencyTableData = computed(() => {
      return endpoints.value.map(ep => {
        const lat = latencyData.value[ep.metric_key] || {};
        return {
          name: ep.name,
          color: ep.color,
          p50: lat.p50 ?? '-',
          p90: lat.p90 ?? '-',
          p99: lat.p99 ?? '-',
          avg: lat.avg ?? '-',
          sample_count: lat.sample_count ?? 0,
        };
      });
    });

    const errorTableData = computed(() => {
      return endpoints.value.map(ep => {
        const err = errorData.value[ep.metric_key] || {};
        return {
          name: ep.name,
          color: ep.color,
          total_requests: err.total_requests ?? 0,
          error_count: err.error_count ?? 0,
          error_rate: err.error_rate ?? 0,
        };
      });
    });

    function getEndpointName(metricKey) {
      const ep = endpoints.value.find(e => e.metric_key === metricKey);
      return ep?.name || metricKey;
    }

    async function fetchDashboard() {
      try {
        const response = await endpointUsageApi.dashboard();
        const data = response.data;
        if (!data) return;

        endpoints.value = Object.values(data.overview || {});
        latencyData.value = data.latency || {};
        errorData.value = data.errors || {};
        errorDetailData.value = data.error_detail || {};
        Object.assign(alertsData, data.alerts || { alerts: [], critical_count: 0, warning_count: 0 });

        // 渲染趋势图
        if (data.trend) {
          await nextTick();
          renderTrendChart(data.trend, data.endpoints);
        }
      } catch (err) {
        console.error('Failed to fetch dashboard:', err);
        ElMessage.error('获取用量数据失败');
      }
    }

    async function fetchTrend() {
      try {
        const response = await endpointUsageApi.trend({ days: trendDays.value });
        const data = response.data;
        if (data?.trend) {
          await nextTick();
          renderTrendChart(data.trend, data.endpoints);
        }
      } catch (err) {
        console.error('Failed to fetch trend:', err);
      }
    }

    function renderTrendChart(trend, endpointDefs) {
      if (!trendChartRef.value) return;

      // 使用简单的 ASCII 趋势图（若需要更丰富的图表，可引入 ECharts/Chart.js）
      const labels = trend.map(t => t.date.slice(5));
      const datasets = endpointDefs ? Object.entries(endpointDefs).map(([key, info]) => ({
        label: info.name,
        data: trend.map(t => t[key] || 0),
        color: info.color || '#409eff',
      })) : [];

      // 渲染为简易 HTML 表格/条形图
      const container = trendChartRef.value;
      if (datasets.length === 0) {
        container.innerHTML = '<div class="empty-state">暂无趋势数据</div>';
        return;
      }

      let html = '<div class="trend-table"><table><thead><tr><th>日期</th>';
      datasets.forEach(d => {
        html += `<th style="color:${d.color}">${d.label}</th>`;
      });
      html += '</tr></thead><tbody>';

      // 显示最近 14 条（或全部）
      const showCount = Math.min(labels.length, 14);
      const startIdx = labels.length - showCount;

      for (let i = startIdx; i < labels.length; i++) {
        html += `<tr><td class="trend-date">${labels[i]}</td>`;
        datasets.forEach(d => {
          const val = d.data[i] || 0;
          const maxVal = Math.max(...d.data, 1);
          const barWidth = Math.max((val / maxVal) * 100, 1);
          html += `<td><div class="trend-bar-wrapper"><div class="trend-bar" style="width:${barWidth}%;background:${d.color}"></div><span class="trend-val">${val}</span></div></td>`;
        });
        html += '</tr>';
      }

      html += '</tbody></table></div>';
      container.innerHTML = html;
    }

    async function refreshData() {
      await fetchDashboard();
    }

    onMounted(() => {
      fetchDashboard();
    });

    return {
      trendDays,
      trendChartRef,
      endpoints,
      alertsData,
      latencyData,
      errorData,
      errorDetailData,
      totalCallsToday,
      totalCallsMonth,
      overallErrorRate,
      latencyTableData,
      errorTableData,
      hasErrorDetail,
      getEndpointName,
      fetchTrend,
      refreshData,
    };
  },
};
</script>

<style scoped>
.endpoint-usage {
  padding: 20px;
}

.mb-4 {
  margin-bottom: 16px;
}

.ml-2 {
  margin-left: 8px;
}

.mr-1 {
  margin-right: 4px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-left h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-subtitle {
  font-size: 13px;
  color: #909399;
  margin-left: 12px;
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
  font-size: 14px;
}

.alert-banner.critical {
  background: #fef0f0;
  color: #f56c6c;
  border: 1px solid #fbc4c4;
}

.alert-banner.warning {
  background: #fdf6ec;
  color: #e6a23c;
  border: 1px solid #f5dab1;
}

.stat-box {
  text-align: center;
  padding: 8px 0;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
}

.stat-value.success {
  color: #67c23a;
}

.stat-value.danger {
  color: #f56c6c;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.endpoint-card {
  cursor: default;
}

.endpoint-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.endpoint-method {
  font-weight: 700;
  font-size: 13px;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

.endpoint-name {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.endpoint-path {
  font-size: 12px;
  color: #909399;
  font-family: 'SF Mono', 'Fira Code', monospace;
  margin-bottom: 4px;
}

.endpoint-metrics {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.metric-label {
  color: #909399;
}

.metric-value {
  color: #303133;
  font-weight: 600;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

.metric-value.up {
  color: #f56c6c;
}

.metric-value.down {
  color: #67c23a;
}

.trend-container {
  min-height: 200px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.empty-state {
  text-align: center;
  padding: 24px;
  color: #c0c4cc;
  font-size: 14px;
}

.error-detail-title {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 8px;
}

.error-metric-label {
  font-size: 12px;
  color: #909399;
  padding: 4px 0;
  font-weight: 500;
}

.error-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 13px;
}

.error-msg {
  color: #606266;
  flex: 1;
}

.error-count {
  color: #909399;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

.alert-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;
}

.alert-item:last-child {
  border-bottom: none;
}

.alert-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.alert-name {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
}

.alert-change,
.alert-numbers {
  font-size: 12px;
  color: #909399;
}

/* 趋势图表格 */
.trend-table table {
  width: 100%;
  border-collapse: collapse;
}

.trend-table th {
  text-align: left;
  padding: 6px 8px;
  font-size: 12px;
  font-weight: 600;
  border-bottom: 2px solid #ebeef5;
}

.trend-table td {
  padding: 3px 8px;
  font-size: 12px;
  border-bottom: 1px solid #f5f7fa;
}

.trend-date {
  color: #909399;
  font-family: 'SF Mono', 'Fira Code', monospace;
  white-space: nowrap;
}

.trend-bar-wrapper {
  display: flex;
  align-items: center;
  gap: 6px;
}

.trend-bar {
  height: 14px;
  border-radius: 3px;
  min-width: 2px;
  transition: width 0.3s ease;
}

.trend-val {
  font-family: 'SF Mono', 'Fira Code', monospace;
  color: #606266;
  font-size: 11px;
}
</style>
