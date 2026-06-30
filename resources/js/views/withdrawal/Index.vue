<template>
    <div class="withdrawal-page">
        <div class="page-header">
            <div>
                <h2>提现管理</h2>
                <p class="text-muted">多渠道提现审核与批次打款</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6" :lg="4">
                <el-card shadow="hover"><div class="stat-label">待审核</div><div class="stat-value warning">{{ stats.pending_review_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">待处理金额</div><div class="stat-value">¥{{ formatMoney(stats.pending_amount) }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">处理中</div><div class="stat-value primary">{{ stats.processing_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">今日打款</div><div class="stat-value success">¥{{ formatMoney(stats.today_completed_amount) }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">本月打款</div><div class="stat-value">¥{{ formatMoney(stats.month_completed_amount) }}</div></el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover">
            <el-tabs v-model="activeTab" @tab-change="onTabChange">
                <el-tab-pane label="提现记录" name="records">
                    <div class="tab-toolbar">
                        <el-input v-model="filters.search" placeholder="搜索用户" clearable style="width:200px" @keyup.enter="loadRecords" />
                        <el-select v-model="filters.status" placeholder="状态" clearable style="width:130px" @change="loadRecords">
                            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                        <el-select v-model="filters.channel" placeholder="渠道" clearable style="width:120px" @change="loadRecords">
                            <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                        <el-date-picker v-model="filters.dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:260px" @change="loadRecords" />
                        <el-button @click="loadRecords">查询</el-button>
                        <el-button type="warning" :loading="retryLoading" @click="handleBatchRetry">批量重试失败</el-button>
                        <el-button type="success" :loading="releasing" @click="handleReleasePending">T+30 解冻</el-button>
                    </div>
                    <el-table :data="records" stripe v-loading="recordsLoading">
                        <el-table-column label="ID" prop="id" width="70" />
                        <el-table-column label="用户" min-width="180">
                            <template #default="{ row }">
                                <div>{{ row.earnings_account?.user?.name || '-' }}</div>
                                <div class="text-muted small">{{ row.earnings_account?.user?.email || '' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="金额" width="100"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                        <el-table-column label="手续费" width="90"><template #default="{ row }">¥{{ formatMoney(row.fee) }}</template></el-table-column>
                        <el-table-column label="净额" width="100"><template #default="{ row }">¥{{ formatMoney(row.net_amount) }}</template></el-table-column>
                        <el-table-column label="渠道" width="90"><template #default="{ row }"><el-tag size="small">{{ channelLabel(row.channel) }}</el-tag></template></el-table-column>
                        <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column label="批次号" prop="batch_no" width="130" />
                        <el-table-column label="创建时间" width="160"><template #default="{ row }">{{ formatDate(row.created_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'pending_review'" size="small" link type="primary" @click="openReview(row)">审核</el-button>
                                <el-button v-if="['pending','processing'].includes(row.status)" size="small" link type="success" @click="openComplete(row)">完成</el-button>
                                <el-button v-if="['pending','processing'].includes(row.status)" size="small" link type="danger" @click="openFailed(row)">失败</el-button>
                                <el-button v-if="row.status === 'failed' || row.status === 'rejected'" size="small" link type="warning" @click="handleRetry(row)">重试</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="recordsTotal" :page-size="20" v-model:current-page="recordsPage" @current-change="loadRecords" />
                </el-tab-pane>

                <el-tab-pane label="打款批次" name="batches">
                    <div class="tab-toolbar">
                        <el-select v-model="batchFilters.channel" placeholder="渠道" clearable style="width:120px" @change="loadBatches">
                            <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                        <el-button type="primary" @click="openCreateBatch">创建批次</el-button>
                    </div>
                    <el-table :data="batches" stripe v-loading="batchesLoading">
                        <el-table-column label="批次号" prop="batch_no" width="140" />
                        <el-table-column label="渠道" width="90"><template #default="{ row }">{{ channelLabel(row.channel) }}</template></el-table-column>
                        <el-table-column label="标题" prop="title" min-width="140" />
                        <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag size="small">{{ row.status }}</el-tag></template></el-table-column>
                        <el-table-column label="笔数" prop="total_count" width="70" />
                        <el-table-column label="总金额" width="110"><template #default="{ row }">¥{{ formatMoney(row.total_amount) }}</template></el-table-column>
                        <el-table-column label="创建人" width="100"><template #default="{ row }">{{ row.creator?.name || '-' }}</template></el-table-column>
                        <el-table-column label="创建时间" width="160"><template #default="{ row }">{{ formatDate(row.created_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openBatchDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'pending'" size="small" link type="success" @click="completeBatch(row)">完成批次</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="batchesTotal" :page-size="20" v-model:current-page="batchesPage" @current-change="loadBatches" />
                </el-tab-pane>

                <el-tab-pane label="渠道配置" name="channels">
                    <el-table :data="channels" stripe v-loading="channelsLoading">
                        <el-table-column label="渠道" width="120"><template #default="{ row }">{{ row.name }}</template></el-table-column>
                        <el-table-column label="最低金额" width="110"><template #default="{ row }">¥{{ formatMoney(row.min_amount) }}</template></el-table-column>
                        <el-table-column label="最高金额" width="110"><template #default="{ row }">¥{{ formatMoney(row.max_amount) }}</template></el-table-column>
                        <el-table-column label="手续费率" width="100"><template #default="{ row }">{{ (row.fee_rate * 100).toFixed(2) }}%</template></el-table-column>
                        <el-table-column label="日限额" width="120"><template #default="{ row }">¥{{ formatMoney(row.daily_limit) }}</template></el-table-column>
                        <el-table-column label="待处理" width="90"><template #default="{ row }">{{ stats.channel_stats?.[row.id]?.pending_count || 0 }}</template></el-table-column>
                        <el-table-column label="本月打款" width="120"><template #default="{ row }">¥{{ formatMoney(stats.channel_stats?.[row.id]?.month_amount) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="reviewVisible" title="审核提现" width="420px">
            <el-form label-width="80px">
                <el-form-item label="操作">
                    <el-radio-group v-model="reviewForm.action">
                        <el-radio value="approve">通过</el-radio>
                        <el-radio value="reject">驳回</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model="reviewForm.remark" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="reviewVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitReview">确认</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="completeVisible" title="标记打款完成" width="420px">
            <el-form label-width="90px">
                <el-form-item label="交易号"><el-input v-model="completeForm.transaction_id" /></el-form-item>
                <el-form-item label="凭证"><el-upload :auto-upload="false" :limit="1" :on-change="onProofChange"><el-button>选择图片</el-button></el-upload></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="completeVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitComplete">确认</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="failedVisible" title="标记打款失败" width="420px">
            <el-form label-width="80px">
                <el-form-item label="原因"><el-input v-model="failedForm.failure_reason" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="failedVisible = false">取消</el-button>
                <el-button type="danger" :loading="submitting" @click="submitFailed">确认</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="batchCreateVisible" title="创建打款批次" width="420px">
            <el-form label-width="80px">
                <el-form-item label="渠道">
                    <el-select v-model="batchForm.channel" style="width:100%">
                        <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标题"><el-input v-model="batchForm.title" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="batchCreateVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitCreateBatch">创建</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="提现详情" size="480px">
            <template v-if="currentRecord">
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="ID">{{ currentRecord.id }}</el-descriptions-item>
                    <el-descriptions-item label="用户">{{ currentRecord.earnings_account?.user?.name }} ({{ currentRecord.earnings_account?.user?.email }})</el-descriptions-item>
                    <el-descriptions-item label="金额">¥{{ formatMoney(currentRecord.amount) }}</el-descriptions-item>
                    <el-descriptions-item label="手续费">¥{{ formatMoney(currentRecord.fee) }}</el-descriptions-item>
                    <el-descriptions-item label="净额">¥{{ formatMoney(currentRecord.net_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="渠道">{{ channelLabel(currentRecord.channel) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ statusLabel(currentRecord.status) }}</el-descriptions-item>
                    <el-descriptions-item label="批次号">{{ currentRecord.batch_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="交易号">{{ currentRecord.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="备注">{{ currentRecord.remark || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="失败原因">{{ currentRecord.failure_reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatDate(currentRecord.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="完成时间">{{ formatDate(currentRecord.completed_at) }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-drawer>

        <el-drawer v-model="batchDetailVisible" title="批次详情" size="640px">
            <template v-if="currentBatch">
                <el-descriptions :column="2" border class="mb-3">
                    <el-descriptions-item label="批次号">{{ currentBatch.batch_no }}</el-descriptions-item>
                    <el-descriptions-item label="渠道">{{ channelLabel(currentBatch.channel) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ currentBatch.status }}</el-descriptions-item>
                    <el-descriptions-item label="总金额">¥{{ formatMoney(currentBatch.total_amount) }}</el-descriptions-item>
                </el-descriptions>
                <el-table :data="currentBatch.withdrawals || []" stripe size="small">
                    <el-table-column label="ID" prop="id" width="60" />
                    <el-table-column label="用户" min-width="120"><template #default="{ row }">{{ row.earnings_account?.user?.name || '-' }}</template></el-table-column>
                    <el-table-column label="金额" width="90"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                    <el-table-column label="状态" width="90"><template #default="{ row }">{{ statusLabel(row.status) }}</template></el-table-column>
                </el-table>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import withdrawalApi from '@/api/withdrawal';

const loading = ref(false);
const activeTab = ref('records');
const stats = ref({});
const channels = ref([]);
const channelsLoading = ref(false);

const records = ref([]);
const recordsLoading = ref(false);
const recordsTotal = ref(0);
const recordsPage = ref(1);
const filters = reactive({ search: '', status: '', channel: '', dateRange: null });

const batches = ref([]);
const batchesLoading = ref(false);
const batchesTotal = ref(0);
const batchesPage = ref(1);
const batchFilters = reactive({ channel: '' });

const currentRecord = ref(null);
const currentBatch = ref(null);
const detailVisible = ref(false);
const batchDetailVisible = ref(false);
const reviewVisible = ref(false);
const completeVisible = ref(false);
const failedVisible = ref(false);
const batchCreateVisible = ref(false);
const submitting = ref(false);

const reviewForm = reactive({ action: 'approve', remark: '' });
const completeForm = reactive({ transaction_id: '', proof: null });
const failedForm = reactive({ failure_reason: '' });
const batchForm = reactive({ channel: 'bank', title: '' });

// 重试
const retryLoading = ref(false);
const batchRetryIds = ref([]);

// T+30 解冻
const releasing = ref(false);

const channelOptions = [
    { value: 'bank', label: '银行卡' },
    { value: 'alipay', label: '支付宝' },
    { value: 'wechat', label: '微信' },
    { value: 'paypal', label: 'PayPal' },
];
const statusOptions = [
    { value: 'pending_review', label: '待审核' },
    { value: 'pending', label: '待打款' },
    { value: 'processing', label: '处理中' },
    { value: 'completed', label: '已完成' },
    { value: 'failed', label: '失败' },
    { value: 'rejected', label: '已驳回' },
    { value: 'cancelled', label: '已取消' },
];

function formatMoney(v) {
    return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatDate(v) {
    return v ? new Date(v).toLocaleString('zh-CN') : '-';
}
function channelLabel(ch) {
    return channelOptions.find(c => c.value === ch)?.label || ch;
}
function statusLabel(s) {
    return statusOptions.find(o => o.value === s)?.label || s;
}
function statusTag(s) {
    return { pending_review: 'warning', pending: 'info', processing: 'primary', completed: 'success', failed: 'danger', rejected: 'info', cancelled: 'info' }[s] || 'info';
}

async function loadStats() {
    try {
        const res = await withdrawalApi.stats();
        stats.value = res.data?.data || res.data || {};
    } catch { /* ignore */ }
}

async function loadChannels() {
    channelsLoading.value = true;
    try {
        const res = await withdrawalApi.channels();
        channels.value = res.data?.data || res.data || [];
    } finally {
        channelsLoading.value = false;
    }
}

async function loadRecords() {
    recordsLoading.value = true;
    try {
        const params = { page: recordsPage.value, per_page: 20, status: filters.status || undefined, channel: filters.channel || undefined };
        if (filters.dateRange?.length === 2) {
            params.date_from = filters.dateRange[0];
            params.date_to = filters.dateRange[1];
        }
        const res = await withdrawalApi.index(params);
        const payload = res.data?.data || res.data;
        records.value = payload?.data || payload || [];
        recordsTotal.value = payload?.total || records.value.length;
    } finally {
        recordsLoading.value = false;
    }
}

async function loadBatches() {
    batchesLoading.value = true;
    try {
        const res = await withdrawalApi.batches({ page: batchesPage.value, per_page: 20, channel: batchFilters.channel || undefined });
        const payload = res.data?.data || res.data;
        batches.value = payload?.data || payload || [];
        batchesTotal.value = payload?.total || batches.value.length;
    } finally {
        batchesLoading.value = false;
    }
}

function onTabChange(tab) {
    if (tab === 'batches') loadBatches();
    if (tab === 'channels') loadChannels();
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadStats(), loadRecords(), loadChannels()]);
    if (activeTab.value === 'batches') await loadBatches();
    loading.value = false;
}

function openDetail(row) {
    currentRecord.value = row;
    detailVisible.value = true;
}

function openReview(row) {
    currentRecord.value = row;
    reviewForm.action = 'approve';
    reviewForm.remark = '';
    reviewVisible.value = true;
}

function openComplete(row) {
    currentRecord.value = row;
    completeForm.transaction_id = '';
    completeForm.proof = null;
    completeVisible.value = true;
}

function openFailed(row) {
    currentRecord.value = row;
    failedForm.failure_reason = '';
    failedVisible.value = true;
}

function onProofChange(file) {
    completeForm.proof = file.raw;
}

async function submitReview() {
    submitting.value = true;
    try {
        await withdrawalApi.review(currentRecord.value.id, { action: reviewForm.action, remark: reviewForm.remark });
        ElMessage.success('审核完成');
        reviewVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

async function submitComplete() {
    submitting.value = true;
    try {
        const fd = new FormData();
        if (completeForm.transaction_id) fd.append('transaction_id', completeForm.transaction_id);
        if (completeForm.proof) fd.append('proof', completeForm.proof);
        await withdrawalApi.markCompleted(currentRecord.value.id, fd);
        ElMessage.success('已标记完成');
        completeVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

async function submitFailed() {
    if (!failedForm.failure_reason) return ElMessage.warning('请填写失败原因');
    submitting.value = true;
    try {
        await withdrawalApi.markFailed(currentRecord.value.id, { failure_reason: failedForm.failure_reason });
        ElMessage.success('已标记失败');
        failedVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

// ─── M3-72 增强 ───

async function handleRetry(row) {
    try {
        await ElMessageBox.confirm(`确认重试提现 #${row.id}？将重置为待处理状态。`, '提示');
        await withdrawalApi.retry(row.id);
        ElMessage.success('提现已重置');
        await loadRecords();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '重试失败');
    }
}

async function handleBatchRetry() {
    const failedRecords = records.value.filter(r => r.status === 'failed' || r.status === 'rejected');
    if (!failedRecords.length) return ElMessage.warning('没有可重试的失败/驳回提现');
    try {
        await ElMessageBox.confirm(`确认批量重试 ${failedRecords.length} 条失败提现？`, '提示');
        const ids = failedRecords.map(r => r.id);
        const res = await withdrawalApi.batchRetry(ids);
        ElMessage.success(res.data?.message || '重试完成');
        await loadRecords();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '批量重试失败');
    }
}

async function handleReleasePending() {
    try {
        await ElMessageBox.confirm('确认执行 T+30 到期佣金解冻？将释放所有到期冻结余额。', '提示');
        releasing.value = true;
        const res = await withdrawalApi.releasePending();
        ElMessage.success(res.data?.message || '解冻完成');
        await loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '解冻失败');
    } finally {
        releasing.value = false;
    }
}

function openCreateBatch() {
    batchForm.channel = 'bank';
    batchForm.title = '';
    batchCreateVisible.value = true;
}

async function submitCreateBatch() {
    submitting.value = true;
    try {
        await withdrawalApi.createBatch({ channel: batchForm.channel, title: batchForm.title });
        ElMessage.success('批次已创建');
        batchCreateVisible.value = false;
        activeTab.value = 'batches';
        await loadBatches();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败');
    } finally {
        submitting.value = false;
    }
}

async function openBatchDetail(row) {
    try {
        const res = await withdrawalApi.showBatch(row.id);
        currentBatch.value = res.data?.data || res.data;
        batchDetailVisible.value = true;
    } catch {
        ElMessage.error('加载批次详情失败');
    }
}

async function completeBatch(row) {
    try {
        await ElMessageBox.confirm('确认完成该批次打款？', '提示');
        await withdrawalApi.completeBatch(row.id, {});
        ElMessage.success('批次已完成');
        await loadBatches();
        await loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败');
    }
}

onMounted(refreshAll);
</script>

<style scoped>
.withdrawal-page { padding: 0 4px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.text-muted { color: #909399; font-size: 13px; margin: 0; }
.small { font-size: 12px; }
.tab-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 600; }
.stat-value.warning { color: #e6a23c; }
.stat-value.primary { color: #409eff; }
.stat-value.success { color: #67c23a; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
</style>
