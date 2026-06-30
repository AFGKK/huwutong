<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
    getAdminNotificationPreferences,
    getNotificationPreferenceStats,
    getUserNotificationPreferences,
} from '../../api/notificationPreference.js'

const preferences = ref([])
const stats = ref(null)
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const loading = ref(false)
const detailLoading = ref(false)

const filters = ref({
    channel: '',
    category: '',
    enabled: '',
})

const detailDialogVisible = ref(false)
const detailUserId = ref(null)
const detailPreferences = ref([])
const detailChannels = ref([])

const channelOptions = [
    { value: 'mail', label: '邮件' },
    { value: 'sms', label: '短信' },
    { value: 'database', label: '站内信' },
]

const categoryOptions = [
    { value: 'license_expiry', label: 'License到期' },
    { value: 'invoice', label: '发票/账单' },
    { value: 'payment', label: '支付' },
    { value: 'security', label: '安全' },
    { value: 'system', label: '系统公告' },
    { value: 'promotion', label: '营销推广' },
    { value: 'commission', label: '佣金' },
]

const categoryLabels = categoryOptions.reduce((m, o) => { m[o.value] = o.label; return m }, {})

async function loadStats() {
    try {
        const res = await getNotificationPreferenceStats()
        stats.value = res.data
    } catch (e) {
        console.error('Failed to load stats:', e)
    }
}

async function loadPreferences(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page }
        const res = await getAdminNotificationPreferences(params)
        preferences.value = res.data.data || []
        pagination.value = {
            total: res.data.total || 0,
            current_page: res.data.current_page || page,
            per_page: res.data.per_page || 15,
        }
    } catch (e) {
        console.error('Failed to load preferences:', e)
    } finally {
        loading.value = false
    }
}

function handleSearch() { loadPreferences(1) }
function resetFilters() {
    filters.value = { channel: '', category: '', enabled: '' }
    loadPreferences(1)
}

async function openUserDetail(userId) {
    detailUserId.value = userId
    detailDialogVisible.value = true
    detailLoading.value = true
    try {
        const res = await getUserNotificationPreferences(userId)
        detailPreferences.value = res.data.preferences || []
        detailChannels.value = res.data.channels || []
    } catch (e) {
        ElMessage.error('获取用户偏好失败')
    } finally {
        detailLoading.value = false
    }
}

function channelLabel(ch) {
    const map = { mail: '邮件', sms: '短信', database: '站内信' }
    return map[ch] || ch
}

function handlePageChange(page) { loadPreferences(page) }

onMounted(() => {
    loadStats()
    loadPreferences()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>系统管理</el-breadcrumb-item>
            <el-breadcrumb-item>通知偏好管理</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <span class="text-lg font-semibold">🔔 通知偏好管理</span>
            </template>

            <!-- 统计 -->
            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">总用户数</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.total_users || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">已设置偏好</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.users_with_preferences || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">覆盖率</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.coverage_percentage || 0 }}%</div>
                    </el-card>
                </el-col>
                <el-col :span="6" v-if="stats.channels">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">邮件/短信/站内信启用</div>
                        <div class="text-2xl font-bold mt-1 flex gap-2">
                            <span>{{ stats.channels.mail?.enabled || 0 }}</span>/
                            <span>{{ stats.channels.sms?.enabled || 0 }}</span>/
                            <span>{{ stats.channels.database?.enabled || 0 }}</span>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 筛选 -->
            <div class="flex gap-3 mb-4 flex-wrap items-center">
                <el-select v-model="filters.channel" placeholder="按渠道筛选" clearable style="width:140px">
                    <el-option v-for="o in channelOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-input v-model="filters.search" placeholder="搜索用户(名称/邮箱)" clearable style="width:220px" />
                <el-button type="primary" @click="handleSearch">搜索</el-button>
                <el-button @click="resetFilters">重置</el-button>
            </div>

            <!-- 列表 -->
            <el-table :data="preferences" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column label="用户" min-width="180">
                    <template #default="{ row }">
                        <span v-if="row.user">{{ row.user.name || row.user.email }}</span>
                        <span v-else class="text-gray-400">#{{ row.user_id }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="通知渠道" width="120">
                    <template #default="{ row }">
                        <div class="flex gap-1 flex-wrap">
                            <el-tag v-if="row.channels?.mail !== false" size="small" type="success">邮件</el-tag>
                            <el-tag v-else size="small" type="info">无邮件</el-tag>
                            <el-tag v-if="row.channels?.sms" size="small" type="success">短信</el-tag>
                            <el-tag v-if="row.channels?.database !== false" size="small" type="success">站内信</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="免打扰" width="100">
                    <template #default="{ row }">
                        <template v-if="row.quiet_hours_start">
                            <el-tag size="small">{{ row.quiet_hours_start }}-{{ row.quiet_hours_end }}</el-tag>
                        </template>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="摘要频率" width="100">
                    <template #default="{ row }">
                        {{ { none: '无', daily: '每日', weekly: '每周', monthly: '每月' }[row.digest_frequency] || row.digest_frequency }}
                    </template>
                </el-table-column>
                <el-table-column label="时区" width="100">
                    <template #default="{ row }">{{ row.timezone || 'Asia/Shanghai' }}</template>
                </el-table-column>
                <el-table-column label="创建时间" width="160">
                    <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString('zh-CN') : '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120">
                    <template #default="{ row }">
                        <el-button size="small" @click="openUserDetail(row.user_id)">用户详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="flex justify-center mt-4">
                <el-pagination v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page" :total="pagination.total"
                    layout="prev, pager, next, total" @current-change="handlePageChange" />
            </div>
        </el-card>

        <!-- 用户详情对话框 -->
        <el-dialog v-model="detailDialogVisible" title="用户通知偏好详情" width="700px">
            <div v-loading="detailLoading">
                <div v-if="detailChannels.length" class="mb-4">
                    <span class="font-semibold text-sm">可用渠道:</span>
                    <el-tag v-for="ch in detailChannels" :key="ch.channel" :type="ch.verified ? 'success' : 'warning'" class="ml-2">
                        {{ channelLabel(ch.channel) }} ({{ ch.description }})
                    </el-tag>
                </div>
                <el-table :data="detailPreferences" stripe>
                    <el-table-column label="渠道" width="100">
                        <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
                    </el-table-column>
                    <el-table-column label="分类" width="150">
                        <template #default="{ row }">{{ categoryLabels[row.category] || row.category }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.enabled ? 'success' : 'info'" size="small">{{ row.enabled ? '开' : '关' }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
</style>
