<template>
  <div class="webhook-monitor">
    <h2 class="mb-4">Webhook 监控中心</h2>

    <!-- 概览统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ summary.endpoints_total }}</div>
            <div class="stat-label">端点总数</div>
            <div class="stat-sub">
              <span class="text-success">{{ summary.active_endpoints }}</span> 活跃 /
              <span class="text-warning">{{ summary.paused_endpoints }}</span> 暂停
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-primary">{{ summary.today_total }}</div>
            <div class="stat-label">今日事件</div>
            <div class="stat-sub">
              <span class="text-success">{{ summary.today_delivered }}</span> 成功 /
              <span class="text-danger">{{ summary.today_failed }}</span> 失败 /
              <span class="text-warning">{{ summary.today_pending }}</span> 待处理
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value" :class="successRateClass(summary.today_success_rate)">{{ summary.today_success_rate }}%</div>
            <div class="stat-label">今日成功率</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value" :class="failureRateClass(summary.hourly_failure_rate)">{{ summary.hourly_failure_rate }}%</div>
            <div class="stat-label">最近1小时失败率</div>
            <div class="stat-sub">共 {{ summary.hourly_total }} 个事件，{{ summary.hourly_failed }} 个失败</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="24">
        <el-card class="mb-4">
          <template #header><span>近7天投递趋势</span></template>
          <div ref="trendChartRef" style="height:280px"></div>
          <div v-if="(!weeklyTrend || weeklyTrend.length === 0) && !loading" class="text-center text-gray-400 py-4">暂无数据</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="16">
        <el-card class="mb-4">
          <template #header><span>端点健康状态</span></template>
          <el-table :data="endpointHealth" stripe v-loading="loading" size="small">
            <el-table-column label="端点名称" prop="name" min-width="140" />
            <el-table-column label="URL" prop="url" min-width="200" show-overflow-tooltip />
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="healthTag(row.health)" size="small">{{ healthLabel(row.health) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="近1小时事件" prop="recent_events" width="100" />
            <el-table-column label="近1小时失败" prop="recent_failures" width="100">
              <template #default="{ row }">
                <span :class="row.recent_failures > 0 ? 'text-danger' : ''">{{ row.recent_failures }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button size="small" @click="showEndpointDetail(row.id)">详情</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><span>事件类型分布</span></template>
          <div ref="eventTypeChartRef" style="height:260px"></div>
          <div v-if="(!eventTypeDistribution || eventTypeDistribution.length === 0) && !loading" class="text-center text-gray-400 py-4">暂无数据</div>
        </el-card>

        <el-card class="mb-4">
          <template #header><span>周期统计</span></template>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="近7天事件">{{ weeklyStats?.total_events || 0 }}</el-descriptions-item>
            <el-descriptions-item label="近7天成功率">{{ weeklyStats?.success_rate || 100 }}%</el-descriptions-item>
            <el-descriptions-item label="近30天事件">{{ monthlyStats?.total_events || 0 }}</el-descriptions-item>
            <el-descriptions-item label="近30天成功率">{{ monthlyStats?.success_rate || 100 }}%</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs: 失败事件 / 延迟分布 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="失败事件" name="failures">
        <el-table :data="failures" stripe v-loading="failuresLoading" size="small">
          <el-table-column label="端点" prop="endpoint?.name" min-width="140" />
          <el-table-column label="事件类型" prop="event_type" width="140" />
          <el-table-column label="状态" prop="status" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'dead_letter' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="尝试次数" prop="attempts" width="80" />
          <el-table-column label="创建时间" prop="created_at" width="160" />
          <el-table-column label="描述" prop="description" min-width="200" show-overflow-tooltip />
        </el-table>
      </el-tab-pane>
      <el-tab-pane label="延迟分布" name="latency">
        <div ref="latencyChartRef" style="height:300px"></div>
        <div v-if="(!latencyDistribution || latencyDistribution.length === 0) && !loading" class="text-center text-gray-400 py-4">暂无延迟数据</div>
      </el-tab-pane>
    </el-tabs>

    <!-- 端点详情对话框 -->
    <EndpointDetailDialog v-model:visible="detailDialog.visible" :endpoint-id="detailDialog.id" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import * as echarts from 'echarts'
import { getWebhookMonitorOverview, getWebhookMonitorFailures, getWebhookLatencyDistribution } from '../../api/webhookMonitor'
import EndpointDetailDialog from './components/EndpointDetailDialog.vue'

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
function healthLabel(h) { return { healthy: '健康', warning: '告警', critical: '严重', paused: '暂停', inactive: '停用', idle: '空闲' }[h] || h }

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
  chart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['总量', '成功', '失败'], bottom: 0 },
    grid: { left: 50, right: 20, bottom: 35, top: 10 },
    xAxis: { type: 'category', data: dates, axisLabel: { rotate: 30, fontSize: 11 } },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      { name: '总量', type: 'bar', data: weeklyTrend.value.map(t => t.total || 0), itemStyle: { color: '#909399' }, barMaxWidth: 20 },
      { name: '成功', type: 'line', data: weeklyTrend.value.map(t => t.delivered || 0), smooth: true, lineStyle: { color: '#67c23a', width: 2 } },
      { name: '失败', type: 'line', data: weeklyTrend.value.map(t => t.failed || 0), smooth: true, lineStyle: { color: '#f56c6c', width: 2 } },
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
    yAxis: { type: 'value', name: '数量' },
    series: [{
      type: 'bar', data: latencyDistribution.value.map(t => t.count),
      itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#409eff' }, { offset: 1, color: '#79bbff' }]) },
      barMaxWidth: 40,
    }],
  })
}

watch(activeTab, (tab) => {
  if (tab === 'latency') nextTick(() => renderLatencyChart())
})

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
.text-primary { color: #409eff !important; }
</style>
