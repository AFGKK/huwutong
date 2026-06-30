<template>
    <div class="transfers-page">
        <div class="page-header">
            <h2>License 转移</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
                <el-button type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon> 新建转移
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">总请求</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">待审批</div>
                    <div class="stat-value">{{ stats.pending }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">已完成</div>
                    <div class="stat-value">{{ stats.completed }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">已批准</div>
                    <div class="stat-value">{{ stats.approved }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-danger">
                    <div class="stat-label">已拒绝</div>
                    <div class="stat-value">{{ stats.rejected }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">已取消</div>
                    <div class="stat-value">{{ stats.cancelled }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 过滤器 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline size="small">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable style="width:140px">
                        <el-option label="待审批" value="pending" />
                        <el-option label="已批准" value="approved" />
                        <el-option label="已拒绝" value="rejected" />
                        <el-option label="已取消" value="cancelled" />
                        <el-option label="已完成" value="completed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="filters.type" placeholder="全部" clearable style="width:140px">
                        <el-option label="设备转移" value="device_transfer" />
                        <el-option label="客户转移" value="customer_transfer" />
                        <el-option label="用户转移" value="user_transfer" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="引用号/License Key" style="width:200px" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 转移请求列表 -->
        <el-card>
            <el-table :data="requests" stripe v-loading="loadingList">
                <el-table-column prop="reference" label="引用号" width="160" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'device_transfer' ? 'primary' : row.type === 'customer_transfer' ? 'success' : 'warning'"
                            size="small">
                            {{ typeLabel(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="License" min-width="180">
                    <template #default="{ row }">
                        <code>{{ row.license?.license_key ?? '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="原因" min-width="160" show-overflow-tooltip prop="reason" />
                <el-table-column label="申请人" width="120" prop="requester?.name" />
                <el-table-column label="审批人" width="120" prop="approver?.name" />
                <el-table-column label="创建时间" width="160">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small" type="success"
                            @click="showApproveDialog(row)">批准</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small" type="danger"
                            @click="showRejectDialog(row)">拒绝</el-button>
                        <el-button v-if="row.status === 'pending'"
                            size="small"
                            @click="confirmCancel(row)">取消</el-button>
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
        <el-dialog v-model="showCreateDialog" title="新建转移请求" width="550px">
            <el-form :model="createForm" label-position="top">
                <el-form-item label="转移类型" :rules="[{ required: true }]">
                    <el-radio-group v-model="createForm.type">
                        <el-radio value="device_transfer">设备转移</el-radio>
                        <el-radio value="customer_transfer">客户转移</el-radio>
                        <el-radio value="user_transfer">用户转移</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="License ID" prop="license_id" :rules="[{ required: true, message: '请输入License ID' }]">
                    <el-input-number v-model="createForm.license_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'customer_transfer'" label="目标客户ID" prop="target_customer_id"
                    :rules="[{ required: true, message: '请输入目标客户ID' }]">
                    <el-input-number v-model="createForm.target_customer_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'user_transfer'" label="目标用户ID" prop="target_user_id"
                    :rules="[{ required: true, message: '请输入目标用户ID' }]">
                    <el-input-number v-model="createForm.target_user_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'device_transfer'" label="目标设备指纹">
                    <el-input v-model="createForm.target_device_fingerprint" placeholder="输入设备指纹" />
                </el-form-item>
                <el-form-item v-if="createForm.type === 'device_transfer'" label="目标设备名称">
                    <el-input v-model="createForm.target_device_name" placeholder="例如: 新办公电脑" />
                </el-form-item>
                <el-form-item label="转移原因">
                    <el-input v-model="createForm.reason" type="textarea" :rows="3" placeholder="说明转移原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" @click="doCreate" :loading="creating">提交</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" title="转移请求详情" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="引用号" :span="2">
                        <code>{{ detailData.reference }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="类型">{{ typeLabel(detailData.type) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="License Key" :span="2">
                        <code>{{ detailData.license?.license_key }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="申请人">{{ detailData.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item label="审批人">{{ detailData.approver?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatTime(detailData.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="审批时间">{{ detailData.approved_at ? formatTime(detailData.approved_at) : '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>源信息</h4>
                <pre class="json-block">{{ JSON.stringify(detailData.source_info, null, 2) }}</pre>

                <el-divider />
                <h4>审计日志</h4>
                <el-table :data="detailData.audit_log || []" size="small">
                    <el-table-column prop="action" label="操作" width="100" />
                    <el-table-column prop="by" label="操作人ID" width="100" />
                    <el-table-column prop="at" label="时间" width="180" />
                    <el-table-column prop="details" label="详情" min-width="160" />
                </el-table>
            </template>
        </el-dialog>

        <!-- 批准弹窗 -->
        <el-dialog v-model="showApproveDialogVisible" title="批准转移" width="450px">
            <el-form>
                <el-form-item label="审批备注">
                    <el-input v-model="approveNotes" type="textarea" :rows="3" placeholder="可选备注" />
                </el-form-item>
                <el-alert title="批准后将自动执行转移操作，不可撤回" type="warning" :closable="false" show-icon />
            </el-form>
            <template #footer>
                <el-button @click="showApproveDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="doApprove" :loading="approving">确认批准</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝弹窗 -->
        <el-dialog v-model="showRejectDialogVisible" title="拒绝转移" width="450px">
            <el-form>
                <el-form-item label="拒绝原因" :rules="[{ required: true }]">
                    <el-input v-model="rejectReason" type="textarea" :rows="3" placeholder="请输入拒绝原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRejectDialogVisible = false">取消</el-button>
                <el-button type="danger" @click="doReject" :loading="rejecting">确认拒绝</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseTransferApi from '@/api/licenseTransfer';

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

function typeLabel(type) {
    const map = { device_transfer: '设备转移', customer_transfer: '客户转移', user_transfer: '用户转移' };
    return map[type] || type;
}

function statusLabel(status) {
    const map = { pending: '待审批', approved: '已批准', rejected: '已拒绝', cancelled: '已取消', completed: '已完成', expired: '已过期' };
    return map[status] || status;
}

function statusType(status) {
    const map = { pending: 'warning', approved: 'info', rejected: 'danger', cancelled: 'info', completed: 'success', expired: 'info' };
    return map[status] || 'info';
}

function formatTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN');
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
        const payload = { ...createForm };
        // Clean up null values for non-required fields
        if (payload.type !== 'customer_transfer') delete payload.target_customer_id;
        if (payload.type !== 'user_transfer') delete payload.target_user_id;
        if (payload.type !== 'device_transfer') {
            delete payload.target_device_fingerprint;
            delete payload.target_device_name;
        }
        await licenseTransferApi.createRequest(payload);
        ElMessage.success('转移请求已创建');
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
        ElMessage.success('转移已批准并执行');
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
        ElMessage.warning('请输入拒绝原因');
        return;
    }
    rejecting.value = true;
    try {
        await licenseTransferApi.reject(rejectTarget.value.id, { reason: rejectReason.value });
        ElMessage.success('转移请求已拒绝');
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
        await ElMessageBox.confirm(`确定取消转移请求 ${row.reference} 吗？`, '确认', { type: 'warning' });
        await licenseTransferApi.cancel(row.id);
        ElMessage.success('转移请求已取消');
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
.stat-info .stat-value { color: #409eff; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }

.json-block { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; max-height: 200px; }
</style>
