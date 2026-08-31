<template>
  <div class="contract-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Document /></el-icon>{{ t('enterprise_contract_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon> {{ t('enterprise_contract_page.buttons.create_contract') }}
        </el-button>
        <el-button @click="refreshAll" :loading="loading" style="margin-left:8px">
          <el-icon><Refresh /></el-icon> {{ t('contracts_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value primary">{{ dashboard.active_contracts }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.active') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_contracts }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.total') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value warning">{{ dashboard.pending_approval }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.pending_approval') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value danger">{{ dashboard.expiring_soon }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.expiring_soon') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value success">¥{{ formatMoney(dashboard.negotiated_value) }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.negotiated_value') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.overdue_contracts }}</div><div class="stat-label">{{ t('enterprise_contract_page.stats.overdue') }}</div></el-card>
      </el-col>
    </el-row>

    <!-- 合同列表 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('enterprise_contract_page.tabs.contracts')" name="contracts">
          <div class="tab-toolbar">
            <el-input v-model="search" :placeholder="t('enterprise_contract_page.filters.search_ph')" clearable style="width:220px" @clear="loadContracts" @keyup.enter="loadContracts" />
            <el-select v-model="filterStatus" :placeholder="t('enterprise_contract_page.filters.status')" clearable style="width:120px;margin-left:8px" @change="loadContracts">
              <el-option :label="t('contracts_page.filters.all')" value="" />
              <el-option v-for="(l, k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterApproval" :placeholder="t('enterprise_contract_page.filters.approval_status')" clearable style="width:130px;margin-left:8px" @change="loadContracts">
              <el-option :label="t('contracts_page.filters.all')" value="" />
              <el-option v-for="opt in approvalFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
          <el-table :data="contracts" stripe v-loading="contractsLoading">
            <el-table-column :label="t('enterprise_contract_page.columns.contract_number')" width="160">
              <template #default="{ row }">
                <el-button type="primary" link @click="showDetail(row)">{{ row.contract_number }}</el-button>
              </template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.name')" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">{{ row.name }}</template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.customer')" width="80">
              <template #default="{ row }">{{ row.customer_name }}</template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.status')" width="90">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.approval')" width="80">
              <template #default="{ row }">
                <el-tag v-if="row.approval_status" :type="row.approval_status === 'approved' ? 'success' : row.approval_status === 'rejected' ? 'danger' : 'warning'" size="small">
                  {{ approvalStatusLabel(row.approval_status) }}
                </el-tag>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.amount')" width="110" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_value) }}</template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.validity')" width="200">
              <template #default="{ row }">{{ row.start_date }} ~ {{ row.end_date }}</template>
            </el-table-column>
            <el-table-column :label="t('enterprise_contract_page.columns.days_remaining')" width="80" align="center">
              <template #default="{ row }">
                <span v-if="row.days_remaining !== null" :class="row.days_remaining < 0 ? 'text-danger' : row.days_remaining < 30 ? 'text-warning' : ''">
                  {{ row.days_remaining < 0 ? t('enterprise_contract_page.days.expired') : t('enterprise_contract_page.days.remaining', { n: row.days_remaining }) }}
                </span>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('contracts_page.columns.actions')" width="220" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'draft'" type="primary" link size="small" @click="handleSubmit(row)">{{ t('enterprise_contract_page.buttons.submit_approval') }}</el-button>
                <el-button v-if="row.status === 'active' && !row.auto_renew" type="warning" link size="small" @click="handleTerminate(row)">{{ t('enterprise_contract_page.buttons.terminate') }}</el-button>
                <el-button v-if="row.status === 'active' && row.auto_renew" type="success" link size="small" @click="handleRenew(row)">{{ t('enterprise_contract_page.buttons.renew') }}</el-button>
                <el-popconfirm v-if="row.status === 'draft'" :title="t('enterprise_contract_page.confirm.delete')" @confirm="handleDelete(row)">
                  <template #reference><el-button type="danger" link size="small">{{ t('actions.delete') }}</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建合同对话框 -->
    <el-dialog v-model="showCreateDialog" :title="t('enterprise_contract_page.create_dialog.title')" width="600px">
      <el-form :model="createForm" label-width="110px">
        <el-form-item :label="t('enterprise_contract_page.create_dialog.name')" required>
          <el-input v-model="createForm.name" />
        </el-form-item>
        <el-form-item :label="t('enterprise_contract_page.create_dialog.customer_id')" required>
          <el-input-number v-model="createForm.customer_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.start_date')" required>
              <el-date-picker v-model="createForm.start_date" type="date" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.end_date')" required>
              <el-date-picker v-model="createForm.end_date" type="date" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.total_value')">
              <el-input-number v-model="createForm.total_value" :min="0" :step="1000" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.negotiated_amount')">
              <el-input-number v-model="createForm.negotiated_amount" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.auto_renew')">
              <el-switch v-model="createForm.auto_renew" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('enterprise_contract_page.create_dialog.renewal_notice_days')">
              <el-input-number v-model="createForm.renewal_notice_days" :min="1" :max="180" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('enterprise_contract_page.create_dialog.notes')">
          <el-input v-model="createForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 审核对话框 -->
    <el-dialog v-model="showApproveDialog" :title="t('enterprise_contract_page.approve_dialog.title')" width="400px">
      <p>{{ t('enterprise_contract_page.approve_dialog.approve_contract', { name: approvingContract?.name }) }}</p>
      <el-radio-group v-model="approveAction" style="margin:12px 0">
        <el-radio value="approved">{{ t('actions.approve') }}</el-radio>
        <el-radio value="rejected">{{ t('enterprise_contract_page.approve_dialog.reject') }}</el-radio>
      </el-radio-group>
      <el-input v-model="approveNotes" type="textarea" :placeholder="t('enterprise_contract_page.approve_dialog.notes_ph')" :rows="3" />
      <template #footer>
        <el-button @click="showApproveDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="confirmApprove" :loading="approving">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 合同详情对话框 -->
    <el-dialog v-model="showDetailDialog" :title="t('enterprise_contract_page.detail_dialog.title')" width="650px">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.contract_number')">{{ detail.contract_number }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.name')">{{ detail.name }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.customer_id')">{{ detail.customer_id }}</el-descriptions-item>
          <el-descriptions-item :label="t('contracts_page.columns.status')">
            <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabels[detail.status] }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.amount')">¥{{ formatMoney(detail.total_value) }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.negotiated_amount')">¥{{ formatMoney(detail.negotiated_amount) }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.discount_rate')">{{ (detail.discount_rate || 0) * 1 }}%</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.auto_renew')">{{ detail.auto_renew ? t('enterprise_contract_page.labels.yes') : t('enterprise_contract_page.labels.no') }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.validity')">{{ detail.start_date }} ~ {{ detail.end_date }}</el-descriptions-item>
          <el-descriptions-item :label="t('enterprise_contract_page.detail_dialog.created_by')">{{ detail.created_by || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="detail.licensed_items && detail.licensed_items.length" style="margin-top:16px">
          <h4>{{ t('enterprise_contract_page.detail_dialog.licensed_items') }}</h4>
          <pre style="background:#f5f7fa;padding:8px;border-radius:4px;font-size:12px">{{ JSON.stringify(detail.licensed_items, null, 2) }}</pre>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Document, Plus, Refresh } from '@element-plus/icons-vue'
import api from '../../api/enterpriseContract'

const { t, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('contracts')

const dashboard = reactive({
    total_contracts: 0, active_contracts: 0, draft_contracts: 0,
    pending_approval: 0, expiring_soon: 0, overdue_contracts: 0,
    total_value: 0, negotiated_value: 0,
})

const statusKeys = ['draft', 'pending_approval', 'active', 'expired', 'terminated']

const statusLabels = computed(() => Object.fromEntries(
    statusKeys.map((k) => [k, t(`enterprise_contract_page.status.${k}`)])
))

const approvalStatusLabels = computed(() => ({
    pending: t('enterprise_contract_page.approval_status.pending'),
    approved: t('enterprise_contract_page.approval_status.approved'),
    rejected: t('enterprise_contract_page.approval_status.rejected'),
}))

const approvalFilterOptions = computed(() => [
    { value: 'pending', label: t('enterprise_contract_page.approval_status.pending') },
    { value: 'approved', label: t('enterprise_contract_page.approval_status.approved') },
    { value: 'rejected', label: t('enterprise_contract_page.approval_status.rejected') },
])

function approvalStatusLabel(status) {
    return approvalStatusLabels.value[status] || status
}

function statusTag(s) {
    const map = { draft: 'info', pending_approval: 'warning', active: 'success', expired: 'danger', terminated: '' }
    return map[s] || 'info'
}

function formatMoney(v) {
    v = parseFloat(v)
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return isNaN(v) ? '0.00' : v.toLocaleString(loc, { minimumFractionDigits: 2 })
}

// Contracts list
const contracts = ref([])
const contractsLoading = ref(false)
const search = ref('')
const filterStatus = ref('')
const filterApproval = ref('')

async function loadContracts() {
    contractsLoading.value = true
    try {
        const params = {}
        if (search.value) params.search = search.value
        if (filterStatus.value) params.status = filterStatus.value
        if (filterApproval.value) params.approval_status = filterApproval.value
        const res = await api.index(params)
        contracts.value = res.data?.data || res.data || []
    } catch (e) { console.error(e) }
    finally { contractsLoading.value = false }
}

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        Object.assign(dashboard, res.data || {})
    } catch (e) { console.error(e) }
}

