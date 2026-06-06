<template>
    <div class="deletion-page">
        <el-tabs v-model="activeTab">
            <!-- 待处理申请 -->
            <el-tab-pane label="待处理申请" name="pending">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>待处理的账号注销申请</span>
                            <el-tag type="danger">待处理 {{ stats.pending || 0 }}</el-tag>
                        </div>
                    </template>

                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">待处理</div>
                                    <div class="stat-value text-warning">{{ stats.pending || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">可执行（已过冷静期）</div>
                                    <div class="stat-value text-danger">{{ stats.pending_cooling_over || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">已完成</div>
                                    <div class="stat-value">{{ stats.completed || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">已拒绝</div>
                                    <div class="stat-value">{{ stats.rejected || 0 }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-table :data="pendingItems" v-loading="loadingPending" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="用户" min-width="180">
                            <template #default="{ row }">
                                <div>{{ row.user?.name || '-' }}</div>
                                <div class="text-muted">{{ row.user?.email || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="注销原因" min-width="200">
                            <template #default="{ row }">
                                {{ row.reason || '未提供' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="冷静期结束" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.cooling_until) }}
                                <el-tag
                                    v-if="row.isCoolingOver !== undefined ? row.isCoolingOver : isCoolingOver(row)"
                                    type="success"
                                    size="small"
                                    class="ml-1"
                                >
                                    已过
                                </el-tag>
                                <el-tag v-else type="info" size="small" class="ml-1">等待中</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="申请时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-popconfirm
                                    title="确认执行账号注销？此操作不可撤销！"
                                    @confirm="handleApprove(row)"
                                >
                                    <template #reference>
                                        <el-button
                                            type="danger"
                                            size="small"
                                            :disabled="!(row.isCoolingOver !== undefined ? row.isCoolingOver : isCoolingOver(row))"
                                        >
                                            执行注销
                                        </el-button>
                                    </template>
                                </el-popconfirm>
                                <el-popconfirm
                                    title="确定拒绝此注销申请？"
                                    @confirm="handleReject(row)"
                                >
                                    <template #reference>
                                        <el-button size="small" type="info" plain class="ml-1">拒绝</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingPending && !pendingItems.length" description="暂无待处理的注销申请" />
                </el-card>
            </el-tab-pane>

            <!-- 历史记录 -->
            <el-tab-pane label="历史记录" name="history">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>注销请求历史</span>
                            <el-select v-model="historyFilter" placeholder="全部状态" clearable style="width: 140px" @change="fetchHistory">
                                <el-option label="全部" value="" />
                                <el-option label="已完成" value="completed" />
                                <el-option label="已拒绝" value="rejected" />
                                <el-option label="已取消" value="cancelled" />
                                <el-option label="待处理" value="pending" />
                            </el-select>
                        </div>
                    </template>

                    <el-table :data="historyItems" v-loading="loadingHistory" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="用户" min-width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="原因" min-width="150" prop="reason" />
                        <el-table-column label="管理员备注" min-width="150" prop="admin_notes" />
                        <el-table-column label="处理时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.processed_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="申请时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingHistory && !historyItems.length" description="暂无历史记录" />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
    getPendingDeletions,
    getDeletionHistory,
    approveDeletion,
    rejectDeletion,
    getDeletionStats,
} from '@/api/account-deletion'

const activeTab = ref('pending')

// 统计
const stats = ref({})

// 待处理
const pendingItems = ref([])
const loadingPending = ref(false)

// 历史
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
        ElMessage.error('获取待处理申请失败')
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
        ElMessage.error('获取历史记录失败')
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
        ElMessage.success('账号已注销')
        fetchPending()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '执行失败')
    }
}

async function handleReject(row) {
    try {
        await rejectDeletion(row.id)
        ElMessage.success('已拒绝注销申请')
        fetchPending()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

function statusType(status) {
    const map = { pending: 'warning', completed: 'danger', rejected: 'info', cancelled: 'default' }
    return map[status] || 'info'
}

function statusLabel(status) {
    const map = { pending: '待处理', completed: '已注销', rejected: '已拒绝', cancelled: '已取消' }
    return map[status] || status
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
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
