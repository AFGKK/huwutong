<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getBugReports, getBugReportDetail, getBugBountyStats,
    reviewBugReport, confirmBugReport, markBugFixed,
    declineBugReport, markBugPaid, deleteBugReport,
    getHallOfFame, updateHallOfFameEntry,
} from '../../api/bugBounty.js'

const { t, locale } = useI18n()

// ─── 数据 ───
const activeTab = ref('reports')
const stats = ref(null)
const reports = ref([])
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const hallOfFame = ref([])
const loading = ref(false)
const detailLoading = ref(false)

// 筛选
const filters = ref({
    status: '',
    severity: '',
    search: '',
})

// 对话框
const detailVisible = ref(false)
const currentReport = ref(null)
const reviewDialogVisible = ref(false)
const confirmDialogVisible = ref(false)
const declineDialogVisible = ref(false)
const honorDialogVisible = ref(false)
const currentHonorEntry = ref(null)

// 表单
const reviewForm = ref({ assigned_to: '' })
const confirmForm = ref({ severity: '', bounty_amount: null, is_public: true, resolution_notes: '' })
const declineForm = ref({ reason: '' })

const SEVERITY_COLORS = {
    critical: '#e74c3c',
    high: '#e67e22',
    medium: '#f1c40f',
    low: '#3498db',
    informational: '#95a5a6',
}

const STATUS_TYPES = {
    submitted: 'info',
    under_review: 'warning',
    confirmed: 'danger',
    fixed: 'success',
    declined: 'info',
    paid: 'success',
}

const severityOptions = computed(() => [
    { value: 'critical', label: t('bug_bounty_page.severity.critical'), color: SEVERITY_COLORS.critical },
    { value: 'high', label: t('bug_bounty_page.severity.high'), color: SEVERITY_COLORS.high },
    { value: 'medium', label: t('bug_bounty_page.severity.medium'), color: SEVERITY_COLORS.medium },
    { value: 'low', label: t('bug_bounty_page.severity.low'), color: SEVERITY_COLORS.low },
    { value: 'informational', label: t('bug_bounty_page.severity.informational'), color: SEVERITY_COLORS.informational },
])

const statusOptions = computed(() => [
    { value: 'submitted', label: t('bug_bounty_page.status.submitted'), type: STATUS_TYPES.submitted },
    { value: 'under_review', label: t('bug_bounty_page.status.under_review'), type: STATUS_TYPES.under_review },
    { value: 'confirmed', label: t('bug_bounty_page.status.confirmed'), type: STATUS_TYPES.confirmed },
    { value: 'fixed', label: t('bug_bounty_page.status.fixed'), type: STATUS_TYPES.fixed },
    { value: 'declined', label: t('bug_bounty_page.status.declined'), type: STATUS_TYPES.declined },
    { value: 'paid', label: t('bug_bounty_page.status.paid'), type: STATUS_TYPES.paid },
])

const severityMap = computed(() => severityOptions.value.reduce((m, o) => { m[o.value] = o; return m }, {}))
const statusMap = computed(() => statusOptions.value.reduce((m, o) => { m[o.value] = o; return m }, {}))

const rankOptions = computed(() => [
    { value: 'gold', label: t('bug_bounty_page.rank.gold') },
    { value: 'silver', label: t('bug_bounty_page.rank.silver') },
    { value: 'bronze', label: t('bug_bounty_page.rank.bronze') },
    { value: 'honorable', label: t('bug_bounty_page.rank.honorable') },
])

const rankMap = computed(() => rankOptions.value.reduce((m, o) => { m[o.value] = o; return m }, {}))

// ─── 计算属性 ───
const openCount = computed(() => {
    if (!stats.value) return 0
    const s = stats.value.by_status || {}
    return (s.submitted?.count || 0) + (s.under_review?.count || 0)
})

const statsData = computed(() => {
    if (!stats.value) return []
    return [
        { key: 'total', label: t('bug_bounty_page.stats.total_reports'), value: stats.value.total, color: '#0f172a' },
        { key: 'pending', label: t('bug_bounty_page.stats.pending'), value: openCount.value, color: '#e6a23c' },
        { key: 'paid', label: t('bug_bounty_page.stats.bounty_paid'), value: `$${stats.value.total_bounty_paid || 0}`, color: '#67c23a' },
        { key: 'pending_pay', label: t('bug_bounty_page.stats.bounty_pending'), value: `$${stats.value.total_bounty_pending || 0}`, color: '#f56c6c' },
    ]
})

