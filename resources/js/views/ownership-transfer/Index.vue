<template>
    <div class="ownership-transfer-page">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">总转移数</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card pending">
                    <div class="stat-value">{{ stats.pending }}</div>
                    <div class="stat-label">待处理</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card success">
                    <div class="stat-value">{{ stats.completed }}</div>
                    <div class="stat-label">已完成</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card danger">
                    <div class="stat-value">{{ stats.rejected }}</div>
                    <div class="stat-label">已拒绝</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">¥{{ stats.total_fees?.toFixed(2) }}</div>
                    <div class="stat-label">总转移费</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="search-card">
            <el-row :gutter="16">
                <el-col :span="6">
                    <el-input v-model="filters.search" placeholder="搜索编号/客户" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.status" placeholder="状态" clearable @change="loadList" style="width: 100%">
                        <el-option label="待源确认" value="pending_source" />
                        <el-option label="待目标确认" value="pending_target" />
                        <el-option label="待审批" value="pending_approval" />
                        <el-option label="已完成" value="completed" />
                        <el-option label="已拒绝" value="rejected" />
                        <el-option label="已取消" value="cancelled" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.transferable_type" placeholder="类型" clearable @change="loadList" style="width: 100%">
                        <el-option label="License" value="license" />
                        <el-option label="产品" value="product" />
                    </el-select>
                </el-col>
                <el-col :span="10" style="text-align: right">
                    <el-button type="primary" @click="showCreateDialog">
                        <el-icon><Plus /></el-icon> 新建转移请求
                    </el-button>
                    <el-button @click="loadList">刷新</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 转移列表 -->
        <el-card class="table-card">
            <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
                <el-table-column prop="reference" label="编号" width="210" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.transferable_type === 'license' ? 'primary' : 'success'" size="small">
                            {{ row.transferable_type === 'license' ? 'License' : '产品' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="源客户" min-width="150">
                    <template #default="{ row }">{{ row.source_customer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="目标客户" min-width="150">
                    <template #default="{ row }">{{ row.target_customer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="转移费" width="100">
                    <template #default="{ row }">
                        {{ row.transfer_fee ? '¥' + row.transfer_fee.toFixed(2) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="130">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="申请人" width="120">
                    <template #default="{ row }">{{ row.requester?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column label="操作" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button size="small" v-if="row.status === 'pending_source'" type="warning" @click="handleConfirmSource(row)">源确认</el-button>
                        <el-button size="small" v-if="row.status === 'pending_target'" type="warning" @click="handleConfirmTarget(row)">目标确认</el-button>
                        <el-button size="small" v-if="row.status === 'pending_approval'" type="success" @click="handleApprove(row)">审批</el-button>
                        <el-button size="small" v-if="['pending_source','pending_target','pending_approval'].includes(row.status)" type="danger" @click="handleReject(row)">拒绝</el-button>
                        <el-button size="small" v-if="['pending_source','pending_target'].includes(row.status)" @click="handleCancel(row)">取消</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 创建对话框 -->
        <el-dialog v-model="createDialogVisible" title="新建所有权转移请求" width="600px" :close-on-click-modal="false">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px">
                <el-form-item label="转移类型" prop="transferable_type">
                    <el-select v-model="createForm.transferable_type" @change="onTypeChange" style="width: 100%">
                        <el-option label="License" value="license" />
                        <el-option label="产品" value="product" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="createForm.transferable_type === 'license' ? '选择License' : '选择产品'" prop="transferable_id">
                    <el-select v-model="createForm.transferable_id" filterable remote :remote-method="searchTransferables" :loading="searchingTransferables" style="width: 100%">
                        <el-option
                            v-for="item in transferableOptions"
                            :key="item.id"
                            :label="createForm.transferable_type === 'license' ? item.license_key : item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="目标客户" prop="target_customer_id">
                    <el-select v-model="createForm.target_customer_id" filterable remote :remote-method="searchTargetCustomers" :loading="searchingCustomers" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="c.name + (c.email ? ' (' + c.email + ')' : '')"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="转移费用">
                    <el-input-number v-model="createForm.transfer_fee" :min="0" :precision="2" style="width: 200px" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="createForm.source_notes" type="textarea" :rows="3" maxlength="1000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitCreate">提交</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailDialogVisible" title="转移请求详情" width="700px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="编号" :span="2">{{ detail.reference }}</el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <el-tag :type="detail.transferable_type === 'license' ? 'primary' : 'success'" size="small">
                            {{ detail.transferable_type === 'license' ? 'License' : '产品' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="源客户">{{ detail.source_customer?.name }}</el-descriptions-item>
                    <el-descriptions-item label="目标客户">{{ detail.target_customer?.name }}</el-descriptions-item>
                    <el-descriptions-item label="转移费用">{{ detail.transfer_fee ? '¥' + detail.transfer_fee : '-' }}</el-descriptions-item>
                    <el-descriptions-item label="申请人">{{ detail.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item label="源确认人">{{ detail.source_confirmer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="目标确认人">{{ detail.target_confirmer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="审批人">{{ detail.approver?.name || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider>迁移记录</el-divider>
                <el-table :data="detail.transfer_records || []" border size="small" max-height="200">
                    <el-table-column prop="entity_type" label="数据类型" width="120" />
                    <el-table-column prop="entity_id" label="数据ID" width="80" />
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'migrated' ? 'success' : row.status === 'skipped' ? 'warning' : 'danger'" size="small">
                                {{ row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="notes" label="备注" />
                </el-table>

                <el-divider>审计日志</el-divider>
                <el-timeline v-if="detail.audit_log?.length">
                    <el-timeline-item
                        v-for="(log, i) in detail.audit_log"
                        :key="i"
                        :timestamp="log.at"
                    >
                        {{ auditActionLabel(log.action) }} - {{ log.by ? '用户#' + log.by : '系统' }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无审计记录" />
            </template>
        </el-dialog>

        <!-- 审批对话框 -->
        <el-dialog v-model="approveDialogVisible" title="审批转移" width="450px">
            <el-form :model="approveForm">
                <el-form-item label="备注">
                    <el-input v-model="approveForm.notes" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="approveDialogVisible = false">取消</el-button>
                <el-button type="success" :loading="approving" @click="submitApprove">确认审批并执行</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝对话框 -->
        <el-dialog v-model="rejectDialogVisible" title="拒绝转移" width="450px">
            <el-form :model="rejectForm">
                <el-form-item label="拒绝原因">
                    <el-input v-model="rejectForm.reason" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rejectDialogVisible = false">取消</el-button>
                <el-button type="danger" :loading="rejecting" @click="submitReject">确认拒绝</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getOwnershipTransferList,
    getOwnershipTransferStats,
    getOwnershipTransferDetail,
    createOwnershipTransfer,
    getTransferables,
    searchCustomers,
    confirmBySource,
    confirmByTarget,
    approveOwnershipTransfer,
    rejectOwnershipTransfer,
    cancelOwnershipTransfer,
} from '@/api/ownershipTransfer'

// ─── 状态 ───
const loading = ref(false)
const stats = ref({
    total: 0, pending: 0, completed: 0, rejected: 0, cancelled: 0, total_fees: 0,
})
const list = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const total = ref(0)

const filters = ref({
    search: '',
    status: '',
    transferable_type: '',
})

// 创建对话框
const createDialogVisible = ref(false)
const createFormRef = ref(null)
const submitting = ref(false)
const createForm = ref({
    transferable_type: 'license',
    transferable_id: null,
    target_customer_id: null,
    transfer_fee: 0,
    source_notes: '',
})
const createRules = {
    transferable_type: [{ required: true, message: '请选择转移类型', trigger: 'change' }],
    transferable_id: [{ required: true, message: '请选择转移对象', trigger: 'change' }],
    target_customer_id: [{ required: true, message: '请选择目标客户', trigger: 'change' }],
}
const transferableOptions = ref([])
const searchingTransferables = ref(false)
const customerOptions = ref([])
const searchingCustomers = ref(false)

// 详情对话框
const detailDialogVisible = ref(false)
const detail = ref(null)

// 审批对话框
const approveDialogVisible = ref(false)
const approveForm = ref({ notes: '' })
const approving = ref(false)
const currentRow = ref(null)

// 拒绝对话框
const rejectDialogVisible = ref(false)
const rejectForm = ref({ reason: '' })
const rejecting = ref(false)

// ─── 状态映射 ───
const statusLabels = {
    pending_source: '待源确认',
    pending_target: '待目标确认',
    pending_approval: '待审批',
    completed: '已完成',
    rejected: '已拒绝',
    cancelled: '已取消',
}
const statusTags = {
    pending_source: 'warning',
    pending_target: 'warning',
    pending_approval: 'info',
    completed: 'success',
    rejected: 'danger',
    cancelled: 'info',
}
const auditLabels = {
    created: '创建请求',
    source_confirmed: '源客户确认',
    target_confirmed: '目标客户确认',
    approved_executed: '审批并执行转移',
    rejected: '拒绝',
    cancelled: '取消',
}

function statusLabel(s) { return statusLabels[s] || s }
function statusTag(s) { return statusTags[s] || 'info' }
function auditActionLabel(a) { return auditLabels[a] || a }

// ─── 数据加载 ───
async function loadStats() {
    try {
        const res = await getOwnershipTransferStats()
        stats.value = res.data || res
    } catch { /* ignore */ }
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value, ...filters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getOwnershipTransferList(params)
        list.value = res.data?.data || res.data || []
        total.value = res.data?.total || res.total || 0
    } catch (e) {
        ElMessage.error('加载失败：' + (e.response?.data?.message || e.message))
    } finally {
        loading.value = false
    }
}

// ─── 创建 ───
function showCreateDialog() {
    createForm.value = { transferable_type: 'license', transferable_id: null, target_customer_id: null, transfer_fee: 0, source_notes: '' }
    transferableOptions.value = []
    customerOptions.value = []
    createDialogVisible.value = true
}

function onTypeChange() {
    createForm.value.transferable_id = null
    transferableOptions.value = []
}

async function searchTransferables(query) {
    searchingTransferables.value = true
    try {
        const res = await getTransferables(createForm.value.transferable_type, query)
        transferableOptions.value = res.data || []
    } catch { transferableOptions.value = [] }
    finally { searchingTransferables.value = false }
}

async function searchTargetCustomers(query) {
    searchingCustomers.value = true
    try {
        const res = await searchCustomers(query)
        customerOptions.value = res.data || []
    } catch { customerOptions.value = [] }
    finally { searchingCustomers.value = false }
}

async function submitCreate() {
    const valid = await createFormRef.value.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
        await createOwnershipTransfer(createForm.value)
        ElMessage.success('转移请求已提交')
        createDialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || e.response?.data?.errors || '提交失败')
    } finally {
        submitting.value = false
    }
}

// ─── 详情 ───
async function showDetail(row) {
    try {
        const res = await getOwnershipTransferDetail(row.id)
        detail.value = res.data || res
        detailDialogVisible.value = true
    } catch (e) {
        ElMessage.error('加载详情失败')
    }
}

// ─── 确认 ───
async function handleConfirmSource(row) {
    try {
        await ElMessageBox.confirm('确认作为源客户同意此转移？', '确认')
        await confirmBySource(row.id)
        ElMessage.success('源客户已确认')
        loadList()
    } catch { /* cancelled */ }
}

async function handleConfirmTarget(row) {
    try {
        await ElMessageBox.confirm('确认作为目标客户同意此转移？', '确认')
        await confirmByTarget(row.id)
        ElMessage.success('目标客户已确认')
        loadList()
    } catch { /* cancelled */ }
}

// ─── 审批 ───
async function handleApprove(row) {
    currentRow.value = row
    approveForm.value = { notes: '' }
    approveDialogVisible.value = true
}

async function submitApprove() {
    approving.value = true
    try {
        await approveOwnershipTransfer(currentRow.value.id, approveForm.value.notes)
        ElMessage.success('转移已审批并执行')
        approveDialogVisible.value = false
        loadList()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '审批失败')
    } finally {
        approving.value = false
    }
}

// ─── 拒绝 ───
async function handleReject(row) {
    currentRow.value = row
    rejectForm.value = { reason: '' }
    rejectDialogVisible.value = true
}

async function submitReject() {
    rejecting.value = true
    try {
        await rejectOwnershipTransfer(currentRow.value.id, rejectForm.value.reason)
        ElMessage.success('已拒绝')
        rejectDialogVisible.value = false
        loadList()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally {
        rejecting.value = false
    }
}

// ─── 取消 ───
async function handleCancel(row) {
    try {
        await ElMessageBox.confirm('确认取消此转移请求？', '取消')
        await cancelOwnershipTransfer(row.id)
        ElMessage.success('已取消')
        loadList()
    } catch { /* cancelled */ }
}

// ─── 初始化 ───
onMounted(() => {
    loadStats()
    loadList()
})
</script>

<style scoped>
.ownership-transfer-page {
    padding: 20px;
}
.stats-row {
    margin-bottom: 20px;
}
.stat-card {
    text-align: center;
    margin-bottom: 10px;
}
.stat-card .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #303133;
}
.stat-card.pending .stat-value { color: #e6a23c; }
.stat-card.success .stat-value { color: #67c23a; }
.stat-card.danger .stat-value { color: #f56c6c; }
.stat-card .stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}
.search-card {
    margin-bottom: 16px;
}
.table-card {
    margin-bottom: 20px;
}
.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
</style>
