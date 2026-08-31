<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/transfer.js'

const { t, locale } = useI18n()

const loading = ref(false)
const stats = ref(null)
const transfers = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const detailVisible = ref(false)
const detailData = ref(null)

const typeOptions = computed(() => [
    { value: 'device_transfer', label: t('admin_transfer_page.types.device') },
    { value: 'customer_transfer', label: t('admin_transfer_page.types.customer') },
    { value: 'user_transfer', label: t('admin_transfer_page.types.user') },
])

function typeLabel(type) {
    return typeOptions.value.find(o => o.value === type)?.label || type
}

function statusLabel(status) {
    const key = { pending: 'pending', approved: 'approved', completed: 'completed', rejected: 'rejected', cancelled: 'cancelled', expired: 'expired' }[status]
    return key ? t(`admin_transfer_page.statuses.${key}`) : status
}

function statusType(status) {
    return { completed: 'success', approved: '', rejected: 'danger', pending: 'warning' }[status] || 'info'
}

async function loadStats() { try { const r = await api.stats(); stats.value = r.data.data } catch (e) {} }

async function loadTransfers(page = 1) {
    loading.value = true
    try {
        const res = await api.list({ page, per_page: 15 })
        const d = res.data.data
        transfers.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function showDetail(row) {
    try { const res = await api.show(row.id); detailData.value = res.data.data; detailVisible.value = true } catch (e) {}
}

async function approveTransfer(row) {
    try { await api.approve(row.id); ElMessage.success(t('admin_transfer_page.messages.approved')); loadTransfers(pagination.value.current_page); loadStats() } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function rejectTransfer(row) {
    try {
        const { value } = await ElMessageBox.prompt(t('admin_transfer_page.reject_prompt'), t('admin_transfer_page.reject_title'))
        if (!value) return
        await api.reject(row.id, { reason: value })
        ElMessage.success(t('admin_transfer_page.messages.rejected')); loadTransfers(pagination.value.current_page); loadStats()
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('messages.failed')) }
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(d).toLocaleString(loc)
}

onMounted(() => { loadStats(); loadTransfers() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('admin_transfer_page.breadcrumb_home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin_transfer_page.breadcrumb_license') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin_transfer_page.breadcrumb_current') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('admin_transfer_page.stats.total') }}</div><div class="stat-value">{{ stats.total }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('admin_transfer_page.stats.pending') }}</div><div class="stat-value text-warning">{{ stats.pending }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('admin_transfer_page.stats.completed') }}</div><div class="stat-value text-success">{{ stats.completed }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('admin_transfer_page.stats.rejected') }}</div><div class="stat-value">{{ stats.rejected }}</div></el-card></el-col>
            <el-col :span="8"><el-card shadow="never"><div class="stat-label">{{ t('admin_transfer_page.stats.by_type') }}</div><div class="stat-value text-sm">{{ stats.by_type?.device_transfer || 0 }} / {{ stats.by_type?.customer_transfer || 0 }} / {{ stats.by_type?.user_transfer || 0 }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <el-table :data="transfers" v-loading="loading" stripe>
                <el-table-column prop="reference" :label="t('admin_transfer_page.cols.ref')" width="140" />
                <el-table-column :label="t('admin_transfer_page.cols.type')" width="100"><template #default="{ row }">{{ typeLabel(row.type) }}</template></el-table-column>
                <el-table-column label="License" width="200">
                    <template #default="{ row }">
                        <div>{{ row.license?.license_key || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ row.license?.product_name || '' }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.requester')" width="120"><template #default="{ row }">{{ row.requester?.name || '-' }}</template></el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.status')" width="90">
                    <template #default="{ row }"><el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.target')" min-width="150"><template #default="{ row }">{{ row.target_customer?.name || row.target_device?.name || '-' }}</template></el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.reason')" width="150" show-overflow-tooltip><template #default="{ row }">{{ row.reason || '-' }}</template></el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.time')" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column :label="t('admin_transfer_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">{{ t('admin_transfer_page.detail') }}</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approveTransfer(row)">{{ t('admin_transfer_page.approve') }}</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="rejectTransfer(row)">{{ t('admin_transfer_page.reject') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadTransfers" /></div>
        </el-card>

        <el-dialog v-model="detailVisible" :title="t('admin_transfer_page.detail_title')" width="650px">
            <div v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('admin_transfer_page.cols.ref')">{{ detailData.reference }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.cols.type')">{{ typeLabel(detailData.type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.cols.status')">{{ detailData.status }}</el-descriptions-item>
                    <el-descriptions-item label="License">{{ detailData.license?.license_key }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.cols.requester')">{{ detailData.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.approver')">{{ detailData.approver?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.approved_at')">{{ fmtDate(detailData.approved_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_transfer_page.target_customer')">{{ detailData.target_customer?.name || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-3"><el-divider>{{ t('admin_transfer_page.reason_title') }}</el-divider><p class="text-sm">{{ detailData.reason || t('admin_transfer_page.none') }}</p></div>
                <div v-if="detailData.admin_notes"><el-divider>{{ t('admin_transfer_page.admin_notes') }}</el-divider><p class="text-sm">{{ detailData.admin_notes }}</p></div>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
</style>
