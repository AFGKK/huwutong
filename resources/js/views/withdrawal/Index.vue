<template>
    <div class="withdrawal-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t(`${P}.refresh`) }}</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6" :lg="4">
                <el-card shadow="hover"><div class="stat-label">{{ t(`${P}.stats.pending_review`) }}</div><div class="stat-value warning">{{ stats.pending_review_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">{{ t(`${P}.stats.pending_amount`) }}</div><div class="stat-value">¥{{ formatMoney(stats.pending_amount) }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">{{ t(`${P}.stats.processing`) }}</div><div class="stat-value primary">{{ stats.processing_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">{{ t(`${P}.stats.today_completed`) }}</div><div class="stat-value success">¥{{ formatMoney(stats.today_completed_amount) }}</div></el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6" :lg="5">
                <el-card shadow="hover"><div class="stat-label">{{ t(`${P}.stats.month_completed`) }}</div><div class="stat-value">¥{{ formatMoney(stats.month_completed_amount) }}</div></el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover">
            <el-tabs v-model="activeTab" @tab-change="onTabChange">
                <el-tab-pane :label="t(`${P}.tabs.records`)" name="records">
                    <div class="tab-toolbar">
                        <el-input v-model="filters.search" :placeholder="t(`${P}.filters.search_user_ph`)" clearable style="width:200px" @keyup.enter="loadRecords" />
                        <el-select v-model="filters.status" :placeholder="t(`${P}.filters.status_ph`)" clearable style="width:130px" @change="loadRecords">
                            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                        <el-select v-model="filters.channel" :placeholder="t(`${P}.filters.channel_ph`)" clearable style="width:120px" @change="loadRecords">
                            <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                        <el-date-picker v-model="filters.dateRange" type="daterange" :range-separator="t(`${P}.filters.date_range_sep`)" :start-placeholder="t(`${P}.filters.date_start_ph`)" :end-placeholder="t(`${P}.filters.date_end_ph`)" value-format="YYYY-MM-DD" style="width:260px" @change="loadRecords" />
                        <el-button @click="loadRecords">{{ t(`${P}.filters.query`) }}</el-button>
                        <el-button type="warning" :loading="retryLoading" @click="handleBatchRetry">{{ t(`${P}.filters.batch_retry`) }}</el-button>
                        <el-button type="success" :loading="releasing" @click="handleReleasePending">{{ t(`${P}.filters.release_pending`) }}</el-button>
                    </div>
                    <el-table :data="records" stripe v-loading="recordsLoading">
                        <el-table-column :label="t(`${P}.cols.id`)" prop="id" width="70" />
                        <el-table-column :label="t(`${P}.cols.user`)" min-width="180">
                            <template #default="{ row }">
                                <div>{{ row.earnings_account?.user?.name || '-' }}</div>
                                <div class="text-muted small">{{ row.earnings_account?.user?.email || '' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.amount`)" width="100"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.fee`)" width="90"><template #default="{ row }">¥{{ formatMoney(row.fee) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.net_amount`)" width="100"><template #default="{ row }">¥{{ formatMoney(row.net_amount) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.channel`)" width="90"><template #default="{ row }"><el-tag size="small">{{ channelLabel(row.channel) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="100"><template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.batch_no`)" prop="batch_no" width="130" />
                        <el-table-column :label="t(`${P}.cols.created_at`)" width="160"><template #default="{ row }">{{ formatDate(row.created_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openDetail(row)">{{ t(`${P}.row_actions.detail`) }}</el-button>
                                <el-button v-if="row.status === 'pending_review'" size="small" link type="primary" @click="openReview(row)">{{ t(`${P}.row_actions.review`) }}</el-button>
                                <el-button v-if="['pending','processing'].includes(row.status)" size="small" link type="success" @click="openComplete(row)">{{ t(`${P}.row_actions.complete`) }}</el-button>
                                <el-button v-if="['pending','processing'].includes(row.status)" size="small" link type="danger" @click="openFailed(row)">{{ t(`${P}.row_actions.failed`) }}</el-button>
                                <el-button v-if="row.status === 'failed' || row.status === 'rejected'" size="small" link type="warning" @click="handleRetry(row)">{{ t('actions.retry') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="recordsTotal" :page-size="20" v-model:current-page="recordsPage" @current-change="loadRecords" />
                </el-tab-pane>

                <el-tab-pane :label="t(`${P}.tabs.batches`)" name="batches">
                    <div class="tab-toolbar">
                        <el-select v-model="batchFilters.channel" :placeholder="t(`${P}.filters.channel_ph`)" clearable style="width:120px" @change="loadBatches">
                            <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                        <el-button type="primary" @click="openCreateBatch">{{ t(`${P}.row_actions.create_batch`) }}</el-button>
                    </div>
                    <el-table :data="batches" stripe v-loading="batchesLoading">
                        <el-table-column :label="t(`${P}.cols.batch_no`)" prop="batch_no" width="140" />
                        <el-table-column :label="t(`${P}.cols.channel`)" width="90"><template #default="{ row }">{{ channelLabel(row.channel) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.title`)" prop="title" min-width="140" />
                        <el-table-column :label="t(`${P}.cols.status`)" width="100"><template #default="{ row }"><el-tag size="small">{{ row.status }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.total_count`)" prop="total_count" width="70" />
                        <el-table-column :label="t(`${P}.cols.total_amount`)" width="110"><template #default="{ row }">¥{{ formatMoney(row.total_amount) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.creator`)" width="100"><template #default="{ row }">{{ row.creator?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.created_at`)" width="160"><template #default="{ row }">{{ formatDate(row.created_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openBatchDetail(row)">{{ t(`${P}.row_actions.detail`) }}</el-button>
                                <el-button v-if="row.status === 'pending'" size="small" link type="success" @click="completeBatch(row)">{{ t(`${P}.row_actions.complete_batch`) }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="batchesTotal" :page-size="20" v-model:current-page="batchesPage" @current-change="loadBatches" />
                </el-tab-pane>

                <el-tab-pane :label="t(`${P}.tabs.channels`)" name="channels">
                    <el-table :data="channels" stripe v-loading="channelsLoading">
                        <el-table-column :label="t(`${P}.cols.channel`)" width="120"><template #default="{ row }">{{ row.name }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.min_amount`)" width="110"><template #default="{ row }">¥{{ formatMoney(row.min_amount) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.max_amount`)" width="110"><template #default="{ row }">¥{{ formatMoney(row.max_amount) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.fee_rate`)" width="100"><template #default="{ row }">{{ (row.fee_rate * 100).toFixed(2) }}%</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.daily_limit`)" width="120"><template #default="{ row }">¥{{ formatMoney(row.daily_limit) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.pending`)" width="90"><template #default="{ row }">{{ stats.channel_stats?.[row.id]?.pending_count || 0 }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.month_payout`)" width="120"><template #default="{ row }">¥{{ formatMoney(stats.channel_stats?.[row.id]?.month_amount) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="reviewVisible" :title="t(`${P}.dialogs.review_title`)" width="420px">
            <el-form label-width="80px">
                <el-form-item :label="t(`${P}.cols.operation`)">
                    <el-radio-group v-model="reviewForm.action">
                        <el-radio value="approve">{{ t('actions.approve') }}</el-radio>
                        <el-radio value="reject">{{ t('actions.reject') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t(`${P}.cols.remark`)"><el-input v-model="reviewForm.remark" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="reviewVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitReview">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="completeVisible" :title="t(`${P}.dialogs.complete_title`)" width="420px">
            <el-form label-width="90px">
                <el-form-item :label="t(`${P}.cols.transaction_id`)"><el-input v-model="completeForm.transaction_id" /></el-form-item>
                <el-form-item :label="t(`${P}.cols.proof`)"><el-upload :auto-upload="false" :limit="1" :on-change="onProofChange"><el-button>{{ t(`${P}.form.select_image`) }}</el-button></el-upload></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="completeVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitComplete">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="failedVisible" :title="t(`${P}.dialogs.failed_title`)" width="420px">
            <el-form label-width="80px">
                <el-form-item :label="t(`${P}.cols.reason`)"><el-input v-model="failedForm.failure_reason" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="failedVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="submitting" @click="submitFailed">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="batchCreateVisible" :title="t(`${P}.dialogs.create_batch_title`)" width="420px">
            <el-form label-width="80px">
                <el-form-item :label="t(`${P}.cols.channel`)">
                    <el-select v-model="batchForm.channel" style="width:100%">
                        <el-option v-for="c in channelOptions" :key="c.value" :label="c.label" :value="c.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.cols.title`)"><el-input v-model="batchForm.title" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="batchCreateVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitCreateBatch">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" :title="t(`${P}.dialogs.detail_title`)" size="480px">
            <template v-if="currentRecord">
                <el-descriptions :column="1" border>
                    <el-descriptions-item :label="t(`${P}.cols.id`)">{{ currentRecord.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.user`)">{{ currentRecord.earnings_account?.user?.name }} ({{ currentRecord.earnings_account?.user?.email }})</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.amount`)">¥{{ formatMoney(currentRecord.amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.fee`)">¥{{ formatMoney(currentRecord.fee) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.net_amount`)">¥{{ formatMoney(currentRecord.net_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.channel`)">{{ channelLabel(currentRecord.channel) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.status`)">{{ statusLabel(currentRecord.status) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.batch_no`)">{{ currentRecord.batch_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.transaction_id`)">{{ currentRecord.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.remark`)">{{ currentRecord.remark || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.failure_reason`)">{{ currentRecord.failure_reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.created_at`)">{{ formatDate(currentRecord.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.completed_at`)">{{ formatDate(currentRecord.completed_at) }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-drawer>

        <el-drawer v-model="batchDetailVisible" :title="t(`${P}.dialogs.batch_detail_title`)" size="640px">
            <template v-if="currentBatch">
                <el-descriptions :column="2" border class="mb-3">
                    <el-descriptions-item :label="t(`${P}.cols.batch_no`)">{{ currentBatch.batch_no }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.channel`)">{{ channelLabel(currentBatch.channel) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.status`)">{{ currentBatch.status }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.total_amount`)">¥{{ formatMoney(currentBatch.total_amount) }}</el-descriptions-item>
                </el-descriptions>
                <el-table :data="currentBatch.withdrawals || []" stripe size="small">
                    <el-table-column :label="t(`${P}.cols.id`)" prop="id" width="60" />
                    <el-table-column :label="t(`${P}.cols.user`)" min-width="120"><template #default="{ row }">{{ row.earnings_account?.user?.name || '-' }}</template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.amount`)" width="90"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.status`)" width="90"><template #default="{ row }">{{ statusLabel(row.status) }}</template></el-table-column>
                </el-table>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import withdrawalApi from '@/api/withdrawal';

const P = 'withdrawal_page';
const { t, locale } = useI18n();

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

const CHANNEL_KEYS = ['bank', 'alipay', 'wechat', 'paypal'];
const STATUS_KEYS = ['pending_review', 'pending', 'processing', 'completed', 'failed', 'rejected', 'cancelled'];

const channelOptions = computed(() =>
    CHANNEL_KEYS.map((value) => ({ value, label: t(`${P}.channel.${value}`) })),
);

const statusOptions = computed(() =>
    STATUS_KEYS.map((value) => ({ value, label: t(`${P}.status.${value}`) })),
);

const channelLabels = computed(() =>
    Object.fromEntries(CHANNEL_KEYS.map((key) => [key, t(`${P}.channel.${key}`)])),
);

const statusLabels = computed(() =>
    Object.fromEntries(STATUS_KEYS.map((key) => [key, t(`${P}.status.${key}`)])),
);

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

function formatMoney(v) {
    return Number(v || 0).toLocaleString(dateLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatDate(v) {
    return v ? new Date(v).toLocaleString(dateLocale.value) : '-';
}
function channelLabel(ch) {
    return channelLabels.value[ch] || ch;
}
function statusLabel(s) {
    return statusLabels.value[s] || s;
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
        ElMessage.success(t(`${P}.messages.review_done`));
        reviewVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
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
        ElMessage.success(t(`${P}.messages.marked_complete`));
        completeVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        submitting.value = false;
    }
}

async function submitFailed() {
    if (!failedForm.failure_reason) return ElMessage.warning(t(`${P}.messages.failure_reason_required`));
    submitting.value = true;
    try {
        await withdrawalApi.markFailed(currentRecord.value.id, { failure_reason: failedForm.failure_reason });
        ElMessage.success(t(`${P}.messages.marked_failed`));
        failedVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        submitting.value = false;
    }
}

// ─── M3-72 增强 ───

async function handleRetry(row) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.retry`, { id: row.id }), t('actions.confirm'));
        await withdrawalApi.retry(row.id);
        ElMessage.success(t(`${P}.messages.reset_done`));
        await loadRecords();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t(`${P}.messages.retry_failed`));
    }
}

async function handleBatchRetry() {
    const failedRecords = records.value.filter(r => r.status === 'failed' || r.status === 'rejected');
    if (!failedRecords.length) return ElMessage.warning(t(`${P}.messages.no_retryable`));
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.batch_retry`, { count: failedRecords.length }), t('actions.confirm'));
        const ids = failedRecords.map(r => r.id);
        const res = await withdrawalApi.batchRetry(ids);
        ElMessage.success(res.data?.message || t(`${P}.messages.reset_done`));
        await loadRecords();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t(`${P}.messages.batch_retry_failed`));
    }
}

async function handleReleasePending() {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.release_pending`), t('actions.confirm'));
        releasing.value = true;
        const res = await withdrawalApi.releasePending();
        ElMessage.success(res.data?.message || t('messages.success'));
        await loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t(`${P}.messages.release_failed`));
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
        ElMessage.success(t(`${P}.messages.batch_created`));
        batchCreateVisible.value = false;
        activeTab.value = 'batches';
        await loadBatches();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.create_failed`));
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
        ElMessage.error(t(`${P}.messages.batch_detail_load_failed`));
    }
}

async function completeBatch(row) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.complete_batch`), t('actions.confirm'));
        await withdrawalApi.completeBatch(row.id, {});
        ElMessage.success(t(`${P}.messages.batch_completed`));
        await loadBatches();
        await loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('messages.failed'));
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
.stat-value.primary { color: #0f172a; }
.stat-value.success { color: #67c23a; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
</style>
