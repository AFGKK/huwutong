<template>
  <div class="audit-visualization">
    <h2 class="mb-4">{{ t(`${P}.title`) }}</h2>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.today_logs }}</div><div class="stat-label">{{ t(`${P}.stats.today_logs`) }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.month_logs }}</div><div class="stat-label">{{ t(`${P}.stats.month_logs`) }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div :class="['stat-value', stats.month_change_pct >= 0 ? 'text-danger' : 'text-success']">
              {{ stats.month_change_pct >= 0 ? '+' : '' }}{{ stats.month_change_pct }}%
            </div>
            <div class="stat-label">{{ t(`${P}.stats.month_change`) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div :class="['stat-value', stats.open_anomalies > 0 ? 'text-danger' : 'text-success']">{{ stats.open_anomalies }}</div><div class="stat-label">{{ t(`${P}.stats.open_anomalies`) }}</div></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center justify-between">
              <span>{{ t(`${P}.charts.trend`) }}</span>
              <div>
                <el-select v-model="trendGranularity" size="small" style="width:100px" @change="loadTrend" class="mr-2">
                  <el-option :label="t(`${P}.granularity.daily`)" value="daily" />
                  <el-option :label="t(`${P}.granularity.weekly`)" value="weekly" />
                  <el-option :label="t(`${P}.granularity.monthly`)" value="monthly" />
                </el-select>
                <el-date-picker
                  v-model="trendRange"
                  type="daterange"
                  :range-separator="t(`${P}.date.sep`)"
                  :start-placeholder="t(`${P}.date.start`)"
                  :end-placeholder="t(`${P}.date.end`)"
                  size="small"
                  value-format="YYYY-MM-DD"
                  @change="loadTrend"
                />
              </div>
            </div>
          </template>
          <v-chart :option="trendOption" style="height:320px" autoresize />
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>{{ t(`${P}.charts.type_dist`) }}</template>
          <v-chart :option="typeDistOption" style="height:320px" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>{{ t(`${P}.charts.hourly`) }}</template>
          <v-chart :option="hourlyOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>{{ t(`${P}.charts.top_actions`) }}</template>
          <v-chart :option="topActionsOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>{{ t(`${P}.charts.top_users`) }}</template>
          <v-chart :option="topUsersOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="t(`${P}.tabs.anomalies`)" name="anomalies">
        <div class="mb-3">
          <el-button type="primary" size="small" @click="runAnomalyDetection" :loading="detecting">
            {{ t(`${P}.actions.run_detect`) }}
          </el-button>
          <el-select v-model="anomalyFilter.severity" :placeholder="t(`${P}.filters.severity`)" clearable size="small" style="width:120px"
            @change="loadAnomalies" class="ml-2">
            <el-option :label="t(`${P}.filters.all`)" value="" />
            <el-option :label="t(`${P}.severity.info`)" value="info" />
            <el-option :label="t(`${P}.severity.warning`)" value="warning" />
            <el-option :label="t(`${P}.severity.critical`)" value="critical" />
          </el-select>
          <el-select v-model="anomalyFilter.status" :placeholder="t(`${P}.filters.status`)" clearable size="small" style="width:120px"
            @change="loadAnomalies" class="ml-2">
            <el-option :label="t(`${P}.filters.all`)" value="" />
            <el-option :label="t(`${P}.status.open`)" value="open" />
            <el-option :label="t(`${P}.status.acknowledged`)" value="acknowledged" />
            <el-option :label="t(`${P}.status.resolved`)" value="resolved" />
            <el-option :label="t(`${P}.status.dismissed`)" value="dismissed" />
          </el-select>
        </div>

        <el-table :data="anomalies" stripe v-loading="anomalyLoading">
          <el-table-column :label="t(`${P}.cols.type`)" prop="anomaly_type" width="110">
            <template #default="{ row }">
              <el-tag :type="typeTag(row.anomaly_type)" size="small">{{ anomalyTypeLabel(row.anomaly_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.severity`)" prop="severity" width="90">
            <template #default="{ row }">
              <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.metric`)" prop="metric" width="150" />
          <el-table-column :label="t(`${P}.cols.baseline`)" prop="baseline_value" width="90" />
          <el-table-column :label="t(`${P}.cols.actual`)" prop="actual_value" width="90" />
          <el-table-column :label="t(`${P}.cols.deviation`)" prop="deviation" width="100">
            <template #default="{ row }">
              <span :class="row.deviation > 0 ? 'text-danger' : 'text-success'">{{ row.deviation >= 0 ? '+' : '' }}{{ row.deviation }}%</span>
            </template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.description`)" prop="description" min-width="260" show-overflow-tooltip />
          <el-table-column :label="t(`${P}.cols.status`)" prop="status" width="90">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.detected_at`)" prop="detected_at" width="160" />
          <el-table-column :label="t(`${P}.cols.actions`)" width="160" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'open'" size="small" @click="updateStatus(row, 'acknowledged')">{{ t(`${P}.actions.acknowledge`) }}</el-button>
              <el-button v-if="['open', 'acknowledged'].includes(row.status)" size="small" type="success"
                @click="updateStatus(row, 'resolved')">{{ t(`${P}.actions.resolve`) }}</el-button>
              <el-button v-if="row.status === 'open'" size="small" type="info"
                @click="updateStatus(row, 'dismissed')">{{ t(`${P}.actions.dismiss`) }}</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="flex justify-center mt-3" v-if="anomalyTotal > anomalyPerPage">
          <el-pagination background layout="prev, pager, next" v-model:current-page="anomalyPage"
            :page-size="anomalyPerPage" :total="anomalyTotal" @current-change="loadAnomalies" />
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, PieChart, BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent, ToolboxComponent } from 'echarts/components'
import {
  getAuditDashboard, getAuditTrend, getTopActions, getTopUsers, getTopIps,
  getHourlyDistribution, getTypeDistribution, getCategoryDistribution,
  detectAnomalies, getAnomalies, updateAnomalyStatus,
} from '../../api/auditVisualization'

use([CanvasRenderer, LineChart, PieChart, BarChart, GridComponent, TooltipComponent, LegendComponent, ToolboxComponent])

const P = 'audit_visualization_page'
const { t } = useI18n()

const stats = ref({ type_distribution: {}, recent_anomalies: [] })
const trendRange = ref([new Date(Date.now() - 90 * 86400000), new Date()])
const trendGranularity = ref('daily')
const trendRaw = ref({ summary: [], by_type: [] })
const typeDistData = ref([])
const hourlyData = ref([])
const topActionsData = ref([])
const topUsersData = ref([])

const activeTab = ref('anomalies')
const anomalies = ref([])
const anomalyLoading = ref(false)
const detecting = ref(false)
const anomalyPage = ref(1)
const anomalyPerPage = 50
const anomalyTotal = ref(0)
const anomalyFilter = reactive({ severity: '', status: '' })

const trendOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  legend: {
    data: [
      t(`${P}.types.total`),
      t(`${P}.types.audit`),
      t(`${P}.types.security`),
      t(`${P}.types.error`),
      t(`${P}.types.system`),
    ],
    top: 0,
  },
  grid: { left: 40, right: 20, bottom: 30, top: 40 },
  xAxis: { type: 'category', data: trendRaw.value.summary.map(s => s.label), axisLabel: { rotate: 30 } },
  yAxis: { type: 'value' },
  series: [
    { name: t(`${P}.types.total`), type: 'line', data: trendRaw.value.summary.map(s => s.count), smooth: true, lineStyle: { width: 2 } },
    { name: t(`${P}.types.audit`), type: 'bar', data: trendRaw.value.by_type.map(s => s.audit), stack: 'types' },
    { name: t(`${P}.types.security`), type: 'bar', data: trendRaw.value.by_type.map(s => s.security), stack: 'types' },
    { name: t(`${P}.types.error`), type: 'bar', data: trendRaw.value.by_type.map(s => s.error), stack: 'types' },
    { name: t(`${P}.types.system`), type: 'bar', data: trendRaw.value.by_type.map(s => s.system), stack: 'types' },
  ],
}))

