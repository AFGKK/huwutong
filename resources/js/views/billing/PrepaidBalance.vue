<template>
    <div class="prepaid-balance-page">
        <div class="page-header">
            <h2>{{ t('prepaid_balance_page.title') }}</h2>
            <p class="text-muted">{{ t('prepaid_balance_page.subtitle') }}</p>
        </div>

        <!-- 统计概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_balance) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.total_balance') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_recharged) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.total_recharged') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_consumed) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.total_consumed') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.active_accounts }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.active_accounts') }}</div>
                    <div class="stat-sub">{{ t('prepaid_balance_page.stats.penetration_rate', { rate: stats.penetration_rate }) }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.recent_30d_recharges) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.recent_30d_recharges') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.auto_recharge_users }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.auto_recharge_users') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 信用额度概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ formatMoney(stats.credit?.total_limit) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.total_credit_limit') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ formatMoney(stats.credit?.total_used) }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.credit_used') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ stats.credit?.utilization_rate }}%</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.credit_utilization') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ stats.credit?.total_accounts }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.credit_accounts') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card warning">
                    <div class="stat-value">{{ stats.low_balance_accounts }}</div>
                    <div class="stat-label">{{ t('prepaid_balance_page.stats.low_balance_accounts') }}</div>
                    <div class="stat-sub">{{ t('prepaid_balance_page.stats.low_balance_threshold') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 工具条 -->
        <el-card class="toolbar-card">
            <el-form :inline="true" :model="searchForm" size="small">
                <el-form-item :label="t('prepaid_balance_page.filters.transaction_type')">
                    <el-select v-model="searchForm.type" clearable :placeholder="t('prepaid_balance_page.placeholders.all_types')" style="width: 140px">
                        <el-option
                            v-for="opt in transactionTypeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.col_status')">
                    <el-select v-model="searchForm.status" clearable :placeholder="t('prepaid_balance_page.placeholders.all_status')" style="width: 120px">
                        <el-option
                            v-for="opt in transactionStatusOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('prepaid_balance_page.filters.customer_id')">
                    <el-input v-model="searchForm.customer_id" :placeholder="t('prepaid_balance_page.placeholders.customer_id')" style="width: 120px" clearable />
                </el-form-item>
                <el-form-item :label="t('prepaid_balance_page.filters.date_from')">
                    <el-date-picker v-model="searchForm.date_from" type="date" :placeholder="t('prepaid_balance_page.placeholders.date_from')" style="width: 140px" />
                </el-form-item>
                <el-form-item :label="t('prepaid_balance_page.filters.date_to')">
                    <el-date-picker v-model="searchForm.date_to" type="date" :placeholder="t('prepaid_balance_page.placeholders.date_to')" style="width: 140px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadTransactions" :icon="Search">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetSearch" :icon="Refresh">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 交易列表 -->
        <el-card class="table-card">
            <template #header>
                <div class="card-header">
                    <span><el-icon><List /></el-icon> {{ t('prepaid_balance_page.table.title') }}</span>
                    <div>
                        <el-button size="small" @click="loadTransactions" :icon="Refresh">{{ t('prepaid_balance_page.refresh') }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="transactions" v-loading="loading" stripe :empty-text="t('prepaid_balance_page.table.empty')" style="width: 100%">
                <el-table-column prop="id" :label="t('prepaid_balance_page.table.col_id')" width="70" />
                <el-table-column prop="customer_id" :label="t('billing_page.col_customer')" width="80">
                    <template #default="{ row }">
                        <router-link :to="`/customers/${row.customer_id}`" class="link">#{{ row.customer_id }}</router-link>
                    </template>
                </el-table-column>
                <el-table-column prop="type" :label="t('billing_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" :label="t('billing_page.col_amount')" width="130">
                    <template #default="{ row }">
                        <span :class="row.amount >= 0 ? 'text-success' : 'text-danger'">
                            {{ row.amount >= 0 ? '+' : '' }}{{ formatMoney(row.amount) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="balance_before" :label="t('prepaid_balance_page.table.col_balance_before')" width="100">
                    <template #default="{ row }">{{ formatMoney(row.balance_before) }}</template>
                </el-table-column>
                <el-table-column prop="balance_after" :label="t('prepaid_balance_page.table.col_balance_after')" width="100">
                    <template #default="{ row }">{{ formatMoney(row.balance_after) }}</template>
                </el-table-column>
                <el-table-column prop="status" :label="t('billing_page.col_status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'pending' ? 'warning' : 'danger'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="payment_method" :label="t('prepaid_balance_page.table.col_payment_method')" width="100">
                    <template #default="{ row }">
                        <span>{{ methodLabel(row.payment_method) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="description" :label="t('billing_page.form_description')" min-width="180" show-overflow-tooltip />
                <el-table-column prop="created_at" :label="t('prepaid_balance_page.table.col_time')" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="showTransactionDetail(row)">{{ t('billing_page.detail') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="totalPages > 1">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="totalRecords"
                    layout="prev, pager, next, total"
                    @current-change="loadTransactions"
                />
            </div>
        </el-card>

        <!-- 交易详情弹窗 -->
        <el-dialog v-model="detailVisible" :title="t('prepaid_balance_page.dialog.transaction_detail')" width="600px">
            <el-descriptions v-if="selectedTransaction" :column="2" border>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.transaction_id')">{{ selectedTransaction.id }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_type')">{{ typeLabel(selectedTransaction.type) }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_amount')" :span="2">
                    <span :class="selectedTransaction.amount >= 0 ? 'text-success' : 'text-danger'">
                        {{ selectedTransaction.amount >= 0 ? '+' : '' }}{{ formatMoney(selectedTransaction.amount) }}
                    </span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.balance_before')">{{ formatMoney(selectedTransaction.balance_before) }}</el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.balance_after')">{{ formatMoney(selectedTransaction.balance_after) }}</el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.table.col_payment_method')">{{ methodLabel(selectedTransaction.payment_method) }}</el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.gateway_transaction_id')">{{ selectedTransaction.gateway_transaction_id || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.currency')">{{ selectedTransaction.currency }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_status')">
                    <el-tag :type="selectedTransaction.status === 'completed' ? 'success' : 'warning'" size="small">
                        {{ statusLabel(selectedTransaction.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.form_description')" :span="2">{{ selectedTransaction.description }}</el-descriptions-item>
                <el-descriptions-item :label="t('prepaid_balance_page.detail.completed_at')">{{ selectedTransaction.completed_at || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_created')">{{ selectedTransaction.created_at }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 手动充值/扣款/调账弹窗 -->
        <el-dialog v-model="actionDialog.visible" :title="actionDialog.title" width="500px">
            <el-form :model="actionForm" :rules="actionRules" ref="actionFormRef" label-width="120px">
                <el-form-item :label="t('billing_page.form_customer')" v-if="!actionForm.customer_id">
                    <el-select v-model="actionForm.customer_id" filterable :placeholder="t('prepaid_balance_page.placeholders.select_customer')" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="`#${c.id} - ${c.user?.name || t('prepaid_balance_page.unknown_customer')}`"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.col_amount')" prop="amount">
                    <el-input-number
                        v-model="actionForm.amount"
                        :min="actionDialog.type === 'deduct' ? 0.01 : undefined"
                        :max="999999.99"
                        :step="10"
                        :precision="2"
                        style="width: 200px"
                    />
                    <span class="ml-2">CNY</span>
                    <div v-if="actionDialog.type === 'adjust'" class="text-muted small">{{ t('prepaid_balance_page.form.adjust_amount_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('prepaid_balance_page.form.description')">
                    <el-input v-model="actionForm.description" type="textarea" :rows="2" maxlength="200" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="actionDialog.visible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="executeAction" :loading="actionSubmitting">{{ t('prepaid_balance_page.actions.confirm_execute') }}</el-button>
            </template>
        </el-dialog>

        <!-- 信用额度设置弹窗 -->
        <el-dialog v-model="creditDialog.visible" :title="t('prepaid_balance_page.dialog.set_credit')" width="500px">
            <el-form :model="creditForm" :rules="creditRules" ref="creditFormRef" label-width="140px">
                <el-form-item :label="t('billing_page.form_customer')" v-if="!creditForm.customer_id">
                    <el-select v-model="creditForm.customer_id" filterable :placeholder="t('prepaid_balance_page.placeholders.select_customer')" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="`#${c.id} - ${c.user?.name || t('prepaid_balance_page.unknown_customer')}`"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('prepaid_balance_page.form.credit_limit')" prop="credit_limit">
                    <el-input-number v-model="creditForm.credit_limit" :min="0" :max="9999999.99" :precision="2" style="width: 200px" />
                    <span class="ml-2">CNY</span>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_grace_days')">
                    <el-input-number v-model="creditForm.grace_days" :min="0" :max="365" style="width: 200px" />
                    <div class="text-muted small">{{ t('prepaid_balance_page.form.grace_days_hint') }}</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="creditDialog.visible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="executeSetCreditLimit" :loading="creditSubmitting">{{ t('prepaid_balance_page.actions.confirm_set') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Search, Refresh, List } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import prepaidBalanceApi from '../../api/prepaidBalance';
import customerApi from '@/api/customer';

const { t, locale } = useI18n();

// ─── 状态 ───
const loading = ref(false);
const transactions = ref([]);
const currentPage = ref(1);
const perPage = ref(20);
const totalRecords = ref(0);
const totalPages = computed(() => Math.ceil(totalRecords.value / perPage.value));

const stats = reactive({
    total_balance: 0,
    total_recharged: 0,
    total_consumed: 0,
    active_accounts: 0,
    total_customers: 0,
    penetration_rate: 0,
    auto_recharge_users: 0,
    low_balance_accounts: 0,
    recent_30d_recharges: 0,
    credit: {
        total_accounts: 0,
        total_limit: 0,
        total_used: 0,
        utilization_rate: 0,
    },
});

const searchForm = reactive({
    type: '',
    status: '',
    customer_id: '',
    date_from: '',
    date_to: '',
});

// 交易详情
const detailVisible = ref(false);
const selectedTransaction = ref(null);

// 操作弹窗
const actionDialog = reactive({
    visible: false,
    title: '',
    type: 'recharge', // recharge | deduct | adjust
});
const actionFormRef = ref(null);
const actionForm = reactive({
    customer_id: '',
    amount: 0,
    description: '',
});
const actionSubmitting = ref(false);

// 信用额度弹窗
const creditDialog = reactive({
    visible: false,
});
const creditFormRef = ref(null);
const creditForm = reactive({
    customer_id: '',
    credit_limit: 0,
    grace_days: 0,
});
const creditSubmitting = ref(false);

// 客户选项
const customerOptions = ref([]);

const typeLabels = computed(() => ({
    recharge: t('prepaid_balance_page.types.recharge'),
    consume: t('prepaid_balance_page.types.consume'),
    refund: t('prepaid_balance_page.types.refund'),
    adjust: t('prepaid_balance_page.types.adjust'),
    credit_use: t('prepaid_balance_page.types.credit_use'),
    credit_repay: t('prepaid_balance_page.types.credit_repay'),
}));

const methodLabels = computed(() => ({
    alipay: t('prepaid_balance_page.methods.alipay'),
    wechat: t('prepaid_balance_page.methods.wechat'),
    yipay: t('prepaid_balance_page.methods.yipay'),
    offline: t('prepaid_balance_page.methods.offline'),
    admin: t('prepaid_balance_page.methods.admin'),
    balance: t('prepaid_balance_page.methods.balance'),
}));

const statusLabels = computed(() => ({
    completed: t('prepaid_balance_page.status.success'),
    pending: t('prepaid_balance_page.status.pending'),
    failed: t('prepaid_balance_page.status.failed'),
}));

const actionDialogTitles = computed(() => ({
    recharge: t('prepaid_balance_page.dialog.manual_recharge'),
    deduct: t('prepaid_balance_page.dialog.manual_deduct'),
    adjust: t('prepaid_balance_page.dialog.balance_adjust'),
}));

const transactionTypeOptions = computed(() => [
    { value: 'recharge', label: typeLabels.value.recharge },
    { value: 'consume', label: typeLabels.value.consume },
    { value: 'refund', label: typeLabels.value.refund },
    { value: 'adjust', label: typeLabels.value.adjust },
    { value: 'credit_use', label: typeLabels.value.credit_use },
    { value: 'credit_repay', label: typeLabels.value.credit_repay },
]);

const transactionStatusOptions = computed(() => [
    { value: 'completed', label: t('prepaid_balance_page.status.completed') },
    { value: 'pending', label: t('prepaid_balance_page.status.pending') },
    { value: 'failed', label: t('prepaid_balance_page.status.failed') },
]);

const actionRules = computed(() => ({
    amount: [{ required: true, type: 'number', min: 0.01, message: t('prepaid_balance_page.validation.amount_required'), trigger: 'blur' }],
}));

const creditRules = computed(() => ({
    credit_limit: [{ required: true, type: 'number', min: 0, message: t('prepaid_balance_page.validation.credit_limit_required'), trigger: 'blur' }],
}));

// ─── 方法 ───

function formatMoney(val) {
    const num = parseFloat(val || 0);
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return '¥' + num.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function typeLabel(type) {
    return typeLabels.value[type] || type;
}

function typeTag(type) {
    const map = {
        recharge: 'success',
        consume: 'danger',
        refund: 'warning',
        adjust: 'info',
        credit_use: 'warning',
        credit_repay: 'success',
    };
    return map[type] || 'info';
}

function methodLabel(method) {
    return methodLabels.value[method] || method || '-';
}

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function resetSearch() {
    searchForm.type = '';
    searchForm.status = '';
    searchForm.customer_id = '';
    searchForm.date_from = '';
    searchForm.date_to = '';
    currentPage.value = 1;
    loadTransactions();
}

async function loadStats() {
    try {
        const res = await prepaidBalanceApi.getStats();
        Object.assign(stats, res.data);
    } catch (e) {
        console.error('Failed to load stats:', e);
    }
}

async function loadTransactions() {
    loading.value = true;
    try {
        const params = {
            page: currentPage.value,
            per_page: perPage.value,
            ...searchForm,
        };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await prepaidBalanceApi.allTransactions(params);
        const data = res.data;
        transactions.value = data.data || data;
        if (data.meta) {
            totalRecords.value = data.meta.total;
            currentPage.value = data.meta.current_page;
        } else if (Array.isArray(data)) {
            transactions.value = data;
        }
    } catch (e) {
        console.error('Failed to load transactions:', e);
        ElMessage.error(t('prepaid_balance_page.messages.load_transactions_failed'));
    } finally {
        loading.value = false;
    }
}

function showTransactionDetail(row) {
    selectedTransaction.value = row;
    detailVisible.value = true;
}

// 操作：充值/扣款/调账
function showActionDialog(type) {
    actionDialog.type = type;
    actionDialog.title = actionDialogTitles.value[type];
    actionForm.customer_id = '';
    actionForm.amount = type === 'deduct' ? 0 : 0;
    actionForm.description = '';
    actionDialog.visible = true;
    loadCustomerOptions();
}

// 信用额度设置
function showCreditDialog() {
    creditDialog.visible = true;
    creditForm.customer_id = '';
    creditForm.credit_limit = 0;
    creditForm.grace_days = 0;
    loadCustomerOptions();
}

async function loadCustomerOptions() {
    try {
        const res = await customerApi.list({ per_page: 50 });
        customerOptions.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to load customers:', e);
    }
}

async function executeAction() {
    const valid = await actionFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    actionSubmitting.value = true;
    try {
        const { type } = actionDialog;
        const customerId = actionForm.customer_id;
        const data = {
            amount: type === 'deduct' ? Math.abs(actionForm.amount) : actionForm.amount,
            description: actionForm.description,
        };

        let res;
        if (type === 'recharge') {
            res = await prepaidBalanceApi.adminRecharge(customerId, data);
        } else if (type === 'deduct') {
            res = await prepaidBalanceApi.adminDeduct(customerId, data);
        } else {
            res = await prepaidBalanceApi.adminAdjust(customerId, data);
        }

        const successMessages = {
            recharge: t('prepaid_balance_page.messages.recharge_success'),
            deduct: t('prepaid_balance_page.messages.deduct_success'),
            adjust: t('prepaid_balance_page.messages.adjust_success'),
        };
        ElMessage.success(successMessages[type]);
        actionDialog.visible = false;
        loadStats();
        loadTransactions();
    } catch (e) {
        ElMessage.error(e.response?.data?.error || t('messages.failed'));
    } finally {
        actionSubmitting.value = false;
    }
}

async function executeSetCreditLimit() {
    const valid = await creditFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creditSubmitting.value = true;
    try {
        await prepaidBalanceApi.setCreditLimit(creditForm.customer_id, {
            credit_limit: creditForm.credit_limit,
            grace_days: creditForm.grace_days,
        });
        ElMessage.success(t('prepaid_balance_page.messages.credit_set_success'));
        creditDialog.visible = false;
        loadStats();
    } catch (e) {
        ElMessage.error(e.response?.data?.error || t('prepaid_balance_page.messages.set_failed'));
    } finally {
        creditSubmitting.value = false;
    }
}

// ─── 初始化 ───
onMounted(() => {
    loadStats();
    loadTransactions();
});
</script>

<style scoped>
.prepaid-balance-page {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.text-muted {
    color: #909399;
    font-size: 13px;
}

.stats-row {
    margin-bottom: 16px;
}

.stat-card {
    text-align: center;
    margin-bottom: 12px;
}

.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.stat-card .stat-sub {
    font-size: 11px;
    color: #c0c4cc;
    margin-top: 2px;
}

.stat-card.credit .stat-value {
    color: #67c23a;
}

.stat-card.warning .stat-value {
    color: #e6a23c;
}

.toolbar-card {
    margin-bottom: 16px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.text-success {
    color: #67c23a;
    font-weight: 600;
}

.text-danger {
    color: #f56c6c;
    font-weight: 600;
}

.small {
    font-size: 12px;
}

.ml-2 {
    margin-left: 8px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: center;
}

.link {
    color: #0f172a;
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}
</style>
