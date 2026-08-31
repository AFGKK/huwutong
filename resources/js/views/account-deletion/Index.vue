<template>
    <div class="deletion-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('account_deletion_page.tabs.pending')" name="pending">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('account_deletion_page.pending_title') }}</span>
                            <el-tag type="danger">{{ t('account_deletion_page.pending_n', { n: stats.pending || 0 }) }}</el-tag>
                        </div>
                    </template>

                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('account_deletion_page.stats.pending') }}</div>
                                    <div class="stat-value text-warning">{{ stats.pending || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('account_deletion_page.stats.cooling_over') }}</div>
                                    <div class="stat-value text-danger">{{ stats.pending_cooling_over || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('account_deletion_page.stats.completed') }}</div>
                                    <div class="stat-value">{{ stats.completed || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('account_deletion_page.stats.rejected') }}</div>
                                    <div class="stat-value">{{ stats.rejected || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-table :data="pendingItems" v-loading="loadingPending" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t('account_deletion_page.cols.user')" min-width="180">
                            <template #default="{ row }">
                                <div>{{ row.user?.name || '-' }}</div>
                                <div class="text-muted">{{ row.user?.email || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.reason')" min-width="200">
                            <template #default="{ row }">
                                {{ row.reason || t('account_deletion_page.no_reason') }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.cooling_until')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.cooling_until) }}
                                <el-tag
                                    v-if="row.isCoolingOver !== undefined ? row.isCoolingOver : isCoolingOver(row)"
                                    type="success"
                                    size="small"
                                    class="ml-1"
                                >
                                    {{ t('account_deletion_page.cooling_done') }}
                                </el-tag>
                                <el-tag v-else type="info" size="small" class="ml-1">{{ t('account_deletion_page.cooling_wait') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.applied_at')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-popconfirm
                                    :title="t('account_deletion_page.confirm_approve')"
                                    @confirm="handleApprove(row)"
                                >
                                    <template #reference>
                                        <el-button
                                            type="danger"
                                            size="small"
                                            :disabled="!(row.isCoolingOver !== undefined ? row.isCoolingOver : isCoolingOver(row))"
                                        >
                                            {{ t('account_deletion_page.approve') }}
                                        </el-button>
                                    </template>
                                </el-popconfirm>
                                <el-popconfirm
                                    :title="t('account_deletion_page.confirm_reject')"
                                    @confirm="handleReject(row)"
                                >
                                    <template #reference>
                                        <el-button size="small" type="info" plain class="ml-1">{{ t('actions.reject') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingPending && !pendingItems.length" :description="t('account_deletion_page.empty_pending')" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('account_deletion_page.tabs.history')" name="history">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('account_deletion_page.history_title') }}</span>
                            <el-select v-model="historyFilter" :placeholder="t('account_deletion_page.filter_all')" clearable style="width: 140px" @change="fetchHistory">
                                <el-option :label="t('account_deletion_page.all')" value="" />
                                <el-option :label="t('account_deletion_page.statuses.completed')" value="completed" />
                                <el-option :label="t('account_deletion_page.statuses.rejected')" value="rejected" />
                                <el-option :label="t('account_deletion_page.statuses.cancelled')" value="cancelled" />
                                <el-option :label="t('account_deletion_page.statuses.pending')" value="pending" />
                            </el-select>
                        </div>
                    </template>

                    <el-table :data="historyItems" v-loading="loadingHistory" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t('account_deletion_page.cols.user')" min-width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.reason')" min-width="150" prop="reason" />
                        <el-table-column :label="t('account_deletion_page.cols.admin_notes')" min-width="150" prop="admin_notes" />
                        <el-table-column :label="t('account_deletion_page.cols.processed_at')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.processed_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('account_deletion_page.cols.applied_at')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingHistory && !historyItems.length" :description="t('account_deletion_page.empty_history')" />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
    getPendingDeletions,
    getDeletionHistory,
    approveDeletion,
    rejectDeletion,
    getDeletionStats,
} from '@/api/account-deletion'

const { t, locale } = useI18n()
const activeTab = ref('pending')

const stats = ref({})
const pendingItems = ref([])
const loadingPending = ref(false)
const historyItems = ref([])
const loadingHistory = ref(false)
const historyFilter = ref('')

function isCoolingOver(row) {
    if (!row.cooling_until) return false
    return new Date(row.cooling_until) < new Date()
}

async function fetchPending() {
    loadingPending.value = true
    try {
        const res = await getPendingDeletions({ per_page: 50 })
        pendingItems.value = res.data?.data?.data || []
    } catch (e) {
        ElMessage.error(t('account_deletion_page.messages.load_pending_failed'))
    } finally {
        loadingPending.value = false
    }
}

async function fetchHistory() {
    loadingHistory.value = true
    try {
        const params = { per_page: 50 }
        if (historyFilter.value) params.status = historyFilter.value
        const res = await getDeletionHistory(params)
        historyItems.value = res.data?.data?.data || []
    } catch (e) {
        ElMessage.error(t('account_deletion_page.messages.load_history_failed'))
    } finally {
        loadingHistory.value = false
    }
}

async function fetchStats() {
    try {
        const res = await getDeletionStats()
        stats.value = res.data?.data || {}
    } catch {
        stats.value = {}
    }
}

async function handleApprove(row) {
    try {
        await approveDeletion(row.id)
        ElMessage.success(t('account_deletion_page.messages.approved'))
        fetchPending()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('account_deletion_page.messages.approve_failed'))
    }
}

async function handleReject(row) {
    try {
        await rejectDeletion(row.id)
        ElMessage.success(t('account_deletion_page.messages.rejected'))
        fetchPending()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

function statusType(status) {
    const map = { pending: 'warning', completed: 'danger', rejected: 'info', cancelled: 'default' }
    return map[status] || 'info'
}

function statusLabel(status) {
    const key = { pending: 'pending', completed: 'completed_done', rejected: 'rejected', cancelled: 'cancelled' }[status]
    return key ? t(`account_deletion_page.statuses.${key}`) : status
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    })
}

onMounted(() => {
    fetchPending()
    fetchStats()
})
</script>

<style scoped>
.deletion-page {
    max-width: 1200px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.mb-4 {
    margin-bottom: 16px;
}

.ml-1 {
    margin-left: 4px;
}

.text-muted {
    color: #999;
    font-size: 12px;
}

.text-warning {
    color: var(--el-color-warning);
}

.text-danger {
    color: var(--el-color-danger);
}

.stat-item {
    text-align: center;
    padding: 8px 0;
}

.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
</style>
