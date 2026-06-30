<template>
  <div class="contract-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Document /></el-icon>智能合同管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon> 创建合同
        </el-button>
        <el-button @click="refreshAll" :loading="loading" style="margin-left:8px">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value primary">{{ dashboard.active_contracts }}</div><div class="stat-label">活跃</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_contracts }}</div><div class="stat-label">总计</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value warning">{{ dashboard.pending_approval }}</div><div class="stat-label">待审批</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value danger">{{ dashboard.expiring_soon }}</div><div class="stat-label">即将到期(30天)</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value success">¥{{ formatMoney(dashboard.negotiated_value) }}</div><div class="stat-label">合同净值</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.overdue_contracts }}</div><div class="stat-label">已过期</div></el-card>
      </el-col>
    </el-row>

    <!-- 合同列表 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="企业合同列表" name="contracts">
          <div class="tab-toolbar">
            <el-input v-model="search" placeholder="搜索合同名/编号" clearable style="width:220px" @clear="loadContracts" @keyup.enter="loadContracts" />
            <el-select v-model="filterStatus" placeholder="状态" clearable style="width:120px;margin-left:8px" @change="loadContracts">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterApproval" placeholder="审批状态" clearable style="width:130px;margin-left:8px" @change="loadContracts">
              <el-option label="全部" value="" />
              <el-option label="待审批" value="pending" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
            </el-select>
          </div>
          <el-table :data="contracts" stripe v-loading="contractsLoading">
            <el-table-column label="合同编号" width="160">
              <template #default="{ row }">
                <el-button type="primary" link @click="showDetail(row)">{{ row.contract_number }}</el-button>
              </template>
            </el-table-column>
            <el-table-column label="名称" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">{{ row.name }}</template>
            </el-table-column>
            <el-table-column label="客户" width="80">
              <template #default="{ row }">{{ row.customer_name }}</template>
            </el-table-column>
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="审批" width="80">
              <template #default="{ row }">
                <el-tag v-if="row.approval_status" :type="row.approval_status === 'approved' ? 'success' : row.approval_status === 'rejected' ? 'danger' : 'warning'" size="small">
                  {{ row.approval_status === 'approved' ? '已通过' : row.approval_status === 'rejected' ? '已拒绝' : '待审批' }}
                </el-tag>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column label="合同金额" width="110" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_value) }}</template>
            </el-table-column>
            <el-table-column label="有效期" width="200">
              <template #default="{ row }">{{ row.start_date }} ~ {{ row.end_date }}</template>
            </el-table-column>
            <el-table-column label="剩余天数" width="80" align="center">
              <template #default="{ row }">
                <span v-if="row.days_remaining !== null" :class="row.days_remaining < 0 ? 'text-danger' : row.days_remaining < 30 ? 'text-warning' : ''">
                  {{ row.days_remaining < 0 ? '已过期' : row.days_remaining + '天' }}
                </span>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'draft'" type="primary" link size="small" @click="handleSubmit(row)">提交审批</el-button>
                <el-button v-if="row.status === 'active' && !row.auto_renew" type="warning" link size="small" @click="handleTerminate(row)">终止</el-button>
                <el-button v-if="row.status === 'active' && row.auto_renew" type="success" link size="small" @click="handleRenew(row)">续签</el-button>
                <el-popconfirm v-if="row.status === 'draft'" title="确定删除?" @confirm="handleDelete(row)">
                  <template #reference><el-button type="danger" link size="small">删除</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建合同对话框 -->
    <el-dialog v-model="showCreateDialog" title="创建企业合同" width="600px">
      <el-form :model="createForm" label-width="110px">
        <el-form-item label="合同名称" required>
          <el-input v-model="createForm.name" />
        </el-form-item>
        <el-form-item label="客户ID" required>
          <el-input-number v-model="createForm.customer_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始日期" required>
              <el-date-picker v-model="createForm.start_date" type="date" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束日期" required>
              <el-date-picker v-model="createForm.end_date" type="date" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="合同总值">
              <el-input-number v-model="createForm.total_value" :min="0" :step="1000" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="折后金额">
              <el-input-number v-model="createForm.negotiated_amount" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="自动续签">
              <el-switch v-model="createForm.auto_renew" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="提醒天数">
              <el-input-number v-model="createForm.renewal_notice_days" :min="1" :max="180" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注">
          <el-input v-model="createForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">创建</el-button>
      </template>
    </el-dialog>

    <!-- 审核对话框 -->
    <el-dialog v-model="showApproveDialog" title="合同审批" width="400px">
      <p>审批合同: <strong>{{ approvingContract?.name }}</strong></p>
      <el-radio-group v-model="approveAction" style="margin:12px 0">
        <el-radio value="approved">通过</el-radio>
        <el-radio value="rejected">拒绝</el-radio>
      </el-radio-group>
      <el-input v-model="approveNotes" type="textarea" placeholder="审批意见（选填）" :rows="3" />
      <template #footer>
        <el-button @click="showApproveDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmApprove" :loading="approving">确认</el-button>
      </template>
    </el-dialog>

    <!-- 合同详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="合同详情" width="650px">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="合同编号">{{ detail.contract_number }}</el-descriptions-item>
          <el-descriptions-item label="名称">{{ detail.name }}</el-descriptions-item>
          <el-descriptions-item label="客户ID">{{ detail.customer_id }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabels[detail.status] }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="合同金额">¥{{ formatMoney(detail.total_value) }}</el-descriptions-item>
          <el-descriptions-item label="折后金额">¥{{ formatMoney(detail.negotiated_amount) }}</el-descriptions-item>
          <el-descriptions-item label="折扣率">{{ (detail.discount_rate || 0) * 1 }}%</el-descriptions-item>
          <el-descriptions-item label="自动续签">{{ detail.auto_renew ? '是' : '否' }}</el-descriptions-item>
          <el-descriptions-item label="有效期">{{ detail.start_date }} ~ {{ detail.end_date }}</el-descriptions-item>
          <el-descriptions-item label="创建人">{{ detail.created_by || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="detail.licensed_items && detail.licensed_items.length" style="margin-top:16px">
          <h4>许可项目</h4>
          <pre style="background:#f5f7fa;padding:8px;border-radius:4px;font-size:12px">{{ JSON.stringify(detail.licensed_items, null, 2) }}</pre>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Document, Plus, Refresh } from '@element-plus/icons-vue'
import api from '../../api/enterpriseContract'

const loading = ref(false)
const activeTab = ref('contracts')

const dashboard = reactive({
    total_contracts: 0, active_contracts: 0, draft_contracts: 0,
    pending_approval: 0, expiring_soon: 0, overdue_contracts: 0,
    total_value: 0, negotiated_value: 0,
})

const statusLabels = { draft: '草稿', pending_approval: '待审批', active: '活跃', expired: '已过期', terminated: '已终止' }

function statusTag(s) {
    const map = { draft: 'info', pending_approval: 'warning', active: 'success', expired: 'danger', terminated: '' }
    return map[s] || 'info'
}

function formatMoney(v) { v = parseFloat(v); return isNaN(v) ? '0.00' : v.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) }

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
        ElMessage.warning('请填写必填项')
        return
    }
    creating.value = true
    try {
        await api.store(createForm)
        ElMessage.success('合同已创建')
        showCreateDialog.value = false
        createForm.name = ''; createForm.customer_id = 1; createForm.start_date = ''; createForm.end_date = ''
        createForm.total_value = 0; createForm.negotiated_amount = 0; createForm.auto_renew = false; createForm.renewal_notice_days = 30; createForm.notes = ''
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error('创建失败: ' + (e.response?.data?.message || e.message)) }
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
    } catch (e) { ElMessage.error('加载失败') }
}

