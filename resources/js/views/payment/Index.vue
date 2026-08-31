<template>
    <div class="payment-dashboard">
        <div class="page-header">
            <h2>{{ t('payment_page.title') }}</h2>
            <div class="header-actions">
                <el-tag v-if="currentGateway" :type="currentGateway === 'mock' ? 'warning' : 'success'">
                    {{ t('payment_page.current_gateway', { gateway: currentGateway }) }}
                </el-tag>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ── Tab 1: 概览 ── -->
            <el-tab-pane :label="t('payment_page.tab_overview')" name="overview">
                <!-- Stats Cards -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value">¥{{ stats.total_revenue || 0 }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_total_revenue') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #67c23a">¥{{ stats.recent_revenue_30d || 0 }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_recent_30d') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #e6a23c">{{ stats.pending_count }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_pending_count') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #f56c6c">{{ stats.failed_count }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_failed_count') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- Channel Stats -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <span>{{ t('payment_page.channel_stats') }}</span>
                    </template>
                    <el-row :gutter="16">
                        <el-col v-for="(count, channel) in stats.channel_stats || {}" :key="channel" :span="6">
                            <div class="channel-stat">
                                <div class="channel-icon" :class="'channel-' + channel">
                                    <el-icon v-if="channel === 'alipay'"><Money /></el-icon>
                                    <el-icon v-else-if="channel === 'wechat'"><ChatDotSquare /></el-icon>
                                    <el-icon v-else-if="channel === 'stripe'"><CreditCard /></el-icon>
                                    <el-icon v-else-if="channel === 'paypal'"><Coin /></el-icon>
                                    <el-icon v-else-if="channel === 'yipay'"><Money /></el-icon>
                                    <el-icon v-else><Coin /></el-icon>
                                </div>
                                <div class="channel-info">
                                    <div class="channel-name">{{ channelLabels[channel] || channel }}</div>
                                    <div class="channel-count">{{ t('payment_page.channel_count', { count }) }}</div>
                                </div>
                            </div>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- Gateway Config -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <span>{{ t('payment_page.gateway_config') }}</span>
                    </template>
                    <el-table :data="gateways" stripe>
                        <el-table-column prop="name" :label="t('payment_page.col_channel')" width="120" />
                        <el-table-column :label="t('billing_page.col_status')" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.enabled" type="success" size="small">{{ t('payment_page.gw_enabled') }}</el-tag>
                                <el-tag v-else type="info" size="small">{{ t('payment_page.gw_disabled') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('payment_page.col_config')" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.configured" type="success" size="small" effect="plain">{{ t('payment_page.gw_configured') }}</el-tag>
                                <el-tag v-else type="danger" size="small" effect="plain">{{ t('payment_page.gw_not_configured') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('payment_page.col_notes')">
                            <template #default="{ row }">
                                <span v-if="!row.configured" class="text-muted">{{ t('payment_page.gw_env_hint') }}</span>
                                <span v-else class="text-success">{{ t('payment_page.gw_config_ok') }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- Recent Transactions -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('payment_page.recent_transactions') }}</span>
                            <el-button text type="primary" @click="$router.push('/admin/payment/transactions')">
                                {{ t('admin_dash.view_all') }}
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="recentTransactions" stripe v-loading="loading">
                        <el-table-column prop="invoice_no" :label="t('payment_page.col_invoice_no')" width="160" />
                        <el-table-column :label="t('billing_page.col_amount')" width="120">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column prop="payment_method" :label="t('payment_page.col_channel')" width="100">
                            <template #default="{ row }">{{ channelLabels[row.payment_method] || row.payment_method || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="status" :label="t('billing_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'paid'" type="success" size="small">{{ overviewStatusLabels.paid }}</el-tag>
                                <el-tag v-else-if="row.status === 'pending'" type="warning" size="small">{{ overviewStatusLabels.pending }}</el-tag>
                                <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">{{ overviewStatusLabels.failed }}</el-tag>
                                <el-tag v-else-if="row.status === 'refunded'" type="info" size="small">{{ overviewStatusLabels.refunded }}</el-tag>
                                <el-tag v-else size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="t('payment_page.col_time')" width="180" />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- ── Tab 2: 支付记录 (M1.1-27) ── -->
            <el-tab-pane :label="t('payment_page.tab_records')" name="records">
                <!-- 统计卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value">¥{{ recordStats.total_revenue || 0 }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_total_paid') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #67c23a">¥{{ recordStats.today_revenue || 0 }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_today_revenue') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #e6a23c">{{ recordStats.pending_count }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_pending_count') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #f56c6c">{{ recordStats.refunded_count }}</div>
                                <div class="stat-label">{{ t('payment_page.stat_refunded_count') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 筛选栏 -->
                <el-card shadow="never" class="mb-4">
                    <el-form :inline="true" :model="filters" size="small">
                        <el-form-item :label="t('payment_page.filter_status')">
                            <el-select v-model="filters.status" clearable :placeholder="t('licenses_page.all')" style="width:130px">
                                <el-option
                                    v-for="opt in recordFilterStatusOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('payment_page.filter_channel')">
                            <el-select v-model="filters.channel" clearable :placeholder="t('licenses_page.all')" style="width:130px">
                                <el-option
                                    v-for="opt in recordFilterChannelOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('payment_page.filter_search')">
                            <el-input v-model="filters.search" :placeholder="t('payment_page.search_ph')" clearable style="width:200px" />
                        </el-form-item>
                        <el-form-item :label="t('payment_page.filter_date')">
                            <el-date-picker
                                v-model="dateRange"
                                type="daterange"
                                :range-separator="t('licenses_page.date_range_sep')"
                                :start-placeholder="t('licenses_page.date_start')"
                                :end-placeholder="t('licenses_page.date_end')"
                                value-format="YYYY-MM-DD"
                                style="width:240px"
                            />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="loadRecords(1)">{{ t('actions.search') }}</el-button>
                            <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 记录表格 -->
                <el-card shadow="never">
                    <el-table :data="records" stripe v-loading="recordsLoading" @row-click="showDetail">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="transaction_id" :label="t('payment_page.col_tx_id')" width="180" show-overflow-tooltip />
                        <el-table-column :label="t('payment_page.col_channel')" width="90">
                            <template #default="{ row }">{{ channelLabels[row.channel] || row.channel }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_amount')" width="120">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column :label="t('payment_page.col_fee')" width="100">
                            <template #default="{ row }">¥{{ row.fee }}</template>
                        </el-table-column>
                        <el-table-column :label="t('payment_page.col_net')" width="100">
                            <template #default="{ row }">¥{{ row.net_amount }}</template>
                        </el-table-column>
                        <el-table-column prop="status" :label="t('billing_page.col_status')" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'completed'" type="success" size="small">{{ recordStatusLabels.completed }}</el-tag>
                                <el-tag v-else-if="row.status === 'pending'" type="warning" size="small">{{ recordStatusLabels.pending }}</el-tag>
                                <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">{{ recordStatusLabels.failed }}</el-tag>
                                <el-tag v-else-if="row.status === 'refunded'" type="info" size="small">{{ recordStatusLabels.refunded }}</el-tag>
                                <el-tag v-else-if="row.status === 'partially_refunded'" type="warning" size="small">{{ recordStatusLabels.partially_refunded }}</el-tag>
                                <el-tag v-else size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('payment_page.col_refunded_amt')" width="100">
                            <template #default="{ row }">¥{{ row.refunded_amount }}</template>
                        </el-table-column>
                        <el-table-column prop="paid_at" :label="t('billing_page.col_paid_at')" width="170" />
                        <el-table-column :label="t('billing_page.col_actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text type="primary" size="small" @click.stop="showDetail(row)">{{ t('billing_page.detail') }}</el-button>
                                <el-button v-if="row.status === 'completed' || row.status === 'partially_refunded'" text type="danger" size="small" @click.stop="handleRefund(row)">{{ t('payment_page.btn_refund') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="page"
                            :page-size="perPage"
                            :total="total"
                            layout="total, prev, pager, next"
                            @current-change="loadRecords" />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 详情抽屉 -->
        <el-drawer v-model="detailVisible" :title="t('payment_page.detail_title')" size="500px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="ID" :span="2">{{ detail.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_tx_id')" :span="2">{{ detail.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.col_channel')">{{ channelLabels[detail.channel] || detail.channel }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_amount')">¥{{ detail.amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_fee')">¥{{ detail.fee }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_net')">¥{{ detail.net_amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_refunded')">¥{{ detail.refunded_amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_status')">
                        <el-tag v-if="detail.status === 'completed'" type="success" size="small">{{ recordStatusLabels.completed }}</el-tag>
                        <el-tag v-else-if="detail.status === 'pending'" type="warning" size="small">{{ recordStatusLabels.pending }}</el-tag>
                        <el-tag v-else-if="detail.status === 'failed'" type="danger" size="small">{{ recordStatusLabels.failed }}</el-tag>
                        <el-tag v-else-if="detail.status === 'refunded'" type="info" size="small">{{ recordStatusLabels.refunded }}</el-tag>
                        <el-tag v-else-if="detail.status === 'partially_refunded'" type="warning" size="small">{{ recordStatusLabels.partially_refunded }}</el-tag>
                        <el-tag v-else size="small">{{ detail.status }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_paid_at')">{{ detail.paid_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_refunded_at')">{{ detail.refunded_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_description')" :span="2">{{ detail.description || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_tenant_id')">{{ detail.tenant_id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('payment_page.label_order_id')">{{ detail.order_id || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-drawer>

        <!-- 退款弹窗 -->
        <el-dialog v-model="refundVisible" :title="t('payment_page.refund_title')" width="400px">
            <el-form :model="refundForm" label-width="80px">
                <el-form-item :label="t('payment_page.label_refundable')">
                    <span class="text-success">¥{{ refundMaxAmount }}</span>
                </el-form-item>
                <el-form-item :label="t('payment_page.label_refund_amount')">
                    <el-input-number v-model="refundForm.amount" :min="0.01" :max="refundMaxAmount" :precision="2" style="width:200px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="refundVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="refunding" @click="confirmRefund">{{ t('payment_page.confirm_refund') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getPaymentStats, getTransactions, getGatewayConfig, getPaymentsDashboard, getPaymentRecords, getPaymentRecordDetail, refundPayment, getPaymentTrend } from '@/api/payment';

const { t } = useI18n();

const loading = ref(false);
const stats = ref({});
const gateways = ref([]);
const recentTransactions = ref([]);
const currentGateway = ref('');
const activeTab = ref('overview');

const channelLabels = computed(() => ({
    alipay: t('payment_result.gw_alipay'),
    wechat: t('payment_result.gw_wechat'),
    stripe: 'Stripe',
    paypal: t('payment_page.channel_paypal'),
    yipay: t('payment_page.channel_yipay'),
    mock: t('payment_page.channel_mock'),
}));

const overviewStatusLabels = computed(() => ({
    paid: t('billing_page.inv_paid'),
    pending: t('billing_page.inv_pending'),
    failed: t('payment_page.status_failed_short'),
    refunded: t('billing_page.inv_refunded'),
}));

const recordStatusLabels = computed(() => ({
    completed: t('refunds_page.st_completed'),
    pending: t('billing_page.inv_pending'),
    failed: t('refunds_page.st_failed'),
    refunded: t('billing_page.inv_refunded'),
    partially_refunded: t('refunds_page.type_partial_refund'),
}));

const recordFilterStatusOptions = computed(() => [
    { label: t('billing_page.inv_pending'), value: 'pending' },
    { label: t('refunds_page.st_completed'), value: 'completed' },
    { label: t('refunds_page.st_failed'), value: 'failed' },
    { label: t('billing_page.inv_refunded'), value: 'refunded' },
    { label: t('refunds_page.type_partial_refund'), value: 'partially_refunded' },
]);

const recordFilterChannelOptions = computed(() => [
    { label: t('payment_result.gw_alipay'), value: 'alipay' },
    { label: t('payment_result.gw_wechat'), value: 'wechat' },
    { label: 'Stripe', value: 'stripe' },
    { label: t('payment_page.channel_paypal'), value: 'paypal' },
    { label: t('payment_page.channel_yipay'), value: 'yipay' },
    { label: t('payment_page.channel_mock'), value: 'mock' },
]);

// ── 概览 ──
const loadStats = async () => {
    try {
        const res = await getPaymentStats();
        if (res.data.success) {
            stats.value = res.data.data;
            currentGateway.value = res.data.data.gateway;
        }
    } catch (e) { /* ignore */ }
};

const loadGateways = async () => {
    try {
        const res = await getGatewayConfig();
        if (res.data.success) {
            gateways.value = Object.entries(res.data.data).map(([key, val]) => ({
                key,
                ...val,
            }));
        }
    } catch (e) { /* ignore */ }
};

const loadRecentTransactions = async () => {
    loading.value = true;
    try {
        const res = await getTransactions({ per_page: 10 });
        if (res.data.success) {
            recentTransactions.value = res.data.data.data || [];
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

// ── 支付记录 (M1.1-27) ──
const recordStats = ref({});
const records = ref([]);
const recordsLoading = ref(false);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const detailVisible = ref(false);
const detail = ref(null);
const refundVisible = ref(false);
const refunding = ref(false);
const refundTarget = ref(null);
const refundForm = reactive({ amount: 0 });
const refundMaxAmount = ref(0);

const filters = reactive({
    status: '',
    channel: '',
    search: '',
});
const dateRange = ref([]);

const loadRecordDashboard = async () => {
    try {
        const res = await getPaymentsDashboard();
        if (res.data.success) {
            recordStats.value = res.data.data;
        }
    } catch (e) { /* ignore */ }
};

const loadRecords = async (p = 1) => {
    page.value = p;
    recordsLoading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            ...filters,
        };
        if (dateRange.value && dateRange.value.length === 2) {
            params.date_from = dateRange.value[0];
            params.date_to = dateRange.value[1];
        }
        // 清理空值
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await getPaymentRecords(params);
        if (res.data.success) {
            records.value = res.data.data.items || [];
            total.value = res.data.data.total || 0;
        }
    } catch (e) { /* ignore */ }
    finally { recordsLoading.value = false; }
};

const resetFilters = () => {
    filters.status = '';
    filters.channel = '';
    filters.search = '';
    dateRange.value = [];
    loadRecords(1);
};

const showDetail = async (row) => {
    try {
        const res = await getPaymentRecordDetail(row.id);
        if (res.data.success) {
            detail.value = res.data.data;
            detailVisible.value = true;
        }
    } catch (e) { /* ignore */ }
};

const handleRefund = (row) => {
    refundTarget.value = row;
    refundMaxAmount.value = row.amount - row.refunded_amount;
    refundForm.amount = refundMaxAmount.value;
    refundVisible.value = true;
};

const confirmRefund = async () => {
    if (!refundTarget.value) return;
    refunding.value = true;
    try {
        const res = await refundPayment(refundTarget.value.id, { amount: refundForm.amount });
        if (res.data.success) {
            ElMessage.success(t('payment_page.msg_refund_ok'));
            refundVisible.value = false;
            loadRecords(page.value);
            loadRecordDashboard();
        } else {
            ElMessage.error(res.data.message || t('payment_page.msg_refund_fail'));
        }
    } catch (e) {
        ElMessage.error(t('payment_page.msg_refund_request_fail'));
    }
    finally { refunding.value = false; }
};

// 切换 Tab 时加载
watch(activeTab, (tab) => {
    if (tab === 'records' && records.value.length === 0) {
        loadRecordDashboard();
        loadRecords();
    }
});

onMounted(() => {
    loadStats();
    loadGateways();
    loadRecentTransactions();
});
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.channel-stat { display: flex; align-items: center; gap: 12px; padding: 12px; }
.channel-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; }
.channel-alipay { background: #1677ff; }
.channel-wechat { background: #07c160; }
.channel-stripe { background: #635bff; }
.channel-yipay { background: #e74c3c; }
.channel-mock { background: #909399; }
.channel-name { font-weight: 600; font-size: 14px; }
.channel-count { font-size: 12px; color: #909399; margin-top: 2px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; }
.text-success { color: #67c23a; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
</style>
