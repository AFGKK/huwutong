<template>
    <div class="approval-container">
        <el-page-header :content="'License 变更审批'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="License 升级/降级/转移/改席位等操作需提交审批，管理员审批通过后方可执行。超时 72 小时自动过期。" type="warning" show-icon :closable="false" class="alert-info" />

        <!-- 统计 -->
        <el-row :gutter="16" class="stat-cards">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-value text-warning">{{ dash.pending }}</div><div class="stat-label">待审批</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-success">{{ dash.approved }}</div><div class="stat-label">已通过</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-danger">{{ dash.rejected }}</div><div class="stat-label">已拒绝</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-primary">{{ dash.today }}</div><div class="stat-label">今日提交</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value">{{ dash.expired }}</div><div class="stat-label">已过期</div></el-card></el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>审批列表</span>
                    <el-select v-model="statusFilter" placeholder="状态" clearable size="small" style="width:120px" @change="loadList">
                        <el-option label="全部" value="" />
                        <el-option label="待审批" value="pending" />
                        <el-option label="已通过" value="approved" />
                        <el-option label="已拒绝" value="rejected" />
                        <el-option label="已过期" value="expired" />
                        <el-option label="已取消" value="cancelled" />
                    </el-select>
                    <el-select v-model="actionFilter" placeholder="操作类型" clearable size="small" style="width:140px" @change="loadList">
                        <el-option label="全部" value="" />
                        <el-option label="升级" value="upgrade" />
                        <el-option label="降级" value="downgrade" />
                        <el-option label="转移" value="transfer" />
                        <el-option label="改席位" value="seat_change" />
                        <el-option label="改类型" value="type_change" />
                    </el-select>
                </el-space>
            </template>
            <el-table :data="approvals" stripe v-loading="loading">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">{{ actionLabel(row.action) }}</template>
                </el-table-column>
                <el-table-column label="License" width="180">
                    <template #default="{ row }">{{ row.license?.license_key || row.license_id }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="申请人" width="120">
                    <template #default="{ row }">{{ row.requester?.name || row.requested_by }}</template>
                </el-table-column>
                <el-table-column label="原因" min-width="200" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.reason || '-' }}</template>
                </el-table-column>
                <el-table-column prop="created_at" label="提交时间" width="160" />
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <template v-if="row.status === 'pending'">
                            <el-button size="small" type="success" @click="handleApprove(row)">通过</el-button>
                            <el-button size="small" type="danger" @click="showReject(row)">拒绝</el-button>
                            <el-button size="small" @click="handleCancel(row)">取消</el-button>
                        </template>
                        <template v-else>
                            <el-button size="small" @click="viewDetail(row)">详情</el-button>
                            <el-tag v-if="row.reject_reason" size="small" type="danger">{{ row.reject_reason }}</el-tag>
                        </template>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
        </el-card>

        <!-- 拒绝对话框 -->
        <el-dialog v-model="rejectVisible" title="拒绝审批" width="400px">
            <el-form label-position="top">
                <el-form-item label="拒绝原因" required>
                    <el-input v-model="rejectReason" type="textarea" :rows="3" placeholder="请填写拒绝原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rejectVisible = false">取消</el-button>
                <el-button type="danger" :loading="rejecting" @click="confirmReject">确认拒绝</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="审批详情" width="700px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
                <el-descriptions-item label="状态"><el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                <el-descriptions-item label="操作">{{ actionLabel(detail.action) }}</el-descriptions-item>
                <el-descriptions-item label="License">{{ detail.license?.license_key }}</el-descriptions-item>
                <el-descriptions-item label="申请人">{{ detail.requester?.name }}</el-descriptions-item>
                <el-descriptions-item label="审批人">{{ detail.approver?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="提交时间">{{ detail.created_at }}</el-descriptions-item>
                <el-descriptions-item label="审批时间">{{ detail.approved_at || '-' }}</el-descriptions-item>
                <el-descriptions-item label="过期时间">{{ detail.expires_at }}</el-descriptions-item>
                <el-descriptions-item label="拒绝原因">{{ detail.reject_reason || '-' }}</el-descriptions-item>
            </el-descriptions>
            <h4>请求数据</h4>
            <pre class="json-view">{{ JSON.stringify(detail?.request_data, null, 2) }}</pre>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import licenseApproval from '@/api/licenseApproval';

const loading = ref(false);
const approvals = ref([]);
const statusFilter = ref('');
const actionFilter = ref('');
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const dash = reactive({ pending: 0, approved: 0, rejected: 0, today: 0, expired: 0 });
const rejectVisible = ref(false);
const rejectReason = ref('');
const rejecting = ref(false);
const rejectTarget = ref(null);
const detailVisible = ref(false);
const detail = ref(null);

function actionLabel(a) { const m = { upgrade: '升级', downgrade: '降级', transfer: '转移', seat_change: '改席位', type_change: '改类型', early_renewal: '提前续费' }; return m[a] || a; }
function statusType(s) { return { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'info', expired: 'info' }[s] || 'info'; }
function statusLabel(s) { return { pending: '待审批', approved: '已通过', rejected: '已拒绝', cancelled: '已取消', expired: '已过期' }[s] || s; }

async function loadDash() {
    try { const res = await licenseApproval.dashboard(); Object.assign(dash, res.data.data); } catch {}
}
async function loadList(p) {
    loading.value = true;
    try {
        const params = { page: p || page.value, per_page: perPage.value };
        if (statusFilter.value) params.status = statusFilter.value;
        if (actionFilter.value) params.action = actionFilter.value;
        const res = await licenseApproval.list(params);
        approvals.value = res.data.data.items || [];
        total.value = res.data.data.total;
        page.value = res.data.data.page;
    } catch {} finally { loading.value = false; }
}
async function handleApprove(row) {
    try { await licenseApproval.approve(row.id); ElMessage.success('已批准'); loadList(); loadDash(); } catch {}
}
function showReject(row) { rejectTarget.value = row; rejectReason.value = ''; rejectVisible.value = true; }
async function confirmReject() {
    if (!rejectReason.value) { ElMessage.warning('请填写拒绝原因'); return; }
    rejecting.value = true;
    try { await licenseApproval.reject(rejectTarget.value.id, rejectReason.value); ElMessage.success('已拒绝'); rejectVisible.value = false; loadList(); loadDash(); } catch {} finally { rejecting.value = false; }
}
async function handleCancel(row) {
    try { await licenseApproval.cancel(row.id); ElMessage.success('已取消'); loadList(); loadDash(); } catch {}
}
async function viewDetail(row) {
    try { const res = await licenseApproval.show(row.id); detail.value = res.data.data; detailVisible.value = true; } catch {}
}

onMounted(() => { loadDash(); loadList(); });
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
.text-primary { color: #409eff; }
.pagination { margin-top: 16px; text-align: center; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; max-height: 300px; overflow: auto; font-size: 12px; }
</style>