// ─── 方法 ───
async function loadStats() {
    try {
        const res = await getBugBountyStats()
        stats.value = res.data
    } catch (e) {
        console.error('Failed to load stats:', e)
    }
}

async function loadReports(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page }
        const res = await getBugReports(params)
        reports.value = res.data.data || []
        pagination.value = {
            total: res.data.total || 0,
            current_page: res.data.current_page || page,
            per_page: res.data.per_page || 15,
        }
    } catch (e) {
        console.error('Failed to load reports:', e)
    } finally {
        loading.value = false
    }
}

async function loadHallOfFame() {
    try {
        const res = await getHallOfFame()
        hallOfFame.value = res.data || []
    } catch (e) {
        console.error('Failed to load hall of fame:', e)
    }
}

function handleSearch() {
    loadReports(1)
}

function resetFilters() {
    filters.value = { status: '', severity: '', search: '' }
    loadReports(1)
}

// ─── 详情 ───
async function openDetail(id) {
    detailLoading.value = true
    detailVisible.value = true
    try {
        const res = await getBugReportDetail(id)
        currentReport.value = res.data
    } catch (e) {
        ElMessage.error(t('bug_bounty_page.messages.load_detail_failed'))
    } finally {
        detailLoading.value = false
    }
}

// ─── 审核 ───
function openReview(report) {
    reviewForm.value = { assigned_to: '' }
    currentReport.value = report
    reviewDialogVisible.value = true
}

