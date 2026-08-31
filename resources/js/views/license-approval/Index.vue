<template>
    <div class="approval-container">
        <el-page-header :content="t('license_approval_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert :title="t('license_approval_page.alert')" type="warning" show-icon :closable="false" class="alert-info" />

        <el-row :gutter="16" class="stat-cards">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-value text-warning">{{ dash.pending }}</div><div class="stat-label">{{ t('license_approval_page.statuses.pending') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-success">{{ dash.approved }}</div><div class="stat-label">{{ t('license_approval_page.statuses.approved') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-danger">{{ dash.rejected }}</div><div class="stat-label">{{ t('license_approval_page.statuses.rejected') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-primary">{{ dash.today }}</div><div class="stat-label">{{ t('license_approval_page.stats.today') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value">{{ dash.expired }}</div><div class="stat-label">{{ t('license_approval_page.statuses.expired') }}</div></el-card></el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>{{ t('license_approval_page.list_title') }}</span>
                    <el-select v-model="statusFilter" :placeholder="t('license_approval_page.cols.status')" clearable size="small" style="width:120px" @change="loadList">
                        <el-option :label="t('license_approval_page.all')" value="" />
                        <el-option :label="t('license_approval_page.statuses.pending')" value="pending" />
                        <el-option :label="t('license_approval_page.statuses.approved')" value="approved" />
                        <el-option :label="t('license_approval_page.statuses.rejected')" value="rejected" />
                        <el-option :label="t('license_approval_page.statuses.expired')" value="expired" />
                        <el-option :label="t('license_approval_page.statuses.cancelled')" value="cancelled" />
                    </el-select>
                    <el-select v-model="actionFilter" :placeholder="t('license_approval_page.action_type')" clearable size="small" style="width:140px" @change="loadList">
                        <el-option :label="t('license_approval_page.all')" value="" />
                        <el-option :label="t('license_approval_page.actions.upgrade')" value="upgrade" />
                        <el-option :label="t('license_approval_page.actions.downgrade')" value="downgrade" />
                        <el-option :label="t('license_approval_page.actions.transfer')" value="transfer" />
                        <el-option :label="t('license_approval_page.actions.seat_change')" value="seat_change" />
                        <el-option :label="t('license_approval_page.actions.type_change')" value="type_change" />
                    </el-select>
                </el-space>
            </template>
            <el-table :data="approvals" stripe v-loading="loading">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column :label="t('license_approval_page.cols.action')" width="100">
                    <template #default="{ row }">{{ actionLabel(row.action) }}</template>
                </el-table-column>
                <el-table-column label="License" width="180">
                    <template #default="{ row }">{{ row.license?.license_key || row.license_id }}</template>
                </el-table-column>
                <el-table-column :label="t('license_approval_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_approval_page.cols.requester')" width="120">
                    <template #default="{ row }">{{ row.requester?.name || row.requested_by }}</template>
                </el-table-column>
                <el-table-column :label="t('license_approval_page.cols.reason')" min-width="200" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.reason || '-' }}</template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('license_approval_page.cols.submitted')" width="160" />
                <el-table-column :label="t('license_approval_page.cols.ops')" width="240" fixed="right">
                    <template #default="{ row }">
                        <template v-if="row.status === 'pending'">
                            <el-button size="small" type="success" @click="handleApprove(row)">{{ t('actions.approve') }}</el-button>
                            <el-button size="small" type="danger" @click="showReject(row)">{{ t('actions.reject') }}</el-button>
                            <el-button size="small" @click="handleCancel(row)">{{ t('actions.cancel') }}</el-button>
                        </template>
                        <template v-else>
                            <el-button size="small" @click="viewDetail(row)">{{ t('actions.view_details') }}</el-button>
                            <el-tag v-if="row.reject_reason" size="small" type="danger">{{ row.reject_reason }}</el-tag>
                        </template>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
        </el-card>

        <el-dialog v-model="rejectVisible" :title="t('license_approval_page.reject_title')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('license_approval_page.reject_reason')" required>
                    <el-input v-model="rejectReason" type="textarea" :rows="3" :placeholder="t('license_approval_page.reject_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rejectVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="rejecting" @click="confirmReject">{{ t('license_approval_page.confirm_reject') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="detailVisible" :title="t('license_approval_page.detail_title')" width="700px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.status')"><el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.action')">{{ actionLabel(detail.action) }}</el-descriptions-item>
                <el-descriptions-item label="License">{{ detail.license?.license_key }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.requester')">{{ detail.requester?.name }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.approver')">{{ detail.approver?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.submitted')">{{ detail.created_at }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.approved_at')">{{ detail.approved_at || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.cols.expires')">{{ detail.expires_at }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_approval_page.reject_reason')">{{ detail.reject_reason || '-' }}</el-descriptions-item>
            </el-descriptions>
            <h4>{{ t('license_approval_page.request_data') }}</h4>
            <pre class="json-view">{{ JSON.stringify(detail?.request_data, null, 2) }}</pre>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import licenseApproval from '@/api/licenseApproval'

const { t } = useI18n()

const loading = ref(false)
const approvals = ref([])
const statusFilter = ref('')
const actionFilter = ref('')
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
const dash = reactive({ pending: 0, approved: 0, rejected: 0, today: 0, expired: 0 })
const rejectVisible = ref(false)
const rejectReason = ref('')
const rejecting = ref(false)
const rejectTarget = ref(null)
const detailVisible = ref(false)
const detail = ref(null)

function actionLabel(a) {
    const key = { upgrade: 'upgrade', downgrade: 'downgrade', transfer: 'transfer', seat_change: 'seat_change', type_change: 'type_change', early_renewal: 'early_renewal' }[a]
    return key ? t(`license_approval_page.actions.${key}`) : a
}
function statusType(s) { return { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'info', expired: 'info' }[s] || 'info' }
function statusLabel(s) {
    const key = { pending: 'pending', approved: 'approved', rejected: 'rejected', cancelled: 'cancelled', expired: 'expired' }[s]
    return key ? t(`license_approval_page.statuses.${key}`) : s
}

async function loadDash() {
    try { const res = await licenseApproval.dashboard(); Object.assign(dash, res.data.data); } catch {}
}
async function loadList(p) {
    loading.value = true
    try {
        const params = { page: p || page.value, per_page: perPage.value }
        if (statusFilter.value) params.status = statusFilter.value
        if (actionFilter.value) params.action = actionFilter.value
        const res = await licenseApproval.list(params)
        approvals.value = res.data.data.items || []
        total.value = res.data.data.total
        page.value = res.data.data.page
    } catch {} finally { loading.value = false }
}
async function handleApprove(row) {
    try { await licenseApproval.approve(row.id); ElMessage.success(t('license_approval_page.messages.approved')); loadList(); loadDash(); } catch {}
}
function showReject(row) { rejectTarget.value = row; rejectReason.value = ''; rejectVisible.value = true }
async function confirmReject() {
    if (!rejectReason.value) { ElMessage.warning(t('license_approval_page.messages.need_reason')); return }
    rejecting.value = true
    try { await licenseApproval.reject(rejectTarget.value.id, rejectReason.value); ElMessage.success(t('license_approval_page.messages.rejected')); rejectVisible.value = false; loadList(); loadDash(); } catch {} finally { rejecting.value = false }
}
async function handleCancel(row) {
    try { await licenseApproval.cancel(row.id); ElMessage.success(t('license_approval_page.messages.cancelled')); loadList(); loadDash(); } catch {}
}
async function viewDetail(row) {
    try { const res = await licenseApproval.show(row.id); detail.value = res.data.data; detailVisible.value = true; } catch {}
}

onMounted(() => { loadDash(); loadList() })
</script>

<style scoped>
.approval-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-primary { color: #0f172a; }
.pagination { margin-top: 16px; text-align: center; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; max-height: 300px; overflow: auto; font-size: 12px; }
</style>
