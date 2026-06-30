<template>
    <div class="order-management">
        <div class="page-header">
            <div>
                <h2>订单管理</h2>
                <p class="text-muted">查看和管理所有订单</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total }}</div>
                        <div class="stat-label">全部订单</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c;">{{ stats.pending }}</div>
                        <div class="stat-label">待支付</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a;">{{ stats.paid }}</div>
                        <div class="stat-label">已支付</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c;">{{ stats.cancelled }}</div>
                        <div class="stat-label">已取消/退款</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" @keyup.enter="doSearch">
                <el-form-item label="订单号">
                    <el-input v-model="filters.search" placeholder="输入订单号" clearable style="width: 180px;" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 140px;">
                        <el-option label="待支付" value="pending" />
                        <el-option label="已支付" value="paid" />
                        <el-option label="已取消" value="cancelled" />
                        <el-option label="退款中" value="refunding" />
                        <el-option label="已退款" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item label="日期">
                    <el-date-picker
                        v-model="filters.dateRange"
                        type="daterange"
                        range-separator="至"
                        start-placeholder="开始日期"
                        end-placeholder="结束日期"
                        style="width: 240px;"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 订单列表 -->
        <el-card shadow="never">
            <el-table :data="orders" v-loading="loading" stripe @sort-change="handleSortChange">
                <el-table-column prop="order_no" label="订单号" width="200" sortable="custom" />
                <el-table-column label="客户" width="150">
                    <template #default="{ row }">
                        {{ row.user?.name || row.user?.email || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="final_amount" label="金额" width="120" sortable="custom">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.final_amount }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="payment_method" label="支付方式" width="120" />
                <el-table-column prop="created_at" label="创建时间" width="170" sortable="custom">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button
                            v-if="row.status === 'pending'"
                            type="warning"
                            link
                            size="small"
                            @click="handleCancel(row)"
                        >取消</el-button>
                        <el-button
                            v-if="row.status === 'pending'"
                            type="success"
                            link
                            size="small"
                            @click="handlePay(row)"
                        >标记支付</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex-center">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadOrders"
                    @current-change="loadOrders"
                />
            </div>
        </el-card>

        <!-- 详情抽屉 -->
        <el-drawer v-model="drawerVisible" title="订单详情" size="600px">
            <template v-if="currentOrder">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="订单号">{{ currentOrder.order_no }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(currentOrder.status)" size="small">
                            {{ statusLabel(currentOrder.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="总额">¥{{ currentOrder.total_amount }}</el-descriptions-item>
                    <el-descriptions-item label="实付">¥{{ currentOrder.final_amount }}</el-descriptions-item>
                    <el-descriptions-item label="支付方式">{{ currentOrder.payment_method || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="交易号">{{ currentOrder.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ currentOrder.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="支付时间">{{ currentOrder.paid_at || '-' }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="mt-4">订单明细</h4>
                <el-table :data="currentOrder.items" stripe>
                    <el-table-column prop="name" label="商品" />
                    <el-table-column prop="unit_price" label="单价">
                        <template #default="{ row }">¥{{ row.unit_price }}</template>
                    </el-table-column>
                    <el-table-column prop="quantity" label="数量" width="80" />
                    <el-table-column prop="subtotal" label="小计">
                        <template #default="{ row }">¥{{ row.subtotal }}</template>
                    </el-table-column>
                </el-table>

                <h4 class="mt-4">发货记录</h4>
                <el-table :data="currentOrder.deliveries || []" stripe v-if="currentOrder.deliveries?.length">
                    <el-table-column prop="delivery_type" label="类型" />
                    <el-table-column prop="delivery_channel" label="渠道" />
                    <el-table-column prop="status" label="状态">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'delivered' ? 'success' : 'warning'" size="small">
                                {{ row.status === 'delivered' ? '已交付' : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sent_at" label="发送时间" width="170" />
                    <el-table-column prop="delivered_at" label="交付时间" width="170" />
                </el-table>
                <el-empty v-else description="暂无发货记录" />
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import orderApi from '@/api/order';

const loading = ref(false);
const orders = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const sortField = ref('-created_at');
const drawerVisible = ref(false);
const currentOrder = ref(null);

const filters = reactive({
    search: '',
    status: '',
    dateRange: null,
});

const stats = computed(() => {
    const all = orders.value;
    return {
        total: total.value,
        pending: all.filter(o => o.status === 'pending').length,
        paid: all.filter(o => o.status === 'paid').length,
        cancelled: all.filter(o => ['cancelled', 'refunded', 'refunding'].includes(o.status)).length,
    };
});

function statusType(status) {
    return {
        pending: 'warning',
        paid: 'success',
        cancelled: 'info',
        refunding: 'danger',
        refunded: 'danger',
        partial_refund: 'danger',
    }[status] || 'info';
}

function statusLabel(status) {
    return {
        pending: '待支付',
        paid: '已支付',
        cancelled: '已取消',
        refunding: '退款中',
        refunded: '已退款',
        partial_refund: '部分退款',
    }[status] || status;
}

async function loadOrders() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: sortField.value,
        };
        if (filters.status) params.status = filters.status;
        if (filters.search) params.search = filters.search;
        if (filters.dateRange) {
            params.date_from = filters.dateRange[0];
            params.date_to = filters.dateRange[1];
        }

        const { data: res } = await orderApi.list(params);
        orders.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch { /* ignore */ }
    finally { loading.value = false; }
}

function doSearch() { page.value = 1; loadOrders(); }
function resetFilters() {
    filters.search = '';
    filters.status = '';
    filters.dateRange = null;
    doSearch();
}
function handleSortChange({ prop, order }) {
    sortField.value = (order === 'desc' ? '-' : '') + (prop || 'created_at');
    loadOrders();
}

async function viewDetail(row) {
    try {
        const { data: res } = await orderApi.show(row.id);
        currentOrder.value = res.data;
        drawerVisible.value = true;
    } catch {
        ElMessage.error('加载订单详情失败');
    }
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm('确定取消该订单？', '确认', { type: 'warning' });
        await orderApi.cancel(row.id, '手动取消');
        ElMessage.success('订单已取消');
        loadOrders();
    } catch { /* cancelled */ }
}

async function handlePay(row) {
    try {
        await ElMessageBox.prompt('请输入支付方式', '标记支付', {
            inputValue: 'manual',
            inputPlaceholder: '如: manual, alipay, wechat',
        });
        await orderApi.markPaid(row.id, {
            payment_method: 'manual',
            transaction_id: 'MANUAL-' + Date.now(),
        });
        ElMessage.success('已标记为已支付');
        loadOrders();
    } catch { /* cancelled */ }
}

onMounted(loadOrders);
</script>

<style scoped>
.order-management { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-price { font-weight: 600; color: #f56c6c; }
.flex-center { display: flex; justify-content: center; }
</style>
