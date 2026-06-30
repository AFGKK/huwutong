<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12" justify="space-between" align="middle">
        <el-col :span="12">
          <el-space>
            <span class="text-lg font-medium">自定义仪表盘</span>
            <el-select v-model="currentDashboardId" placeholder="选择仪表盘" style="width:220px" @change="switchDashboard">
              <el-option v-for="d in dashboards" :key="d.id" :label="d.name" :value="d.id">
                <span>{{ d.name }}</span>
                <span v-if="d.is_default" class="ml-2">
                  <el-tag size="small" type="warning">默认</el-tag>
                </span>
                <span class="text-gray-400 text-xs ml-2">{{ d.widgets_count }} 小部件</span>
              </el-option>
            </el-select>
          </el-space>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-space>
            <el-button size="small" @click="refreshAll">刷新全部</el-button>
            <el-button size="small" @click="openWidgetLibrary">+ 添加小部件</el-button>
            <el-dropdown trigger="click">
              <el-button size="small">
                仪表盘管理 <el-icon><ArrowDown /></el-icon>
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item @click="openCreateDashboard">新建仪表盘</el-dropdown-item>
                  <el-dropdown-item @click="editCurrentDashboard">编辑当前</el-dropdown-item>
                  <el-dropdown-item @click="setCurrentAsDefault" v-if="currentDashboard && !currentDashboard.is_default">
                    设为默认
                  </el-dropdown-item>
                  <el-dropdown-item @click="duplicateCurrent">复制当前</el-dropdown-item>
                  <el-dropdown-item divided @click="deleteCurrent" class="text-red">删除当前</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </el-space>
        </el-col>
      </el-row>
    </el-card>

    <!-- Widget 网格 -->
    <div v-if="loading" v-loading="loading" style="min-height:200px" />
    <div v-else-if="!currentDashboard" class="text-center text-gray-400 py-12">
      请选择或创建一个仪表盘
    </div>
    <div v-else-if="!currentDashboard.widgets?.length" class="text-center text-gray-400 py-12">
      此仪表盘暂无小部件，点击"添加小部件"开始构建
    </div>

    <div v-if="currentDashboard?.widgets?.length" class="widget-grid" :style="gridStyle">
      <div v-for="widget in currentDashboard.widgets" :key="widget.id"
        class="widget-item" :style="widgetStyle(widget)">
        <el-card shadow="hover" class="widget-card">
          <template #header>
            <div class="widget-header">
              <span class="widget-title">{{ widget.title }}</span>
              <el-space>
                <el-tag size="small" effect="plain" class="widget-type-tag">{{ typeLabel(widget.type) }}</el-tag>
                <el-dropdown trigger="click" size="small">
                  <el-button size="small" link><el-icon><MoreFilled /></el-icon></el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item @click="editWidget(widget)">编辑</el-dropdown-item>
                      <el-dropdown-item @click="refreshWidget(widget)">刷新数据</el-dropdown-item>
                      <el-dropdown-item divided @click="deleteWidget(widget)" class="text-red">删除</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </el-space>
            </div>
          </template>

          <!-- Stat 统计数字 -->
          <div v-if="widget.type === 'stat'" class="widget-stat">
            <div v-for="(val, key) in widget.data" :key="key" class="stat-item">
              <div class="stat-key">{{ statKeyLabel(key) }}</div>
              <div class="stat-val">{{ val }}</div>
            </div>
          </div>

          <!-- Chart 图表（简单展示） -->
          <div v-else-if="widget.type === 'chart'" class="widget-chart">
            <div v-if="widget.data?.by_status">
              <div v-for="(cnt, status) in widget.data.by_status" :key="status" class="chart-row">
                <span class="chart-label">{{ status }}</span>
                <el-progress :percentage="chartPercent(cnt, widget.data.total)" :stroke-width="20" striped />
                <span class="chart-value">{{ cnt }}</span>
              </div>
            </div>
            <div v-else-if="widget.data?.by_date" class="chart-trend">
              <div v-for="d in widget.data.by_date.slice(-14)" :key="d.date" class="trend-bar-wrapper">
                <div class="trend-bar" :style="{ height: trendHeight(d.cnt, widget.data.by_date) + '%' }"></div>
                <div class="trend-label">{{ d.date?.slice(5) }}</div>
              </div>
            </div>
            <div v-else class="text-gray-400 text-xs">暂无图表数据</div>
          </div>

          <!-- Metric 指标卡 -->
          <div v-else-if="widget.type === 'metric'" class="widget-metric">
            <div v-for="(val, key) in widget.data" :key="key" class="metric-card">
              <div class="metric-label">{{ statKeyLabel(key) }}</div>
              <div class="metric-value">{{ val }}</div>
            </div>
          </div>

          <!-- List 列表 -->
          <div v-else-if="widget.type === 'list'" class="widget-list">
            <div v-for="(item, idx) in (widget.data?.slice(0, 8) || [])" :key="idx" class="list-item">
              <span>{{ item.name || item.title || item.id }}</span>
              <span class="text-gray-400 text-xs">{{ item.created_at?.slice(0, 10) }}</span>
            </div>
            <div v-if="!widget.data?.length" class="text-gray-400 text-xs">暂无数据</div>
          </div>

          <!-- Table 表格 -->
          <div v-else-if="widget.type === 'table' && widget.data?.length" class="widget-table-wrapper">
            <el-table :data="widget.data.slice(0, 6)" size="small" max-height="240">
              <el-table-column v-for="col in Object.keys(widget.data[0] || {}).slice(0, 5)" :key="col"
                :prop="col" :label="col" min-width="80" show-overflow-tooltip />
            </el-table>
          </div>

          <!-- Alert 告警 -->
          <div v-else-if="widget.type === 'alert'" class="widget-alert">
            <div v-for="t in widget.data?.slice(0, 8) || []" :key="t.id" class="alert-item">
              <el-tag :type="t.status === 'open' ? 'danger' : 'warning'" size="small" class="mr-2">
                {{ t.status }}
              </el-tag>
              <span class="text-xs">{{ t.title || t.description }}</span>
            </div>
            <div v-if="!widget.data?.length" class="text-gray-400 text-xs">暂无告警</div>
          </div>

          <!-- iFrame -->
          <div v-else-if="widget.type === 'iframe'" class="widget-iframe">
            <iframe :src="widget.config?.url" class="iframe-content" frameborder="0" />
          </div>

          <!-- HTML -->
          <div v-else-if="widget.type === 'html'" class="widget-html" v-html="widget.config?.html" />

          <!-- Report 报表快照 -->
          <div v-else-if="widget.type === 'report'" class="widget-report">
            <div class="text-xs text-gray-400">报表数据 (ID: {{ widget.config?.report_id }})</div>
          </div>

          <!-- Fallback -->
          <div v-else class="text-gray-400 text-xs py-4">未知小部件类型: {{ widget.type }}</div>
        </el-card>
      </div>
    </div>

    <!-- 导航点（快速切换仪表盘） -->
    <div v-if="dashboards.length > 1" class="nav-dots">
      <div v-for="d in dashboards" :key="d.id" class="nav-dot-wrapper">
        <div class="nav-dot" :class="{ active: d.id === currentDashboardId }"
          @click="currentDashboardId = d.id; switchDashboard()"
          :title="d.name" />
        <div class="nav-dot-title">{{ d.name }}</div>
      </div>
    </div>

    <!-- 对话框 -->
    <DashboardDialog ref="dashboardDialogRef" @saved="loadDashboards" />
    <WidgetDialog ref="widgetDialogRef" :dashboard-id="currentDashboardId" @saved="loadDashboard" />
    <WidgetLibrary ref="libraryRef" :dashboard-id="currentDashboardId" @added="loadDashboard" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, MoreFilled } from '@element-plus/icons-vue'
