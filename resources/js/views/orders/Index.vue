<template>
    <div class="order-management">
        <div class="page-header">
            <div>
                <h2>{{ t('orders_page.title') }}</h2>
                <p class="text-muted">{{ t('orders_page.subtitle') }}</p>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total }}</div>
                        <div class="stat-label">{{ t('orders_page.stats.total') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c;">{{ stats.pending }}</div>
                        <div class="stat-label">{{ t('orders_page.statuses.pending') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a;">{{ stats.paid }}</div>
                        <div class="stat-label">{{ t('orders_page.statuses.paid') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c;">{{ stats.cancelled }}</div>
                        <div class="stat-label">{{ t('orders_page.stats.cancelled_refund') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" @keyup.enter="doSearch">
                <el-form-item :label="t('orders_page.order_no')">
                    <el-input v-model="filters.search" :placeholder="t('orders_page.order_no_ph')" clearable style="width: 180px;" />
                </el-form-item>
                <el-form-item :label="t('orders_page.cols.status')">
                    <el-select v-model="filters.status" :placeholder="t('orders_page.all_statuses')" clearable style="width: 140px;">
                        <el-option :label="t('orders_page.statuses.pending')" value="pending" />
                        <el-option :label="t('orders_page.statuses.paid')" value="paid" />
                        <el-option :label="t('orders_page.statuses.cancelled')" value="cancelled" />
                        <el-option :label="t('orders_page.statuses.refunding')" value="refunding" />
                        <el-option :label="t('orders_page.statuses.refunded')" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('orders_page.date')">
                    <el-date-picker
                        v-model="filters.dateRange"
                        type="daterange"
                        :range-separator="t('orders_page.range_sep')"
                        :start-placeholder="t('orders_page.start_date')"
                        :end-placeholder="t('orders_page.end_date')"
                        style="width: 240px;"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <el-table :data="orders" v-loading="loading" stripe @sort-change="handleSortChange">
                <el-table-column prop="order_no" :label="t('orders_page.order_no')" width="200" sortable="custom" />
                <el-table-column :label="t('orders_page.cols.customer')" width="150">
                    <template #default="{ row }">
                        {{ row.user?.name || row.user?.email || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="final_amount" :label="t('orders_page.cols.amount')" width="120" sortable="custom">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.final_amount }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('orders_page.cols.status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="payment_method" :label="t('orders_page.cols.payment')" width="120" />
                <el-table-column prop="created_at" :label="t('orders_page.cols.created')" width="170" sortable="custom">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t('orders_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">{{ t('actions.view_details') }}</el-button>
                        <el-button
                            v-if="row.status === 'pending'"
                            type="warning"
                            link
                            size="small"
                            @click="handleCancel(row)"
                        >{{ t('actions.cancel') }}</el-button>
                        <el-button
                            v-if="row.status === 'pending'"
                            type="success"
                            link
                            size="small"
                            @click="handlePay(row)"
                        >{{ t('orders_page.mark_paid') }}</el-button>
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

        <el-drawer v-model="drawerVisible" :title="t('orders_page.detail_title')" size="600px">
            <template v-if="currentOrder">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('orders_page.order_no')">{{ currentOrder.order_no }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.cols.status')">
                        <el-tag :type="statusType(currentOrder.status)" size="small">
                            {{ statusLabel(currentOrder.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.total')">¥{{ currentOrder.total_amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.paid_amount')">¥{{ currentOrder.final_amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.cols.payment')">{{ currentOrder.payment_method || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.txn')">{{ currentOrder.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.cols.created')">{{ currentOrder.created_at }}</el-descriptions-item>
                    <el-descriptions-item :label="t('orders_page.paid_at')">{{ currentOrder.paid_at || '-' }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="mt-4">{{ t('orders_page.line_items') }}</h4>
                <el-table :data="currentOrder.items" stripe>
                    <el-table-column prop="name" :label="t('orders_page.cols.product')" />
                    <el-table-column prop="unit_price" :label="t('orders_page.cols.unit_price')">
                        <template #default="{ row }">¥{{ row.unit_price }}</template>
                    </el-table-column>
                    <el-table-column prop="quantity" :label="t('orders_page.cols.qty')" width="80" />
                    <el-table-column prop="subtotal" :label="t('orders_page.cols.subtotal')">
                        <template #default="{ row }">¥{{ row.subtotal }}</template>
                    </el-table-column>
                </el-table>

                <h4 class="mt-4">{{ t('orders_page.deliveries') }}</h4>
                <el-table :data="currentOrder.deliveries || []" stripe v-if="currentOrder.deliveries?.length">
                    <el-table-column prop="delivery_type" :label="t('orders_page.cols.type')" />
                    <el-table-column prop="delivery_channel" :label="t('orders_page.cols.channel')" />
                    <el-table-column prop="status" :label="t('orders_page.cols.status')">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'delivered' ? 'success' : 'warning'" size="small">
                                {{ row.status === 'delivered' ? t('orders_page.delivered') : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sent_at" :label="t('orders_page.cols.sent_at')" width="170" />
                    <el-table-column prop="delivered_at" :label="t('orders_page.cols.delivered_at')" width="170" />
                </el-table>
                <el-empty v-else :description="t('orders_page.empty_deliveries')" />
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import orderApi from '@/api/order'

const { t } = useI18n()

const loading = ref(false)
const orders = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const sortField = ref('-created_at')
const drawerVisible = ref(false)
const currentOrder = ref(null)

const filters = reactive({
    search: '',
    status: '',
    dateRange: null,
})

const stats = computed(() => {
    const all = orders.value
    return {
        total: total.value,
        pending: all.filter(o => o.status === 'pending').length,
        paid: all.filter(o => o.status === 'paid').length,
        cancelled: all.filter(o => ['cancelled', 'refunded', 'refunding'].includes(o.status)).length,
    }
})

function statusType(status) {
    return {
        pending: 'warning',
        paid: 'success',
        cancelled: 'info',
        refunding: 'danger',
        refunded: 'danger',
        partial_refund: 'danger',
    }[status] || 'info'
}

function statusLabel(status) {
    const key = {
        pending: 'pending',
        paid: 'paid',
        cancelled: 'cancelled',
        refunding: 'refunding',
        refunded: 'refunded',
        partial_refund: 'partial_refund',
    }[status]
    return key ? t(`orders_page.statuses.${key}`) : status
}

async function loadOrders() {
    loading.value = true
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: sortField.value,
        }
        if (filters.status) params.status = filters.status
        if (filters.search) params.search = filters.search
        if (filters.dateRange) {
            params.date_from = filters.dateRange[0]
            params.date_to = filters.dateRange[1]
        }

        const { data: res } = await orderApi.list(params)
        orders.value = res.data?.data || []
        total.value = res.data?.total || 0
    } catch { /* ignore */ }
    finally { loading.value = false }
}

function doSearch() { page.value = 1; loadOrders() }
function resetFilters() {
    filters.search = ''
    filters.status = ''
    filters.dateRange = null
    doSearch()
}
function handleSortChange({ prop, order }) {
    sortField.value = (order === 'desc' ? '-' : '') + (prop || 'created_at')
    loadOrders()
}

async function viewDetail(row) {
    try {
        const { data: res } = await orderApi.show(row.id)
        currentOrder.value = res.data
        drawerVisible.value = true
    } catch {
        ElMessage.error(t('orders_page.messages.detail_failed'))
    }
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(t('orders_page.messages.cancel_confirm'), t('actions.confirm'), { type: 'warning' })
        await orderApi.cancel(row.id, t('orders_page.manual_cancel'))
        ElMessage.success(t('orders_page.messages.cancelled'))
        loadOrders()
    } catch { /* cancelled */ }
}

async function handlePay(row) {
    try {
        await ElMessageBox.prompt(t('orders_page.messages.pay_prompt'), t('orders_page.mark_paid'), {
            inputValue: 'manual',
            inputPlaceholder: t('orders_page.messages.pay_ph'),
        })
        await orderApi.markPaid(row.id, {
            payment_method: 'manual',
            transaction_id: 'MANUAL-' + Date.now(),
        })
        ElMessage.success(t('orders_page.messages.marked_paid'))
        loadOrders()
    } catch { /* cancelled */ }
}

onMounted(loadOrders)
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
