<template>
  <div>
    <!-- 标题 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">{{ t('contracts_page.title') }}</span>
          <el-tag type="success" size="small" class="ml-2" v-if="dashboardData.active_contracts > 0">
            {{ t('contracts_page.active_contracts_tag', { n: dashboardData.active_contracts }) }}
          </el-tag>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button size="small" @click="refreshAll">{{ t('contracts_page.refresh') }}</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4" v-for="stat in statCards" :key="stat.label">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 评估趋势图 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span>{{ t('contracts_page.trend_title') }}</span>
      </template>
      <div style="height: 180px; position: relative" v-if="trendsData.length > 0">
        <div class="trend-chart">
          <div v-for="(item, idx) in trendsData" :key="idx" class="trend-bar-group">
            <div class="trend-bar-stack">
              <div
                class="trend-bar trend-granted"
                :style="{ height: (item.granted / maxTrend) * 130 + 'px' }"
                :title="t('contracts_page.trend_granted', { n: item.granted })"
              ></div>
              <div
                class="trend-bar trend-denied"
                :style="{ height: (item.denied / maxTrend) * 130 + 'px' }"
                :title="t('contracts_page.trend_denied', { n: item.denied })"
              ></div>
            </div>
            <div class="trend-date">{{ item.date.slice(5) }}</div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">{{ t('contracts_page.no_trend_data') }}</div>
    </el-card>

    <!-- 主要内容 Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab1: 合约管理 -->
        <el-tab-pane :label="t('contracts_page.tabs.contracts')" name="contracts">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="contractFilters.contract_type" :placeholder="t('contracts_page.filters.contract_type')" clearable size="small" @change="fetchContracts">
                <el-option :label="t('contracts_page.filters.all')" value="" />
                <el-option v-for="(label, val) in contractTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-input v-model="contractFilters.search" :placeholder="t('contracts_page.filters.search_contracts')" size="small" clearable @change="fetchContracts" />
            </el-col>
            <el-col :span="12" class="text-right">
              <el-button size="small" @click="dialogContractVisible = true; isEditingContract = false; resetContractForm()">{{ t('contracts_page.buttons.new_contract') }}</el-button>
              <el-button size="small" plain @click="handleSeedContracts">{{ t('contracts_page.buttons.seed_contracts') }}</el-button>
            </el-col>
          </el-row>

          <el-table :data="contractsData" v-loading="loading.contracts" stripe style="width: 100%">
            <el-table-column prop="id" :label="t('contracts_page.columns.id')" width="60" />
            <el-table-column :label="t('contracts_page.columns.name')" min-width="160">
              <template #default="{ row }">
                <span>{{ row.name }}</span>
                <el-tag v-if="row.is_system" size="small" type="info" class="ml-1">{{ t('contracts_page.tags.system') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.type')" width="100">
              <template #default="{ row }">
                {{ contractTypeOptions[row.contract_type] || row.contract_type }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.eval_mode')" width="100">
              <template #default="{ row }">
                <el-tag size="small">{{ evalModeLabel(row.evaluation_mode) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.conditions_count')" width="80">
              <template #default="{ row }">
                {{ row.conditions?.length || 0 }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.assignments_count')" width="80">
              <template #default="{ row }">
                {{ row.assignments_count || 0 }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.version')" width="60">
              <template #default="{ row }">
                v{{ row.version }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="(v) => toggleContractActive(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.actions')" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="editContract(row)">{{ t('actions.edit') }}</el-button>
                <el-button size="small" link type="success" @click="testContract(row)">{{ t('contracts_page.buttons.test') }}</el-button>
                <el-button v-if="!row.is_system" size="small" link type="danger" @click="handleDeleteContract(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="flex justify-center mt-4" v-if="contractsPagination.last_page > 1">
            <el-pagination background layout="prev, pager, next" :total="contractsPagination.total" :page-size="contractsPagination.per_page" :current-page="contractsPagination.current_page" @current-change="(p) => { contractsPagination.current_page = p; fetchContracts(); }" />
          </div>
        </el-tab-pane>

        <!-- Tab2: 合约分配 -->
        <el-tab-pane :label="t('contracts_page.tabs.assignments')" name="assignments">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="assignmentContractId" :placeholder="t('contracts_page.filters.select_contract')" size="small" clearable @change="fetchAssignments">
                <el-option v-for="c in contractsData" :key="c.id" :label="c.name" :value="c.id" />
              </el-select>
            </el-col>
            <el-col :span="18" class="text-right">
              <el-button size="small" :disabled="!assignmentContractId" @click="dialogAssignmentVisible = true; resetAssignmentForm()">{{ t('contracts_page.buttons.new_assignment') }}</el-button>
            </el-col>
          </el-row>

          <el-table :data="assignmentsData" v-loading="loading.assignments" stripe style="width: 100%">
            <el-table-column :label="t('contracts_page.columns.contract')" width="160">
              <template #default="{ row }">
                {{ row.contract?.name || '—' }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.assignable')" min-width="180">
              <template #default="{ row }">
                <el-tag size="small">{{ row.assignable_type }} #{{ row.assignable_id }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.effective_time')" width="160">
              <template #default="{ row }">
                {{ row.effective_from ? formatTime(row.effective_from) : t('contracts_page.effective.immediate') }}
                ~ {{ row.effective_until ? formatTime(row.effective_until) : t('contracts_page.effective.permanent') }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_enabled" @change="(v) => toggleAssignment(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.actions')" width="120">
              <template #default="{ row }">
                <el-button size="small" link type="danger" @click="handleDeleteAssignment(row)">{{ t('contracts_page.buttons.remove') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab3: 评估日志 -->
        <el-tab-pane :label="t('contracts_page.tabs.logs')" name="logs">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="4">
              <el-select v-model="logFilters.result" :placeholder="t('contracts_page.filters.result')" clearable size="small" @change="fetchLogs">
                <el-option v-for="opt in logResultOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-col>
            <el-col :span="20" class="text-right">
            </el-col>
          </el-row>

          <el-table :data="logsData" v-loading="loading.logs" stripe style="width: 100%">
            <el-table-column prop="id" :label="t('contracts_page.columns.id')" width="60" />
            <el-table-column :label="t('contracts_page.columns.contract')" min-width="160">
              <template #default="{ row }">
                {{ row.contract?.name || row.contract_name || '—' }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.evaluatable')" width="150">
              <template #default="{ row }">
                {{ row.evaluatable_type }} #{{ row.evaluatable_id }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.result')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.result === 'granted' ? 'success' : 'danger'" size="small">
                  {{ resultLabel(row.result) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.duration')" width="80">
              <template #default="{ row }">
                {{ row.evaluation_time_ms }}ms
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.time')" width="160">
              <template #default="{ row }">
                {{ formatTime(row.created_at) }}
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.reason')" min-width="200">
              <template #default="{ row }">
                {{ row.reason || '—' }}
              </template>
            </el-table-column>
          </el-table>

          <div class="flex justify-center mt-4" v-if="logsPagination.last_page > 1">
            <el-pagination background layout="prev, pager, next" :total="logsPagination.total" :page-size="logsPagination.per_page" :current-page="logsPagination.current_page" @current-change="(p) => { logsPagination.current_page = p; fetchLogs(); }" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 合约编辑对话框 -->
    <el-dialog v-model="dialogContractVisible" :title="isEditingContract ? t('contracts_page.contract_dialog.edit_title') : t('contracts_page.contract_dialog.create_title')" width="700px" :close-on-click-modal="false">
      <el-form :model="contractForm" label-width="120px" size="small">
        <el-form-item :label="t('contracts_page.contract_dialog.name')" required>
          <el-input v-model="contractForm.name" :placeholder="t('contracts_page.contract_dialog.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.type')" required>
          <el-select v-model="contractForm.contract_type" style="width: 100%">
            <el-option v-for="(label, val) in contractTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.description')">
          <el-input v-model="contractForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.eval_mode')">
          <el-select v-model="contractForm.evaluation_mode" style="width: 100%">
            <el-option v-for="opt in evalModeFormOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="contractForm.evaluation_mode === 'custom'" :label="t('contracts_page.contract_dialog.custom_expression')">
          <el-input v-model="contractForm.custom_expression" :placeholder="t('contracts_page.contract_dialog.custom_expression_ph')" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.conditions_json')">
          <el-input v-model="conditionsText" type="textarea" :rows="5" :placeholder="t('contracts_page.contract_dialog.conditions_ph')" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.grant_template_json')">
          <el-input v-model="grantTemplateText" type="textarea" :rows="3" :placeholder="t('contracts_page.contract_dialog.grant_template_ph')" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.contract_dialog.priority')">
          <el-input-number v-model="contractForm.priority" :min="0" :max="999" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogContractVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveContract">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新增分配对话框 -->
    <el-dialog v-model="dialogAssignmentVisible" :title="t('contracts_page.assignment_dialog.title')" width="500px" :close-on-click-modal="false">
      <el-form :model="assignmentForm" label-width="120px" size="small">
        <el-form-item :label="t('contracts_page.assignment_dialog.contract')">
          <el-select v-model="assignmentForm.contract_id" style="width: 100%" disabled>
            <el-option v-for="c in contractsData" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('contracts_page.assignment_dialog.entity_type')" required>
          <el-select v-model="assignmentForm.assignable_type" style="width: 100%">
            <el-option v-for="opt in entityTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('contracts_page.assignment_dialog.entity_id')" required>
          <el-input-number v-model="assignmentForm.assignable_id" :min="1" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.assignment_dialog.effective_from')">
          <el-date-picker v-model="assignmentForm.effective_from" type="datetime" :placeholder="t('contracts_page.effective.from_placeholder')" style="width: 100%" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.assignment_dialog.effective_until')">
          <el-date-picker v-model="assignmentForm.effective_until" type="datetime" :placeholder="t('contracts_page.effective.until_placeholder')" style="width: 100%" />
        </el-form-item>
        <el-form-item :label="t('contracts_page.assignment_dialog.params_json')">
          <el-input v-model="assignmentParamsText" type="textarea" :rows="3" :placeholder="t('contracts_page.assignment_dialog.params_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogAssignmentVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveAssignment">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 合约测试结果对话框 -->
    <el-dialog v-model="dialogTestVisible" :title="t('contracts_page.test_dialog.title')" width="600px" :close-on-click-modal="false">
      <div v-if="testResult">
        <el-alert
          :title="testResult.granted ? t('contracts_page.test_dialog.granted') : t('contracts_page.test_dialog.denied')"
          :type="testResult.granted ? 'success' : 'error'"
          :description="t('contracts_page.test_dialog.contract_info', { name: testResult.contract_name, slug: testResult.contract_slug })"
          show-icon
          class="mb-3"
        />

        <h4 class="mb-2">{{ t('contracts_page.test_dialog.conditions_detail') }}</h4>
        <div v-for="(cond, idx) in testResult.conditions_results" :key="idx" class="condition-row mb-1">
          <el-tag :type="cond.matched ? 'success' : 'danger'" size="small" class="mr-1">
            {{ cond.matched ? t('contracts_page.test_dialog.matched') : t('contracts_page.test_dialog.unmatched') }}
          </el-tag>
          <span class="condition-text">{{ cond.label || cond.type }}</span>
          <span class="text-gray-400 text-sm ml-2">
            ({{ cond.field }}: {{ JSON.stringify(cond.actual) }} {{ cond.operator }} {{ JSON.stringify(cond.expected) }})
          </span>
        </div>

        <p class="mt-2 text-sm text-gray-400">{{ t('contracts_page.test_dialog.duration', { ms: testResult.evaluation_time_ms }) }}</p>
      </div>
      <template #footer>
        <el-button @click="dialogTestVisible = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import contractsApi from '../../api/contracts'

const { t, locale } = useI18n()

const activeTab = ref('contracts')
const dashboardData = ref({})
const trendsData = ref([])
const contractTypeOptions = ref({})
const maxTrend = computed(() => {
  if (trendsData.value.length === 0) return 1
  return Math.max(...trendsData.value.map(d => d.total), 1)
})

const evalModeFormKeys = [
  { value: 'all', key: 'all_and' },
  { value: 'any', key: 'any_or' },
  { value: 'custom', key: 'custom_expr' },
]
const logResultKeys = [
  { value: '', key: 'all', ns: 'filters' },
  { value: 'granted', key: 'granted', ns: 'results' },
  { value: 'denied', key: 'denied', ns: 'results' },
]
const entityTypeKeys = [
  { value: 'App\\Models\\License', key: 'license' },
  { value: 'App\\Models\\User', key: 'user' },
  { value: 'App\\Models\\Product', key: 'product' },
  { value: 'App\\Models\\Tenant', key: 'tenant' },
]

const evalModeFormOptions = computed(() =>
  evalModeFormKeys.map(({ value, key }) => ({
    value,
    label: t(`contracts_page.eval_modes.${key}`),
  }))
)
const logResultOptions = computed(() =>
  logResultKeys.map(({ value, key, ns }) => ({
    value,
    label: t(`contracts_page.${ns}.${key}`),
  }))
)
const entityTypeOptions = computed(() =>
  entityTypeKeys.map(({ value, key }) => ({
    value,
    label: t(`contracts_page.entity_types.${key}`),
  }))
)

function evalModeLabel(mode) {
  const key = `contracts_page.eval_modes.${mode}`
  const label = t(key)
  return label !== key ? label : mode
}

function resultLabel(result) {
  const key = result === 'granted' ? 'contracts_page.results.grant' : 'contracts_page.results.deny'
  return t(key)
}

function dateLocale() {
  return locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
}

const loading = ref({ contracts: false, assignments: false, logs: false, dashboard: false })

// ─── 合约数据 ───
const contractsData = ref([])
const contractsPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const contractFilters = ref({ contract_type: '', search: '' })

// ─── 分配数据 ───
const assignmentContractId = ref(null)
const assignmentsData = ref([])

// ─── 日志数据 ───
const logsData = ref([])
const logsPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const logFilters = ref({ result: '' })

// ─── 弹窗 ───
const dialogContractVisible = ref(false)
const isEditingContract = ref(false)
const editingContractId = ref(null)
const contractForm = ref({
  name: '',
  contract_type: 'license',
  description: '',
  evaluation_mode: 'all',
  custom_expression: '',
  conditions: [{ type: 'time_window', operator: 'between', field: 'current_time', days: [1, 2, 3, 4, 5], start_time: '09:00', end_time: '18:00', label: t('contracts_page.work_hours') }],
  grant_template: null,
  priority: 100,
})
const conditionsText = ref(JSON.stringify(contractForm.value.conditions, null, 2))
const grantTemplateText = ref('')

const dialogAssignmentVisible = ref(false)
const assignmentForm = ref({ contract_id: null, assignable_type: 'App\\Models\\License', assignable_id: 1, effective_from: null, effective_until: null, parameters: null, is_enabled: true })
const assignmentParamsText = ref('')

const dialogTestVisible = ref(false)
const testResult = ref(null)

// ─── 统计卡片 ───
const statCards = computed(() => [
  { label: t('contracts_page.stats.total_contracts'), value: dashboardData.value?.total_contracts ?? 0, color: '#0f172a' },
  { label: t('contracts_page.stats.active_contracts'), value: dashboardData.value?.active_contracts ?? 0, color: '#67c23a' },
  { label: t('contracts_page.stats.system_contracts'), value: dashboardData.value?.system_contracts ?? 0, color: '#909399' },
  { label: t('contracts_page.stats.total_evaluations'), value: dashboardData.value?.total_evaluations ?? 0, color: '#e6a23c' },
  { label: t('contracts_page.stats.today_evaluations'), value: dashboardData.value?.today_evaluations ?? 0, color: '#0f172a' },
  { label: t('contracts_page.stats.grant_rate'), value: dashboardData.value?.total_evaluations ? Math.round(dashboardData.value.granted_count / dashboardData.value.total_evaluations * 100) + '%' : '—', color: '#67c23a' },
])

// ─── 方法 ───
function formatTime(time) {
  if (!time) return '—'
  const d = new Date(time)
  return d.toLocaleString(dateLocale(), { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}
function resetContractForm() {
  contractForm.value = { name: '', contract_type: 'license', description: '', evaluation_mode: 'all', custom_expression: '', conditions: [], grant_template: null, priority: 100 }
  conditionsText.value = '[]'
  grantTemplateText.value = ''
}
function resetAssignmentForm() {
  assignmentForm.value = { contract_id: assignmentContractId.value, assignable_type: 'App\\Models\\License', assignable_id: 1, effective_from: null, effective_until: null, parameters: null, is_enabled: true }
  assignmentParamsText.value = ''
}

// ─── 数据获取 ───
async function fetchDashboard() {
  try {
    const res = await contractsApi.dashboard()
    dashboardData.value = res.data || {}
  } catch (e) { console.error(e) }
}
async function fetchTrends() {
  try {
    const res = await contractsApi.trends(7)
    trendsData.value = res.data || []
  } catch (e) { console.error(e) }
}
async function fetchReferences() {
  try {
    const res = await contractsApi.types()
    contractTypeOptions.value = res.data?.contract_types || {}
  } catch (e) { console.error(e) }
}
async function fetchContracts() {
  loading.value.contracts = true
  try {
    const params = { ...contractFilters.value, page: contractsPagination.value.current_page, per_page: 20 }
    const res = await contractsApi.contracts(params)
    if (res.data) {
      contractsData.value = res.data.data || []
      contractsPagination.value = { current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total, per_page: res.data.per_page }
    }
  } catch (e) { console.error(e) }
  finally { loading.value.contracts = false }
}
async function fetchAssignments() {
  if (!assignmentContractId.value) return
  loading.value.assignments = true
  try {
    const res = await contractsApi.assignments(assignmentContractId.value)
    assignmentsData.value = res.data || []
  } catch (e) { console.error(e) }
  finally { loading.value.assignments = false }
}
async function fetchLogs() {
  loading.value.logs = true
  try {
    const params = { ...logFilters.value, page: logsPagination.value.current_page, per_page: 20 }
    const res = await contractsApi.evaluationLogs(params)
    if (res.data) {
      logsData.value = res.data.data || []
      logsPagination.value = { current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total, per_page: res.data.per_page }
    }
  } catch (e) { console.error(e) }
  finally { loading.value.logs = false }
}

// ─── 操作 ───
async function saveContract() {
  try {
    contractForm.value.conditions = JSON.parse(conditionsText.value)
    contractForm.value.grant_template = grantTemplateText.value ? JSON.parse(grantTemplateText.value) : null
  } catch (e) {
    ElMessage.error(t('contracts_page.messages.json_conditions_error'))
    return
  }

  try {
    if (isEditingContract.value && editingContractId.value) {
      const res = await contractsApi.updateContract(editingContractId.value, contractForm.value)
      ElMessage.success(res.message || t('contracts_page.messages.contract_updated'))
    } else {
      const res = await contractsApi.storeContract(contractForm.value)
      ElMessage.success(res.message || t('contracts_page.messages.contract_created'))
    }
    dialogContractVisible.value = false
    fetchContracts()
    fetchDashboard()
  } catch (e) {
    ElMessage.error(t('contracts_page.messages.save_failed'))
  }
}

function editContract(row) {
  isEditingContract.value = true
  editingContractId.value = row.id
  contractForm.value = { name: row.name, contract_type: row.contract_type, description: row.description || '', evaluation_mode: row.evaluation_mode, custom_expression: row.custom_expression || '', conditions: row.conditions || [], grant_template: row.grant_template, priority: row.priority }
  conditionsText.value = JSON.stringify(contractForm.value.conditions, null, 2)
  grantTemplateText.value = row.grant_template ? JSON.stringify(row.grant_template, null, 2) : ''
  dialogContractVisible.value = true
}

async function toggleContractActive(row, isActive) {
  try {
    await contractsApi.updateContract(row.id, { is_active: isActive })
    ElMessage.success(isActive ? t('contracts_page.messages.contract_enabled') : t('contracts_page.messages.contract_disabled'))
    fetchContracts()
  } catch (e) { ElMessage.error(t('contracts_page.messages.operation_failed')) }
}

async function handleDeleteContract(row) {
  try {
    await ElMessageBox.confirm(t('contracts_page.confirm.delete_contract', { name: row.name }), t('actions.confirm'), { type: 'warning' })
    await contractsApi.deleteContract(row.id)
    ElMessage.success(t('contracts_page.messages.deleted'))
    fetchContracts()
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('contracts_page.messages.delete_failed')) }
}

async function handleSeedContracts() {
  try {
    const res = await contractsApi.seedContracts()
    ElMessage.success(res.message || t('contracts_page.messages.seed_done'))
    fetchContracts()
    fetchDashboard()
  } catch (e) { ElMessage.error(t('contracts_page.messages.seed_failed')) }
}

async function testContract(row) {
  try {
    const res = await contractsApi.evaluateContract(row.id, { current_time: new Date().toLocaleTimeString(dateLocale(), { hour12: false }), current_day: new Date().getDay() || 7 })
    testResult.value = res.data
    dialogTestVisible.value = true
  } catch (e) { ElMessage.error(t('contracts_page.messages.test_failed')) }
}

async function saveAssignment() {
  try {
    assignmentForm.value.parameters = assignmentParamsText.value ? JSON.parse(assignmentParamsText.value) : null
  } catch (e) {
    ElMessage.error(t('contracts_page.messages.json_params_error'))
    return
  }
  try {
    const res = await contractsApi.storeAssignment(assignmentForm.value)
    ElMessage.success(res.message || t('contracts_page.messages.assignment_success'))
    dialogAssignmentVisible.value = false
    fetchAssignments()
  } catch (e) { ElMessage.error(t('contracts_page.messages.assignment_failed')) }
}

async function toggleAssignment(row, isEnabled) {
  try {
    await contractsApi.updateAssignment(row.id, { is_enabled: isEnabled })
    ElMessage.success(t('contracts_page.messages.status_updated'))
    fetchAssignments()
  } catch (e) { ElMessage.error(t('contracts_page.messages.operation_failed')) }
}

async function handleDeleteAssignment(row) {
  try {
    await ElMessageBox.confirm(t('contracts_page.confirm.remove_assignment'), t('actions.confirm'), { type: 'warning' })
    await contractsApi.deleteAssignment(row.id)
    ElMessage.success(t('contracts_page.messages.removed'))
    fetchAssignments()
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('contracts_page.messages.remove_failed')) }
}

function refreshAll() {
  fetchDashboard()
  fetchTrends()
  if (activeTab.value === 'contracts') fetchContracts()
  else if (activeTab.value === 'assignments') fetchAssignments()
  else fetchLogs()
}

watch(activeTab, (tab) => {
  if (tab === 'contracts') fetchContracts()
  else if (tab === 'assignments') fetchAssignments()
  else fetchLogs()
})

watch(assignmentContractId, () => { fetchAssignments() })

onMounted(() => {
  fetchDashboard()
  fetchTrends()
  fetchContracts()
  fetchReferences()
  fetchLogs()
})
</script>

<style scoped>
.stat-card { text-align: center; border-left: 3px solid transparent; }
.stat-value { font-size: 26px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mb-1 { margin-bottom: 4px; }
.ml-2 { margin-left: 8px; }
.ml-1 { margin-left: 4px; }
.mr-1 { margin-right: 4px; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-gray-400 { color: #c0c4cc; }
.text-sm { font-size: 12px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.flex { display: flex; }
.justify-center { justify-content: center; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }

.trend-chart { display: flex; align-items: flex-end; justify-content: space-around; height: 160px; gap: 8px; }
.trend-bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; }
.trend-bar-stack { flex: 1; display: flex; flex-direction: column-reverse; width: 24px; gap: 1px; }
.trend-bar { width: 100%; border-radius: 3px 3px 0 0; min-height: 2px; transition: height 0.3s; }
.trend-granted { background: #67c23a; }
.trend-denied { background: #f56c6c; }
.trend-date { font-size: 11px; color: #909399; margin-top: 4px; }

.condition-row { display: flex; align-items: center; }
.condition-text { font-size: 13px; }
</style>
