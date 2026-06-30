<template>
    <div class="portal-billing">
        <div class="page-header">
            <div>
                <h2>账单与发票</h2>
                <p class="text-muted">查看您的订阅和发票记录，在线支付待付账单。</p>
            </div>
        </div>

        <!-- 订阅信息 -->
        <el-card class="mb-4" shadow="never">
            <template #header>
                <span>我的订阅</span>
            </template>
            <el-table v-if="subscriptions.length" :data="subscriptions" v-loading="loading" stripe>
                <el-table-column prop="plan_name" label="订阅方案" min-width="140">
                    <template #default="{ row }">{{ row.plan_name || row.plan?.name || row.plan || '-' }}</template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="subStatusType(row.status)" size="small" effect="dark">
                            {{ subStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" label="金额" width="100">
                    <template #default="{ row }">
                        <span class="price">¥{{ row.amount || row.price || 0 }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_interval" label="周期" width="80">
                    <template #default="{ row }">{{ row.billing_interval === 'monthly' ? '月付' : row.billing_interval === 'yearly' ? '年付' : row.billing_interval || '-' }}</template>
                </el-table-column>
                <el-table-column prop="current_period_start" label="本期开始" width="150" />
                <el-table-column prop="current_period_end" label="本期结束" width="150" />
                <el-table-column prop="created_at" label="创建时间" width="150" />
            </el-table>
            <el-empty v-else-if="!loading" description="暂无订阅信息" :image-size="60" />
        </el-card>

        <!-- 发票记录 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>发票记录</span>
                    <el-select v-model="statusFilter" placeholder="筛选状态" clearable size="small" style="width: 120px" @change="fetchInvoices">
                        <el-option label="全部" value="" />
                        <el-option label="待支付" value="pending" />
                        <el-option label="已支付" value="paid" />
                        <el-option label="已取消" value="cancelled" />
                        <el-option label="已退款" value="refunded" />
                    </el-select>
                </div>
            </template>
            <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                <el-table-column prop="invoice_no" label="发票号" min-width="140">
                    <template #default="{ row }">{{ row.invoice_no || row.invoice_number || `#${row.id}` }}</template>
                </el-table-column>
                <el-table-column label="描述" min-width="160">
                    <template #default="{ row }">{{ row.description || billingReasonLabel(row.billing_reason) || '-' }}</template>
                </el-table-column>
                <el-table-column label="金额" width="100">
                    <template #default="{ row }">
                        <span class="price">¥{{ row.amount || 0 }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="invoiceStatusType(row.status)" size="small">
                            {{ invoiceStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="到期日" width="120">
                    <template #default="{ row }">{{ formatDate(row.due_at || row.due_date) }}</template>
                </el-table-column>
                <el-table-column label="支付时间" width="150">
                    <template #default="{ row }">{{ formatDate(row.paid_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'pending'"
                            type="primary"
                            link
                            size="small"
                            :loading="payingId === row.id"
                            @click="openPayDialog(row)"
                        >
                            去支付
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

        <!-- 支付对话框 -->
        <el-dialog v-model="payDialogVisible" title="支付发票" width="420px" :close-on-click-modal="!payLoading">
            <template v-if="payTarget">
                <el-descriptions :column="1" border size="small" class="mb-3">
                    <el-descriptions-item label="发票号">{{ payTarget.invoice_no || payTarget.invoice_number || payTarget.id }}</el-descriptions-item>
                    <el-descriptions-item label="应付金额"><span class="price">¥{{ payTarget.amount }}</span></el-descriptions-item>
                </el-descriptions>
                <el-form label-width="90px" size="small">
                    <el-form-item label="支付方式">
                        <el-select v-model="payMethod" style="width:100%">
                            <el-option label="在线支付（支付宝/Stripe）" value="gateway" />
                            <el-option label="预付余额" value="prepaid" />
                        </el-select>
                    </el-form-item>
                </el-form>
                <p class="pay-hint">支付完成后，系统将通过 Payment Webhook 自动确认到账；Mock 环境下可即时完成。</p>
            </template>
            <template #footer>
                <el-button @click="payDialogVisible = false" :disabled="payLoading">取消</el-button>
                <el-button type="primary" :loading="payLoading" @click="submitPay">确认支付</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import billingApi from '@/api/billing';
import { ElMessage } from 'element-plus';

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

const SUB_STATUS_MAP = {
    active: { type: 'success', label: '活跃' },
    trialing: { type: 'info', label: '试用中' },
    past_due: { type: 'warning', label: '逾期' },
    canceled: { type: 'info', label: '已取消' },
    cancelled: { type: 'info', label: '已取消' },
    unpaid: { type: 'danger', label: '未支付' },
    expired: { type: 'info', label: '已过期' },
};

const INVOICE_STATUS_MAP = {
    pending: { type: 'warning', label: '待支付' },
    paid: { type: 'success', label: '已支付' },
    cancelled: { type: 'info', label: '已取消' },
    refunded: { type: 'danger', label: '已退款' },
    failed: { type: 'danger', label: '支付失败' },
};

const BILLING_REASON_MAP = {
    subscription_create: '订阅创建',
    subscription_renew: '订阅续费',
    subscription_update: '订阅变更',
    manual: '手动账单',
};

function subStatusType(s) { return SUB_STATUS_MAP[s]?.type || 'info'; }
function subStatusLabel(s) { return SUB_STATUS_MAP[s]?.label || s; }
function invoiceStatusType(s) { return INVOICE_STATUS_MAP[s]?.type || 'info'; }
function invoiceStatusLabel(s) { return INVOICE_STATUS_MAP[s]?.label || s; }
function billingReasonLabel(r) { return BILLING_REASON_MAP[r] || r; }
function formatDate(v) { return v ? new Date(v).toLocaleString('zh-CN') : '-'; }

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
            ElMessage.success('支付成功');
            payDialogVisible.value = false;
            await fetchInvoices();
            return;
        }

        if (payload.redirect_url) {
            ElMessage.info('正在跳转至支付页面…');
            window.open(payload.redirect_url, '_blank');
        } else {
            ElMessage.info('支付已发起，正在等待确认…');
        }

        payDialogVisible.value = false;
        startPolling(payTarget.value.id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || e.response?.data?.error || '支付失败');
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
                ElMessage.success('支付已确认');
                await fetchInvoices();
            } else if (attempts >= 15) {
                stopPolling();
                ElMessage.warning('支付确认超时，请稍后刷新页面查看');
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
        ElMessage.info('如已完成支付，请稍候系统自动确认');
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
