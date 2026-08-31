<template>
    <div class="payment-transactions">
        <div class="page-header">
            <h2>{{ t('transactions_page.title') }}</h2>
            <div class="header-actions">
                <el-button @click="loadData">{{ t('actions.refresh') }}</el-button>
            </div>
        </div>

        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('transactions_page.cols.status')">
                    <el-select v-model="filters.status" clearable :placeholder="t('transactions_page.all')" style="width:130px">
                        <el-option :label="t('transactions_page.statuses.paid')" value="paid" />
                        <el-option :label="t('transactions_page.statuses.pending')" value="pending" />
                        <el-option :label="t('transactions_page.statuses.failed')" value="failed" />
                        <el-option :label="t('transactions_page.statuses.refunded')" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('transactions_page.cols.channel')">
                    <el-select v-model="filters.payment_method" clearable :placeholder="t('transactions_page.all')" style="width:130px">
                        <el-option :label="t('transactions_page.channels.alipay')" value="alipay" />
                        <el-option :label="t('transactions_page.channels.wechat')" value="wechat" />
                        <el-option label="Stripe" value="stripe" />
                        <el-option label="PayPal" value="paypal" />
                        <el-option :label="t('transactions_page.channels.mock')" value="mock" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('actions.search')">
                    <el-input v-model="filters.search" :placeholder="t('transactions_page.search_ph')" clearable style="width:160px" />
                </el-form-item>
                <el-form-item :label="t('transactions_page.date_from')">
                    <el-date-picker v-model="filters.date_from" type="date" :placeholder="t('transactions_page.start')" style="width:140px" />
                </el-form-item>
                <el-form-item :label="t('transactions_page.date_to')">
                    <el-date-picker v-model="filters.date_to" type="date" :placeholder="t('transactions_page.end')" style="width:140px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="invoice_no" :label="t('transactions_page.cols.invoice')" width="160" />
                <el-table-column :label="t('transactions_page.cols.amount')" width="120">
                    <template #default="{ row }">¥{{ row.amount }}</template>
                </el-table-column>
                <el-table-column prop="payment_method" :label="t('transactions_page.cols.channel')" width="100">
                    <template #default="{ row }">{{ channelLabel(row.payment_method) }}</template>
                </el-table-column>
                <el-table-column prop="status" :label="t('transactions_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('transactions_page.cols.customer')" min-width="150">
                    <template #default="{ row }">{{ row.customer?.name || row.customer?.email || '-' }}</template>
                </el-table-column>
                <el-table-column prop="paid_at" :label="t('transactions_page.cols.paid_at')" width="180" />
                <el-table-column prop="created_at" :label="t('transactions_page.cols.created')" width="180" />
                <el-table-column :label="t('transactions_page.cols.actions')" width="80" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="showDetail(row)">{{ t('transactions_page.detail') }}</el-button>
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

        <el-dialog v-model="detailVisible" :title="t('transactions_page.detail_title')" width="600px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('transactions_page.cols.invoice')">{{ detail.invoice_no }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.cols.amount')">¥{{ detail.amount }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.cols.channel')">{{ channelLabel(detail.payment_method) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.cols.status')">
                        <el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.customer_name')">{{ detail.customer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.customer_email')">{{ detail.customer?.email || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.plan')">{{ detail.subscription?.plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.cols.paid_at')">{{ detail.paid_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('transactions_page.cols.created')">{{ detail.created_at }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getTransactions, getTransactionDetail } from '@/api/payment'

const { t } = useI18n()

const loading = ref(false)
const list = ref([])
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filters = ref({
    status: '',
    payment_method: '',
    search: '',
    date_from: '',
    date_to: '',
})
const detailVisible = ref(false)
const detail = ref(null)

function channelLabel(method) {
    const key = { alipay: 'alipay', wechat: 'wechat', mock: 'mock' }[method]
    if (key) return t(`transactions_page.channels.${key}`)
    return method || '-'
}

function statusLabel(status) {
    const key = { paid: 'paid', pending: 'pending', failed: 'failed', refunded: 'refunded' }[status]
    return key ? t(`transactions_page.statuses.${key}`) : status
}

function statusType(status) {
    return { paid: 'success', pending: 'warning', failed: 'danger', refunded: 'info' }[status] || ''
}

const loadData = async () => {
    loading.value = true
    try {
        const params = { ...filters.value, page: pagination.value.current_page, per_page: pagination.value.per_page }
        const res = await getTransactions(params)
        if (res.data.success) {
            list.value = res.data.data.data || []
            pagination.value = {
                current_page: res.data.data.current_page,
                last_page: res.data.data.last_page,
                per_page: res.data.data.per_page,
                total: res.data.data.total,
            }
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false }
}

const showDetail = async (row) => {
    try {
        const res = await getTransactionDetail(row.id)
        if (res.data.success) {
            detail.value = res.data.data
            detailVisible.value = true
        }
    } catch (e) { /* ignore */ }
}

onMounted(() => loadData())
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