// Submit for approval
async function handleSubmit(row) {
    try {
        await api.submitForApproval(row.id)
        ElMessage.success('已提交审批')
        loadContracts()
    } catch (e) { ElMessage.error('提交失败: ' + (e.response?.data?.message || e.message)) }
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
        ElMessage.success(approveAction.value === 'approved' ? '合同已批准' : '合同已拒绝')
        showApproveDialog.value = false
        approvingContract.value = null
        approveNotes.value = ''
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
    finally { approving.value = false }
}

// Terminate
async function handleTerminate(row) {
    try {
        await api.terminate(row.id)
        ElMessage.success('合同已终止')
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error('终止失败') }
}

// Renew
async function handleRenew(row) {
    try {
        await api.renew(row.id)
        ElMessage.success('合同已续签')
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error('续签失败') }
}

// Delete
async function handleDelete(row) {
    try {
        await api.destroy(row.id)
        ElMessage.success('已删除')
        loadContracts(); loadDashboard()
    } catch (e) { ElMessage.error('删除失败') }
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
.stat-value.primary { color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.danger { color: #f56c6c; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.no-data { color: #c0c4cc; }
.text-warning { color: #e6a23c; font-weight: 600; }
.text-danger { color: #f56c6c; font-weight: 600; }
</style>
