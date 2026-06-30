<template>
  <div class="audit-visualization">
    <h2 class="mb-4">审计可视化分析</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.today_logs }}</div><div class="stat-label">今日日志</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.month_logs }}</div><div class="stat-label">本月日志</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div :class="['stat-value', stats.month_change_pct >= 0 ? 'text-danger' : 'text-success']">
              {{ stats.month_change_pct >= 0 ? '+' : '' }}{{ stats.month_change_pct }}%
            </div>
            <div class="stat-label">环比变化</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div :class="['stat-value', stats.open_anomalies > 0 ? 'text-danger' : 'text-success']">{{ stats.open_anomalies }}</div><div class="stat-label">未处理异常</div></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 图表区域 -->
    <el-row :gutter="20" class="mb-4">
      <!-- 趋势图 -->
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center justify-between">
              <span>审计日志趋势</span>
              <div>
                <el-select v-model="trendGranularity" size="small" style="width:100px" @change="loadTrend" class="mr-2">
                  <el-option label="每日" value="daily" />
                  <el-option label="每周" value="weekly" />
                  <el-option label="每月" value="monthly" />
                </el-select>
                <el-date-picker v-model="trendRange" type="daterange" range-separator="至" start-placeholder="开始"
                  end-placeholder="结束" size="small" value-format="YYYY-MM-DD" @change="loadTrend" />
              </div>
            </div>
          </template>
          <v-chart :option="trendOption" style="height:320px" autoresize />
        </el-card>
      </el-col>
      <!-- 类型分布 -->
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>审计类型分布</template>
          <v-chart :option="typeDistOption" style="height:320px" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mb-4">
      <!-- 时段分布 -->
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>时段分布 (小时)</template>
          <v-chart :option="hourlyOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
      <!-- Top 操作 -->
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>热门操作 Top 10</template>
          <v-chart :option="topActionsOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
      <!-- Top 用户 -->
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>活跃用户 Top 10</template>
          <v-chart :option="topUsersOption" style="height:260px" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <!-- 异常检测 Tabs -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="异常检测" name="anomalies">
        <div class="mb-3">
          <el-button type="primary" size="small" @click="runAnomalyDetection" :loading="detecting">
            执行异常检测
          </el-button>
          <el-select v-model="anomalyFilter.severity" placeholder="严重程度" clearable size="small" style="width:120px"
            @change="loadAnomalies" class="ml-2">
            <el-option label="全部" value="" />
            <el-option label="提示" value="info" />
            <el-option label="警告" value="warning" />
            <el-option label="紧急" value="critical" />
          </el-select>
          <el-select v-model="anomalyFilter.status" placeholder="状态" clearable size="small" style="width:120px"
            @change="loadAnomalies" class="ml-2">
            <el-option label="全部" value="" />
            <el-option label="待处理" value="open" />
            <el-option label="已确认" value="acknowledged" />
            <el-option label="已解决" value="resolved" />
            <el-option label="已忽略" value="dismissed" />
          </el-select>
        </div>

        <el-table :data="anomalies" stripe v-loading="anomalyLoading">
          <el-table-column label="类型" prop="anomaly_type" width="110">
            <template #default="{ row }">
              <el-tag :type="typeTag(row.anomaly_type)" size="small">{{ typeLabel(row.anomaly_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="严重程度" prop="severity" width="90">
            <template #default="{ row }">
              <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="指标" prop="metric" width="150" />
          <el-table-column label="基线值" prop="baseline_value" width="90" />
          <el-table-column label="实际值" prop="actual_value" width="90" />
          <el-table-column label="偏差" prop="deviation" width="100">
            <template #default="{ row }">
              <span :class="row.deviation > 0 ? 'text-danger' : 'text-success'">{{ row.deviation >= 0 ? '+' : '' }}{{ row.deviation }}%</span>
            </template>
          </el-table-column>
          <el-table-column label="描述" prop="description" min-width="260" show-overflow-tooltip />
          <el-table-column label="状态" prop="status" width="90">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="检测时间" prop="detected_at" width="160" />
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'open'" size="small" @click="updateStatus(row, 'acknowledged')">确认</el-button>
              <el-button v-if="['open', 'acknowledged'].includes(row.status)" size="small" type="success"
                @click="updateStatus(row, 'resolved')">解决</el-button>
              <el-button v-if="row.status === 'open'" size="small" type="info"
                @click="updateStatus(row, 'dismissed')">忽略</el-button>
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

const stats = ref({ type_distribution: {}, recent_anomalies: [] })
const trendRange = ref([new Date(Date.now() - 90 * 86400000), new Date()])
const trendGranularity = ref('daily')
const trendRaw = ref({ summary: [], by_type: [] })
const typeDistData = ref([])
const hourlyData = ref([])
const topActionsData = ref([])
const topUsersData = ref([])

// 异常
const activeTab = ref('anomalies')
const anomalies = ref([])
const anomalyLoading = ref(false)
const detecting = ref(false)
const anomalyPage = ref(1)
const anomalyPerPage = 50
const anomalyTotal = ref(0)
const anomalyFilter = reactive({ severity: '', status: '' })

// ─── 趋势图 ───
const trendOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  legend: { data: ['总数', '审计', '安全', '错误', '系统'], top: 0 },
  grid: { left: 40, right: 20, bottom: 30, top: 40 },
  xAxis: { type: 'category', data: trendRaw.value.summary.map(s => s.label), axisLabel: { rotate: 30 } },
  yAxis: { type: 'value' },
  series: [
    { name: '总数', type: 'line', data: trendRaw.value.summary.map(s => s.count), smooth: true, lineStyle: { width: 2 } },
    { name: '审计', type: 'bar', data: trendRaw.value.by_type.map(s => s.audit), stack: 'types' },
    { name: '安全', type: 'bar', data: trendRaw.value.by_type.map(s => s.security), stack: 'types' },
    { name: '错误', type: 'bar', data: trendRaw.value.by_type.map(s => s.error), stack: 'types' },
    { name: '系统', type: 'bar', data: trendRaw.value.by_type.map(s => s.system), stack: 'types' },
  ],
}))

