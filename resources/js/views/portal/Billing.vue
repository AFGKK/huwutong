<template>
    <div class="portal-billing">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.billing_title') }}</h2>
                <p class="text-muted">{{ $t('portal.billing_subtitle') }}</p>
            </div>
        </div>

        <el-card class="mb-4" shadow="never">
            <template #header>
                <span>{{ $t('portal.my_subscriptions') }}</span>
            </template>
            <el-table v-if="subscriptions.length" :data="subscriptions" v-loading="loading" stripe>
                <el-table-column prop="plan_name" :label="$t('portal.plan')" min-width="140">
                    <template #default="{ row }">{{ row.plan_name || row.plan?.name || row.plan || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="subStatusType(row.status)" size="small" effect="dark">
                            {{ subStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" :label="$t('portal.amount')" width="100">
                    <template #default="{ row }">
                        <span class="price">¥{{ row.amount || row.price || 0 }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_interval" :label="$t('portal.interval')" width="80">
                    <template #default="{ row }">
                        {{ row.billing_interval === 'monthly' ? $t('portal.monthly') : row.billing_interval === 'yearly' ? $t('portal.yearly') : row.billing_interval || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="current_period_start" :label="$t('portal.period_start')" width="150" />
                <el-table-column prop="current_period_end" :label="$t('portal.period_end')" width="150" />
                <el-table-column prop="created_at" :label="$t('portal.created_at')" width="150" />
            </el-table>
            <el-empty v-else-if="!loading" :description="$t('portal.no_subscriptions')" :image-size="60" />
        </el-card>

        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('portal.invoice_records') }}</span>
                    <el-select v-model="statusFilter" :placeholder="$t('portal.filter_status')" clearable size="small" style="width: 120px" @change="fetchInvoices">
                        <el-option :label="$t('portal.all')" value="" />
                        <el-option :label="$t('portal.inv_pending')" value="pending" />
                        <el-option :label="$t('portal.inv_paid')" value="paid" />
                        <el-option :label="$t('portal.inv_cancelled')" value="cancelled" />
                        <el-option :label="$t('portal.inv_refunded')" value="refunded" />
                    </el-select>
                </div>
            </template>
            <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                <el-table-column prop="invoice_no" :label="$t('portal.invoice_no')" min-width="140">
                    <template #default="{ row }">{{ row.invoice_no || row.invoice_number || `#${row.id}` }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.description')" min-width="160">
                    <template #default="{ row }">{{ row.description || billingReasonLabel(row.billing_reason) || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.amount')" width="100">
                    <template #default="{ row }">
                        <span class="price">¥{{ row.amount || 0 }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="invoiceStatusType(row.status)" size="small">
                            {{ invoiceStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.due_date')" width="120">
                    <template #default="{ row }">{{ formatDate(row.due_at || row.due_date) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.paid_at')" width="150">
                    <template #default="{ row }">{{ formatDate(row.paid_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'pending'"
                            type="primary"
                            link
                            size="small"
                            :loading="payingId === row.id"
                            @click="openPayDialog(row)"
                        >
                            {{ $t('portal.pay_now') }}
                        </el-button>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="invoicePage"
                    v-model:page-size="invoicePerPage"
                    :total="invoiceTotal"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchInvoices"
                    @size-change="fetchInvoices"
                />
            </div>
        </el-card>

        <el-dialog v-model="payDialogVisible" :title="$t('portal.pay_invoice')" width="420px" :close-on-click-modal="!payLoading">
            <template v-if="payTarget">
                <el-descriptions :column="1" border size="small" class="mb-3">
                    <el-descriptions-item :label="$t('portal.invoice_no')">{{ payTarget.invoice_no || payTarget.invoice_number || payTarget.id }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.amount_due')"><span class="price">¥{{ payTarget.amount }}</span></el-descriptions-item>
                </el-descriptions>
                <el-form label-width="90px" size="small">
                    <el-form-item :label="$t('portal.pay_method')">
                        <el-select v-model="payMethod" style="width:100%">
                            <el-option :label="$t('portal.pay_gateway')" value="gateway" />
                            <el-option :label="$t('portal.pay_prepaid')" value="prepaid" />
                        </el-select>
                    </el-form-item>
                </el-form>
                <p class="pay-hint">{{ $t('portal.pay_hint') }}</p>
            </template>
            <template #footer>
                <el-button @click="payDialogVisible = false" :disabled="payLoading">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="payLoading" @click="submitPay">{{ $t('portal.confirm_pay') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import billingApi from '@/api/billing';
import { ElMessage } from 'element-plus';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const loading = ref(false);
const subscriptions = ref([]);
const loadingInvoices = ref(false);
const invoices = ref([]);
const invoiceTotal = ref(0);
const invoicePage = ref(1);
const invoicePerPage = ref(10);
const statusFilter = ref('');

const payDialogVisible = ref(false);
const payTarget = ref(null);
const payMethod = ref('gateway');
const payLoading = ref(false);
const payingId = ref(null);
let pollTimer = null;

function subStatusType(s) {
    const map = {
        active: 'success', trialing: 'info', past_due: 'warning',
        canceled: 'info', cancelled: 'info', unpaid: 'danger', expired: 'info',
    };
    return map[s] || 'info';
}
function subStatusLabel(s) {
    const map = {
        active: t('portal.st_active'),
        trialing: t('portal.sub_trialing'),
        past_due: t('portal.sub_past_due'),
        canceled: t('portal.sub_canceled'),
        cancelled: t('portal.sub_canceled'),
        unpaid: t('portal.sub_unpaid'),
        expired: t('portal.st_expired'),
    };
    return map[s] || s;
}
function invoiceStatusType(s) {
    const map = {
        pending: 'warning', paid: 'success', cancelled: 'info',
        refunded: 'danger', failed: 'danger',
    };
    return map[s] || 'info';
}
function invoiceStatusLabel(s) {
    const map = {
        pending: t('portal.inv_pending'),
        paid: t('portal.inv_paid'),
        cancelled: t('portal.inv_cancelled'),
        refunded: t('portal.inv_refunded'),
        failed: t('portal.inv_failed'),
    };
    return map[s] || s;
}
function billingReasonLabel(r) {
    const map = {
        subscription_create: t('portal.reason_create'),
        subscription_renew: t('portal.reason_renew'),
        subscription_update: t('portal.reason_update'),
        manual: t('portal.reason_manual'),
    };
    return map[r] || r;
}
function formatDate(v) {
    if (!v) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(v).toLocaleString(loc);
}

async function fetchSubscriptions() {
    loading.value = true;
    try {
        const { data: res } = await billingApi.subscriptions({ per_page: 20 });
        subscriptions.value = res.data?.data || res.data || [];
    } catch {
        subscriptions.value = [];
    } finally {
        loading.value = false;
    }
}

async function fetchInvoices() {
    loadingInvoices.value = true;
    try {
        const params = {
            page: invoicePage.value,
            per_page: invoicePerPage.value,
            sort: '-created_at',
        };
        if (statusFilter.value) params.status = statusFilter.value;
        const { data: res } = await billingApi.invoices(params);
        invoices.value = res.data?.data || [];
        invoiceTotal.value = res.data?.total || 0;
    } catch {
        invoices.value = [];
    } finally {
        loadingInvoices.value = false;
    }
}

function openPayDialog(row) {
    payTarget.value = row;
    payMethod.value = 'gateway';
    payDialogVisible.value = true;
}

async function submitPay() {
    if (!payTarget.value) return;
    payLoading.value = true;
    payingId.value = payTarget.value.id;
    try {
        const { data: res } = await billingApi.payInvoice(payTarget.value.id, payMethod.value);
        const payload = res.data || {};

        if (payload.status === 'paid') {
            ElMessage.success(t('portal.pay_success'));
            payDialogVisible.value = false;
            await fetchInvoices();
            return;
        }

        if (payload.redirect_url) {
            ElMessage.info(t('portal.pay_redirect'));
            window.open(payload.redirect_url, '_blank');
        } else {
            ElMessage.info(t('portal.pay_pending'));
        }

        payDialogVisible.value = false;
        startPolling(payTarget.value.id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || e.response?.data?.error || t('portal.pay_fail'));
    } finally {
        payLoading.value = false;
        payingId.value = null;
    }
}

function startPolling(invoiceId) {
    stopPolling();
    let attempts = 0;
    pollTimer = setInterval(async () => {
        attempts++;
        try {
            const { data: res } = await billingApi.paymentStatus(invoiceId);
            if (res.data?.status === 'paid') {
                stopPolling();
                ElMessage.success(t('portal.pay_confirmed'));
                await fetchInvoices();
            } else if (attempts >= 15) {
                stopPolling();
                ElMessage.warning(t('portal.pay_timeout'));
            }
        } catch {
            if (attempts >= 15) stopPolling();
        }
    }, 2000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

onMounted(async () => {
    await Promise.all([fetchSubscriptions(), fetchInvoices()]);
    if (route.query.payment === 'return') {
        ElMessage.info(t('portal.pay_return_hint'));
        router.replace({ path: route.path });
    }
});

onUnmounted(() => stopPolling());
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 4px;
}

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.price {
    font-weight: 600;
    color: #303133;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.pay-hint {
    font-size: 12px;
    color: #909399;
    margin: 0;
    line-height: 1.5;
}
</style>