import { getDashboards, getDashboard, deleteDashboard, setDefaultDashboard, duplicateDashboard, refreshWidgetData } from '../../api/dashboard'
import DashboardDialog from './components/DashboardDialog.vue'
import WidgetDialog from './components/WidgetDialog.vue'
import WidgetLibrary from './components/WidgetLibrary.vue'

const dashboards = ref([])
const currentDashboardId = ref(null)
const currentDashboard = ref(null)
const loading = ref(false)
const dashboardDialogRef = ref(null)
const widgetDialogRef = ref(null)
const libraryRef = ref(null)

const gridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${currentDashboard.value?.columns || 12}, minmax(0, 1fr))`,
}))

function widgetStyle(w) {
  const layout = w.layout || { w: 4, h: 2 }
  return {
    gridColumn: `span ${layout.w || 4}`,
    gridRow: `span ${layout.h || 2}`,
  }
}

const typeLabels = { stat: '统计', chart: '图表', list: '列表', metric: '指标', table: '表格', iframe: '嵌入', html: 'HTML', alert: '告警', report: '报表' }
const statKeyLabels = {
  total_licenses: '总License', active_licenses: '活跃License', total_subscriptions: '总订阅',
  active_subscriptions: '活跃订阅', total_users: '总用户', today_logs: '今日日志',
  total: '总计', expiring_soon: '即将过期', monthly_revenue: '月收入',
  active_today: '今日活跃', new_last_30d: '近30天新增', period_days: '统计天数',
}

function typeLabel(t) { return typeLabels[t] || t }
function statKeyLabel(k) { return statKeyLabels[k] || k.replace(/_/g, ' ') }
function chartPercent(cnt, total) { return total ? Math.round((cnt / total) * 100) : 0 }
function trendHeight(cnt, data) {
  const max = Math.max(...data.map(d => d.cnt), 1)
  return Math.max(5, (cnt / max) * 100)
}

async function loadDashboards() {
  try {
    const { data } = await getDashboards()
    dashboards.value = data || []
    if (!currentDashboardId.value && dashboards.value.length) {
      const def = dashboards.value.find(d => d.is_default) || dashboards.value[0]
      currentDashboardId.value = def.id
      await loadDashboard()
    }
  } catch (e) {
    ElMessage.error('获取仪表盘列表失败')
  }
}

async function loadDashboard() {
  if (!currentDashboardId.value) return
  loading.value = true
  try {
    const { data } = await getDashboard(currentDashboardId.value)
    currentDashboard.value = data
  } catch (e) {
    ElMessage.error('获取仪表盘数据失败')
  } finally {
    loading.value = false
  }
}

function switchDashboard() {
  loadDashboard()
}

async function refreshAll() {
  ElMessage.info('正在刷新所有小部件数据...')
  await loadDashboard()
  ElMessage.success('刷新完成')
}

async function refreshWidget(widget) {
  try {
    const { data } = await refreshWidgetData(widget.id)
    widget.data = data
    ElMessage.success('已刷新')
  } catch (e) {
    ElMessage.error('刷新失败')
  }
}

// ─── 仪表盘操作 ───
function openCreateDashboard() { dashboardDialogRef.value?.open('create') }
function editCurrentDashboard() { dashboardDialogRef.value?.open('edit', currentDashboard.value) }

async function setCurrentAsDefault() {
  try {
    await setDefaultDashboard(currentDashboardId.value)
    ElMessage.success('已设为默认仪表盘')
    loadDashboards()
  } catch (e) { ElMessage.error('操作失败') }
}

async function duplicateCurrent() {
  try {
    const { data } = await duplicateDashboard(currentDashboardId.value)
    ElMessage.success('已复制仪表盘')
    loadDashboards()
    currentDashboardId.value = data.id
    loadDashboard()
  } catch (e) { ElMessage.error('复制失败') }
}

function deleteCurrent() {
  ElMessageBox.confirm(`确定删除仪表盘「${currentDashboard.value?.name}」？`, '确认删除', {
    confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    try {
      await deleteDashboard(currentDashboardId.value)
      ElMessage.success('已删除')
      currentDashboardId.value = null
      currentDashboard.value = null
      loadDashboards()
    } catch (e) { ElMessage.error('删除失败') }
  }).catch(() => {})
}

// ─── Widget 操作 ───
function editWidget(widget) { widgetDialogRef.value?.open('edit', widget) }
function deleteWidget(widget) {
  ElMessageBox.confirm(`删除小部件「${widget.title}」？`, '确认', {
    confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    try {
      const { deleteWidget } = await import('../../api/dashboard')
      await deleteWidget(widget.id)
      ElMessage.success('已删除')
      loadDashboard()
    } catch (e) { ElMessage.error('删除失败') }
  }).catch(() => {})
}

function openWidgetLibrary() { libraryRef.value?.open() }

onMounted(() => loadDashboards())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
.text-red { color: #f56c6c; }
.ml-2 { margin-left: 8px; }
.mr-2 { margin-right: 8px; }
.py-12 { padding: 48px 0; }

.widget-grid {
  display: grid;
  gap: 12px;
  grid-auto-rows: minmax(120px, auto);
}
.widget-item { min-height: 0; }
.widget-card { height: 100%; }
.widget-card :deep(.el-card__body) { overflow: auto; max-height: 360px; }
.widget-header { display: flex; justify-content: space-between; align-items: center; }
.widget-title { font-weight: 600; font-size: 14px; }
.widget-type-tag { font-size: 11px; }

.widget-stat { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.stat-item { text-align: center; padding: 8px; background: #f5f7fa; border-radius: 6px; }
.stat-key { font-size: 11px; color: #909399; margin-bottom: 4px; }
.stat-val { font-size: 22px; font-weight: 700; color: #409eff; }

.chart-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.chart-label { width: 80px; font-size: 12px; text-transform: capitalize; }
.chart-value { width: 40px; text-align: right; font-size: 12px; font-weight: 600; }
.chart-trend { display: flex; gap: 4px; align-items: flex-end; height: 120px; padding-top: 20px; }
.trend-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; }
.trend-bar { width: 100%; max-width: 24px; background: linear-gradient(to top, #409eff, #79bbff); border-radius: 3px 3px 0 0; min-height: 4px; }
.trend-label { font-size: 10px; color: #909399; margin-top: 4px; }

.metric-card { padding: 12px; background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-radius: 8px; margin-bottom: 8px; }
.metric-label { font-size: 11px; color: #606266; }
.metric-value { font-size: 20px; font-weight: 700; color: #303133; }

.list-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.widget-table-wrapper :deep(.el-table) { font-size: 12px; }

.alert-item { display: flex; align-items: center; margin-bottom: 6px; }

.widget-iframe .iframe-content { width: 100%; height: 200px; border: none; }
.widget-html { font-size: 13px; }

.nav-dots { display: flex; justify-content: center; gap: 24px; margin-top: 20px; padding: 12px; }
.nav-dot-wrapper { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.nav-dot { width: 12px; height: 12px; border-radius: 50%; background: #dcdfe6; cursor: pointer; transition: all 0.2s; }
.nav-dot.active { background: #409eff; transform: scale(1.3); }
.nav-dot-title { font-size: 11px; color: #909399; white-space: nowrap; }
</style>
