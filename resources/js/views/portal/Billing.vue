<template>
    <div class="portal-billing">
        <div class="page-header">
            <div>
                <h2>账单与发票</h2>
                <p class="text-muted">查看您的订阅和发票记录。</p>
            </div>
        </div>

        <!-- 订阅信息 -->
        <el-card class="mb-4" shadow="never">
            <template #header>
                <span>我的订阅</span>
            </template>
            <el-table v-if="subscriptions.length" :data="subscriptions" v-loading="loading" stripe>
                <el-table-column prop="plan_name" label="订阅方案" min-width="140">
                    <template #default="{ row }">{{ row.plan_name || row.plan?.name || '-' }}</template>
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
                        <span class="price">¥{{ row.amount || 0 }}</span>
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
                <el-table-column prop="invoice_number" label="发票号" min-width="140" />
                <el-table-column prop="description" label="描述" min-width="160" />
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
                <el-table-column prop="due_date" label="到期日" width="120" />
                <el-table-column prop="paid_at" label="支付时间" width="150" />
                <el-table-column prop="created_at" label="创建时间" width="150" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'pending'"
                            type="primary"
                            link
                            size="small"
                            @click="handlePay(row)"
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
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import billingApi from '@/api/billing';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const subscriptions = ref([]);
const loadingInvoices = ref(false);
const invoices = ref([]);
const invoiceTotal = ref(0);
const invoicePage = ref(1);
const invoicePerPage = ref(10);
const statusFilter = ref('');

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

function subStatusType(s) { return SUB_STATUS_MAP[s]?.type || 'info'; }
function subStatusLabel(s) { return SUB_STATUS_MAP[s]?.label || s; }
function invoiceStatusType(s) { return INVOICE_STATUS_MAP[s]?.type || 'info'; }
function invoiceStatusLabel(s) { return INVOICE_STATUS_MAP[s]?.label || s; }

async function fetchSubscriptions() {
    loading.value = true;
    try {
        const { data: res } = await billingApi.subscriptions({ per_page: 20 });
        subscriptions.value = res.data?.data || [];
    } catch {
        // 订阅可能未启用
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
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }
        const { data: res } = await billingApi.invoices(params);
        invoices.value = res.data?.data || [];
        invoiceTotal.value = res.data?.total || 0;
    } catch {
        invoices.value = [];
    } finally {
        loadingInvoices.value = false;
    }
}

function handlePay(row) {
    ElMessage.info(`支付功能正在开发中，发票 #${row.invoice_number || row.id}`);
}

onMounted(() => {
    fetchSubscriptions();
    fetchInvoices();
});
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
</style>
