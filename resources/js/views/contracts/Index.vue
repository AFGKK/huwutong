<template>
  <div>
    <!-- 标题 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">智能合约式授权</span>
          <el-tag type="success" size="small" class="ml-2" v-if="dashboardData.active_contracts > 0">
            {{ dashboardData.active_contracts }} 个活跃合约
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
        <el-card shadow="never" class="stat-card">
          <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 评估趋势图 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span>评估趋势（近7天）</span>
      </template>
      <div style="height: 180px; position: relative" v-if="trendsData.length > 0">
        <div class="trend-chart">
          <div v-for="(item, idx) in trendsData" :key="idx" class="trend-bar-group">
            <div class="trend-bar-stack">
              <div
                class="trend-bar trend-granted"
                :style="{ height: (item.granted / maxTrend) * 130 + 'px' }"
                :title="'已授权: ' + item.granted"
              ></div>
              <div
                class="trend-bar trend-denied"
                :style="{ height: (item.denied / maxTrend) * 130 + 'px' }"
                :title="'已拒绝: ' + item.denied"
              ></div>
            </div>
            <div class="trend-date">{{ item.date.slice(5) }}</div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">暂无评估数据</div>
    </el-card>

    <!-- 主要内容 Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab1: 合约管理 -->
        <el-tab-pane label="授权合约" name="contracts">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="contractFilters.contract_type" placeholder="合约类型" clearable size="small" @change="fetchContracts">
                <el-option label="全部" value="" />
                <el-option v-for="(label, val) in contractTypeOptions" :key="val" :label="label" :value="val" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-input v-model="contractFilters.search" placeholder="搜索合约..." size="small" clearable @change="fetchContracts" />
            </el-col>
            <el-col :span="12" class="text-right">
              <el-button size="small" @click="dialogContractVisible = true; isEditingContract = false; resetContractForm()">新建合约</el-button>
              <el-button size="small" plain @click="handleSeedContracts">播种系统合约</el-button>
            </el-col>
          </el-row>

          <el-table :data="contractsData" v-loading="loading.contracts" stripe style="width: 100%">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="合约名称" min-width="160">
              <template #default="{ row }">
                <span>{{ row.name }}</span>
                <el-tag v-if="row.is_system" size="small" type="info" class="ml-1">系统</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="类型" width="100">
              <template #default="{ row }">
                {{ contractTypeOptions[row.contract_type] || row.contract_type }}
              </template>
            </el-table-column>
            <el-table-column label="评估模式" width="100">
              <template #default="{ row }">
                <el-tag size="small">{{ row.evaluation_mode === 'all' ? '全部满足' : row.evaluation_mode === 'any' ? '任一满足' : '自定义' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="条件数" width="80">
              <template #default="{ row }">
                {{ row.conditions?.length || 0 }}
              </template>
            </el-table-column>
            <el-table-column label="分配数" width="80">
              <template #default="{ row }">
                {{ row.assignments_count || 0 }}
              </template>
            </el-table-column>
            <el-table-column label="版本" width="60">
              <template #default="{ row }">
                v{{ row.version }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="(v) => toggleContractActive(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="editContract(row)">编辑</el-button>
                <el-button size="small" link type="success" @click="testContract(row)">测试</el-button>
                <el-button v-if="!row.is_system" size="small" link type="danger" @click="handleDeleteContract(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="flex justify-center mt-4" v-if="contractsPagination.last_page > 1">
            <el-pagination background layout="prev, pager, next" :total="contractsPagination.total" :page-size="contractsPagination.per_page" :current-page="contractsPagination.current_page" @current-change="(p) => { contractsPagination.current_page = p; fetchContracts(); }" />
          </div>
        </el-tab-pane>

        <!-- Tab2: 合约分配 -->
        <el-tab-pane label="合约分配" name="assignments">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="6">
              <el-select v-model="assignmentContractId" placeholder="选择合约" size="small" clearable @change="fetchAssignments">
                <el-option v-for="c in contractsData" :key="c.id" :label="c.name" :value="c.id" />
              </el-select>
            </el-col>
            <el-col :span="18" class="text-right">
              <el-button size="small" :disabled="!assignmentContractId" @click="dialogAssignmentVisible = true; resetAssignmentForm()">新增分配</el-button>
            </el-col>
          </el-row>

          <el-table :data="assignmentsData" v-loading="loading.assignments" stripe style="width: 100%">
            <el-table-column label="合约" width="160">
              <template #default="{ row }">
                {{ row.contract?.name || '—' }}
              </template>
            </el-table-column>
            <el-table-column label="分配对象" min-width="180">
              <template #default="{ row }">
                <el-tag size="small">{{ row.assignable_type }} #{{ row.assignable_id }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="生效时间" width="160">
              <template #default="{ row }">
                {{ row.effective_from ? formatTime(row.effective_from) : '即时' }}
                ~ {{ row.effective_until ? formatTime(row.effective_until) : '永久' }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_enabled" @change="(v) => toggleAssignment(row, v)" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button size="small" link type="danger" @click="handleDeleteAssignment(row)">移除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab3: 评估日志 -->
        <el-tab-pane label="评估日志" name="logs">
          <el-row :gutter="12" class="mb-3">
            <el-col :span="4">
              <el-select v-model="logFilters.result" placeholder="结果" clearable size="small" @change="fetchLogs">
                <el-option label="全部" value="" />
                <el-option label="已授权" value="granted" />
                <el-option label="已拒绝" value="denied" />
              </el-select>
            </el-col>
            <el-col :span="20" class="text-right">
            </el-col>
          </el-row>

          <el-table :data="logsData" v-loading="loading.logs" stripe style="width: 100%">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="合约" min-width="160">
              <template #default="{ row }">
                {{ row.contract?.name || row.contract_name || '—' }}
              </template>
            </el-table-column>
            <el-table-column label="评估对象" width="150">
              <template #default="{ row }">
                {{ row.evaluatable_type }} #{{ row.evaluatable_id }}
              </template>
            </el-table-column>
            <el-table-column label="结果" width="80">
              <template #default="{ row }">
                <el-tag :type="row.result === 'granted' ? 'success' : 'danger'" size="small">
                  {{ row.result === 'granted' ? '授权' : '拒绝' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="耗时" width="80">
              <template #default="{ row }">
                {{ row.evaluation_time_ms }}ms
              </template>
            </el-table-column>
            <el-table-column label="时间" width="160">
              <template #default="{ row }">
                {{ formatTime(row.created_at) }}
              </template>
            </el-table-column>
            <el-table-column label="原因" min-width="200">
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
    <el-dialog v-model="dialogContractVisible" :title="isEditingContract ? '编辑合约' : '新建合约'" width="700px" :close-on-click-modal="false">
      <el-form :model="contractForm" label-width="120px" size="small">
        <el-form-item label="合约名称" required>
          <el-input v-model="contractForm.name" placeholder="合约名称" />
        </el-form-item>
        <el-form-item label="合约类型" required>
          <el-select v-model="contractForm.contract_type" style="width: 100%">
            <el-option v-for="(label, val) in contractTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="contractForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="评估模式">
          <el-select v-model="contractForm.evaluation_mode" style="width: 100%">
            <el-option label="全部满足 (AND)" value="all" />
            <el-option label="任一满足 (OR)" value="any" />
            <el-option label="自定义表达式" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="contractForm.evaluation_mode === 'custom'" label="自定义表达式">
          <el-input v-model="contractForm.custom_expression" placeholder="如: cond_0 && (cond_1 || cond_2)" />
        </el-form-item>
        <el-form-item label="条件配置 (JSON)">
          <el-input v-model="conditionsText" type="textarea" :rows="5" placeholder='[{"type": "time_window", "operator": "between", "field": "current_time", "days": [1,2,3,4,5], "start_time": "09:00", "end_time": "18:00", "label": "工作时间"}]' />
        </el-form-item>
        <el-form-item label="授权模板 (JSON)">
          <el-input v-model="grantTemplateText" type="textarea" :rows="3" placeholder='{"features": ["api_access"]}' />
        </el-form-item>
        <el-form-item label="优先级">
          <el-input-number v-model="contractForm.priority" :min="0" :max="999" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogContractVisible = false">取消</el-button>
        <el-button type="primary" @click="saveContract">保存</el-button>
      </template>
    </el-dialog>

    <!-- 新增分配对话框 -->
    <el-dialog v-model="dialogAssignmentVisible" title="新增合约分配" width="500px" :close-on-click-modal="false">
      <el-form :model="assignmentForm" label-width="120px" size="small">
        <el-form-item label="合约">
          <el-select v-model="assignmentForm.contract_id" style="width: 100%" disabled>
            <el-option v-for="c in contractsData" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="实体类型" required>
          <el-select v-model="assignmentForm.assignable_type" style="width: 100%">
            <el-option label="License" value="App\Models\License" />
            <el-option label="用户" value="App\Models\User" />
            <el-option label="产品" value="App\Models\Product" />
            <el-option label="租户" value="App\Models\Tenant" />
          </el-select>
        </el-form-item>
        <el-form-item label="实体ID" required>
          <el-input-number v-model="assignmentForm.assignable_id" :min="1" />
        </el-form-item>
        <el-form-item label="生效时间">
          <el-date-picker v-model="assignmentForm.effective_from" type="datetime" placeholder="立即生效" style="width: 100%" />
        </el-form-item>
        <el-form-item label="失效时间">
          <el-date-picker v-model="assignmentForm.effective_until" type="datetime" placeholder="永不过期" style="width: 100%" />
        </el-form-item>
        <el-form-item label="参数 (JSON)">
          <el-input v-model="assignmentParamsText" type="textarea" :rows="3" placeholder='{"key": "value"}' />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogAssignmentVisible = false">取消</el-button>
        <el-button type="primary" @click="saveAssignment">保存</el-button>
      </template>
    </el-dialog>

    <!-- 合约测试结果对话框 -->
    <el-dialog v-model="dialogTestVisible" title="合约评估测试结果" width="600px" :close-on-click-modal="false">
      <div v-if="testResult">
        <el-alert :title="testResult.granted ? '授权通过 ✓' : '授权拒绝 ✗'" :type="testResult.granted ? 'success' : 'error'" :description="`合约: ${testResult.contract_name} (${testResult.contract_slug})`" show-icon class="mb-3" />

        <h4 class="mb-2">条件评估详情</h4>
        <div v-for="(cond, idx) in testResult.conditions_results" :key="idx" class="condition-row mb-1">
          <el-tag :type="cond.matched ? 'success' : 'danger'" size="small" class="mr-1">
            {{ cond.matched ? '✓' : '✗' }}
          </el-tag>
          <span class="condition-text">{{ cond.label || cond.type }}</span>
          <span class="text-gray-400 text-sm ml-2">
            ({{ cond.field }}: {{ JSON.stringify(cond.actual) }} {{ cond.operator }} {{ JSON.stringify(cond.expected) }})
          </span>
        </div>

        <p class="mt-2 text-sm text-gray-400">耗时: {{ testResult.evaluation_time_ms }}ms</p>
      </div>
      <template #footer>
        <el-button @click="dialogTestVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import contractsApi from '../../api/contracts'

const activeTab = ref('contracts')
const dashboardData = ref({})
const trendsData = ref([])
const contractTypeOptions = ref({})
const maxTrend = computed(() => {
  if (trendsData.value.length === 0) return 1
  return Math.max(...trendsData.value.map(d => d.total), 1)
})

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
  conditions: [{ type: 'time_window', operator: 'between', field: 'current_time', days: [1, 2, 3, 4, 5], start_time: '09:00', end_time: '18:00', label: '工作时间' }],
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
  { label: '合约总数', value: dashboardData.value?.total_contracts ?? 0, color: '#409eff' },
  { label: '活跃合约', value: dashboardData.value?.active_contracts ?? 0, color: '#67c23a' },
  { label: '系统合约', value: dashboardData.value?.system_contracts ?? 0, color: '#909399' },
  { label: '评估总数', value: dashboardData.value?.total_evaluations ?? 0, color: '#e6a23c' },
  { label: '今日评估', value: dashboardData.value?.today_evaluations ?? 0, color: '#409eff' },
  { label: '授权率', value: dashboardData.value?.total_evaluations ? Math.round(dashboardData.value.granted_count / dashboardData.value.total_evaluations * 100) + '%' : '—', color: '#67c23a' },
])

// ─── 方法 ───
function formatTime(t) {
  if (!t) return '—'
  const d = new Date(t)
  return d.toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
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
    ElMessage.error('条件或模板 JSON 格式错误')
    return
  }

  try {
    if (isEditingContract.value && editingContractId.value) {
      const res = await contractsApi.updateContract(editingContractId.value, contractForm.value)
      ElMessage.success(res.message || '合约已更新')
    } else {
      const res = await contractsApi.storeContract(contractForm.value)
      ElMessage.success(res.message || '合约已创建')
    }
    dialogContractVisible.value = false
    fetchContracts()
    fetchDashboard()
  } catch (e) {
    ElMessage.error('保存失败')
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
    ElMessage.success(isActive ? '合约已启用' : '合约已禁用')
    fetchContracts()
  } catch (e) { ElMessage.error('操作失败') }
}

async function handleDeleteContract(row) {
  try {
    await ElMessageBox.confirm(`确认删除合约 "${row.name}"？`, '确认', { type: 'warning' })
    await contractsApi.deleteContract(row.id)
    ElMessage.success('已删除')
    fetchContracts()
  } catch (e) { if (e !== 'cancel') ElMessage.error('删除失败') }
}

async function handleSeedContracts() {
  try {
    const res = await contractsApi.seedContracts()
    ElMessage.success(res.message || '系统合约已播种')
    fetchContracts()
    fetchDashboard()
  } catch (e) { ElMessage.error('播种失败') }
}

async function testContract(row) {
  try {
    const res = await contractsApi.evaluateContract(row.id, { current_time: new Date().toLocaleTimeString('zh-CN', { hour12: false }), current_day: new Date().getDay() || 7 })
    testResult.value = res.data
    dialogTestVisible.value = true
  } catch (e) { ElMessage.error('测试失败') }
}

async function saveAssignment() {
  try {
    assignmentForm.value.parameters = assignmentParamsText.value ? JSON.parse(assignmentParamsText.value) : null
  } catch (e) {
    ElMessage.error('参数 JSON 格式错误')
    return
  }
  try {
    const res = await contractsApi.storeAssignment(assignmentForm.value)
    ElMessage.success(res.message || '分配成功')
    dialogAssignmentVisible.value = false
    fetchAssignments()
  } catch (e) { ElMessage.error('分配失败') }
}

async function toggleAssignment(row, isEnabled) {
  try {
    await contractsApi.updateAssignment(row.id, { is_enabled: isEnabled })
    ElMessage.success('状态已更新')
    fetchAssignments()
  } catch (e) { ElMessage.error('操作失败') }
}

async function handleDeleteAssignment(row) {
  try {
    await ElMessageBox.confirm('确认移除该分配？', '确认', { type: 'warning' })
    await contractsApi.deleteAssignment(row.id)
    ElMessage.success('已移除')
    fetchAssignments()
  } catch (e) { if (e !== 'cancel') ElMessage.error('移除失败') }
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
