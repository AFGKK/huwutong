<template>
    <div class="transfers-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('ownership_transfer_page.refresh') }}
                </el-button>
                <el-button type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon> {{ t(`${P}.create_transfer`) }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t(`${P}.stats.total_requests`) }}</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">{{ t(`${P}.stats.pending`) }}</div>
                    <div class="stat-value">{{ stats.pending }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.completed') }}</div>
                    <div class="stat-value">{{ stats.completed }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">{{ t(`${P}.stats.approved`) }}</div>
                    <div class="stat-value">{{ stats.approved }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-danger">
                    <div class="stat-label">{{ t('ownership_transfer_page.stats.rejected') }}</div>
                    <div class="stat-value">{{ stats.rejected }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t(`${P}.stats.cancelled`) }}</div>
                    <div class="stat-value">{{ stats.cancelled }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 过滤器 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline size="small">
                <el-form-item :label="t('ownership_transfer_page.status')">
                    <el-select v-model="filters.status" :placeholder="t('licenses_page.all')" clearable style="width:140px">
                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('ownership_transfer_page.type')">
                    <el-select v-model="filters.type" :placeholder="t('licenses_page.all')" clearable style="width:140px">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('actions.search')">
                    <el-input v-model="filters.search" :placeholder="t(`${P}.search_ph`)" style="width:200px" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 转移请求列表 -->
        <el-card>
            <el-table :data="requests" stripe v-loading="loadingList">
                <el-table-column prop="reference" :label="t(`${P}.col_reference`)" width="160" />
                <el-table-column :label="t('ownership_transfer_page.type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'device_transfer' ? 'primary' : row.type === 'customer_transfer' ? 'success' : 'warning'"
                            size="small">
                            {{ typeLabel(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_license`)" min-width="180">
                    <template #default="{ row }">
                        <code>{{ row.transferable?.license_key ?? '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_reason`)" min-width="160" show-overflow-tooltip prop="reason" />
                <el-table-column :label="t('ownership_transfer_page.col_requester')" width="120" prop="requester?.name" />
                <el-table-column :label="t('ownership_transfer_page.label_approver')" width="120" prop="approver?.name" />
                <el-table-column :label="t('ownership_transfer_page.col_created_at')" width="160">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('ownership_transfer_page.col_actions')" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">{{ t('ownership_transfer_page.detail') }}</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small" type="success"
                            @click="showApproveDialog(row)">{{ t(`${P}.approve`) }}</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small" type="danger"
                            @click="showRejectDialog(row)">{{ t('ownership_transfer_page.reject') }}</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small"
                            @click="confirmCancel(row)">{{ t('actions.cancel') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination
                    v-if="pagination.total > 0"
                    :current-page="pagination.current_page"
                    :total="pagination.total"
                    :page-size="pagination.per_page"
                    layout="total, prev, pager, next"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>

        <!-- 新建转移弹窗 -->
        <el-dialog v-model="showCreateDialog" :title="t('ownership_transfer_page.create_dialog_title')" width="550px">
            <el-form :model="createForm" label-position="top">
                <el-form-item :label="t('ownership_transfer_page.form.transfer_type')" :rules="[{ required: true }]">
                    <el-radio-group v-model="createForm.type">
                        <el-radio v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.license_id`)" prop="license_id" :rules="[{ required: true, message: t(`${P}.validation.license_id`) }]">
                    <el-input-number v-model="createForm.license_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'customer_transfer'" :label="t(`${P}.form.target_customer_id`)" prop="target_customer_id"
                    :rules="[{ required: true, message: t(`${P}.validation.target_customer_id`) }]">
                    <el-input-number v-model="createForm.target_customer_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'user_transfer'" :label="t(`${P}.form.target_user_id`)" prop="target_user_id"
                    :rules="[{ required: true, message: t(`${P}.validation.target_user_id`) }]">
                    <el-input-number v-model="createForm.target_user_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'device_transfer'" :label="t(`${P}.form.target_device_fingerprint`)">
                    <el-input v-model="createForm.target_device_fingerprint" :placeholder="t(`${P}.form.target_device_fingerprint_ph`)" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'device_transfer'" :label="t(`${P}.form.target_device_name`)">
                    <el-input v-model="createForm.target_device_name" :placeholder="t(`${P}.form.target_device_name_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.reason`)">
                    <el-input v-model="createForm.reason" type="textarea" :rows="3" :placeholder="t(`${P}.form.reason_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doCreate" :loading="creating">{{ t('actions.submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" :title="t('ownership_transfer_page.detail_dialog_title')" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t(`${P}.col_reference`)" :span="2">
                        <code>{{ detailData.reference }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.type')">{{ typeLabel(detailData.type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.status')">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('licenses_page.license_key')" :span="2">
                        <code>{{ detailData.transferable?.license_key }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_requester')">{{ detailData.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.label_approver')">{{ detailData.approver?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('ownership_transfer_page.col_created_at')">{{ formatTime(detailData.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.col_approved_at`)">{{ detailData.approved_at ? formatTime(detailData.approved_at) : '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>{{ t(`${P}.section_source_info`) }}</h4>
                <pre class="json-block">{{ JSON.stringify(detailData.source_info, null, 2) }}</pre>

                <el-divider />
                <h4>{{ t('ownership_transfer_page.section_audit_log') }}</h4>
                <el-table :data="detailData.audit_log || []" size="small">
                    <el-table-column prop="action" :label="t(`${P}.col_audit_action`)" width="100" />
                    <el-table-column prop="by" :label="t(`${P}.col_operator_id`)" width="100" />
                    <el-table-column prop="at" :label="t(`${P}.col_time`)" width="180" />
                    <el-table-column prop="details" :label="t(`${P}.col_details`)" min-width="160" />
                </el-table>
            </template>
        </el-dialog>

        <!-- 批准弹窗 -->
        <el-dialog v-model="showApproveDialogVisible" :title="t(`${P}.approve_dialog_title`)" width="450px">
            <el-form>
                <el-form-item :label="t(`${P}.form.approve_notes`)">
                    <el-input v-model="approveNotes" type="textarea" :rows="3" :placeholder="t(`${P}.form.approve_notes_ph`)" />
                </el-form-item>
                <el-alert :title="t(`${P}.approve_warning`)" type="warning" :closable="false" show-icon />
            </el-form>
            <template #footer>
                <el-button @click="showApproveDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doApprove" :loading="approving">{{ t(`${P}.approve_submit`) }}</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝弹窗 -->
        <el-dialog v-model="showRejectDialogVisible" :title="t('ownership_transfer_page.reject_dialog_title')" width="450px">
            <el-form>
                <el-form-item :label="t('ownership_transfer_page.reject_reason')" :rules="[{ required: true }]">
                    <el-input v-model="rejectReason" type="textarea" :rows="3" :placeholder="t(`${P}.validation.reject_reason_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRejectDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="doReject" :loading="rejecting">{{ t('ownership_transfer_page.reject_submit') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseTransferApi from '@/api/licenseTransfer';

const P = 'transfers_page';
const { t, locale } = useI18n();

const loading = ref(false);
const loadingList = ref(false);
const creating = ref(false);
const approving = ref(false);
const rejecting = ref(false);
const showCreateDialog = ref(false);
const showDetailDialog = ref(false);
const showApproveDialogVisible = ref(false);
const showRejectDialogVisible = ref(false);

const requests = ref([]);
const detailData = ref(null);
const approveTarget = ref(null);
const rejectTarget = ref(null);
const approveNotes = ref('');
const rejectReason = ref('');

const stats = reactive({
    total: 0, pending: 0, approved: 0, completed: 0, rejected: 0, cancelled: 0, by_type: {},
});

const filters = reactive({
    status: '',
    type: '',
    search: '',
});

const pagination = reactive({
    current_page: 1, total: 0, per_page: 20,
});

const createForm = reactive({
    type: 'device_transfer',
    license_id: null,
    target_customer_id: null,
    target_user_id: null,
    target_device_fingerprint: '',
    target_device_name: '',
    reason: '',
});

const statusOptions = computed(() => [
    { value: 'pending', label: t(`${P}.status_map.pending`) },
    { value: 'approved', label: t(`${P}.status_map.approved`) },
    { value: 'rejected', label: t(`${P}.status_map.rejected`) },
    { value: 'cancelled', label: t(`${P}.status_map.cancelled`) },
    { value: 'completed', label: t(`${P}.status_map.completed`) },
]);

const typeOptions = computed(() => [
    { value: 'device_transfer', label: t(`${P}.type_map.device_transfer`) },
    { value: 'customer_transfer', label: t(`${P}.type_map.customer_transfer`) },
    { value: 'user_transfer', label: t(`${P}.type_map.user_transfer`) },
]);

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

function typeLabel(type) {
    return t(`${P}.type_map.${type}`, type);
}

function statusLabel(status) {
    return t(`${P}.status_map.${status}`, status);
}

function statusType(status) {
    const map = { pending: 'warning', approved: 'info', rejected: 'danger', cancelled: 'info', completed: 'success', expired: 'info' };
    return map[status] || 'info';
}

function formatTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(dateLocale.value);
}

async function loadAll() {
    loading.value = true;
    try {
        const [listRes, statsRes] = await Promise.all([
            licenseTransferApi.getList({ ...filters, page: pagination.current_page, per_page: pagination.per_page }),
            licenseTransferApi.getStats(),
        ]);
        const listData = listRes.data?.data || {};
        requests.value = listData.data || [];
        pagination.current_page = listData.current_page || 1;
        pagination.total = listData.total || 0;
        Object.assign(stats, statsRes.data?.data || {});
    } catch (err) {
        console.error('Failed to load transfers', err);
    } finally {
        loading.value = false;
    }
}

async function loadList() {
    loadingList.value = true;
    pagination.current_page = 1;
    try {
        const res = await licenseTransferApi.getList({ ...filters, page: 1, per_page: pagination.per_page });
        const listData = res.data?.data || {};
        requests.value = listData.data || [];
        pagination.current_page = listData.current_page || 1;
        pagination.total = listData.total || 0;
    } catch (err) {
        console.error('Failed to load list', err);
    } finally {
        loadingList.value = false;
    }
}

function resetFilters() {
    filters.status = '';
    filters.type = '';
    filters.search = '';
    loadList();
}

function onPageChange(page) {
    pagination.current_page = page;
    loadingList.value = true;
    licenseTransferApi.getList({ ...filters, page, per_page: pagination.per_page })
        .then(res => {
            const listData = res.data?.data || {};
            requests.value = listData.data || [];
        })
        .finally(() => { loadingList.value = false; });
}

async function doCreate() {
    creating.value = true;
    try {
        const payload = {
            transferable_type: 'license',
            transferable_id: createForm.license_id,
            reason: createForm.reason,
        };
        if (createForm.type === 'customer_transfer') payload.target_customer_id = createForm.target_customer_id;
        if (createForm.type === 'user_transfer') payload.target_user_id = createForm.target_user_id;
        if (createForm.type === 'device_transfer') {
            payload.target_device_fingerprint = createForm.target_device_fingerprint;
            payload.target_device_name = createForm.target_device_name;
        }
        await licenseTransferApi.createRequest(payload);
        ElMessage.success(t(`${P}.messages.create_success`));
        showCreateDialog.value = false;
        createForm.reason = '';
        await loadAll();
    } catch (err) {
        console.error('Create failed', err);
    } finally {
        creating.value = false;
    }
}

async function viewDetail(row) {
    showDetailDialog.value = true;
    detailData.value = null;
    try {
        const res = await licenseTransferApi.getDetail(row.id);
        detailData.value = res.data?.data;
    } catch (err) {
        console.error('Failed to load detail', err);
    }
}

function showApproveDialog(row) {
    approveTarget.value = row;
    approveNotes.value = '';
    showApproveDialogVisible.value = true;
}

function showRejectDialog(row) {
    rejectTarget.value = row;
    rejectReason.value = '';
    showRejectDialogVisible.value = true;
}

async function doApprove() {
    if (!approveTarget.value) return;
    approving.value = true;
    try {
        await licenseTransferApi.approve(approveTarget.value.id, { notes: approveNotes.value });
        ElMessage.success(t(`${P}.messages.approve_success`));
        showApproveDialogVisible.value = false;
        await loadAll();
    } catch (err) {
        console.error('Approve failed', err);
    } finally {
        approving.value = false;
    }
}

async function doReject() {
    if (!rejectTarget.value || !rejectReason.value) {
        ElMessage.warning(t(`${P}.validation.reject_reason_required`));
        return;
    }
    rejecting.value = true;
    try {
        await licenseTransferApi.reject(rejectTarget.value.id, { reason: rejectReason.value });
        ElMessage.success(t(`${P}.messages.rejected`));
        showRejectDialogVisible.value = false;
        await loadAll();
    } catch (err) {
        console.error('Reject failed', err);
    } finally {
        rejecting.value = false;
    }
}

async function confirmCancel(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.cancel`, { reference: row.reference }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await licenseTransferApi.cancel(row.id);
        ElMessage.success(t(`${P}.messages.cancelled`));
        await loadAll();
    } catch (err) {
        // cancelled
    }
}

onMounted(loadAll);
</script>

<style scoped>
.transfers-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }

.mb-4 { margin-bottom: 16px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }

.stat-active .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #0f172a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }

.json-block { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; max-height: 200px; }
</style>
