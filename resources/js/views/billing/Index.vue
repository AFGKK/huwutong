<template>
    <div class="billing-page">
        <div class="page-header">
            <h2>订阅计费</h2>
            <div class="header-actions">
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 创建订阅
                </el-button>
            </div>
        </div>

        <!-- Stats Cards -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.active }}</div>
                        <div class="stat-label">活跃订阅</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.in_grace_period }}</div>
                        <div class="stat-label">宽限期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #409eff">{{ stats.mrr }}</div>
                        <div class="stat-label">MRR (¥)</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a">{{ stats.estimated_arr }}</div>
                        <div class="stat-label">ARR (¥)</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="订阅列表" name="subscriptions">
                    <el-table :data="subscriptions" v-loading="loading" stripe>
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="客户" min-width="150">
                            <template #default="{ row }">
                                {{ row.customer?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="产品" min-width="120">
                            <template #default="{ row }">
                                {{ row.product?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="plan" label="套餐" width="100" />
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">
                                ¥{{ row.price }} / {{ periodLabel(row.billing_period) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="到期时间" width="170">
                            <template #default="{ row }">
                                <span v-if="row.ends_at">{{ formatTime(row.ends_at) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="auto_renew" label="自动续费" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.auto_renew ? 'success' : 'info'" size="small">
                                    {{ row.auto_renew ? '开启' : '关闭' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewSubscription(row)">详情</el-button>
                                <el-button
                                    v-if="row.status === 'active'"
                                    text
                                    size="small"
                                    type="warning"
                                    @click="handleCancel(row)"
                                >
                                    取消
                                </el-button>
                                <el-button
                                    v-if="row.status !== 'active' && row.status !== 'expired'"
                                    text
                                    size="small"
                                    type="primary"
                                    @click="handleResume(row)"
                                >
                                    恢复
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="page"
                            :page-size="perPage"
                            :total="total"
                            layout="prev, pager, next, total"
                            @current-change="fetchSubscriptions"
                        />
                    </div>
                </el-tab-pane>

                <el-tab-pane label="发票记录" name="invoices">
                    <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                        <el-table-column prop="invoice_no" label="发票号" width="180" />
                        <el-table-column label="客户" min-width="150">
                            <template #default="{ row }">
                                {{ row.customer?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">
                                <span class="font-mono">¥{{ row.amount }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="invoiceStatusType(row.status)" size="small">
                                    {{ invoiceStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="billing_reason" label="原因" width="140" />
                        <el-table-column label="创建时间" width="170">
                            <template #default="{ row }">
                                {{ formatTime(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewInvoice(row)">详情</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="invoicePage"
                            :page-size="invoicePerPage"
                            :total="invoiceTotal"
                            layout="prev, pager, next, total"
                            @current-change="fetchInvoices"
                        />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- Create Subscription Dialog -->
        <el-dialog v-model="showCreate" title="创建订阅" width="560px">
            <el-form :model="createForm" label-width="120px" :rules="createRules" ref="createFormRef">
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="createForm.customer_id" filterable remote
                        :remote-method="searchCustomers" placeholder="搜索客户" style="width: 100%"
                        :loading="searchingCustomers">
                        <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="产品" prop="product_id">
                    <el-select v-model="createForm.product_id" placeholder="选择产品" style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="套餐名称" prop="plan">
                    <el-input v-model="createForm.plan" placeholder="如：Pro、Enterprise" />
                </el-form-item>
                <el-form-item label="价格" prop="price">
                    <el-input-number v-model="createForm.price" :min="0" :precision="2" style="width: 200px" />
                </el-form-item>
                <el-form-item label="计费周期">
                    <el-select v-model="createForm.billing_period" style="width: 160px">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="半年付" value="semi_annually" />
                        <el-option label="年付" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="试用天数">
                    <el-input-number v-model="createForm.trial_days" :min="0" :max="90" />
                </el-form-item>
                <el-form-item label="自动续费">
                    <el-switch v-model="createForm.auto_renew" />
                </el-form-item>
                <el-form-item label="关联 License">
                    <el-select v-model="createForm.license_id" filterable remote
                        :remote-method="searchLicenses" placeholder="可选" style="width: 100%"
                        :loading="searchingLicenses" clearable>
                        <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" @click="handleCreate" :loading="creating">创建</el-button>
            </template>
        </el-dialog>

        <!-- Subscription Detail Dialog -->
        <el-dialog v-model="showDetail" title="订阅详情" width="600px">
            <el-descriptions v-if="currentSubscription" :column="2" border>
                <el-descriptions-item label="ID">{{ currentSubscription.id }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="statusType(currentSubscription.status)" size="small">
                        {{ statusLabel(currentSubscription.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="客户">{{ currentSubscription.customer?.name }}</el-descriptions-item>
                <el-descriptions-item label="产品">{{ currentSubscription.product?.name }}</el-descriptions-item>
                <el-descriptions-item label="套餐">{{ currentSubscription.plan }}</el-descriptions-item>
                <el-descriptions-item label="价格">¥{{ currentSubscription.price }} / {{ periodLabel(currentSubscription.billing_period) }}</el-descriptions-item>
                <el-descriptions-item label="开始时间">{{ formatTime(currentSubscription.starts_at) }}</el-descriptions-item>
                <el-descriptions-item label="到期时间">{{ formatTime(currentSubscription.ends_at) }}</el-descriptions-item>
                <el-descriptions-item label="自动续费">
                    <el-tag :type="currentSubscription.auto_renew ? 'success' : 'info'" size="small">
                        {{ currentSubscription.auto_renew ? '开启' : '关闭' }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="计费周期">{{ periodLabel(currentSubscription.billing_period) }}</el-descriptions-item>
                <el-descriptions-item label="已付总额">¥{{ currentSubscription.total_paid }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- Invoice Detail Dialog -->
        <el-dialog v-model="showInvoiceDetail" title="发票详情" width="500px">
            <el-descriptions v-if="currentInvoice" :column="2" border>
                <el-descriptions-item label="发票号">{{ currentInvoice.invoice_no }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="invoiceStatusType(currentInvoice.status)" size="small">
                        {{ invoiceStatusLabel(currentInvoice.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="金额">¥{{ currentInvoice.amount }}</el-descriptions-item>
                <el-descriptions-item label="币种">{{ currentInvoice.currency }}</el-descriptions-item>
                <el-descriptions-item label="原因">{{ currentInvoice.billing_reason }}</el-descriptions-item>
                <el-descriptions-item label="支付时间">{{ formatTime(currentInvoice.paid_at) }}</el-descriptions-item>
                <el-descriptions-item label="客户">{{ currentInvoice.customer?.name }}</el-descriptions-item>
                <el-descriptions-item label="到期日">{{ formatTime(currentInvoice.due_at) }}</el-descriptions-item>
            </el-descriptions>
            <div v-if="currentInvoice?.status === 'pending'" class="mt-4">
                <el-input v-model="transactionId" placeholder="输入交易编号" class="mr-2" style="width: 240px" />
                <el-button type="primary" @click="handleMarkPaid(currentInvoice)">标记已支付</el-button>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import billingApi from '@/api/billing';
import customerApi from '@/api/customer';
import productApi from '@/api/product';
import licenseApi from '@/api/license';

const loading = ref(false);
const loadingInvoices = ref(false);
const creating = ref(false);
const searchingCustomers = ref(false);
const searchingLicenses = ref(false);
const activeTab = ref('subscriptions');
const showCreate = ref(false);
const showDetail = ref(false);
const showInvoiceDetail = ref(false);
const createFormRef = ref(null);

// Subscriptions
const subscriptions = ref([]);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);

// Invoices
const invoices = ref([]);
const invoicePage = ref(1);
const invoicePerPage = ref(15);
const invoiceTotal = ref(0);

// Stats
const stats = reactive({
    total: 0,
    active: 0,
    in_grace_period: 0,
    mrr: 0,
    estimated_arr: 0,
});

// Create form
const createForm = reactive({
    customer_id: null,
    product_id: null,
    plan: '',
    price: 0,
    billing_period: 'monthly',
    trial_days: 0,
    auto_renew: true,
    license_id: null,
});

const createRules = {
    customer_id: [{ required: true, message: '请选择客户' }],
    product_id: [{ required: true, message: '请选择产品' }],
    plan: [{ required: true, message: '请输入套餐名称' }],
    price: [{ required: true, message: '请输入价格' }],
};

const customerOptions = ref([]);
const products = ref([]);
const licenseOptions = ref([]);
const currentSubscription = ref(null);
const currentInvoice = ref(null);
const transactionId = ref('');

function statusType(status) {
    const map = { active: 'success', grace: 'warning', suspended: 'info', expired: 'danger', canceled: 'info', pending: 'info' };
    return map[status] || 'info';
}

function statusLabel(status) {
    const map = { active: '活跃', grace: '宽限期', suspended: '已暂停', expired: '已过期', canceled: '已取消', pending: '待激活' };
    return map[status] || status;
}

function invoiceStatusType(status) {
    const map = { paid: 'success', pending: 'warning', refunded: 'danger', canceled: 'info' };
    return map[status] || 'info';
}

function invoiceStatusLabel(status) {
    const map = { paid: '已支付', pending: '待支付', refunded: '已退款', canceled: '已取消' };
    return map[status] || status;
}

function periodLabel(period) {
    const map = { monthly: '月', quarterly: '季度', semi_annually: '半年', yearly: '年' };
    return map[period] || period;
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

async function fetchSubscriptions() {
    loading.value = true;
    try {
        const { data: res } = await billingApi.subscriptions({ page: page.value, per_page: perPage.value });
        if (res.success) {
            subscriptions.value = res.data?.data || [];
            total.value = res.data?.total || 0;
        }
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

async function fetchInvoices() {
    loadingInvoices.value = true;
    try {
        const { data: res } = await billingApi.invoices({ page: invoicePage.value, per_page: invoicePerPage.value });
        if (res.success) {
            invoices.value = res.data?.data || [];
            invoiceTotal.value = res.data?.total || 0;
        }
    } catch {
        // ignore
    } finally {
        loadingInvoices.value = false;
    }
}

async function fetchStats() {
    try {
        const { data: res } = await billingApi.stats();
        if (res.success) {
            Object.assign(stats, res.data);
        }
    } catch {
        // ignore
    }
}

async function loadProducts() {
    try {
        const { data: res } = await productApi.list({ per_page: 100 });
        if (res.success) {
            products.value = res.data?.data || [];
        }
    } catch {
        // ignore
    }
}

async function searchCustomers(query) {
    if (!query) return;
    searchingCustomers.value = true;
    try {
        const { data: res } = await customerApi.list({ search: query, per_page: 20 });
        if (res.success) {
            customerOptions.value = res.data?.data || [];
        }
    } catch {
        // ignore
    } finally {
        searchingCustomers.value = false;
    }
}

async function searchLicenses(query) {
    if (!query) return;
    searchingLicenses.value = true;
    try {
        const { data: res } = await licenseApi.list({ search: query, per_page: 20 });
        if (res.success) {
            licenseOptions.value = res.data?.data || [];
        }
    } catch {
        // ignore
    } finally {
        searchingLicenses.value = false;
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        const { data: res } = await billingApi.createSubscription(createForm);
        if (res.success) {
            ElMessage.success('订阅创建成功');
            showCreate.value = false;
            createFormRef.value?.resetFields();
            fetchSubscriptions();
            fetchStats();
        } else {
            ElMessage.error(res.message || '创建失败');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败');
    } finally {
        creating.value = false;
    }
}

function viewSubscription(row) {
    currentSubscription.value = row;
    showDetail.value = true;
}

function viewInvoice(row) {
    currentInvoice.value = row;
    transactionId.value = '';
    showInvoiceDetail.value = true;
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm('取消后，当前周期结束后将不再续费，确认取消？', '确认取消', { type: 'warning' });
        const { data: res } = await billingApi.cancelSubscription(row.id);
        if (res.success) {
            ElMessage.success('已取消');
            fetchSubscriptions();
        }
    } catch {
        // cancelled
    }
}

async function handleResume(row) {
    try {
        await ElMessageBox.confirm('确认恢复此订阅？', '确认恢复');
        const { data: res } = await billingApi.resumeSubscription(row.id);
        if (res.success) {
            ElMessage.success('已恢复');
            fetchSubscriptions();
        }
    } catch {
        // cancelled
    }
}

async function handleMarkPaid(invoice) {
    if (!transactionId.value) {
        ElMessage.warning('请输入交易编号');
        return;
    }
    try {
        const { data: res } = await billingApi.markInvoicePaid(invoice.id, transactionId.value);
        if (res.success) {
            ElMessage.success('已标记为已支付');
            showInvoiceDetail.value = false;
            fetchInvoices();
            fetchStats();
        }
    } catch {
        ElMessage.error('操作失败');
    }
}

onMounted(() => {
    fetchSubscriptions();
    fetchInvoices();
    fetchStats();
    loadProducts();
});
</script>

<style scoped>
.billing-page {
    padding: 0;
}

.stat-card {
    text-align: center;
    padding: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 13px;
    color: #909399;
}

.font-mono {
    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
}

.mb-4 {
    margin-bottom: 16px;
}

.mt-4 {
    margin-top: 16px;
}

.mr-2 {
    margin-right: 8px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.text-gray-400 {
    color: #909399;
}
</style>
