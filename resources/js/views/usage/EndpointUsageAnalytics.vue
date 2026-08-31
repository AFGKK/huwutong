<template>
  <div class="endpoint-usage">
    <div class="page-header">
      <div class="header-left">
        <h2>{{ t('endpoint_usage_page.title') }}</h2>
        <span class="header-subtitle">{{ t('endpoint_usage_page.subtitle') }}</span>
      </div>
      <div class="header-right">
        <el-radio-group v-model="trendDays" size="small" @change="fetchTrend">
          <el-radio-button :value="7">{{ t('endpoint_usage_page.days_n', { n: 7 }) }}</el-radio-button>
          <el-radio-button :value="14">{{ t('endpoint_usage_page.days_n', { n: 14 }) }}</el-radio-button>
          <el-radio-button :value="30">{{ t('endpoint_usage_page.days_n', { n: 30 }) }}</el-radio-button>
        </el-radio-group>
        <el-button @click="refreshData" class="ml-2">
          <el-icon><Refresh /></el-icon> {{ t('endpoint_usage_page.refresh') }}
        </el-button>
      </div>
    </div>

    <div v-if="alertsData.critical_count > 0" class="alert-banner critical">
      <el-icon><WarningFilled /></el-icon>
      <span>{{ t('endpoint_usage_page.banner_critical', { n: alertsData.critical_count }) }}</span>
    </div>
    <div v-else-if="alertsData.warning_count > 0" class="alert-banner warning">
      <el-icon><WarningFilled /></el-icon>
      <span>{{ t('endpoint_usage_page.banner_warning', { n: alertsData.warning_count }) }}</span>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ totalCallsToday }}</div>
            <div class="stat-label">{{ t('endpoint_usage_page.stats.calls_today') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ totalCallsMonth }}</div>
            <div class="stat-label">{{ t('endpoint_usage_page.stats.calls_month') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">4</div>
            <div class="stat-label">{{ t('endpoint_usage_page.stats.endpoints') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value" :class="overallErrorRate > 5 ? 'danger' : 'success'">{{ overallErrorRate }}%</div>
            <div class="stat-label">{{ t('endpoint_usage_page.stats.error_rate') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6" v-for="ep in endpoints" :key="ep.metric_key" style="margin-bottom: 16px">
        <el-card shadow="hover" class="endpoint-card" :style="{ borderTop: `3px solid ${ep.color || '#0f172a'}` }">
          <div class="endpoint-header">
            <span class="endpoint-method" :style="{ color: ep.color }">{{ ep.method }}</span>
            <span class="endpoint-name">{{ ep.name }}</span>
          </div>
          <div class="endpoint-path">{{ ep.path }}</div>
          <el-divider />
          <div class="endpoint-metrics">
            <div class="metric-row">
              <span class="metric-label">{{ t('endpoint_usage_page.today_calls') }}</span>
              <span class="metric-value">{{ ep.today_quantity }}</span>
            </div>
            <div class="metric-row">
              <span class="metric-label">{{ t('endpoint_usage_page.month_calls') }}</span>
              <span class="metric-value">{{ ep.this_month_quantity }}</span>
            </div>
            <div class="metric-row" v-if="ep.monthly_change_percent !== 0">
              <span class="metric-label">{{ t('endpoint_usage_page.mom') }}</span>
              <span class="metric-value" :class="ep.monthly_change_percent > 0 ? 'up' : 'down'">
                {{ ep.monthly_change_percent > 0 ? '+' : '' }}{{ ep.monthly_change_percent }}%
              </span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mb-4">
      <template #header>
        <div class="card-header">
          <span>{{ t('endpoint_usage_page.trend_title') }}</span>
        </div>
      </template>
      <div class="trend-container" ref="trendChartRef"></div>
    </el-card>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header>
            <span>{{ t('endpoint_usage_page.latency_title') }}</span>
          </template>
          <el-table :data="latencyTableData" stripe size="small">
            <el-table-column :label="t('endpoint_usage_page.cols.endpoint')" min-width="120">
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
            <el-table-column :label="t('endpoint_usage_page.cols.avg')" width="80" align="right">
              <template #default="{ row }">{{ row.avg }}ms</template>
            </el-table-column>
            <el-table-column :label="t('endpoint_usage_page.cols.samples')" width="70" align="right">
              <template #default="{ row }">{{ row.sample_count }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card shadow="never">
          <template #header>
            <span>{{ t('endpoint_usage_page.error_title') }}</span>
          </template>
          <el-table :data="errorTableData" stripe size="small">
            <el-table-column :label="t('endpoint_usage_page.cols.endpoint')" min-width="120">
              <template #default="{ row }">
                <span :style="{ color: row.color }">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('endpoint_usage_page.cols.requests')" width="80" align="right">
              <template #default="{ row }">{{ row.total_requests }}</template>
            </el-table-column>
            <el-table-column :label="t('endpoint_usage_page.cols.errors')" width="80" align="right">
              <template #default="{ row }">{{ row.error_count }}</template>
            </el-table-column>
            <el-table-column :label="t('endpoint_usage_page.cols.error_rate')" width="90" align="right">
              <template #default="{ row }">
                <el-tag :type="row.error_rate > 5 ? 'danger' : row.error_rate > 1 ? 'warning' : 'success'" size="small">
                  {{ row.error_rate }}%
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <el-divider />
          <div class="error-detail-title">{{ t('endpoint_usage_page.error_codes') }}</div>
          <div v-if="!hasErrorDetail" class="empty-state">{{ t('endpoint_usage_page.no_errors') }}</div>
          <div v-else v-for="(errors, metricKey) in errorDetailData" :key="metricKey">
            <div class="error-metric-label" v-if="errors.length">{{ getEndpointName(metricKey) }}</div>
            <div v-for="err in errors" :key="err.error_code" class="error-item">
              <el-tag size="small" type="danger">{{ err.error_code }}</el-tag>
              <span class="error-msg">{{ err.error_message }}</span>
              <span class="error-count">{{ t('endpoint_usage_page.times_n', { n: err.count }) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>{{ t('endpoint_usage_page.alerts_title') }}</span>
          <div>
            <el-tag v-if="alertsData.critical_count" type="danger" size="small" class="mr-1">
              {{ t('endpoint_usage_page.levels.critical') }} {{ alertsData.critical_count }}
            </el-tag>
            <el-tag v-if="alertsData.warning_count" type="warning" size="small">
              {{ t('endpoint_usage_page.levels.warning') }} {{ alertsData.warning_count }}
            </el-tag>
          </div>
        </div>
      </template>
      <div v-if="!alertsData.alerts?.length" class="empty-state">{{ t('endpoint_usage_page.no_alerts') }}</div>
      <div v-else>
        <div v-for="alert in alertsData.alerts" :key="alert.metric_key" class="alert-item">
          <el-tag :type="alert.level === 'critical' ? 'danger' : alert.level === 'warning' ? 'warning' : 'info'" size="small" effect="plain">
            {{ alertLevelLabel(alert.level) }}
          </el-tag>
          <div class="alert-content">
            <span class="alert-name">{{ alert.name }}</span>
            <span class="alert-change" v-if="alert.message">{{ alert.message }}</span>
            <span class="alert-numbers" v-else>
              {{ t('endpoint_usage_page.alert_compare', { this_month: alert.this_month, last_month: alert.last_month }) }}
            </span>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Refresh, WarningFilled } from '@element-plus/icons-vue'
import endpointUsageApi from '../../api/endpointUsage'

const { t } = useI18n()

const trendDays = ref(14)
const trendChartRef = ref(null)
const endpoints = ref([])
const alertsData = reactive({ alerts: [], critical_count: 0, warning_count: 0 })
const latencyData = ref({})
const errorData = ref({})
const errorDetailData = ref({})

const totalCallsToday = computed(() => {
  return endpoints.value.reduce((sum, ep) => sum + (ep.today_quantity || 0), 0)
})

const totalCallsMonth = computed(() => {
  return endpoints.value.reduce((sum, ep) => sum + (ep.this_month_quantity || 0), 0)
})

const overallErrorRate = computed(() => {
  const errors = Object.values(errorData.value)
  if (!errors.length) return 0
  const total = errors.reduce((s, e) => s + (e.total_requests || 0), 0)
  const errs = errors.reduce((s, e) => s + (e.error_count || 0), 0)
  return total > 0 ? Number(((errs / total) * 100).toFixed(2)) : 0
})

const hasErrorDetail = computed(() => {
  return Object.values(errorDetailData.value).some(errors => errors.length > 0)
})

const latencyTableData = computed(() => {
  return endpoints.value.map(ep => {
    const lat = latencyData.value[ep.metric_key] || {}
    return {
      name: ep.name,
      color: ep.color,
      p50: lat.p50 ?? '-',
      p90: lat.p90 ?? '-',
      p99: lat.p99 ?? '-',
      avg: lat.avg ?? '-',
      sample_count: lat.sample_count ?? 0,
    }
  })
})

const errorTableData = computed(() => {
  return endpoints.value.map(ep => {
    const err = errorData.value[ep.metric_key] || {}
    return {
      name: ep.name,
      color: ep.color,
      total_requests: err.total_requests ?? 0,
      error_count: err.error_count ?? 0,
      error_rate: err.error_rate ?? 0,
    }
  })
})

function alertLevelLabel(level) {
  if (level === 'critical') return t('endpoint_usage_page.levels.critical')
  if (level === 'warning') return t('endpoint_usage_page.levels.warning')
  return t('endpoint_usage_page.levels.normal')
}

function getEndpointName(metricKey) {
  const ep = endpoints.value.find(e => e.metric_key === metricKey)
  return ep?.name || metricKey
}

async function fetchDashboard() {
  try {
    const response = await endpointUsageApi.dashboard()
    const data = response.data
    if (!data) return

    endpoints.value = Object.values(data.overview || {})
    latencyData.value = data.latency || {}
    errorData.value = data.errors || {}
    errorDetailData.value = data.error_detail || {}
    Object.assign(alertsData, data.alerts || { alerts: [], critical_count: 0, warning_count: 0 })

    if (data.trend) {
      await nextTick()
      renderTrendChart(data.trend, data.endpoints)
    }
  } catch (err) {
    console.error('Failed to fetch dashboard:', err)
    ElMessage.error(t('endpoint_usage_page.messages.load_failed'))
  }
}

async function fetchTrend() {
  try {
    const response = await endpointUsageApi.trend({ days: trendDays.value })
    const data = response.data
    if (data?.trend) {
      await nextTick()
      renderTrendChart(data.trend, data.endpoints)
    }
  } catch (err) {
    console.error('Failed to fetch trend:', err)
  }
}

function renderTrendChart(trend, endpointDefs) {
  if (!trendChartRef.value) return

  const labels = trend.map(row => row.date.slice(5))
  const datasets = endpointDefs ? Object.entries(endpointDefs).map(([key, info]) => ({
    label: info.name,
    data: trend.map(row => row[key] || 0),
    color: info.color || '#0f172a',
  })) : []

  const container = trendChartRef.value
  if (datasets.length === 0) {
    container.innerHTML = `<div class="empty-state">${t('endpoint_usage_page.no_trend')}</div>`
    return
  }

  let html = `<div class="trend-table"><table><thead><tr><th>${t('endpoint_usage_page.cols.date')}</th>`
  datasets.forEach(d => {
    html += `<th style="color:${d.color}">${d.label}</th>`
  })
  html += '</tr></thead><tbody>'

  const showCount = Math.min(labels.length, 14)
  const startIdx = labels.length - showCount

  for (let i = startIdx; i < labels.length; i++) {
    html += `<tr><td class="trend-date">${labels[i]}</td>`
    datasets.forEach(d => {
      const val = d.data[i] || 0
      const maxVal = Math.max(...d.data, 1)
      const barWidth = Math.max((val / maxVal) * 100, 1)
      html += `<td><div class="trend-bar-wrapper"><div class="trend-bar" style="width:${barWidth}%;background:${d.color}"></div><span class="trend-val">${val}</span></div></td>`
    })
    html += '</tr>'
  }

  html += '</tbody></table></div>'
  container.innerHTML = html
}

async function refreshData() {
  await fetchDashboard()
}

onMounted(() => {
  fetchDashboard()
})
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
