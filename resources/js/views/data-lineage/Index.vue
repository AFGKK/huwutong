<template>
  <div class="data-lineage-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>数据血缘追踪</h2>
      <p class="text-muted">追踪 License Key / 客户 PII / 设备指纹等关键数据的完整生命周期、来源流向与变更历史</p>
    </div>

    <!-- 仪表盘卡片 -->
    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_records }}</div>
          <div class="stat-label">血缘记录数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_tracked_objects }}</div>
          <div class="stat-label">追踪对象数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ topCategory }}</div>
          <div class="stat-label">最多数据类别</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ topEvent }}</div>
          <div class="stat-label">最多事件类型</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 类别分布 + 敏感度 -->
    <el-row :gutter="20" style="margin-bottom:20px">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>数据类别分布</span></template>
          <div style="height:200px;display:flex;align-items:center;justify-content:center">
            <el-empty v-if="!hasCategoryData" description="暂无数据" />
            <div v-else style="width:100%">
              <div v-for="(cnt, cat) in dashboard.by_category" :key="cat" class="bar-item">
                <span class="bar-label">{{ categoryLabel(cat) }}</span>
                <el-progress :percentage="categoryPercent(cnt)" :stroke-width="16" :format="() => `${cnt}条`" />
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>敏感度分布</span></template>
          <div style="height:200px;display:flex;align-items:center;justify-content:center">
            <el-empty v-if="!hasSensitivityData" description="暂无数据" />
            <div v-else style="width:100%">
              <div v-for="(cnt, sens) in dashboard.by_sensitivity" :key="sens" class="bar-item">
                <span class="bar-label">{{ sensitivityLabel(sens) }}</span>
                <el-progress :percentage="sensitivityPercent(cnt)" :stroke-width="16"
                  :color="sensitivityColor(sens)" :format="() => `${cnt}条`" />
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选栏 -->
    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="对象类型">
          <el-select v-model="filters.trackable_type" clearable placeholder="全部" style="width:130px">
            <el-option label="License" value="license" />
            <el-option label="客户" value="customer" />
            <el-option label="设备" value="device" />
          </el-select>
        </el-form-item>
        <el-form-item label="数据类别">
          <el-select v-model="filters.data_category" clearable placeholder="全部" style="width:150px">
            <el-option v-for="(lbl, val) in dataCategories" :key="val" :label="lbl" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item label="事件类型">
          <el-select v-model="filters.event_type" clearable placeholder="全部" style="width:130px">
            <el-option v-for="(lbl, val) in eventTypes" :key="val" :label="lbl" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item label="敏感度">
          <el-select v-model="filters.sensitivity" clearable placeholder="全部" style="width:130px">
            <el-option v-for="(lbl, val) in sensitivityLevels" :key="val" :label="lbl" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="标签/ID" clearable style="width:180px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadRecords">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
          <el-button @click="exportCsv">导出 CSV</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 追踪对象 Tab -->
    <el-tabs v-model="activeTab" @tab-change="onTabChange">
      <el-tab-pane label="血缘记录" name="records">
        <el-table :data="records" v-loading="loading" stripe style="width:100%">
          <el-table-column prop="recorded_at" label="时间" width="170" />
          <el-table-column prop="trackable_type" label="对象类型" width="100">
            <template #default="{ row }">
              <el-tag size="small">{{ typeLabel(row.trackable_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="trackable_label" label="对象" min-width="180" show-overflow-tooltip />
          <el-table-column prop="data_category" label="类别" width="110">
            <template #default="{ row }">
              <el-tag :type="categoryTagType(row.data_category)" size="small">{{ categoryLabel(row.data_category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sensitivity" label="敏感度" width="80">
            <template #default="{ row }">
              <el-tag :type="sensitivityTagType(row.sensitivity)" size="small">{{ sensitivityLabel(row.sensitivity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="event_type" label="事件" width="80">
            <template #default="{ row }">
              <el-tag :type="eventTagType(row.event_type)" size="small">{{ eventLabel(row.event_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="event_label" label="描述" min-width="160" show-overflow-tooltip />
          <el-table-column prop="actor" label="操作人" width="140">
            <template #default="{ row }">
              <span>{{ row.actor?.name || row.actor?.email || row.actor_type || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="source_system" label="来源" width="90" />
          <el-table-column label="操作" width="80" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click="showChain(row)">链路</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          background layout="prev,pager,next,total"
          :total="pagination.total" :page-size="pagination.per_page"
          :current-page="pagination.page" @current-change="onPageChange"
          style="margin-top:16px;justify-content:center"
        />
      </el-tab-pane>

      <el-tab-pane label="追踪对象" name="objects">
        <el-table :data="trackedObjects" v-loading="objectsLoading" stripe style="width:100%">
          <el-table-column prop="trackable_type" label="类型" width="100">
            <template #default="{ row }">
              <el-tag size="small">{{ typeLabel(row.trackable_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="trackable_id" label="ID" width="80" />
          <el-table-column prop="trackable_label" label="标签" min-width="200" show-overflow-tooltip />
          <el-table-column prop="data_category" label="类别" width="110">
            <template #default="{ row }">
              <el-tag :type="categoryTagType(row.data_category)" size="small">{{ categoryLabel(row.data_category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sensitivity" label="敏感度" width="80">
            <template #default="{ row }">
              <el-tag :type="sensitivityTagType(row.sensitivity)" size="small">{{ sensitivityLabel(row.sensitivity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="event_count" label="事件数" width="80" align="center" />
          <el-table-column prop="first_event_at" label="首次事件" width="170" />
          <el-table-column prop="last_event_at" label="最近事件" width="170" />
          <el-table-column label="操作" width="80">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click="viewObjectLineage(row)">查看链路</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 链路详情对话框 -->
    <el-dialog v-model="showChainDialog" title="数据血缘链路" width="900px" :close-on-click-modal="false">
      <div v-loading="chainLoading">
        <!-- 血缘链路时间线 -->
        <div v-if="chainData.chain?.length" class="lineage-timeline">
          <el-timeline>
            <el-timeline-item
              v-for="item in chainData.chain"
              :key="item.id"
              :timestamp="item.recorded_at"
              :color="chainEventColor(item.event_type)"
              placement="top"
            >
              <div class="timeline-item">
                <div class="tl-header">
                  <el-tag :type="eventTagType(item.event_type)" size="small">{{ eventLabel(item.event_type) }}</el-tag>
                  <strong style="margin-left:8px">{{ item.event_label }}</strong>
                </div>
                <div class="tl-meta">
                  <span>对象: {{ typeLabel(item.trackable_type) }} #{{ item.trackable_id }}</span>
                  <span v-if="item.trackable_label"> - {{ item.trackable_label }}</span>
                </div>
                <div class="tl-meta">
                  <span>类别: {{ categoryLabel(item.data_category) }}</span>
                  <span> / 敏感度: {{ sensitivityLabel(item.sensitivity) }}</span>
                  <span> / 来源: {{ item.source_system || '-' }}</span>
                  <span v-if="item.target_system"> → {{ item.target_system }}</span>
                </div>
                <div class="tl-meta" v-if="item.actor_name || item.actor">
                  <span>操作人: {{ item.actor_name || item.actor?.name || '-' }}</span>
                </div>

                <!-- 变更详情 -->
                <div v-if="item.changes?.length" class="tl-changes">
                  <el-table :data="item.changes" size="small" max-height="200" style="margin-top:8px">
                    <el-table-column prop="label || field" label="字段" width="140">
                      <template #default="{ row }">{{ row.label || row.field }}</template>
                    </el-table-column>
                    <el-table-column label="旧值" min-width="160">
                      <template #default="{ row }">
                        <span class="old-value">{{ formatChangeValue(row.old) }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="新值" min-width="160">
                      <template #default="{ row }">
                        <span class="new-value">{{ formatChangeValue(row.new) }}</span>
                      </template>
                    </el-table-column>
                  </el-table>
                </div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </div>
        <el-empty v-else description="暂无链路数据" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getLineageDashboard,
  getLineageRecords,
  getObjectLineage,
  getLineageChain,
  getTrackedObjects,
  exportLineageCsv,
} from '@/api/dataLineage'

// ── 数据 ──
const loading = ref(false)
const objectsLoading = ref(false)
const records = ref([])
const trackedObjects = ref([])
const pagination = reactive({ page: 1, per_page: 20, total: 0, last_page: 1 })
const activeTab = ref('records')
const dashboard = reactive({
  total_records: 0,
  total_tracked_objects: 0,
  by_category: {},
  by_event: {},
  by_sensitivity: {},
  weekly_trend: {},
  latest_by_category: {},
})

const categoryTotals = computed(() => {
  const vals = Object.values(dashboard.by_category)
  return vals.length ? vals.reduce((a, b) => a + b, 0) : 0
})

const sensitivityTotals = computed(() => {
  const vals = Object.values(dashboard.by_sensitivity)
  return vals.length ? vals.reduce((a, b) => a + b, 0) : 0
})

const topCategory = computed(() => {
  const entries = Object.entries(dashboard.by_category)
  if (!entries.length) return '-'
  const sorted = entries.sort((a, b) => b[1] - a[1])
  return categoryLabel(sorted[0][0])
})

const topEvent = computed(() => {
  const entries = Object.entries(dashboard.by_event)
  if (!entries.length) return '-'
  const sorted = entries.sort((a, b) => b[1] - a[1])
  return eventLabel(sorted[0][0])
})

const hasCategoryData = computed(() => Object.keys(dashboard.by_category).length > 0)
const hasSensitivityData = computed(() => Object.keys(dashboard.by_sensitivity).length > 0)

// ── 筛选器 ──
const filters = reactive({
  trackable_type: '',
  data_category: '',
  event_type: '',
  sensitivity: '',
  search: '',
})

// ── 常量 ──
const dataCategories = {
  license_key: 'License Key',
  pii: '客户个人信息',
  device_fingerprint: '设备指纹',
  subscription: '订阅信息',
  payment: '支付信息',
  api_key: 'API Key',
  configuration: '系统配置',
}

const eventTypes = {
  created: '创建', read: '读取', updated: '更新', exported: '导出',
  archived: '归档', deleted: '删除', activated: '激活', validated: '验证',
  revoked: '撤销', drifted: '指纹漂移', transferred: '转移', merged: '合并', restored: '恢复',
}

const sensitivityLevels = {
  public: '公开', internal: '内部', confidential: '机密', restricted: '限制',
}

function categoryLabel(val) { return dataCategories[val] || val }
function eventLabel(val) { return eventTypes[val] || val }
function sensitivityLabel(val) { return sensitivityLevels[val] || val }

function typeLabel(type) {
  const map = { license: 'License', customer: '客户', device: '设备' }
  return map[type] || type
}

function categoryTagType(cat) {
  const map = { license_key: 'warning', pii: 'danger', device_fingerprint: 'info', api_key: 'primary' }
  return map[cat] || ''
}

function sensitivityTagType(sens) {
  const map = { public: 'info', internal: '', confidential: 'warning', restricted: 'danger' }
  return map[sens] || ''
}

function eventTagType(evt) {
  const map = { created: 'success', updated: 'primary', deleted: 'danger', archived: 'warning',
    activated: 'success', validated: '', revoked: 'danger', restored: 'success', exported: 'info' }
  return map[evt] || ''
}

function chainEventColor(evt) {
  const map = { created: '#67c23a', updated: '#409eff', deleted: '#f56c6c', archived: '#e6a23c',
    activated: '#67c23a', validated: '#909399', revoked: '#f56c6c', restored: '#67c23a' }
  return map[evt] || '#409eff'
}

function categoryPercent(cnt) { return categoryTotals.value ? Math.round(cnt / categoryTotals.value * 100) : 0 }
function sensitivityPercent(cnt) { return sensitivityTotals.value ? Math.round(cnt / sensitivityTotals.value * 100) : 0 }

function sensitivityColor(sens) {
  const map = { public: '#909399', internal: '#409eff', confidential: '#e6a23c', restricted: '#f56c6c' }
  return map[sens] || '#409eff'
}

function formatChangeValue(val) {
  if (val === null || val === undefined) return '<span class="null-value">null</span>'
  if (typeof val === 'boolean') return val ? '是' : '否'
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}

// ── 方法 ──
async function loadDashboard() {
  try {
    const res = await getLineageDashboard()
    Object.assign(dashboard, res.data || {})
  } catch (_) { /* ignore */ }
}

async function loadRecords(page = 1) {
  loading.value = true
  pagination.page = page
  try {
    const params = { ...filters, page, per_page: pagination.per_page }
    // 清理空值
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await getLineageRecords(params)
    records.value = res.data?.items || []
    Object.assign(pagination, res.data?.pagination || {})
  } catch (_) {
    records.value = []
  } finally {
    loading.value = false
  }
}

async function loadTrackedObjects() {
  objectsLoading.value = true
  try {
    const res = await getTrackedObjects({ per_page: 50 })
    trackedObjects.value = res.data?.items || []
  } catch (_) {
    trackedObjects.value = []
  } finally {
    objectsLoading.value = false
  }
}

function resetFilters() {
  Object.keys(filters).forEach(k => { filters[k] = '' })
  loadRecords()
}

function onPageChange(page) {
  loadRecords(page)
}

function onTabChange(tab) {
  if (tab === 'objects' && !trackedObjects.value.length) {
    loadTrackedObjects()
  }
}

// ── 链路 ──
const showChainDialog = ref(false)
const chainLoading = ref(false)
const chainData = reactive({ chain: [], record: null })

async function showChain(row) {
  showChainDialog.value = true
  chainLoading.value = true
  try {
    const res = await getLineageChain(row.id)
    chainData.chain = res.data?.chain || []
    chainData.record = res.data?.record || null
  } catch (_) {
    chainData.chain = []
  } finally {
    chainLoading.value = false
  }
}

async function viewObjectLineage(obj) {
  activeTab.value = 'records'
  filters.trackable_type = obj.trackable_type
  filters.data_category = obj.data_category
  await loadRecords()
}

async function exportCsv() {
  try {
    const res = await exportLineageCsv()
    const url = window.URL.createObjectURL(new Blob([res]))
    const a = document.createElement('a')
    a.href = url
    a.download = `data-lineage-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    window.URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (_) {
    ElMessage.error('导出失败')
  }
}

onMounted(() => {
  loadDashboard()
  loadRecords()
})
</script>

<style scoped>
.data-lineage-page {
  padding: 20px;
}
.page-header {
  margin-bottom: 24px;
}
.page-header h2 {
  margin: 0 0 4px;
  font-size: 22px;
}
.page-header .text-muted {
  color: #909399;
  font-size: 14px;
}
.dashboard-cards .stat-card {
  text-align: center;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #409eff;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
.filter-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
}
.bar-item {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}
.bar-label {
  width: 120px;
  font-size: 13px;
  flex-shrink: 0;
}
.timeline-item {
  padding: 4px 0;
}
.tl-header {
  margin-bottom: 4px;
}
.tl-meta {
  font-size: 12px;
  color: #909399;
  line-height: 1.8;
}
.tl-changes {
  margin-top: 4px;
  padding: 4px 8px;
  background: #fafafa;
  border-radius: 4px;
}
:deep(.old-value) {
  color: #f56c6c;
  text-decoration: line-through;
}
:deep(.new-value) {
  color: #67c23a;
  font-weight: 500;
}
:deep(.null-value) {
  color: #c0c4cc;
  font-style: italic;
}
</style>