const typeDistOption = computed(() => ({
  tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
  series: [{
    type: 'pie', radius: ['40%', '70%'], center: ['50%', '55%'],
    data: typeDistData.value.map(d => ({ name: logTypeLabel(d.type), value: d.count })),
    label: { show: true, formatter: '{b}\n{d}%' },
    emphasis: { label: { show: true, fontSize: 14 } },
  }],
}))

const hourlyOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'category', data: hourlyData.value.map(d => `${d.hour}:00`) },
  yAxis: { type: 'value' },
  series: [{ type: 'bar', data: hourlyData.value.map(d => d.count), itemStyle: { color: '#0f172a' } }],
  grid: { left: 40, right: 10, bottom: 30 },
}))

const topActionsOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'value' },
  yAxis: { type: 'category', data: topActionsData.value.map(d => truncate(d.action, 25)).reverse(), axisLabel: { fontSize: 11 } },
  series: [{ type: 'bar', data: topActionsData.value.map(d => d.count).reverse(), itemStyle: { color: '#67c23a' } }],
  grid: { left: 120, right: 20, top: 10, bottom: 10 },
}))

const topUsersOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'value' },
  yAxis: { type: 'category', data: topUsersData.value.map(d => d.user_name || 'System').reverse(), axisLabel: { fontSize: 11 } },
  series: [{ type: 'bar', data: topUsersData.value.map(d => d.count).reverse(), itemStyle: { color: '#e6a23c' } }],
  grid: { left: 100, right: 20, top: 10, bottom: 10 },
}))

