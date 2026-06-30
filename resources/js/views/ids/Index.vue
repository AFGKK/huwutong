<template>
  <div>
    <!-- 标题 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">入侵检测与防御 (IDS/IPS)</span>
          <el-tag type="danger" size="small" class="ml-2" v-if="dashboardData.open_alerts > 0">
            {{ dashboardData.open_alerts }} 个待处理告警
          </el-tag>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button size="small" @click="refreshAll">刷新</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4" v-for="stat in statCards" :key="stat.label">
        <el-card shadow="never" class="stat-card" :class="stat.cls">
          <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 告警趋势图 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span>告警趋势（近7天）</span>
      </template>
      <div style="height: 200px; position: relative" v-if="trendsData.length > 0">
        <div class="trend-chart">
          <div v-for="(item, idx) in trendsData" :key="idx" class="trend-bar-group">
            <div class="trend-bar-stack">
              <div
                class="trend-bar trend-critical"
                :style="{ height: (item.critical / maxTrend) * 140 + 'px' }"
                :title="'严重: ' + item.critical"
              ></div>
              <div
                class="trend-bar trend-warning"
                :style="{ height: (item.warning / maxTrend) * 140 + 'px' }"
                :title="'警告: ' + item.warning"
              ></div>
              <div
                class="trend-bar trend-info"
                :style="{ height: (item.info / maxTrend) * 140 + 'px' }"
                :title="'信息: ' + item.info"
              ></div>
            </div>
            <div class="trend-date">{{ item.date.slice(5) }}</div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">暂无趋势数据</div>
    </el-card>

    <!-- 主要内容：两个标签页 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 标签1: 告警监控 -->
        <el-tab-pane label="告警监控" name="alerts">
          <!-- 筛选栏 -->
          <el-row :gutter="12" class="mb-3">
            <el-col :span="4">
              <el-select v-model="filters.severity" placeholder="严重级别" clearable size="small" @change="fetchAlerts">
                <el-option label="全部" value="" />
                <el-option label="严重" value="critical" />
                <el-option label="警告" value="warning" />
                <el-option label="信息" value="info" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-select v-model="filters.status" placeholder="状态" clearable size="small" @change="fetchAlerts">
                <el-option label="全部" value="" />
                <el-option v-for="(label, val) in statusOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-select v-model="filters.detection_type" placeholder="检测类型" clearable size="small" @change="fetchAlerts">
                <el-option label="全部" value="" />
                <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-input v-model="filters.source_ip" placeholder="来源IP" size="small" clearable @change="fetchAlerts" />
            </el-col>
            <el-col :span="8" class="text-right">
              <el-button type="danger" size="small" plain @click="handleClearAlerts">清除旧告警</el-button>
            </el-col>
          </el-row>

          <!-- 告警表格 -->
          <el-table :data="alertsData" v-loading="loading.alerts" stripe style="width: 100%">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="严重级别" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTagType(row.severity)" size="small" effect="dark">
                  {{ row.severity === 'critical' ? '严重' : row.severity === 'warning' ? '警告' : '信息' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="检测类型" width="130">
              <template #default="{ row }">
                {{ detectionTypeOptions[row.detection_type] || row.detection_type }}
              </template>
            </el-table-column>
            <el-table-column label="规则" min-width="150">
              <template #default="{ row }">
                {{ row.rule?.name || row.rule_name || '—' }}
              </template>
            </el-table-column>
            <el-table-column prop="source_ip" label="来源IP" width="140" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">{{ statusOptions[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="时间" width="160">
              <template #default="{ row }">
                {{ formatTime(row.created_at) }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="viewAlert(row)">详情</el-button>
                <el-dropdown v-if="row.status === 'open' || row.status === 'investigating'" trigger="click" @command="(cmd) => handleChangeStatus(row, cmd)">
                  <el-button size="small" link type="warning">处理</el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="investigating">开始调查</el-dropdown-item>
                      <el-dropdown-item command="mitigated">标记已缓解</el-dropdown-item>
                      <el-dropdown-item command="false_positive">标记误报</el-dropdown-item>
                      <el-dropdown-item command="closed">关闭</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </template>
            </el-table-column>
          </el-table>

          <!-- 分页 -->
          <div class="flex justify-center mt-4" v-if="alertsPagination.last_page > 1">
            <el-pagination
              background
              layout="prev, pager, next"
              :total="alertsPagination.total"
              :page-size="alertsPagination.per_page"
              :current-page="alertsPagination.current_page"
              @current-change="(p) => { alertsPagination.current_page = p; fetchAlerts(); }"
            />
          </div>
        </el-tab-pane>

        <!-- 标签2: 检测规则 -->
        <el-tab-pane label="检测规则" name="rules">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="ruleFilters.detection_type" placeholder="检测类型" clearable size="small" @change="fetchRules">
                <el-option label="全部" value="" />
                <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-select v-model="ruleFilters.is_active" placeholder="状态" clearable size="small" @change="fetchRules">
                <el-option label="全部" value="" />
                <el-option label="已启用" :value="true" />
                <el-option label="已禁用" :value="false" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-input v-model="ruleFilters.search" placeholder="搜索规则..." size="small" clearable @change="fetchRules" />
            </el-col>
            <el-col :span="6" class="text-right">
              <el-button size="small" @click="dialogRuleVisible = true; isEditingRule = false; resetRuleForm()">新建规则</el-button>
              <el-button size="small" plain @click="handleSeedRules">播种默认规则</el-button>
            </el-col>
          </el-row>

          <el-table :data="rulesData" v-loading="loading.rules" stripe style="width: 100%">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="名称" min-width="180">
              <template #default="{ row }">
                <span>{{ row.name }}</span>
                <el-tag v-if="row.is_system" size="small" type="info" class="ml-1">系统</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="检测类型" width="130">
              <template #default="{ row }">
                {{ detectionTypeOptions[row.detection_type] || row.detection_type }}
              </template>
            </el-table-column>
            <el-table-column label="严重级别" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTagType(row.severity)" size="small" effect="dark">
                  {{ row.severity === 'critical' ? '严重' : row.severity === 'warning' ? '警告' : '信息' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="阈值" width="100">
              <template #default="{ row }">
                {{ row.threshold_count }}次/{{ row.threshold_window_minutes }}分
              </template>
            </el-table-column>
            <el-table-column label="命中" width="70">
              <template #default="{ row }">
                {{ row.hit_count || 0 }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="(v) => toggleRuleActive(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="editRule(row)">编辑</el-button>
                <el-button v-if="!row.is_system" size="small" link type="danger" @click="handleDeleteRule(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <!-- 分页 -->
          <div class="flex justify-center mt-4" v-if="rulesPagination.last_page > 1">
            <el-pagination
              background
              layout="prev, pager, next"
              :total="rulesPagination.total"
              :page-size="rulesPagination.per_page"
              :current-page="rulesPagination.current_page"
              @current-change="(p) => { rulesPagination.current_page = p; fetchRules(); }"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 告警详情对话框 -->
    <el-dialog v-model="dialogAlertVisible" title="告警详情" width="640px" :close-on-click-modal="false">
      <div v-if="currentAlert">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="告警ID">{{ currentAlert.id }}</el-descriptions-item>
          <el-descriptions-item label="检测类型">{{ detectionTypeOptions[currentAlert.detection_type] || currentAlert.detection_type }}</el-descriptions-item>
          <el-descriptions-item label="严重级别">
            <el-tag :type="severityTagType(currentAlert.severity)" size="small" effect="dark">
              {{ currentAlert.severity === 'critical' ? '严重' : currentAlert.severity === 'warning' ? '警告' : '信息' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(currentAlert.status)" size="small">{{ statusOptions[currentAlert.status] }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="规则名称">{{ currentAlert.rule_name || '—' }}</el-descriptions-item>
          <el-descriptions-item label="来源IP">{{ currentAlert.source_ip || '—' }}</el-descriptions-item>
          <el-descriptions-item label="目标资源">{{ currentAlert.target_resource || '—' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatTime(currentAlert.created_at) }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4 class="mb-2">检测证据</h4>
        <pre class="evidence-json">{{ JSON.stringify(currentAlert.evidence, null, 2) }}</pre>
      </div>
      <template #footer>
        <el-button @click="dialogAlertVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 规则编辑对话框 -->
    <el-dialog v-model="dialogRuleVisible" :title="isEditingRule ? '编辑规则' : '新建规则'" width="640px" :close-on-click-modal="false">
      <el-form :model="ruleForm" label-width="120px" size="small">
        <el-form-item label="名称" required>
          <el-input v-model="ruleForm.name" placeholder="规则名称" />
        </el-form-item>
        <el-form-item label="检测类型" required>
          <el-select v-model="ruleForm.detection_type" placeholder="选择检测类型" style="width: 100%">
            <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item label="严重级别" required>
          <el-select v-model="ruleForm.severity" placeholder="选择级别" style="width: 100%">
            <el-option label="严重" value="critical" />
            <el-option label="警告" value="warning" />
            <el-option label="信息" value="info" />
          </el-select>
        </el-form-item>
        <el-form-item label="阈值(次)">
          <el-input-number v-model="ruleForm.threshold_count" :min="1" :max="10000" />
        </el-form-item>
        <el-form-item label="时间窗口(分)">
          <el-input-number v-model="ruleForm.threshold_window_minutes" :min="0" :max="1440" />
        </el-form-item>
        <el-form-item label="优先级">
          <el-input-number v-model="ruleForm.priority" :min="0" :max="999" />
        </el-form-item>
        <el-form-item label="条件配置">
          <el-input v-model="conditionsText" type="textarea" :rows="4" placeholder='{"event_type": "login_failed", "group_by": "ip_address"}' />
        </el-form-item>
        <el-form-item label="响应动作">
          <el-input v-model="actionsText" type="textarea" :rows="4" placeholder='[{"type": "block_ip", "duration_minutes": 30}]' />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogRuleVisible = false">取消</el-button>
        <el-button type="primary" @click="saveRule">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import idsApi from '../../api/ids'

// ─── 状态 ───
const activeTab = ref('alerts')
const dashboardData = ref({})
const trendsData = ref([])
const alertsData = ref([])
const alertsPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const rulesData = ref([])
const rulesPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const detectionTypeOptions = ref({})
const statusOptions = ref({})
const currentAlert = ref(null)

const loading = ref({ alerts: false, rules: false, dashboard: false })

const filters = ref({ severity: '', status: '', detection_type: '', source_ip: '' })
const ruleFilters = ref({ detection_type: '', is_active: '', search: '' })

const dialogAlertVisible = ref(false)
const dialogRuleVisible = ref(false)
const isEditingRule = ref(false)
const editingRuleId = ref(null)
const ruleForm = ref({
  name: '',
  detection_type: 'brute_force',
  severity: 'warning',
  threshold_count: 5,
  threshold_window_minutes: 5,
  priority: 100,
  conditions: { event_type: 'login_failed', group_by: 'ip_address' },
  actions: [{ type: 'notify_admin' }],
})
const conditionsText = ref(JSON.stringify(ruleForm.value.conditions, null, 2))
const actionsText = ref(JSON.stringify(ruleForm.value.actions, null, 2))

const maxTrend = computed(() => {
  if (trendsData.value.length === 0) return 1
  return Math.max(...trendsData.value.map(d => d.total), 1)
})

// ─── 统计卡片 ───
const statCards = computed(() => [
  { label: '待处理告警', value: dashboardData.value?.open_alerts ?? 0, color: '#f56c6c' },
  { label: '严重告警', value: dashboardData.value?.critical_alerts ?? 0, color: '#e63946' },
  { label: '今日告警', value: dashboardData.value?.today_alerts ?? 0, color: '#e6a23c' },
  { label: '总告警数', value: dashboardData.value?.total_alerts ?? 0, color: '#409eff' },
  { label: '活跃规则', value: dashboardData.value?.rule_stats?.active ?? 0, color: '#67c23a' },
  { label: '系统规则', value: dashboardData.value?.rule_stats?.system ?? 0, color: '#909399' },
])

// ─── 方法 ───
function severityTagType(severity) {
  return severity === 'critical' ? 'danger' : severity === 'warning' ? 'warning' : 'info'
}
function statusTagType(status) {
  return status === 'open' ? 'danger' : status === 'investigating' ? 'warning' : status === 'mitigated' ? 'success' : status === 'false_positive' ? 'info' : ''
}
function formatTime(t) {
  if (!t) return '—'
  const d = new Date(t)
  return d.toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}
function resetRuleForm() {
  ruleForm.value = { name: '', detection_type: 'brute_force', severity: 'warning', threshold_count: 5, threshold_window_minutes: 5, priority: 100, conditions: { event_type: 'login_failed', group_by: 'ip_address' }, actions: [{ type: 'notify_admin' }] }
  conditionsText.value = JSON.stringify(ruleForm.value.conditions, null, 2)
  actionsText.value = JSON.stringify(ruleForm.value.actions, null, 2)
}

// ─── 数据获取 ───
async function fetchDashboard() {
  loading.value.dashboard = true
  try {
    const res = await idsApi.dashboard()
    dashboardData.value = res.data || {}
  } catch (e) {
    console.error('获取仪表盘失败', e)
  } finally {
    loading.value.dashboard = false
  }
}

async function fetchTrends() {
  try {
    const res = await idsApi.trends(7)
    trendsData.value = res.data || []
  } catch (e) {
    console.error('获取趋势失败', e)
  }
}

async function fetchAlerts() {
  loading.value.alerts = true
  try {
    const params = { ...filters.value, page: alertsPagination.value.current_page, per_page: 20 }
    const res = await idsApi.alerts(params)
    if (res.data) {
      alertsData.value = res.data.data || []
      alertsPagination.value = {
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
        per_page: res.data.per_page,
      }
    }
  } catch (e) {
    console.error('获取告警失败', e)
  } finally {
    loading.value.alerts = false
  }
}

async function fetchRules() {
  loading.value.rules = true
  try {
    const params = { ...ruleFilters.value, page: rulesPagination.value.current_page, per_page: 20 }
    const res = await idsApi.rules(params)
    if (res.data) {
      rulesData.value = res.data.data || []
      rulesPagination.value = {
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
        per_page: res.data.per_page,
      }
    }
  } catch (e) {
    console.error('获取规则失败', e)
  } finally {
    loading.value.rules = false
  }
}

async function fetchReferences() {
  try {
    const typesRes = await idsApi.detectionTypes()
    detectionTypeOptions.value = typesRes.data?.types || {}
    const statusRes = await idsApi.alertStatuses()
    statusOptions.value = statusRes.data?.statuses || {}
  } catch (e) {
    console.error('获取引用数据失败', e)
  }
}

// ─── 操作 ───
function viewAlert(row) {
  currentAlert.value = row
  dialogAlertVisible.value = true
}

async function handleChangeStatus(row, status) {
  try {
    const res = await idsApi.updateAlertStatus(row.id, status)
    ElMessage.success(res.message || '状态已更新')
    fetchAlerts()
    fetchDashboard()
  } catch (e) {
    ElMessage.error('更新失败')
  }
}

async function handleClearAlerts() {
  try {
    await ElMessageBox.confirm('清除30天前的已关闭告警记录？', '确认', { type: 'warning' })
    const res = await idsApi.clearAlerts('30 days')
    ElMessage.success(res.message || '已清除')
    fetchAlerts()
    fetchDashboard()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('清除失败')
  }
}

async function handleSeedRules() {
  try {
    const res = await idsApi.seedRules()
    ElMessage.success(res.message || '规则已播种')
    fetchRules()
    fetchDashboard()
  } catch (e) {
    ElMessage.error('播种失败')
  }
}

function editRule(row) {
  isEditingRule.value = true
  editingRuleId.value = row.id
  ruleForm.value = {
    name: row.name,
    detection_type: row.detection_type,
    severity: row.severity,
    threshold_count: row.threshold_count,
    threshold_window_minutes: row.threshold_window_minutes,
    priority: row.priority,
    conditions: row.conditions || {},
    actions: row.actions || [],
  }
  conditionsText.value = JSON.stringify(ruleForm.value.conditions, null, 2)
  actionsText.value = JSON.stringify(ruleForm.value.actions, null, 2)
  dialogRuleVisible.value = true
}

async function saveRule() {
  try {
    ruleForm.value.conditions = JSON.parse(conditionsText.value)
    ruleForm.value.actions = JSON.parse(actionsText.value)
  } catch (e) {
    ElMessage.error('条件或动作JSON格式错误')
    return
  }

  try {
    if (isEditingRule.value && editingRuleId.value) {
      const res = await idsApi.updateRule(editingRuleId.value, ruleForm.value)
      ElMessage.success(res.message || '规则已更新')
    } else {
      const res = await idsApi.storeRule(ruleForm.value)
      ElMessage.success(res.message || '规则已创建')
    }
    dialogRuleVisible.value = false
    fetchRules()
  } catch (e) {
    ElMessage.error('保存失败')
  }
}

async function handleDeleteRule(row) {
  try {
    await ElMessageBox.confirm(`确认删除规则 "${row.name}"？`, '确认', { type: 'warning' })
    await idsApi.deleteRule(row.id)
    ElMessage.success('已删除')
    fetchRules()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('删除失败')
  }
}

async function toggleRuleActive(row, isActive) {
  try {
    await idsApi.updateRule(row.id, { is_active: isActive })
    ElMessage.success(isActive ? '规则已启用' : '规则已禁用')
    fetchRules()
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

function refreshAll() {
  fetchDashboard()
  fetchTrends()
  if (activeTab.value === 'alerts') fetchAlerts()
  else fetchRules()
}

// ─── 监听Tab切换 ───
watch(activeTab, (tab) => {
  if (tab === 'alerts') fetchAlerts()
  else fetchRules()
})

// ─── 初始化 ───
onMounted(() => {
  fetchDashboard()
  fetchTrends()
  fetchAlerts()
  fetchReferences()
})
</script>

<style scoped>
.stat-card {
  text-align: center;
  border-left: 3px solid transparent;
}
.stat-value {
  font-size: 26px;
  font-weight: 700;
}
.stat-label {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.ml-2 { margin-left: 8px; }
.ml-1 { margin-left: 4px; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-gray-400 { color: #c0c4cc; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.flex { display: flex; }
.justify-center { justify-content: center; }
.mt-4 { margin-top: 16px; }

.trend-chart {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 180px;
  gap: 8px;
}
.trend-bar-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
}
.trend-bar-stack {
  flex: 1;
  display: flex;
  flex-direction: column-reverse;
  width: 24px;
  gap: 1px;
}
.trend-bar {
  width: 100%;
  border-radius: 3px 3px 0 0;
  min-height: 2px;
  transition: height 0.3s;
}
.trend-critical { background: #e63946; }
.trend-warning { background: #e6a23c; }
.trend-info { background: #409eff; }
.trend-date { font-size: 11px; color: #909399; margin-top: 4px; }

.evidence-json {
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  max-height: 300px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
