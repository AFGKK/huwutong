<template>
    <div class="time-restriction-page">
        <div class="page-header">
            <h2><el-icon style="vertical-align:middle;margin-right:8px"><Clock /></el-icon>{{ t('time_restriction_page.title') }}</h2>
            <p class="text-muted">{{ t('time_restriction_page.subtitle') }}</p>
            <div class="header-actions">
                <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('time_restriction_page.refresh') }}</el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('time_restriction_page.stats.total_configs') }}</div>
                    <div class="stat-value">{{ stats.total_configs }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">{{ t('time_restriction_page.stats.active_configs') }}</div>
                    <div class="stat-value">{{ stats.active_configs }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">{{ t('time_restriction_page.stats.today_checks') }}</div>
                    <div class="stat-value">{{ stats.today_checks }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-danger">
                    <div class="stat-label">{{ t('time_restriction_page.stats.today_denials') }}</div>
                    <div class="stat-value">{{ stats.today_denials }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 标签页 -->
        <el-card shadow="hover">
            <el-tabs v-model="activeTab">
                <!-- 配置列表 -->
                <el-tab-pane :label="t('time_restriction_page.tabs.configs')" name="configs">
                    <div class="tab-toolbar">
                        <el-input v-model="search" :placeholder="t('time_restriction_page.search_ph')" clearable style="width:240px" @clear="loadConfigs" @keyup.enter="loadConfigs" />
                        <el-select v-model="filterActive" :placeholder="t('time_restriction_page.filter_status_ph')" clearable style="width:120px;margin-left:8px" @change="loadConfigs">
                            <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </div>
                    <el-table :data="configs" stripe v-loading="configsLoading">
                        <el-table-column :label="t('time_restriction_page.cols.id')" width="60" prop="id" />
                        <el-table-column :label="t('time_restriction_page.cols.restrictable')" min-width="200">
                            <template #default="{ row }">
                                <div class="restrictable-info">
                                    <el-tag size="small" type="info">{{ row.restrictable_type_label }}</el-tag>
                                    <span class="ml-1">#{{ row.restrictable_id }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.timezone')" width="140" prop="timezone" />
                        <el-table-column :label="t('time_restriction_page.cols.weekly_schedule')" min-width="200">
                            <template #default="{ row }">
                                <div v-if="row.summary?.weekly_schedule?.length" class="schedule-summary">
                                    <el-tag v-for="s in row.summary.weekly_schedule" :key="s" size="small" class="mr-1">
                                        {{ s }}
                                    </el-tag>
                                </div>
                                <span v-else class="no-data">{{ t('time_restriction_page.not_configured') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.holidays')" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.summary?.holiday_count" type="warning" size="small">
                                    {{ t('time_restriction_page.holiday_days', { n: row.summary.holiday_count }) }}
                                </el-tag>
                                <span v-else class="no-data">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.out_of_hours_action')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="actionTagType(row.out_of_hours_action)" size="small">
                                    {{ actionLabel(row.out_of_hours_action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.description')" min-width="120" show-overflow-tooltip prop="description" />
                        <el-table-column :label="t('time_restriction_page.cols.actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button type="primary" link size="small" @click="viewLicense(row)">{{ t('time_restriction_page.view_license') }}</el-button>
                                <el-popconfirm :title="t('time_restriction_page.delete_confirm')" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button type="danger" link size="small">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 检查日志 -->
                <el-tab-pane :label="t('time_restriction_page.tabs.logs')" name="logs">
                    <el-table :data="logs" stripe v-loading="logsLoading" max-height="600">
                        <el-table-column :label="t('time_restriction_page.cols.time')" width="170">
                            <template #default="{ row }">{{ row.checked_at || row.created_at }}</template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.license_id')" width="100" prop="license_id" />
                        <el-table-column :label="t('time_restriction_page.cols.result')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'allowed' ? 'success' : row.result === 'denied' ? 'danger' : 'warning'" size="small">
                                    {{ resultLabel(row.result) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('time_restriction_page.cols.reason')" min-width="200" prop="reason" show-overflow-tooltip />
                        <el-table-column :label="t('time_restriction_page.cols.ip')" width="140" prop="ip_address" />
                        <el-table-column :label="t('time_restriction_page.cols.timezone')" width="140" prop="timezone_used" />
                    </el-table>
                    <div v-if="logPagination.total > 0" class="pagination-footer">
                        <el-pagination
                            v-model:current-page="logPage"
                            :page-size="20"
                            :total="logPagination.total"
                            layout="total, prev, pager, next"
                            @current-change="loadLogs"
                            small
                        />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Clock, Refresh } from '@element-plus/icons-vue'
import api from '../../api/timeRestriction'

const { t } = useI18n()

const loading = ref(false)
const activeTab = ref('configs')
const search = ref('')
const filterActive = ref('')

const statusFilterOptions = computed(() => [
    { label: t('time_restriction_page.filter_all'), value: '' },
    { label: t('time_restriction_page.filter_enabled'), value: '1' },
    { label: t('time_restriction_page.filter_disabled'), value: '0' },
])

// ─── 统计 ───
const stats = reactive({
    total_configs: 0,
    active_configs: 0,
    today_checks: 0,
    today_denials: 0,
})

// ─── 配置列表 ───
const configs = ref([])
const configsLoading = ref(false)

async function loadConfigs() {
    configsLoading.value = true
    try {
        const res = await api.listAll({
            search: search.value || undefined,
            is_active: filterActive.value || undefined,
        })
        configs.value = res.data?.data || []
    } catch (e) {
        console.error(e)
    } finally {
        configsLoading.value = false
    }
}

async function loadStats() {
    try {
        const res = await api.getStats()
        Object.assign(stats, res.data || {})
    } catch (e) {
        console.error(e)
    }
}

// ─── 日志 ───
const logs = ref([])
const logsLoading = ref(false)
const logPage = ref(1)
const logPagination = reactive({ total: 0 })

async function loadLogs() {
    logsLoading.value = true
    try {
        const res = await api.getLogs({ page: logPage.value, per_page: 20 })
        const d = res.data?.data
        if (Array.isArray(d)) {
            logs.value = d
        } else if (d?.data) {
            logs.value = d.data
            logPagination.total = d.total || 0
        } else {
            logs.value = []
        }
    } catch (e) {
        console.error(e)
    } finally {
        logsLoading.value = false
    }
}

// ─── 操作 ───

function viewLicense(row) {
    // Navigate to License detail page in a new tab
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '')
    window.open(`${baseUrl}/licenses/${row.restrictable_id}`, '_blank')
}

async function handleDelete(row) {
    try {
        await api.deleteConfig(row.restrictable_id)
        ElMessage.success(t('time_restriction_page.messages.config_deleted'))
        loadConfigs()
        loadStats()
    } catch (e) {
        ElMessage.error(t('time_restriction_page.messages.delete_failed'))
    }
}

function actionTagType(action) {
    return { deny: 'danger', grace: 'warning', warn: 'info' }[action] || 'info'
}

function actionLabel(action) {
    const map = {
        deny: t('time_restriction_page.out_of_hours.deny'),
        grace: t('time_restriction_page.out_of_hours.grace'),
        warn: t('time_restriction_page.out_of_hours.warn'),
    }
    return map[action] || action
}

function resultLabel(result) {
    const map = {
        allowed: t('time_restriction_page.result.allowed'),
        denied: t('time_restriction_page.result.denied'),
        grace: t('time_restriction_page.result.grace'),
    }
    return map[result] || result
}

function refreshAll() {
    loading.value = true
    Promise.all([loadStats(), loadConfigs(), loadLogs()])
        .finally(() => { loading.value = false })
}

onMounted(() => {
    refreshAll()
})
</script>

<style scoped>
.time-restriction-page {
    padding: 20px;
}
.page-header {
    margin-bottom: 20px;
}
.page-header h2 {
    margin: 0 0 4px;
    font-size: 22px;
}
.text-muted {
    color: #909399;
    font-size: 13px;
    margin: 0 0 12px;
}
.header-actions {
    display: flex;
    gap: 8px;
}
.mb-4 {
    margin-bottom: 16px;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 24px;
    font-weight: 700;
}
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }
.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}
.restrictable-info {
    display: flex;
    align-items: center;
    gap: 4px;
}
.ml-1 { margin-left: 4px; }
.mr-1 { margin-right: 4px; }
.schedule-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.no-data {
    color: #c0c4cc;
}
.pagination-footer {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
</style>