// ─── 类型分布饼图 ───
const typeDistOption = computed(() => ({
  tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
  series: [{
    type: 'pie', radius: ['40%', '70%'], center: ['50%', '55%'],
    data: typeDistData.value.map(d => ({ name: typeLabel(d.type), value: d.count })),
    label: { show: true, formatter: '{b}\n{d}%' },
    emphasis: { label: { show: true, fontSize: 14 } },
  }],
}))

// ─── 时段分布 ───
const hourlyOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'category', data: hourlyData.value.map(d => `${d.hour}:00`) },
  yAxis: { type: 'value' },
  series: [{ type: 'bar', data: hourlyData.value.map(d => d.count), itemStyle: { color: '#409eff' } }],
  grid: { left: 40, right: 10, bottom: 30 },
}))

// ─── Top 操作 ───
const topActionsOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'value' },
  yAxis: { type: 'category', data: topActionsData.value.map(d => truncate(d.action, 25)).reverse(), axisLabel: { fontSize: 11 } },
  series: [{ type: 'bar', data: topActionsData.value.map(d => d.count).reverse(), itemStyle: { color: '#67c23a' } }],
  grid: { left: 120, right: 20, top: 10, bottom: 10 },
}))

// ─── Top 用户 ───
const topUsersOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  xAxis: { type: 'value' },
  yAxis: { type: 'category', data: topUsersData.value.map(d => d.user_name || 'System').reverse(), axisLabel: { fontSize: 11 } },
  series: [{ type: 'bar', data: topUsersData.value.map(d => d.count).reverse(), itemStyle: { color: '#e6a23c' } }],
  grid: { left: 100, right: 20, top: 10, bottom: 10 },
}))

// ─── 标签映射 ───
function typeLabel(t) {
  const map = { audit: '审计', security: '安全', error: '错误', system: '系统', login_audit: '登录审计', api_key_audit: 'API密钥审计' }
  return map[t] || t
}
function typeTag(t) {
  const map = { spike: 'danger', drop: 'warning', pattern_change: 'info', unusual_hours: 'warning', geo_anomaly: 'danger' }
  return map[t] || ''
}
function severityTag(s) {
  return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info'
}
function statusTag(s) {
  return { open: 'danger', acknowledged: 'warning', resolved: 'success', dismissed: 'info' }[s] || 'info'
}
function statusLabel(s) {
  return { open: '待处理', acknowledged: '已确认', resolved: '已解决', dismissed: '已忽略' }[s] || s
}
function truncate(str, len) {
  return str?.length > len ? str.substr(0, len) + '...' : str
}

// ─── 数据加载 ───
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
    ElMessage.error('加载异常数据失败')
  } finally {
    anomalyLoading.value = false
  }
}

async function runAnomalyDetection() {
  detecting.value = true
  try {
    const { data } = await detectAnomalies()
    ElMessage.success(`检测完成，发现 ${data?.length || 0} 个新异常`)
    await loadAnomalies()
  } catch (e) {
    ElMessage.error('异常检测失败')
  } finally {
    detecting.value = false
  }
}

async function updateStatus(row, status) {
  try {
    await updateAnomalyStatus(row.id, status)
    ElMessage.success('状态已更新')
    await loadAnomalies()
  } catch (e) {
    ElMessage.error('操作失败')
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
</style>
