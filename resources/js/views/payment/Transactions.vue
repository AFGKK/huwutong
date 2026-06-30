<template>
    <div class="payment-transactions">
        <div class="page-header">
            <h2>交易流水</h2>
            <div class="header-actions">
                <el-button @click="loadData">刷新</el-button>
            </div>
        </div>

        <!-- Filters -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width:130px">
                        <el-option label="已支付" value="paid" />
                        <el-option label="待支付" value="pending" />
                        <el-option label="失败" value="failed" />
                        <el-option label="已退款" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item label="渠道">
                    <el-select v-model="filters.payment_method" clearable placeholder="全部" style="width:130px">
                        <el-option label="支付宝" value="alipay" />
                        <el-option label="微信支付" value="wechat" />
                        <el-option label="Stripe" value="stripe" />
                        <el-option label="PayPal" value="paypal" />
                        <el-option label="模拟支付" value="mock" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="单号/ID" clearable style="width:160px" />
                </el-form-item>
                <el-form-item label="起始">
                    <el-date-picker v-model="filters.date_from" type="date" placeholder="开始日期" style="width:140px" />
                </el-form-item>
                <el-form-item label="截止">
                    <el-date-picker v-model="filters.date_to" type="date" placeholder="结束日期" style="width:140px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- Table -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="invoice_no" label="单号" width="160" />
                <el-table-column label="金额" width="120">
                    <template #default="{ row }">¥{{ row.amount }}</template>
                </el-table-column>
                <el-table-column prop="payment_method" label="渠道" width="100">
                    <template #default="{ row }">{{ channelLabels[row.payment_method] || row.payment_method || '-' }}</template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 'paid'" type="success" size="small">已支付</el-tag>
                        <el-tag v-else-if="row.status === 'pending'" type="warning" size="small">待支付</el-tag>
                        <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">失败</el-tag>
                        <el-tag v-else-if="row.status === 'refunded'" type="info" size="small">已退款</el-tag>
                        <el-tag v-else size="small">{{ row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="客户" min-width="150">
                    <template #default="{ row }">{{ row.customer?.name || row.customer?.email || '-' }}</template>
                </el-table-column>
                <el-table-column prop="paid_at" label="支付时间" width="180" />
                <el-table-column prop="created_at" label="创建时间" width="180" />
                <el-table-column label="操作" width="80" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="showDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="pagination.last_page > 1">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next, total"
                    @current-change="loadData"
                />
            </div>
        </el-card>

        <!-- Detail Dialog -->
        <el-dialog v-model="detailVisible" title="交易详情" width="600px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="单号">{{ detail.invoice_no }}</el-descriptions-item>
                    <el-descriptions-item label="金额">¥{{ detail.amount }}</el-descriptions-item>
                    <el-descriptions-item label="渠道">{{ channelLabels[detail.payment_method] || detail.payment_method || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag v-if="detail.status === 'paid'" type="success" size="small">已支付</el-tag>
                        <el-tag v-else-if="detail.status === 'pending'" type="warning" size="small">待支付</el-tag>
                        <el-tag v-else-if="detail.status === 'failed'" type="danger" size="small">失败</el-tag>
                        <el-tag v-else size="small">{{ detail.status }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="客户名">{{ detail.customer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="客户邮箱">{{ detail.customer?.email || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="订阅方案">{{ detail.subscription?.plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="支付时间">{{ detail.paid_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getTransactions, getTransactionDetail } from '@/api/payment';

const loading = ref(false);
const list = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = ref({
    status: '',
    payment_method: '',
    search: '',
    date_from: '',
    date_to: '',
});
const detailVisible = ref(false);
const detail = ref(null);

const channelLabels = {
    alipay: '支付宝',
    wechat: '微信支付',
    stripe: 'Stripe',
    mock: '模拟支付',
};

const loadData = async (page = 1) => {
    loading.value = true;
    try {
        const params = { ...filters.value, page: pagination.value.current_page, per_page: pagination.value.per_page };
        const res = await getTransactions(params);
        if (res.data.success) {
            list.value = res.data.data.data || [];
            pagination.value = {
                current_page: res.data.data.current_page,
                last_page: res.data.data.last_page,
                per_page: res.data.data.per_page,
                total: res.data.data.total,
            };
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

const showDetail = async (row) => {
    try {
        const res = await getTransactionDetail(row.id);
        if (res.data.success) {
            detail.value = res.data.data;
            detailVisible.value = true;
        }
    } catch (e) { /* ignore */ }
};

onMounted(() => loadData());
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
