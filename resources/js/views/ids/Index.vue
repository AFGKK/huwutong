<template>
  <div>
    <!-- 标题 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">{{ t('ids_page.title') }}</span>
          <el-tag type="danger" size="small" class="ml-2" v-if="dashboardData.open_alerts > 0">
            {{ t('ids_page.open_alerts_badge', { count: dashboardData.open_alerts }) }}
          </el-tag>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button size="small" @click="refreshAll">{{ t('ids_page.refresh') }}</el-button>
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
        <span>{{ t('ids_page.trend.title') }}</span>
      </template>
      <div style="height: 200px; position: relative" v-if="trendsData.length > 0">
        <div class="trend-chart">
          <div v-for="(item, idx) in trendsData" :key="idx" class="trend-bar-group">
            <div class="trend-bar-stack">
              <div
                class="trend-bar trend-critical"
                :style="{ height: (item.critical / maxTrend) * 140 + 'px' }"
                :title="severityLabel('critical') + ': ' + item.critical"
              ></div>
              <div
                class="trend-bar trend-warning"
                :style="{ height: (item.warning / maxTrend) * 140 + 'px' }"
                :title="severityLabel('warning') + ': ' + item.warning"
              ></div>
              <div
                class="trend-bar trend-info"
                :style="{ height: (item.info / maxTrend) * 140 + 'px' }"
                :title="severityLabel('info') + ': ' + item.info"
              ></div>
            </div>
            <div class="trend-date">{{ item.date.slice(5) }}</div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">{{ t('ids_page.trend.no_data') }}</div>
    </el-card>

    <!-- 主要内容：两个标签页 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 标签1: 告警监控 -->
        <el-tab-pane :label="t('ids_page.tabs.alerts')" name="alerts">
          <!-- 筛选栏 -->
          <el-row :gutter="12" class="mb-3">
            <el-col :span="4">
              <el-select v-model="filters.severity" :placeholder="t('ids_page.filters.severity')" clearable size="small" @change="fetchAlerts">
                <el-option :label="t('ids_page.filters.all')" value="" />
                <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-select v-model="filters.status" :placeholder="t('ids_page.filters.status')" clearable size="small" @change="fetchAlerts">
                <el-option :label="t('ids_page.filters.all')" value="" />
                <el-option v-for="(label, val) in statusOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-select v-model="filters.detection_type" :placeholder="t('ids_page.filters.detection_type')" clearable size="small" @change="fetchAlerts">
                <el-option :label="t('ids_page.filters.all')" value="" />
                <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-input v-model="filters.source_ip" :placeholder="t('ids_page.filters.source_ip')" size="small" clearable @change="fetchAlerts" />
            </el-col>
            <el-col :span="8" class="text-right">
              <el-button type="danger" size="small" plain @click="handleClearAlerts">{{ t('ids_page.row_actions.clear_old_alerts') }}</el-button>
            </el-col>
          </el-row>

          <!-- 告警表格 -->
          <el-table :data="alertsData" v-loading="loading.alerts" stripe style="width: 100%">
            <el-table-column prop="id" :label="t('ids_page.columns.id')" width="60" />
            <el-table-column :label="t('ids_page.columns.severity')" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTagType(row.severity)" size="small" effect="dark">
                  {{ severityLabel(row.severity) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.detection_type')" width="130">
              <template #default="{ row }">
                {{ detectionTypeLabel(row.detection_type) }}
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.rule')" min-width="150">
              <template #default="{ row }">
                {{ row.rule?.name || row.rule_name || '—' }}
              </template>
            </el-table-column>
            <el-table-column prop="source_ip" :label="t('ids_page.columns.source_ip')" width="140" />
            <el-table-column :label="t('ids_page.columns.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.time')" width="160">
              <template #default="{ row }">
                {{ formatTime(row.created_at) }}
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.actions')" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="viewAlert(row)">{{ t('ids_page.row_actions.details') }}</el-button>
                <el-dropdown v-if="row.status === 'open' || row.status === 'investigating'" trigger="click" @command="(cmd) => handleChangeStatus(row, cmd)">
                  <el-button size="small" link type="warning">{{ t('ids_page.row_actions.handle') }}</el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="investigating">{{ t('ids_page.row_actions.start_investigation') }}</el-dropdown-item>
                      <el-dropdown-item command="mitigated">{{ t('ids_page.row_actions.mark_mitigated') }}</el-dropdown-item>
                      <el-dropdown-item command="false_positive">{{ t('ids_page.row_actions.mark_false_positive') }}</el-dropdown-item>
                      <el-dropdown-item command="closed">{{ t('ids_page.row_actions.close_alert') }}</el-dropdown-item>
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
        <el-tab-pane :label="t('ids_page.tabs.rules')" name="rules">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="ruleFilters.detection_type" :placeholder="t('ids_page.filters.detection_type')" clearable size="small" @change="fetchRules">
                <el-option :label="t('ids_page.filters.all')" value="" />
                <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-select v-model="ruleFilters.is_active" :placeholder="t('ids_page.filters.status')" clearable size="small" @change="fetchRules">
                <el-option :label="t('ids_page.filters.all')" value="" />
                <el-option :label="t('ids_page.filters.enabled')" :value="true" />
                <el-option :label="t('ids_page.filters.disabled')" :value="false" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-input v-model="ruleFilters.search" :placeholder="t('ids_page.filters.search_rules')" size="small" clearable @change="fetchRules" />
            </el-col>
            <el-col :span="6" class="text-right">
              <el-button size="small" @click="dialogRuleVisible = true; isEditingRule = false; resetRuleForm()">{{ t('ids_page.row_actions.create_rule') }}</el-button>
              <el-button size="small" plain @click="handleSeedRules">{{ t('ids_page.row_actions.seed_rules') }}</el-button>
            </el-col>
          </el-row>

          <el-table :data="rulesData" v-loading="loading.rules" stripe style="width: 100%">
            <el-table-column prop="id" :label="t('ids_page.columns.id')" width="60" />
            <el-table-column :label="t('ids_page.columns.name')" min-width="180">
              <template #default="{ row }">
                <span>{{ row.name }}</span>
                <el-tag v-if="row.is_system" size="small" type="info" class="ml-1">{{ t('ids_page.tags.system') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.detection_type')" width="130">
              <template #default="{ row }">
                {{ detectionTypeLabel(row.detection_type) }}
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.severity')" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTagType(row.severity)" size="small" effect="dark">
                  {{ severityLabel(row.severity) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.threshold')" width="100">
              <template #default="{ row }">
                {{ t('ids_page.threshold_format', { count: row.threshold_count, minutes: row.threshold_window_minutes }) }}
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.hits')" width="70">
              <template #default="{ row }">
                {{ row.hit_count || 0 }}
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="(v) => toggleRuleActive(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column :label="t('ids_page.columns.actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="editRule(row)">{{ t('actions.edit') }}</el-button>
                <el-button v-if="!row.is_system" size="small" link type="danger" @click="handleDeleteRule(row)">{{ t('actions.delete') }}</el-button>
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
    <el-dialog v-model="dialogAlertVisible" :title="t('ids_page.alert_detail.title')" width="640px" :close-on-click-modal="false">
      <div v-if="currentAlert">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('ids_page.alert_detail.alert_id')">{{ currentAlert.id }}</el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.columns.detection_type')">{{ detectionTypeLabel(currentAlert.detection_type) }}</el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.columns.severity')">
            <el-tag :type="severityTagType(currentAlert.severity)" size="small" effect="dark">
              {{ severityLabel(currentAlert.severity) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.columns.status')">
            <el-tag :type="statusTagType(currentAlert.status)" size="small">{{ statusLabel(currentAlert.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.alert_detail.rule_name')">{{ currentAlert.rule_name || '—' }}</el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.columns.source_ip')">{{ currentAlert.source_ip || '—' }}</el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.alert_detail.target_resource')">{{ currentAlert.target_resource || '—' }}</el-descriptions-item>
          <el-descriptions-item :label="t('ids_page.alert_detail.created_at')">{{ formatTime(currentAlert.created_at) }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4 class="mb-2">{{ t('ids_page.alert_detail.evidence') }}</h4>
        <pre class="evidence-json">{{ JSON.stringify(currentAlert.evidence, null, 2) }}</pre>
      </div>
      <template #footer>
        <el-button @click="dialogAlertVisible = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>

    <!-- 规则编辑对话框 -->
    <el-dialog v-model="dialogRuleVisible" :title="isEditingRule ? t('ids_page.rule_dialog.edit_title') : t('ids_page.rule_dialog.create_title')" width="640px" :close-on-click-modal="false">
      <el-form :model="ruleForm" label-width="120px" size="small">
        <el-form-item :label="t('ids_page.columns.name')" required>
          <el-input v-model="ruleForm.name" :placeholder="t('ids_page.rule_dialog.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('ids_page.columns.detection_type')" required>
          <el-select v-model="ruleForm.detection_type" :placeholder="t('ids_page.rule_dialog.select_detection_type')" style="width: 100%">
            <el-option v-for="(label, val) in detectionTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ids_page.columns.severity')" required>
          <el-select v-model="ruleForm.severity" :placeholder="t('ids_page.rule_dialog.select_severity')" style="width: 100%">
            <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ids_page.rule_dialog.threshold_count')">
          <el-input-number v-model="ruleForm.threshold_count" :min="1" :max="10000" />
        </el-form-item>
        <el-form-item :label="t('ids_page.rule_dialog.threshold_window')">
          <el-input-number v-model="ruleForm.threshold_window_minutes" :min="0" :max="1440" />
        </el-form-item>
        <el-form-item :label="t('ids_page.rule_dialog.priority')">
          <el-input-number v-model="ruleForm.priority" :min="0" :max="999" />
        </el-form-item>
        <el-form-item :label="t('ids_page.rule_dialog.conditions')">
          <el-input v-model="conditionsText" type="textarea" :rows="4" placeholder='{"event_type": "login_failed", "group_by": "ip_address"}' />
        </el-form-item>
        <el-form-item :label="t('ids_page.rule_dialog.actions_field')">
          <el-input v-model="actionsText" type="textarea" :rows="4" placeholder='[{"type": "block_ip", "duration_minutes": 30}]' />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogRuleVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveRule">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import idsApi from '../../api/ids'

const { t, locale } = useI18n()

const detectionTypeKeys = ['brute_force', 'geo_anomaly', 'rate_burst', 'suspicious_pattern', 'ip_reputation', 'credential_stuffing']
const statusKeys = ['open', 'investigating', 'mitigated', 'false_positive', 'closed']
const severityKeys = ['critical', 'warning', 'info']

// ─── 状态 ───
const activeTab = ref('alerts')
const dashboardData = ref({})
const trendsData = ref([])
const alertsData = ref([])
const alertsPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const rulesData = ref([])
const rulesPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const rawDetectionTypes = ref({})
const rawStatusOptions = ref({})
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

const severityOptions = computed(() =>
  severityKeys.map((value) => ({
    value,
    label: severityLabel(value),
  })),
)

const detectionTypeOptions = computed(() => {
  const keys = [...new Set([...detectionTypeKeys, ...Object.keys(rawDetectionTypes.value)])]
  return Object.fromEntries(keys.map((key) => [key, detectionTypeLabel(key)]))
})

const statusOptions = computed(() => {
  const keys = [...new Set([...statusKeys, ...Object.keys(rawStatusOptions.value)])]
  return Object.fromEntries(keys.map((key) => [key, statusLabel(key)]))
})

// ─── 统计卡片 ───
const statCards = computed(() => [
  { label: t('ids_page.stats.open_alerts'), value: dashboardData.value?.open_alerts ?? 0, color: '#f56c6c' },
  { label: t('ids_page.stats.critical_alerts'), value: dashboardData.value?.critical_alerts ?? 0, color: '#e63946' },
  { label: t('ids_page.stats.today_alerts'), value: dashboardData.value?.today_alerts ?? 0, color: '#e6a23c' },
  { label: t('ids_page.stats.total_alerts'), value: dashboardData.value?.total_alerts ?? 0, color: '#0f172a' },
  { label: t('ids_page.stats.active_rules'), value: dashboardData.value?.rule_stats?.active ?? 0, color: '#67c23a' },
  { label: t('ids_page.stats.system_rules'), value: dashboardData.value?.rule_stats?.system ?? 0, color: '#909399' },
])

function localizedKey(prefix, value) {
  const key = `${prefix}.${value}`
  return t(key) !== key ? t(key) : value
}

function detectionTypeLabel(type) {
  return localizedKey('ids_page.detection_types', type)
}

function statusLabel(status) {
  return localizedKey('ids_page.statuses', status)
}

function severityLabel(severity) {
  return localizedKey('ids_page.severity', severity)
}

// ─── 方法 ───
function severityTagType(severity) {
  return severity === 'critical' ? 'danger' : severity === 'warning' ? 'warning' : 'info'
}
function statusTagType(status) {
  return status === 'open' ? 'danger' : status === 'investigating' ? 'warning' : status === 'mitigated' ? 'success' : status === 'false_positive' ? 'info' : ''
}
function formatTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'
  return d.toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
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
    console.error('fetchDashboard failed', e)
  } finally {
    loading.value.dashboard = false
  }
}

async function fetchTrends() {
  try {
    const res = await idsApi.trends(7)
    trendsData.value = res.data || []
  } catch (e) {
    console.error('fetchTrends failed', e)
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
    console.error('fetchAlerts failed', e)
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
    console.error('fetchRules failed', e)
  } finally {
    loading.value.rules = false
  }
}

async function fetchReferences() {
  try {
    const typesRes = await idsApi.detectionTypes()
    rawDetectionTypes.value = typesRes.data?.types || {}
    const statusRes = await idsApi.alertStatuses()
    rawStatusOptions.value = statusRes.data?.statuses || {}
  } catch (e) {
    console.error('fetchReferences failed', e)
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
    ElMessage.success(res.message || t('ids_page.messages.status_updated'))
    fetchAlerts()
    fetchDashboard()
  } catch (e) {
    ElMessage.error(t('ids_page.messages.update_failed'))
  }
}

async function handleClearAlerts() {
  try {
    await ElMessageBox.confirm(t('ids_page.confirm.clear_alerts'), t('actions.confirm'), { type: 'warning' })
    const res = await idsApi.clearAlerts('30 days')
    ElMessage.success(res.message || t('ids_page.messages.cleared'))
    fetchAlerts()
    fetchDashboard()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('ids_page.messages.clear_failed'))
  }
}

async function handleSeedRules() {
  try {
    const res = await idsApi.seedRules()
    ElMessage.success(res.message || t('ids_page.messages.rules_seeded'))
    fetchRules()
    fetchDashboard()
  } catch (e) {
    ElMessage.error(t('ids_page.messages.seed_failed'))
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
    ElMessage.error(t('ids_page.messages.json_invalid'))
    return
  }

  try {
    if (isEditingRule.value && editingRuleId.value) {
      const res = await idsApi.updateRule(editingRuleId.value, ruleForm.value)
      ElMessage.success(res.message || t('ids_page.messages.rule_updated'))
    } else {
      const res = await idsApi.storeRule(ruleForm.value)
      ElMessage.success(res.message || t('ids_page.messages.rule_created'))
    }
    dialogRuleVisible.value = false
    fetchRules()
  } catch (e) {
    ElMessage.error(t('ids_page.messages.save_failed'))
  }
}

async function handleDeleteRule(row) {
  try {
    await ElMessageBox.confirm(t('ids_page.confirm.delete_rule', { name: row.name }), t('actions.confirm'), { type: 'warning' })
    await idsApi.deleteRule(row.id)
    ElMessage.success(t('ids_page.messages.deleted'))
    fetchRules()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('ids_page.messages.delete_failed'))
  }
}

async function toggleRuleActive(row, isActive) {
  try {
    await idsApi.updateRule(row.id, { is_active: isActive })
    ElMessage.success(isActive ? t('ids_page.messages.rule_enabled') : t('ids_page.messages.rule_disabled'))
    fetchRules()
  } catch (e) {
    ElMessage.error(t('messages.failed'))
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
.trend-info { background: #0f172a; }
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
