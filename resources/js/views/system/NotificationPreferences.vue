<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
    getAdminNotificationPreferences,
    getNotificationPreferenceStats,
    getUserNotificationPreferences,
} from '../../api/notificationPreference.js'

const { t, locale } = useI18n()

const preferences = ref([])
const stats = ref(null)
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const loading = ref(false)
const detailLoading = ref(false)

const filters = ref({
    channel: '',
    category: '',
    enabled: '',
    search: '',
})

const detailDialogVisible = ref(false)
const detailUserId = ref(null)
const detailPreferences = ref([])
const detailChannels = ref([])

const channelOptions = computed(() => [
    { value: 'mail', label: t('notification_prefs_page.channels.mail') },
    { value: 'sms', label: t('notification_prefs_page.channels.sms') },
    { value: 'database', label: t('notification_prefs_page.channels.database') },
])

const categoryOptions = computed(() => [
    { value: 'license_expiry', label: t('notification_prefs_page.categories.license_expiry') },
    { value: 'invoice', label: t('notification_prefs_page.categories.invoice') },
    { value: 'payment', label: t('notification_prefs_page.categories.payment') },
    { value: 'security', label: t('notification_prefs_page.categories.security') },
    { value: 'system', label: t('notification_prefs_page.categories.system') },
    { value: 'promotion', label: t('notification_prefs_page.categories.promotion') },
    { value: 'commission', label: t('notification_prefs_page.categories.commission') },
])

const categoryLabels = computed(() =>
    categoryOptions.value.reduce((m, o) => { m[o.value] = o.label; return m }, {})
)

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
    filters.value = { channel: '', category: '', enabled: '', search: '' }
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
        ElMessage.error(t('notification_prefs_page.messages.detail_failed'))
    } finally {
        detailLoading.value = false
    }
}

function channelLabel(ch) {
    const key = { mail: 'mail', sms: 'sms', database: 'database' }[ch]
    return key ? t(`notification_prefs_page.channels.${key}`) : ch
}

function digestLabel(freq) {
    const key = { none: 'none', daily: 'daily', weekly: 'weekly', monthly: 'monthly' }[freq]
    return key ? t(`notification_prefs_page.digest.${key}`) : freq
}

function formatTime(time) {
    if (!time) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(time).toLocaleString(loc)
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
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('notification_prefs_page.breadcrumb_home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('notification_prefs_page.breadcrumb_system') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('notification_prefs_page.breadcrumb_current') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <span class="text-lg font-semibold">{{ t('notification_prefs_page.title') }}</span>
            </template>

            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ t('notification_prefs_page.stats.users') }}</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.total_users || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ t('notification_prefs_page.stats.configured') }}</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.users_with_preferences || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ t('notification_prefs_page.stats.coverage') }}</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.coverage_percentage || 0 }}%</div>
                    </el-card>
                </el-col>
                <el-col :span="6" v-if="stats.channels">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ t('notification_prefs_page.stats.channel_enabled') }}</div>
                        <div class="text-2xl font-bold mt-1 flex gap-2">
                            <span>{{ stats.channels.mail?.enabled || 0 }}</span>/
                            <span>{{ stats.channels.sms?.enabled || 0 }}</span>/
                            <span>{{ stats.channels.database?.enabled || 0 }}</span>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <div class="flex gap-3 mb-4 flex-wrap items-center">
                <el-select v-model="filters.channel" :placeholder="t('notification_prefs_page.filter_channel')" clearable style="width:140px">
                    <el-option v-for="o in channelOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-input v-model="filters.search" :placeholder="t('notification_prefs_page.search_ph')" clearable style="width:220px" />
                <el-button type="primary" @click="handleSearch">{{ t('actions.search') }}</el-button>
                <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
            </div>

            <el-table :data="preferences" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column :label="t('notification_prefs_page.cols.user')" min-width="180">
                    <template #default="{ row }">
                        <span v-if="row.user">{{ row.user.name || row.user.email }}</span>
                        <span v-else class="text-gray-400">#{{ row.user_id }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.channels')" width="120">
                    <template #default="{ row }">
                        <div class="flex gap-1 flex-wrap">
                            <el-tag v-if="row.channels?.mail !== false" size="small" type="success">{{ t('notification_prefs_page.channels.mail') }}</el-tag>
                            <el-tag v-else size="small" type="info">{{ t('notification_prefs_page.no_mail') }}</el-tag>
                            <el-tag v-if="row.channels?.sms" size="small" type="success">{{ t('notification_prefs_page.channels.sms') }}</el-tag>
                            <el-tag v-if="row.channels?.database !== false" size="small" type="success">{{ t('notification_prefs_page.channels.database') }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.quiet')" width="100">
                    <template #default="{ row }">
                        <template v-if="row.quiet_hours_start">
                            <el-tag size="small">{{ row.quiet_hours_start }}-{{ row.quiet_hours_end }}</el-tag>
                        </template>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.digest')" width="100">
                    <template #default="{ row }">
                        {{ digestLabel(row.digest_frequency) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.timezone')" width="100">
                    <template #default="{ row }">{{ row.timezone || 'Asia/Shanghai' }}</template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.created')" width="160">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('notification_prefs_page.cols.actions')" width="120">
                    <template #default="{ row }">
                        <el-button size="small" @click="openUserDetail(row.user_id)">{{ t('notification_prefs_page.user_detail') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="flex justify-center mt-4">
                <el-pagination v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page" :total="pagination.total"
                    layout="prev, pager, next, total" @current-change="handlePageChange" />
            </div>
        </el-card>

        <el-dialog v-model="detailDialogVisible" :title="t('notification_prefs_page.detail_title')" width="700px">
            <div v-loading="detailLoading">
                <div v-if="detailChannels.length" class="mb-4">
                    <span class="font-semibold text-sm">{{ t('notification_prefs_page.available_channels') }}</span>
                    <el-tag v-for="ch in detailChannels" :key="ch.channel" :type="ch.verified ? 'success' : 'warning'" class="ml-2">
                        {{ channelLabel(ch.channel) }} ({{ ch.description }})
                    </el-tag>
                </div>
                <el-table :data="detailPreferences" stripe>
                    <el-table-column :label="t('notification_prefs_page.cols.channel')" width="100">
                        <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('notification_prefs_page.cols.category')" width="150">
                        <template #default="{ row }">{{ categoryLabels[row.category] || row.category }}</template>
                    </el-table-column>
                    <el-table-column :label="t('notification_prefs_page.cols.status')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.enabled ? 'success' : 'info'" size="small">{{ row.enabled ? t('notification_prefs_page.on') : t('notification_prefs_page.off') }}</el-tag>
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
