<template>
  <div class="webhook-monitor">
    <h2 class="mb-4">{{ $t('webhook_monitor_page.title') }}</h2>

    <!-- 概览统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ summary.endpoints_total }}</div>
            <div class="stat-label">{{ $t('webhook_monitor_page.endpoints_total') }}</div>
            <div class="stat-sub">
              <span class="text-success">{{ summary.active_endpoints }}</span> {{ $t('webhook_monitor_page.active') }} /
              <span class="text-warning">{{ summary.paused_endpoints }}</span> {{ $t('webhook_monitor_page.paused') }}
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-primary">{{ summary.today_total }}</div>
            <div class="stat-label">{{ $t('webhook_monitor_page.today_events') }}</div>
            <div class="stat-sub">
              <span class="text-success">{{ summary.today_delivered }}</span> {{ $t('webhook_monitor_page.success') }} /
              <span class="text-danger">{{ summary.today_failed }}</span> {{ $t('webhook_monitor_page.failed') }} /
              <span class="text-warning">{{ summary.today_pending }}</span> {{ $t('webhook_monitor_page.pending') }}
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value" :class="successRateClass(summary.today_success_rate)">{{ summary.today_success_rate }}%</div>
            <div class="stat-label">{{ $t('webhook_monitor_page.today_success_rate') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value" :class="failureRateClass(summary.hourly_failure_rate)">{{ summary.hourly_failure_rate }}%</div>
            <div class="stat-label">{{ $t('webhook_monitor_page.hourly_failure_rate') }}</div>
            <div class="stat-sub">{{ $t('webhook_monitor_page.hourly_events_summary', { total: summary.hourly_total, failed: summary.hourly_failed }) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="24">
        <el-card class="mb-4">
          <template #header><span>{{ $t('webhook_monitor_page.weekly_delivery_trend') }}</span></template>
          <div ref="trendChartRef" style="height:280px"></div>
          <div v-if="(!weeklyTrend || weeklyTrend.length === 0) && !loading" class="text-center text-gray-400 py-4">{{ $t('webhook_monitor_page.no_data') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="16">
        <el-card class="mb-4">
          <template #header><span>{{ $t('webhook_monitor_page.endpoint_health') }}</span></template>
          <el-table :data="endpointHealth" stripe v-loading="loading" size="small">
            <el-table-column :label="$t('webhook_monitor_page.col_name')" prop="name" min-width="140" />
            <el-table-column :label="$t('webhook_monitor_page.col_url')" prop="url" min-width="200" show-overflow-tooltip />
            <el-table-column :label="$t('webhook_monitor_page.col_status')" width="80">
              <template #default="{ row }">
                <el-tag :type="healthTag(row.health)" size="small">{{ healthLabel(row.health) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="$t('webhook_monitor_page.col_recent_events')" prop="recent_events" width="100" />
            <el-table-column :label="$t('webhook_monitor_page.col_recent_failures')" prop="recent_failures" width="100">
              <template #default="{ row }">
                <span :class="row.recent_failures > 0 ? 'text-danger' : ''">{{ row.recent_failures }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="$t('webhook_monitor_page.col_actions')" width="120">
              <template #default="{ row }">
                <el-button size="small" @click="showEndpointDetail(row.id)">{{ $t('webhook_monitor_page.detail') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><span>{{ $t('webhook_monitor_page.event_type_distribution') }}</span></template>
          <div ref="eventTypeChartRef" style="height:260px"></div>
          <div v-if="(!eventTypeDistribution || eventTypeDistribution.length === 0) && !loading" class="text-center text-gray-400 py-4">{{ $t('webhook_monitor_page.no_data') }}</div>
        </el-card>

        <el-card class="mb-4">
          <template #header><span>{{ $t('webhook_monitor_page.period_stats') }}</span></template>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item :label="$t('webhook_monitor_page.events_7d')">{{ weeklyStats?.total_events || 0 }}</el-descriptions-item>
            <el-descriptions-item :label="$t('webhook_monitor_page.success_rate_7d')">{{ weeklyStats?.success_rate || 100 }}%</el-descriptions-item>
            <el-descriptions-item :label="$t('webhook_monitor_page.events_30d')">{{ monthlyStats?.total_events || 0 }}</el-descriptions-item>
            <el-descriptions-item :label="$t('webhook_monitor_page.success_rate_30d')">{{ monthlyStats?.success_rate || 100 }}%</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs: 失败事件 / 延迟分布 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="$t('webhook_monitor_page.tab_failures')" name="failures">
        <el-table :data="failures" stripe v-loading="failuresLoading" size="small">
          <el-table-column :label="$t('webhook_monitor_page.col_endpoint')" prop="endpoint?.name" min-width="140" />
          <el-table-column :label="$t('webhook_monitor_page.col_event_type')" prop="event_type" width="140" />
          <el-table-column :label="$t('webhook_monitor_page.col_status')" prop="status" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'dead_letter' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="$t('webhook_monitor_page.col_attempts')" prop="attempts" width="80" />
          <el-table-column :label="$t('webhook_monitor_page.col_created_at')" prop="created_at" width="160" />
          <el-table-column :label="$t('webhook_monitor_page.col_description')" prop="description" min-width="200" show-overflow-tooltip />
        </el-table>
      </el-tab-pane>
      <el-tab-pane :label="$t('webhook_monitor_page.tab_latency')" name="latency">
        <div ref="latencyChartRef" style="height:300px"></div>
        <div v-if="(!latencyDistribution || latencyDistribution.length === 0) && !loading" class="text-center text-gray-400 py-4">{{ $t('webhook_monitor_page.no_latency_data') }}</div>
      </el-tab-pane>
    </el-tabs>

    <!-- 端点详情对话框 -->
    <EndpointDetailDialog v-model:visible="detailDialog.visible" :endpoint-id="detailDialog.id" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'
import { getWebhookMonitorOverview, getWebhookMonitorFailures, getWebhookLatencyDistribution } from '../../api/webhookMonitor'
import EndpointDetailDialog from './components/EndpointDetailDialog.vue'

const { t, locale } = useI18n()

const loading = ref(false)
const failuresLoading = ref(false)
const activeTab = ref('failures')
const summary = reactive({
  endpoints_total: 0, active_endpoints: 0, paused_endpoints: 0,
  today_total: 0, today_delivered: 0, today_failed: 0, today_pending: 0,
  today_success_rate: 100, hourly_total: 0, hourly_failed: 0, hourly_failure_rate: 0,
})
const weeklyTrend = ref([])
const endpointHealth = ref([])
const eventTypeDistribution = ref([])
const weeklyStats = ref(null)
const monthlyStats = ref(null)
const failures = ref([])
const latencyDistribution = ref([])

const trendChartRef = ref(null)
const eventTypeChartRef = ref(null)
const latencyChartRef = ref(null)

const detailDialog = reactive({ visible: false, id: null })

function successRateClass(v) { if (v >= 99) return 'text-success'; if (v >= 90) return 'text-warning'; return 'text-danger' }
function failureRateClass(v) { if (v <= 1) return 'text-success'; if (v <= 10) return 'text-warning'; return 'text-danger' }
function healthTag(h) { return { healthy: 'success', warning: 'warning', critical: 'danger', paused: 'info', inactive: 'info', idle: '' }[h] || 'info' }
function healthLabel(h) {
  const map = {
    healthy: t('webhook_monitor_page.health_healthy'),
    warning: t('webhook_monitor_page.health_warning'),
    critical: t('webhook_monitor_page.health_critical'),
    paused: t('webhook_monitor_page.health_paused'),
    inactive: t('webhook_monitor_page.health_inactive'),
    idle: t('webhook_monitor_page.health_idle'),
  }
  return map[h] || h
}

function showEndpointDetail(id) { detailDialog.id = id; detailDialog.visible = true }

async function loadOverview() {
  loading.value = true
  try {
    const { data } = await getWebhookMonitorOverview()
    Object.assign(summary, data.summary || {})
    weeklyTrend.value = data.weekly_trend || []
    endpointHealth.value = data.endpoint_health || []
    eventTypeDistribution.value = data.event_type_distribution || []
    weeklyStats.value = data.weekly_stats || null
    monthlyStats.value = data.monthly_stats || null
    await nextTick()
    renderCharts()
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function loadFailures() {
  failuresLoading.value = true
  try {
    const { data } = await getWebhookMonitorFailures()
    failures.value = Array.isArray(data) ? data : data?.data || []
  } catch (e) { console.error(e) } finally { failuresLoading.value = false }
}

async function loadLatency() {
  try {
    const { data } = await getWebhookLatencyDistribution({ days: 7 })
    latencyDistribution.value = Array.isArray(data) ? data : []
  } catch (e) { console.error(e) }
}

function renderCharts() {
  renderTrendChart()
  renderEventTypeChart()
  renderLatencyChart()
}

function renderTrendChart() {
  const el = trendChartRef.value
  if (!el) return
  const chart = echarts.init(el)
  const dates = weeklyTrend.value.map(t => t.date)
  const totalLabel = t('webhook_monitor_page.chart_total')
  const successLabel = t('webhook_monitor_page.chart_success')
  const failedLabel = t('webhook_monitor_page.chart_failed')
  chart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: [totalLabel, successLabel, failedLabel], bottom: 0 },
    grid: { left: 50, right: 20, bottom: 35, top: 10 },
    xAxis: { type: 'category', data: dates, axisLabel: { rotate: 30, fontSize: 11 } },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      { name: totalLabel, type: 'bar', data: weeklyTrend.value.map(t => t.total || 0), itemStyle: { color: '#909399' }, barMaxWidth: 20 },
      { name: successLabel, type: 'line', data: weeklyTrend.value.map(t => t.delivered || 0), smooth: true, lineStyle: { color: '#67c23a', width: 2 } },
      { name: failedLabel, type: 'line', data: weeklyTrend.value.map(t => t.failed || 0), smooth: true, lineStyle: { color: '#f56c6c', width: 2 } },
    ],
  })
}

function renderEventTypeChart() {
  const el = eventTypeChartRef.value
  if (!el || eventTypeDistribution.value.length === 0) return
  const chart = echarts.init(el)
  chart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    series: [{
      type: 'pie', radius: ['35%', '60%'],
      data: eventTypeDistribution.value.map(t => ({ name: t.event_type, value: t.total })),
      label: { show: false },
      emphasis: { label: { show: true, fontSize: 12, fontWeight: 'bold' } },
    }],
  })
}

function renderLatencyChart() {
  const el = latencyChartRef.value
  if (!el || latencyDistribution.value.length === 0) return
  const chart = echarts.init(el)
  chart.setOption({
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: 80, right: 20, bottom: 30, top: 10 },
    xAxis: { type: 'category', data: latencyDistribution.value.map(t => t.bucket) },
    yAxis: { type: 'value', name: t('webhook_monitor_page.quantity_axis') },
    series: [{
      type: 'bar', data: latencyDistribution.value.map(t => t.count),
      itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#0f172a' }, { offset: 1, color: '#94a3b8' }]) },
      barMaxWidth: 40,
    }],
  })
}

watch(activeTab, (tab) => {
  if (tab === 'latency') nextTick(() => renderLatencyChart())
})

watch(locale, renderCharts)

onMounted(() => {
  loadOverview()
  loadFailures()
  loadLatency()
})
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.stat-sub { font-size: 12px; color: #c0c4cc; margin-top: 2px; }
.text-success { color: #67c23a !important; }
.text-danger { color: #f56c6c !important; }
.text-warning { color: #e6a23c !important; }
.text-primary { color: #0f172a !important; }
</style>
