<template>
    <div class="ownership-transfer-page">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card pending">
                    <div class="stat-value">{{ stats.pending }}</div>
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.pending') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card success">
                    <div class="stat-value">{{ stats.completed }}</div>
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.completed') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card danger">
                    <div class="stat-value">{{ stats.rejected }}</div>
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.rejected') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">¥{{ stats.total_fees?.toFixed(2) }}</div>
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.total_fees') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="search-card">
            <el-row :gutter="16">
                <el-col :span="6">
                    <el-input v-model="filters.search" :placeholder="t('ownership_transfer_page.search_ph')" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.status" :placeholder="t('ownership_transfer_page.status')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.transferable_type" :placeholder="t('ownership_transfer_page.type')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-col>
                <el-col :span="10" style="text-align: right">
                    <el-button type="primary" @click="showCreateDialog">
                        <el-icon><Plus /></el-icon> {{ t('ownership_transfer_page.create_request') }}
                    </el-button>
                    <el-button @click="loadList">{{ t('ownership_transfer_page.refresh') }}</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 转移列表 -->
        <el-card class="table-card">
            <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
                <el-table-column prop="reference" :label="t('ownership_transfer_page.col_reference')" width="210" />
                <el-table-column :label="t('ownership_transfer_page.type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.transferable_type === 'license' ? 'primary' : 'success'" size="small">
                            {{ typeLabel(row.transferable_type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_source_customer')" min-width="150">
                    <template #default="{ row }">{{ row.source_customer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_target_customer')" min-width="150">
                    <template #default="{ row }">{{ row.target_customer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_transfer_fee')" width="100">
                    <template #default="{ row }">
                        {{ row.transfer_fee ? '¥' + row.transfer_fee.toFixed(2) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.status')" width="130">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_requester')" width="120">
                    <template #default="{ row }">{{ row.requester?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_created_at')" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_actions')" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">{{ t('ownership_transfer_page.detail') }}</el-button>
                        <el-button size="small" v-if="row.status === 'pending_source'" type="warning" @click="handleConfirmSource(row)">{{ t('ownership_transfer_page.confirm_source') }}</el-button>
                        <el-button size="small" v-if="row.status === 'pending_target'" type="warning" @click="handleConfirmTarget(row)">{{ t('ownership_transfer_page.confirm_target') }}</el-button>
                        <el-button size="small" v-if="row.status === 'pending_approval'" type="success" @click="handleApprove(row)">{{ t('ownership_transfer_page.approve') }}</el-button>
                        <el-button size="small" v-if="['pending_source','pending_target','pending_approval'].includes(row.status)" type="danger" @click="handleReject(row)">{{ t('ownership_transfer_page.reject') }}</el-button>
                        <el-button size="small" v-if="['pending_source','pending_target'].includes(row.status)" @click="handleCancel(row)">{{ t('actions.cancel') }}</el-button>
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
        <el-dialog v-model="createDialogVisible" :title="t('ownership_transfer_page.create_dialog_title')" width="600px" :close-on-click-modal="false">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px">
                <el-form-item :label="t('ownership_transfer_page.form.transfer_type')" prop="transferable_type">
                    <el-select v-model="createForm.transferable_type" @change="onTypeChange" style="width: 100%">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="createForm.transferable_type === 'license' ? t('ownership_transfer_page.form.select_license') : t('ownership_transfer_page.form.select_product')" prop="transferable_id">
                    <el-select v-model="createForm.transferable_id" filterable remote :remote-method="searchTransferables" :loading="searchingTransferables" style="width: 100%">
                        <el-option
                            v-for="item in transferableOptions"
                            :key="item.id"
                            :label="createForm.transferable_type === 'license' ? item.license_key : item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('ownership_transfer_page.form.target_customer')" prop="target_customer_id">
                    <el-select v-model="createForm.target_customer_id" filterable remote :remote-method="searchTargetCustomers" :loading="searchingCustomers" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="c.name + (c.email ? ' (' + c.email + ')' : '')"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('ownership_transfer_page.form.transfer_fee')">
                    <el-input-number v-model="createForm.transfer_fee" :min="0" :precision="2" style="width: 200px" />
                </el-form-item>
                <el-form-item :label="t('ownership_transfer_page.form.notes')">
                    <el-input v-model="createForm.source_notes" type="textarea" :rows="3" maxlength="1000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitCreate">{{ t('actions.submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailDialogVisible" :title="t('ownership_transfer_page.detail_dialog_title')" width="700px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_reference')" :span="2">{{ detail.reference }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.type')">
                        <el-tag :type="detail.transferable_type === 'license' ? 'primary' : 'success'" size="small">
                            {{ typeLabel(detail.transferable_type) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.status')">
                        <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_source_customer')">{{ detail.source_customer?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_target_customer')">{{ detail.target_customer?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.form.transfer_fee')">{{ detail.transfer_fee ? '¥' + detail.transfer_fee : '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_requester')">{{ detail.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.label_source_confirmer')">{{ detail.source_confirmer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.label_target_confirmer')">{{ detail.target_confirmer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.label_approver')">{{ detail.approver?.name || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider>{{ t('ownership_transfer_page.section_migration_records') }}</el-divider>
                <el-table :data="detail.transfer_records || []" border size="small" max-height="200">
                    <el-table-column prop="entity_type" :label="t('ownership_transfer_page.col_entity_type')" width="120" />
                    <el-table-column prop="entity_id" :label="t('ownership_transfer_page.col_entity_id')" width="80" />
                    <el-table-column prop="status" :label="t('ownership_transfer_page.status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'migrated' ? 'success' : row.status === 'skipped' ? 'warning' : 'danger'" size="small">
                                {{ recordStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="notes" :label="t('ownership_transfer_page.col_notes')" />
                </el-table>

                <el-divider>{{ t('ownership_transfer_page.section_audit_log') }}</el-divider>
                <el-timeline v-if="detail.audit_log?.length">
                    <el-timeline-item
                        v-for="(log, i) in detail.audit_log"
                        :key="i"
                        :timestamp="log.at"
                    >
                        {{ auditActionLabel(log.action) }} - {{ log.by ? t('ownership_transfer_page.audit_user', { id: log.by }) : t('ownership_transfer_page.audit_system') }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else :description="t('ownership_transfer_page.empty_audit')" />
            </template>
        </el-dialog>

        <!-- 审批对话框 -->
        <el-dialog v-model="approveDialogVisible" :title="t('ownership_transfer_page.approve_dialog_title')" width="450px">
            <el-form :model="approveForm">
                <el-form-item :label="t('ownership_transfer_page.form.notes')">
                    <el-input v-model="approveForm.notes" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="approveDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="success" :loading="approving" @click="submitApprove">{{ t('ownership_transfer_page.approve_submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝对话框 -->
        <el-dialog v-model="rejectDialogVisible" :title="t('ownership_transfer_page.reject_dialog_title')" width="450px">
            <el-form :model="rejectForm">
                <el-form-item :label="t('ownership_transfer_page.reject_reason')">
                    <el-input v-model="rejectForm.reason" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rejectDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="rejecting" @click="submitReject">{{ t('ownership_transfer_page.reject_submit') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
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

const { t } = useI18n()

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
const createRules = computed(() => ({
    transferable_type: [{ required: true, message: t('ownership_transfer_page.validation.transfer_type'), trigger: 'change' }],
    transferable_id: [{ required: true, message: t('ownership_transfer_page.validation.transferable_id'), trigger: 'change' }],
    target_customer_id: [{ required: true, message: t('ownership_transfer_page.validation.target_customer_id'), trigger: 'change' }],
}))
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

// ─── 选项与映射 ───
const statusOptions = computed(() => [
    { value: 'pending_source', label: t('ownership_transfer_page.status_map.pending_source') },
    { value: 'pending_target', label: t('ownership_transfer_page.status_map.pending_target') },
    { value: 'pending_approval', label: t('ownership_transfer_page.status_map.pending_approval') },
    { value: 'completed', label: t('ownership_transfer_page.status_map.completed') },
    { value: 'rejected', label: t('ownership_transfer_page.status_map.rejected') },
    { value: 'cancelled', label: t('ownership_transfer_page.status_map.cancelled') },
])

const typeOptions = computed(() => [
    { value: 'license', label: t('ownership_transfer_page.type_license') },
    { value: 'product', label: t('ownership_transfer_page.type_product') },
])

const statusTags = {
    pending_source: 'warning',
    pending_target: 'warning',
    pending_approval: 'info',
    completed: 'success',
    rejected: 'danger',
    cancelled: 'info',
}

function statusLabel(s) {
    return t(`ownership_transfer_page.status_map.${s}`, s)
}
function statusTag(s) { return statusTags[s] || 'info' }
function typeLabel(type) {
    return type === 'license'
        ? t('ownership_transfer_page.type_license')
        : t('ownership_transfer_page.type_product')
}
function auditActionLabel(a) {
    return t(`ownership_transfer_page.audit.${a}`, a)
}
function recordStatusLabel(s) {
    return t(`ownership_transfer_page.record_status.${s}`, s)
}

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
        ElMessage.error(t('messages.load_failed') + ': ' + (e.response?.data?.message || e.message))
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
        ElMessage.success(t('ownership_transfer_page.messages.create_success'))
        createDialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || e.response?.data?.errors || t('ownership_transfer_page.messages.submit_failed'))
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
    } catch {
        ElMessage.error(t('ownership_transfer_page.messages.detail_failed'))
    }
}

// ─── 确认 ───
async function handleConfirmSource(row) {
    try {
        await ElMessageBox.confirm(t('ownership_transfer_page.confirm.source'), t('actions.confirm'))
        await confirmBySource(row.id)
        ElMessage.success(t('ownership_transfer_page.messages.source_confirmed'))
        loadList()
    } catch { /* cancelled */ }
}

async function handleConfirmTarget(row) {
    try {
        await ElMessageBox.confirm(t('ownership_transfer_page.confirm.target'), t('actions.confirm'))
        await confirmByTarget(row.id)
        ElMessage.success(t('ownership_transfer_page.messages.target_confirmed'))
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
        ElMessage.success(t('ownership_transfer_page.messages.approve_success'))
        approveDialogVisible.value = false
        loadList()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('ownership_transfer_page.messages.approve_failed'))
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
        ElMessage.success(t('ownership_transfer_page.messages.rejected'))
        rejectDialogVisible.value = false
        loadList()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        rejecting.value = false
    }
}

// ─── 取消 ───
async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(t('ownership_transfer_page.confirm.cancel'), t('actions.cancel'))
        await cancelOwnershipTransfer(row.id)
        ElMessage.success(t('ownership_transfer_page.messages.cancelled'))
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