async function doReview() {
    try {
        await reviewBugReport(currentReport.value.id, reviewForm.value)
        ElMessage.success(t('bug_bounty_page.messages.marked_under_review'))
        reviewDialogVisible.value = false
        loadReports(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 确认 ───
function openConfirm(report) {
    confirmForm.value = {
        severity: report.severity,
        bounty_amount: null,
        is_public: true,
        resolution_notes: '',
    }
    currentReport.value = report
    confirmDialogVisible.value = true
}

async function doConfirm() {
    try {
        await confirmBugReport(currentReport.value.id, confirmForm.value)
        ElMessage.success(t('bug_bounty_page.messages.confirmed'))
        confirmDialogVisible.value = false
        loadReports(pagination.value.current_page)
        loadHallOfFame()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 修复 ───
async function handleMarkFixed(id) {
    try {
        await ElMessageBox.confirm(t('bug_bounty_page.confirm.mark_fixed'), t('actions.confirm'))
        await markBugFixed(id)
        ElMessage.success(t('bug_bounty_page.messages.marked_fixed'))
        loadReports(pagination.value.current_page)
        loadHallOfFame()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 拒绝 ───
function openDecline(report) {
    declineForm.value = { reason: '' }
    currentReport.value = report
    declineDialogVisible.value = true
}

async function doDecline() {
    try {
        await declineBugReport(currentReport.value.id, declineForm.value)
        ElMessage.success(t('bug_bounty_page.messages.declined'))
        declineDialogVisible.value = false
        loadReports(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 打款 ───
async function handleMarkPaid(id) {
    try {
        await ElMessageBox.confirm(t('bug_bounty_page.confirm.mark_paid'), t('actions.confirm'))
        await markBugPaid(id)
        ElMessage.success(t('bug_bounty_page.messages.marked_paid'))
        loadReports(pagination.value.current_page)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 删除 ───
async function handleDelete(id) {
    try {
        await ElMessageBox.confirm(t('bug_bounty_page.confirm.delete_warning'), t('bug_bounty_page.confirm.warning_title'), { confirmButtonClass: 'el-button--danger' })
        await deleteBugReport(id)
        ElMessage.success(t('bug_bounty_page.messages.deleted'))
        loadReports(pagination.value.current_page)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 致谢墙 ───
function openEditHonor(entry) {
    currentHonorEntry.value = { ...entry }
    honorDialogVisible.value = true
}

async function saveHonorEntry() {
    try {
        await updateHallOfFameEntry(currentHonorEntry.value.id, currentHonorEntry.value)
        ElMessage.success(t('bug_bounty_page.messages.updated'))
        honorDialogVisible.value = false
        loadHallOfFame()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

function severityTag(sev) {
    return severityMap.value[sev] || { label: sev, color: '#ccc' }
}

function statusTag(st) {
    return statusMap.value[st] || { label: st, type: 'info' }
}

function rankLabel(rank) {
    return rankMap.value[rank]?.label || rank
}

function formatDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'
    return new Date(d).toLocaleDateString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function handlePageChange(page) {
    loadReports(page)
}

onMounted(() => {
    loadStats()
    loadReports()
    loadHallOfFame()
})
</script>

<template>
    <div>
        <!-- 面包屑 -->
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('bug_bounty_page.breadcrumb_security') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('bug_bounty_page.breadcrumb_current') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">{{ t('bug_bounty_page.title') }}</span>
                    <el-button type="primary" @click="loadStats(); loadReports(); loadHallOfFame()" :icon="'Refresh'" circle />
                </div>
            </template>

            <!-- 统计数据 -->
            <el-row :gutter="20" class="mb-4">
                <el-col :span="6" v-for="item in statsData" :key="item.key">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-sm text-gray-500">{{ item.label }}</div>
                        <div class="text-2xl font-bold mt-1" :style="{ color: item.color }">{{ item.value }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- Tab 切换 -->
            <el-tabs v-model="activeTab" type="border-card">
                <!-- Tab 1: 报告列表 -->
                <el-tab-pane :label="t('bug_bounty_page.tabs.reports')" name="reports">
                    <!-- 筛选栏 -->
                    <div class="flex gap-3 mb-4 flex-wrap items-center">
                        <el-select v-model="filters.status" :placeholder="t('bug_bounty_page.filters.status')" clearable style="width:140px">
                            <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select>
                        <el-select v-model="filters.severity" :placeholder="t('bug_bounty_page.filters.severity')" clearable style="width:150px">
                            <el-option v-for="o in severityOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select>
                        <el-input v-model="filters.search" :placeholder="t('bug_bounty_page.filters.search')" clearable style="width:260px" @keyup.enter="handleSearch" />
                        <el-button type="primary" @click="handleSearch">{{ t('actions.search') }}</el-button>
                        <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                    </div>

                    <el-table :data="reports" v-loading="loading" stripe style="width:100%">
                        <el-table-column prop="id" :label="t('bug_bounty_page.cols.id')" width="70" />
                        <el-table-column :label="t('bug_bounty_page.cols.severity')" width="100">
                            <template #default="{ row }">
                                <el-tag :color="severityTag(row.severity).color" effect="dark" size="small">
                                    {{ severityTag(row.severity).label }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="title" :label="t('bug_bounty_page.cols.title')" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="t('bug_bounty_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusTag(row.status).type" size="small">{{ statusTag(row.status).label }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="vulnerability_type" :label="t('bug_bounty_page.cols.vuln_type')" width="120" />
                        <el-table-column prop="reporter_email" :label="t('bug_bounty_page.cols.reporter')" width="180" show-overflow-tooltip />
                        <el-table-column :label="t('bug_bounty_page.cols.bounty')" width="100">
                            <template #default="{ row }">
                                <span v-if="row.bounty_amount">${{ row.bounty_amount }}</span>
                                <span v-else class="text-gray-400">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bug_bounty_page.cols.submitted_at')" width="160">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('bug_bounty_page.cols.actions')" width="360" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openDetail(row.id)">{{ t('bug_bounty_page.row_actions.detail') }}</el-button>
                                <el-button v-if="row.status === 'submitted'" size="small" type="warning" @click="openReview(row)">{{ t('bug_bounty_page.row_actions.review') }}</el-button>
                                <el-button v-if="row.status === 'under_review'" size="small" type="danger" @click="openConfirm(row)">{{ t('bug_bounty_page.row_actions.confirm') }}</el-button>
                                <el-button v-if="row.status === 'confirmed'" size="small" type="success" @click="handleMarkFixed(row.id)">{{ t('bug_bounty_page.row_actions.fix') }}</el-button>
                                <el-button v-if="row.status === 'fixed'" size="small" type="success" @click="handleMarkPaid(row.id)">{{ t('bug_bounty_page.row_actions.pay') }}</el-button>
                                <el-popconfirm v-if="row.status === 'submitted' || row.status === 'under_review'"
                                    :title="t('bug_bounty_page.confirm.decline_report')" @confirm="openDecline(row)">
                                    <template #reference>
                                        <el-button size="small" type="info">{{ t('bug_bounty_page.row_actions.decline') }}</el-button>
                                    </template>
                                </el-popconfirm>
                                <el-popconfirm :title="t('bug_bounty_page.confirm.delete_report')" @confirm="handleDelete(row.id)">
                                    <template #reference>
                                        <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- 分页 -->
                    <div class="flex justify-center mt-4">
                        <el-pagination
                            v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page"
                            :total="pagination.total"
                            layout="prev, pager, next, total"
                            @current-change="handlePageChange"
                        />
                    </div>
                </el-tab-pane>

                <!-- Tab 2: 致谢墙 -->
                <el-tab-pane :label="t('bug_bounty_page.tabs.hall_of_fame')" name="hall-of-fame">
                    <div class="mb-4">
                        <el-button type="primary" @click="loadHallOfFame()" :icon="'Refresh'">{{ t('bug_bounty_page.refresh') }}</el-button>
                    </div>
                    <el-table :data="hallOfFame" stripe v-if="hallOfFame.length">
                        <el-table-column prop="hacker_name" :label="t('bug_bounty_page.cols.hacker_name')" width="180" />
                        <el-table-column prop="hacker_handle" :label="t('bug_bounty_page.cols.handle')" width="180" />
                        <el-table-column :label="t('bug_bounty_page.cols.rank')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.rank === 'gold' ? 'warning' : row.rank === 'silver' ? '' : row.rank === 'bronze' ? 'danger' : 'info'" effect="dark" size="small">
                                    {{ rankLabel(row.rank) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reports_count" :label="t('bug_bounty_page.cols.valid_reports')" width="100" />
                        <el-table-column :label="t('bug_bounty_page.cols.total_bounty')" width="120">
                            <template #default="{ row }">${{ row.total_bounty || 0 }}</template>
                        </el-table-column>
                        <el-table-column :label="t('bug_bounty_page.cols.featured')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.is_featured ? 'success' : 'info'" size="small">{{ row.is_featured ? t('bug_bounty_page.yes') : t('bug_bounty_page.no') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="bio" :label="t('bug_bounty_page.cols.bio')" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="t('bug_bounty_page.cols.actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openEditHonor(row)">{{ t('actions.edit') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="t('bug_bounty_page.empty_hof')" />
                </el-tab-pane>

                <!-- Tab 3: 按严重级别统计 -->
                <el-tab-pane :label="t('bug_bounty_page.tabs.stats')" name="stats">
                    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <el-card shadow="never">
                            <template #header><span class="font-semibold">{{ t('bug_bounty_page.stats_section.by_severity') }}</span></template>
                            <div v-for="(item, key) in stats.by_severity" :key="key" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <el-tag :color="severityMap[key]?.color || '#ccc'" effect="dark" size="small">{{ severityMap[key]?.label || key }}</el-tag>
                                <span>{{ t('bug_bounty_page.stats_section.report_count', { count: item.count }) }}</span>
                            </div>
                        </el-card>
                        <el-card shadow="never">
                            <template #header><span class="font-semibold">{{ t('bug_bounty_page.stats_section.by_status') }}</span></template>
                            <div v-for="(item, key) in stats.by_status" :key="key" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <el-tag :type="statusMap[key]?.type || 'info'" size="small">{{ statusMap[key]?.label || key }}</el-tag>
                                <span>{{ t('bug_bounty_page.stats_section.item_count', { count: item.count }) }}</span>
                            </div>
                        </el-card>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('bug_bounty_page.detail_dialog.title')" width="700px">
            <div v-loading="detailLoading">
                <el-descriptions :column="2" border v-if="currentReport">
                    <el-descriptions-item :label="t('bug_bounty_page.cols.id')" :span="1">{{ currentReport.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.severity')" :span="1">
                        <el-tag :color="severityTag(currentReport.severity).color" effect="dark" size="small">
                            {{ severityTag(currentReport.severity).label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.title')" :span="2">{{ currentReport.title }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.vuln_type')" :span="1">{{ currentReport.vulnerability_type || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.affected_endpoint')" :span="1">{{ currentReport.affected_endpoint || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.reporter')" :span="1">{{ currentReport.reporter_name || currentReport.reporter_email || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.email')" :span="1">{{ currentReport.reporter_email || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.status')" :span="1">
                        <el-tag :type="statusTag(currentReport.status).type" size="small">{{ statusTag(currentReport.status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.bounty')" :span="1">{{ currentReport.bounty_amount ? `$${currentReport.bounty_amount} ${currentReport.bounty_currency || 'USD'}` : '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.description')" :span="2">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.description }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.steps')" :span="2" v-if="currentReport.steps_to_reproduce">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.steps_to_reproduce }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.impact')" :span="2" v-if="currentReport.impact">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.impact }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.resolution_notes')" :span="2" v-if="currentReport.resolution_notes">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.resolution_notes }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.cols.submitted_at')" :span="1">{{ formatDate(currentReport.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.confirmed_at')" :span="1">{{ formatDate(currentReport.confirmed_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.fixed_at')" :span="1">{{ formatDate(currentReport.fixed_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('bug_bounty_page.detail_dialog.paid_at')" :span="1">{{ formatDate(currentReport.paid_at) }}</el-descriptions-item>
                </el-descriptions>
            </div>
        </el-dialog>

        <!-- 审核对话框 -->
        <el-dialog v-model="reviewDialogVisible" :title="t('bug_bounty_page.review_dialog.title')" width="400px">
            <el-form :model="reviewForm" label-position="top">
                <el-form-item :label="t('bug_bounty_page.review_dialog.assigned_to')" required>
                    <el-input v-model="reviewForm.assigned_to" :placeholder="t('bug_bounty_page.review_dialog.assigned_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="reviewDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doReview">{{ t('bug_bounty_page.review_dialog.submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 确认漏洞对话框 -->
        <el-dialog v-model="confirmDialogVisible" :title="t('bug_bounty_page.confirm_dialog.title')" width="500px">
            <el-form :model="confirmForm" label-position="top">
                <el-form-item :label="t('bug_bounty_page.cols.severity')">
                    <el-select v-model="confirmForm.severity" style="width:100%">
                        <el-option v-for="o in severityOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.confirm_dialog.bounty_amount')">
                    <el-input-number v-model="confirmForm.bounty_amount" :min="0" :step="50" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.confirm_dialog.public_ack')">
                    <el-switch v-model="confirmForm.is_public" :active-text="t('bug_bounty_page.yes')" :inactive-text="t('bug_bounty_page.no')" />
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.detail_dialog.resolution_notes')">
                    <el-input v-model="confirmForm.resolution_notes" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="confirmDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="doConfirm">{{ t('bug_bounty_page.confirm_dialog.confirm_btn') }}</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝对话框 -->
        <el-dialog v-model="declineDialogVisible" :title="t('bug_bounty_page.decline_dialog.title')" width="400px">
            <el-form :model="declineForm" label-position="top">
                <el-form-item :label="t('bug_bounty_page.decline_dialog.reason')" required>
                    <el-input v-model="declineForm.reason" type="textarea" :rows="3" :placeholder="t('bug_bounty_page.decline_dialog.reason_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="declineDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="info" @click="doDecline">{{ t('bug_bounty_page.decline_dialog.confirm_btn') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑致谢墙 -->
        <el-dialog v-model="honorDialogVisible" :title="t('bug_bounty_page.honor_dialog.title')" width="500px">
            <el-form :model="currentHonorEntry" label-position="top" v-if="currentHonorEntry">
                <el-form-item :label="t('bug_bounty_page.honor_dialog.hacker_name')">
                    <el-input v-model="currentHonorEntry.hacker_name" />
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.cols.rank')">
                    <el-select v-model="currentHonorEntry.rank" style="width:100%">
                        <el-option v-for="o in rankOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.cols.bio')">
                    <el-input v-model="currentHonorEntry.bio" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.cols.featured')">
                    <el-switch v-model="currentHonorEntry.is_featured" :active-text="t('bug_bounty_page.yes')" :inactive-text="t('bug_bounty_page.no')" />
                </el-form-item>
                <el-form-item :label="t('bug_bounty_page.honor_dialog.sort_order')">
                    <el-input-number v-model="currentHonorEntry.sort_order" :min="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="honorDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="saveHonorEntry">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card {
    border-radius: 8px;
}
.stat-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}
pre {
    margin: 0;
    line-height: 1.5;
}
</style>