function logTypeLabel(key) {
  const map = {
    audit: t(`${P}.types.audit`),
    security: t(`${P}.types.security`),
    error: t(`${P}.types.error`),
    system: t(`${P}.types.system`),
    login_audit: t(`${P}.types.login_audit`),
    api_key_audit: t(`${P}.types.api_key_audit`),
  }
  return map[key] || key
}
function anomalyTypeLabel(key) {
  const map = {
    spike: t(`${P}.anomaly_types.spike`),
    drop: t(`${P}.anomaly_types.drop`),
    pattern_change: t(`${P}.anomaly_types.pattern_change`),
    unusual_hours: t(`${P}.anomaly_types.unusual_hours`),
    geo_anomaly: t(`${P}.anomaly_types.geo_anomaly`),
  }
  return map[key] || key
}
function typeTag(key) {
  const map = { spike: 'danger', drop: 'warning', pattern_change: 'info', unusual_hours: 'warning', geo_anomaly: 'danger' }
  return map[key] || ''
}
function severityTag(s) {
  return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info'
}
function severityLabel(s) {
  return ({
    info: t(`${P}.severity.info`),
    warning: t(`${P}.severity.warning`),
    critical: t(`${P}.severity.critical`),
  })[s] || s
}
function statusTag(s) {
  return { open: 'danger', acknowledged: 'warning', resolved: 'success', dismissed: 'info' }[s] || 'info'
}
function statusLabel(s) {
  return ({
    open: t(`${P}.status.open`),
    acknowledged: t(`${P}.status.acknowledged`),
    resolved: t(`${P}.status.resolved`),
    dismissed: t(`${P}.status.dismissed`),
  })[s] || s
}
function truncate(str, len) {
  return str?.length > len ? str.substr(0, len) + '...' : str
}

function formatDate(d) {
  const dt = new Date(d)
  return dt.toISOString().split('T')[0]
}

async function loadDashboard() {
  try {
    const { data } = await getAuditDashboard()
    stats.value = data
  } catch (e) {
    console.error('Dashboard load failed', e)
  }
}

async function loadTrend() {
  if (!trendRange.value?.length) return
  try {
    const params = {
      start_date: formatDate(trendRange.value[0]),
      end_date: formatDate(trendRange.value[1]),
      granularity: trendGranularity.value,
    }
    const { data } = await getAuditTrend(params)
    trendRaw.value = data
  } catch (e) {
    console.error('Trend load failed', e)
  }
}

async function loadDistributions() {
  try {
    const end = formatDate(new Date())
    const start = formatDate(new Date(Date.now() - 90 * 86400000))
    const params = { start_date: start, end_date: end }

    const [typeRes, hourlyRes, actionsRes, usersRes] = await Promise.all([
      getTypeDistribution(params),
      getHourlyDistribution(params),
      getTopActions(params),
      getTopUsers(params),
    ])
    typeDistData.value = typeRes.data || []
    hourlyData.value = hourlyRes.data || []
    topActionsData.value = actionsRes.data || []
    topUsersData.value = usersRes.data || []
  } catch (e) {
    console.error('Distributions load failed', e)
  }
}

async function loadAnomalies() {
  anomalyLoading.value = true
  try {
    const params = {
      page: anomalyPage.value,
      per_page: anomalyPerPage,
      ...anomalyFilter,
    }
    const { data } = await getAnomalies(params)
    const list = Array.isArray(data) ? data : data?.data || []
    anomalies.value = list
    anomalyTotal.value = data?.total || list.length
  } catch (e) {
    ElMessage.error(t(`${P}.messages.load_anomalies_failed`))
  } finally {
    anomalyLoading.value = false
  }
}

async function runAnomalyDetection() {
  detecting.value = true
  try {
    const { data } = await detectAnomalies()
    ElMessage.success(t(`${P}.messages.detect_done`, { n: data?.length || 0 }))
    await loadAnomalies()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.detect_failed`))
  } finally {
    detecting.value = false
  }
}

async function updateStatus(row, status) {
  try {
    await updateAnomalyStatus(row.id, status)
    ElMessage.success(t(`${P}.messages.status_updated`))
    await loadAnomalies()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.action_failed`))
  }
}

onMounted(() => {
  loadDashboard()
  loadTrend()
  loadDistributions()
  loadAnomalies()
})
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.ml-2 { margin-left: 8px; }
.mr-2 { margin-right: 8px; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
.justify-between { justify-content: space-between; }
.mt-3 { margin-top: 12px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
</style>