function refreshAll() {
    loading.value = true
    Promise.all([loadDashboard(), loadContracts()])
        .finally(() => { loading.value = false })
}

// Create
const showCreateDialog = ref(false)
const creating = ref(false)
const createForm = reactive({
    name: '', customer_id: 1, start_date: '', end_date: '',
    total_value: 0, negotiated_amount: 0, auto_renew: false, renewal_notice_days: 30, notes: '',
})

async function handleCreate() {
    if (!createForm.name || !createForm.start_date || !createForm.end_date) {
        ElMessage.warning(t('enterprise_contract_page.validation.required_fields'))
        return
    }
    creating.value = true
    try {
        await api.store(createForm)
        ElMessage.success(t('enterprise_contract_page.messages.created'))
        showCreateDialog.value = false
        createForm.name = ''; createForm.customer_id = 1; createForm.start_date = ''; createForm.end_date = ''
        createForm.total_value = 0; createForm.negotiated_amount = 0; createForm.auto_renew = false; createForm.renewal_notice_days = 30; createForm.notes = ''
        loadContracts(); loadDashboard()
    } catch (e) {
        ElMessage.error(t('enterprise_contract_page.messages.create_failed', { error: e.response?.data?.message || e.message }))
    }
    finally { creating.value = false }
}

// Detail
const showDetailDialog = ref(false)
const detail = ref(null)

async function showDetail(row) {
    try {
        const res = await api.show(row.id)
        detail.value = res.data || {}
        showDetailDialog.value = true
    } catch (e) { ElMessage.error(t('messages.load_failed')) }
}

// Submit for approval
async function handleSubmit(row) {
    try {
        await api.submitForApproval(row.id)
        ElMessage.success(t('enterprise_contract_page.messages.submitted'))
        loadContracts()
    } catch (e) {
        ElMessage.error(t('enterprise_contract_page.messages.submit_failed', { error: e.response?.data?.message || e.message }))
    }
}

// Approve
const showApproveDialog = ref(false)
const approvingContract = ref(null)
const approveAction = ref('approved')
const approveNotes = ref('')
const approving = ref(false)

async function confirmApprove() {
    approving.value = true
    try {
        await api.approve(approvingContract.value.id, approveAction.value, approveNotes.value)
        ElMessage.success(approveAction.value === 'approved'
            ? t('enterprise_contract_page.messages.approved')
            : t('enterprise_contract_page.messages.rejected'))
        showApproveDialog.value = false
        approvingContract.value = null
        approveNotes.value = ''
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
    finally { approving.value = false }
}

// Terminate
async function handleTerminate(row) {
    try {
        await api.terminate(row.id)
        ElMessage.success(t('enterprise_contract_page.messages.terminated'))
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error(t('enterprise_contract_page.messages.terminate_failed')) }
}

// Renew
async function handleRenew(row) {
    try {
        await api.renew(row.id)
        ElMessage.success(t('enterprise_contract_page.messages.renewed'))
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error(t('enterprise_contract_page.messages.renew_failed')) }
}

// Delete
async function handleDelete(row) {
    try {
        await api.destroy(row.id)
        ElMessage.success(t('enterprise_contract_page.messages.deleted'))
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error(t('enterprise_contract_page.messages.delete_failed')) }
}

onMounted(() => { refreshAll() })
</script>

<style scoped>
.contract-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stat-value { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value.primary { color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.danger { color: #f56c6c; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.no-data { color: #c0c4cc; }
.text-warning { color: #e6a23c; font-weight: 600; }
.text-danger { color: #f56c6c; font-weight: 600; }
</style>
