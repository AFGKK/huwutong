<template>
    <div class="compliance-page">
        <div class="page-header"><div><h2>{{ t('compliance_center.title') }}</h2><p class="header-desc text-sm text-gray-500">{{ t('compliance_center.desc') }}</p></div></div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('compliance_center.tabs.main')" name="main"><ComplianceMain /></el-tab-pane>
            <el-tab-pane :label="t('compliance_center.tabs.gdpr')" name="gdpr"><GdprCompliance /></el-tab-pane>
            <el-tab-pane :label="t('compliance_center.tabs.gdpr_enhance')" name="gdpr-enhance"><GdprEnhancement /></el-tab-pane>
            <el-tab-pane :label="t('compliance_center.tabs.pipl')" name="pipl"><PipCompliance /></el-tab-pane>
            <el-tab-pane :label="t('compliance_center.tabs.ai')" name="ai"><AiCompliance /></el-tab-pane>
            <el-tab-pane :label="t('compliance_center.tabs.license')" name="license">
                <div v-if="lc_tabVisited" class="lc-content">
                    <div class="lc-page-header mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">{{ t('license_compliance_page.title') }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ t('license_compliance_page.subtitle') }}</p>
                    </div>

                    <div v-if="lc_loading" class="text-center py-16">
                        <el-skeleton :rows="5" animated />
                    </div>

                    <template v-else>
                        <!-- 统计 -->
                        <el-row :gutter="20" class="mb-6">
                            <el-col :span="6" v-for="s in lc_statCards" :key="s.label">
                                <el-card shadow="never" class="text-center">
                                    <div class="text-2xl font-bold" :style="{ color: s.color }">{{ s.value }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ s.label }}</div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 操作栏 -->
                        <el-card shadow="never" class="mb-6">
                            <div class="flex justify-between items-center">
                                <div class="flex gap-3">
                                    <el-select v-model="lc_filter.type" :placeholder="t('license_compliance_page.filters.report_type')" clearable size="small" style="width:160px" @change="lc_fetchReports">
                                        <el-option :label="t('licenses_page.all')" value="" />
                                        <el-option v-for="(label, key) in lc_typeLabels" :key="key" :label="label" :value="key" />
                                    </el-select>
                                    <el-select v-model="lc_filter.status" :placeholder="t('licenses_page.status')" clearable size="small" style="width:120px" @change="lc_fetchReports">
                                        <el-option :label="t('licenses_page.all')" value="" />
                                        <el-option v-for="opt in lc_statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </div>
                                <el-button type="primary" size="small" @click="lc_showCreateDialog = true">
                                    <el-icon><Plus /></el-icon> {{ t('license_compliance_page.buttons.generate_new') }}
                                </el-button>
                            </div>
                        </el-card>

                        <!-- 报告列表 -->
                        <el-card shadow="never">
                            <el-table :data="lc_reports" v-loading="lc_loading" stripe size="small">
                                <el-table-column prop="title" :label="t('license_compliance_page.cols.title')" min-width="200" />
                                <el-table-column :label="t('license_compliance_page.cols.type')" width="160">
                                    <template #default="{ row }">{{ lc_typeLabels[row.type] || row.type }}</template>
                                </el-table-column>
                                <el-table-column :label="t('license_compliance_page.cols.format')" width="80">
                                    <template #default="{ row }">
                                        <el-tag size="small">{{ row.format?.toUpperCase() }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('license_compliance_page.cols.status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">
                                            {{ lc_statusLabel(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="file_size" :label="t('license_compliance_page.cols.size')" width="100">
                                    <template #default="{ row }">{{ lc_formatSize(row.file_size) }}</template>
                                </el-table-column>
                                <el-table-column prop="generated_at" :label="t('license_compliance_page.cols.generated_at')" width="160" />
                                <el-table-column :label="t('license_compliance_page.cols.actions')" width="180" fixed="right">
                                    <template #default="{ row }">
                                        <el-button v-if="row.status === 'completed'" text size="small" type="primary" @click="lc_downloadReport(row)">
                                            {{ t('actions.download') }}
                                        </el-button>
                                        <el-button v-else-if="row.status === 'generating'" text size="small" disabled>
                                            {{ t('license_compliance_page.buttons.generating') }}
                                        </el-button>
                                        <el-button text size="small" @click="lc_viewReport(row)">{{ t('license_compliance_page.buttons.detail') }}</el-button>
                                        <el-popconfirm :title="t('license_compliance_page.delete_confirm')" @confirm="lc_deleteReport(row)">
                                            <template #reference>
                                                <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                            </template>
                                        </el-popconfirm>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div class="mt-4 flex justify-center" v-if="lc_pagination.total > lc_pagination.per_page">
                                <el-pagination v-model:current-page="lc_pagination.current_page" :page-size="lc_pagination.per_page" :total="lc_pagination.total" layout="prev, pager, next" @current-change="lc_fetchReports" />
                            </div>

                            <el-empty v-if="lc_reports.length === 0" :description="t('license_compliance_page.empty')" />
                        </el-card>
                    </template>

                    <!-- 生成报告对话框 -->
                    <el-dialog v-model="lc_showCreateDialog" :title="t('license_compliance_page.create_dialog.title')" width="540px">
                        <el-form :model="lc_createForm" label-position="top" size="small">
                            <el-form-item :label="t('license_compliance_page.form.report_type')" required>
                                <el-select v-model="lc_createForm.type" style="width:100%">
                                    <el-option v-for="(label, key) in lc_typeLabels" :key="key" :label="label" :value="key" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('license_compliance_page.form.format')">
                                <el-radio-group v-model="lc_createForm.format">
                                    <el-radio value="xlsx">{{ t('license_compliance_page.form.format_xlsx') }}</el-radio>
                                    <el-radio value="csv">{{ t('license_compliance_page.form.format_csv') }}</el-radio>
                                </el-radio-group>
                            </el-form-item>
                            <el-form-item :label="t('license_compliance_page.form.customer_optional')">
                                <el-select v-model="lc_createForm.customer_id" filterable clearable :placeholder="t('license_compliance_page.form.customer_ph')" style="width:100%">
                                    <el-option v-for="c in lc_customers" :key="c.id" :label="c.name" :value="c.id" />
                                </el-select>
                            </el-form-item>
                            <el-row :gutter="16">
                                <el-col :span="12">
                                    <el-form-item :label="t('license_compliance_page.form.start_date')">
                                        <el-date-picker v-model="lc_createForm.report_period_start" type="date" :placeholder="t('license_compliance_page.form.date_optional')" style="width:100%" value-format="YYYY-MM-DD" />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="12">
                                    <el-form-item :label="t('license_compliance_page.form.end_date')">
                                        <el-date-picker v-model="lc_createForm.report_period_end" type="date" :placeholder="t('license_compliance_page.form.date_optional')" style="width:100%" value-format="YYYY-MM-DD" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                            <el-form-item :label="t('license_compliance_page.form.license_status')">
                                <el-select v-model="lc_createForm.filters.status" clearable :placeholder="t('licenses_page.all')" style="width:100%">
                                    <el-option :label="t('licenses_page.all')" value="" />
                                    <el-option :label="t('licenses_page.st_active')" value="active" />
                                    <el-option :label="t('licenses_page.st_expired')" value="expired" />
                                    <el-option :label="t('licenses_page.st_revoked')" value="revoked" />
                                </el-select>
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="lc_showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" @click="lc_generateReport" :loading="lc_generating">{{ t('license_compliance_page.buttons.generate') }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- 报告详情对话框 -->
                    <el-dialog v-model="lc_showDetailDialog" :title="t('license_compliance_page.detail_dialog.title')" width="600px">
                        <template v-if="lc_detail">
                            <el-descriptions :column="2" border size="small">
                                <el-descriptions-item :label="t('license_compliance_page.cols.title')" :span="2">{{ lc_detail.title }}</el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.cols.type')">{{ lc_typeLabels[lc_detail.type] }}</el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.cols.format')">{{ lc_detail.format?.toUpperCase() }}</el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.cols.status')">
                                    <el-tag :type="lc_detail.status === 'completed' ? 'success' : 'danger'" size="small">{{ lc_statusLabel(lc_detail.status) }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.detail_dialog.file_size')">{{ lc_formatSize(lc_detail.file_size) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.cols.generated_at')">{{ lc_detail.generated_at || '-' }}</el-descriptions-item>
                                <el-descriptions-item :label="t('license_compliance_page.detail_dialog.downloaded_at')">{{ lc_detail.downloaded_at || t('license_compliance_page.detail_dialog.not_downloaded') }}</el-descriptions-item>
                            </el-descriptions>

                            <div v-if="lc_detail.summary_data" class="mt-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ t('license_compliance_page.detail_dialog.summary_title') }}</h4>
                                <el-table :data="lc_summaryRows" size="small" stripe>
                                    <el-table-column prop="label" :label="t('license_compliance_page.cols.metric')" />
                                    <el-table-column prop="value" :label="t('license_compliance_page.cols.value')" />
                                </el-table>
                            </div>
                        </template>
                    </el-dialog>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch, defineAsyncComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getComplianceReports,
    getComplianceReport,
    createComplianceReport,
    deleteComplianceReport,
    getComplianceReportStats,
    getComplianceReportDownloadUrl,
} from '@/api/licenseCompliance'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const activeTab = ref(route.query.tab || 'main')
const ComplianceMain = defineAsyncComponent(() => import('@/views/compliance/Index.vue'))
const GdprCompliance = defineAsyncComponent(() => import('@/views/gdpr-compliance/Index.vue'))
const GdprEnhancement = defineAsyncComponent(() => import('@/views/gdpr-compliance/Enhancement.vue'))
const PipCompliance = defineAsyncComponent(() => import('@/views/pipl-compliance/Index.vue'))
const AiCompliance = defineAsyncComponent(() => import('@/views/ai-compliance/Index.vue'))
function onTabChange(tab) { router.replace({ query: { tab } }) }

// ── License 合规 (懒加载) ──
const lc_tabVisited = ref(false)
const lc_loading = ref(true)
const lc_generating = ref(false)
const lc_showCreateDialog = ref(false)
const lc_showDetailDialog = ref(false)
const lc_reports = ref([])
const lc_detail = ref(null)
const lc_customers = ref([])

const REPORT_TYPES = ['full_inventory', 'activation_audit', 'compliance_summary', 'custom']
const REPORT_STATUSES = ['completed', 'generating', 'failed']
const SUMMARY_KEYS = [
    'total_licenses',
    'active_licenses',
    'expired_licenses',
    'total_activations',
    'compliant_licenses',
    'overused_licenses',
]

const lc_typeLabels = computed(() => {
    const map = {}
    for (const key of REPORT_TYPES) {
        map[key] = t(`license_compliance_page.types.${key}`)
    }
    return map
})

const lc_statusOptions = computed(() =>
    REPORT_STATUSES.map((value) => ({
        value,
        label: t(`license_compliance_page.status.${value}`),
    })),
)

function lc_statusLabel(status) {
    const key = `license_compliance_page.status.${status}`
    const translated = t(key)
    return translated !== key ? translated : status
}

const lc_statValues = reactive({
    total: 0,
    completed: 0,
    pending: 0,
    failed: 0,
})

const lc_statCards = computed(() => [
    { label: t('license_compliance_page.stats.total_reports'), value: lc_statValues.total, color: '#0f172a' },
    { label: t('license_compliance_page.stats.completed'), value: lc_statValues.completed, color: '#67c23a' },
    { label: t('license_compliance_page.stats.generating'), value: lc_statValues.pending, color: '#e6a23c' },
    { label: t('license_compliance_page.stats.failed'), value: lc_statValues.failed, color: '#f56c6c' },
])

const lc_filter = reactive({ type: '', status: '' })
const lc_pagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const lc_createForm = reactive({
    type: 'compliance_summary',
    format: 'xlsx',
    customer_id: null,
    report_period_start: null,
    report_period_end: null,
    filters: { status: '', product_id: null },
})

const lc_summaryRows = computed(() => {
    if (!lc_detail.value?.summary_data) return []
    return SUMMARY_KEYS.map((k) => ({
        label: t(`license_compliance_page.summary.${k}`),
        value: lc_detail.value.summary_data[k] ?? '-',
    }))
})

function lc_formatSize(bytes) {
    if (!bytes) return '-'
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}

async function lc_loadData() {
    lc_loading.value = true
    try {
        const [r, s] = await Promise.all([
            getComplianceReports(),
            getComplianceReportStats(),
        ])
        if (r.data?.success) {
            lc_reports.value = r.data.data.data || []
            lc_pagination.current_page = r.data.data.current_page
            lc_pagination.total = r.data.data.total
        }
        if (s.data?.success) {
            lc_statValues.total = s.data.data.total_reports
            lc_statValues.completed = s.data.data.completed
            lc_statValues.pending = s.data.data.pending
            lc_statValues.failed = s.data.data.failed
        }
    } catch (e) {
        ElMessage.error(t('messages.load_failed'))
    } finally {
        lc_loading.value = false
    }
}

async function lc_fetchReports(page) {
    lc_loading.value = true
    try {
        const res = await getComplianceReports({ ...lc_filter, page: page || lc_pagination.current_page })
        if (res.data?.success) {
            lc_reports.value = res.data.data.data || []
            lc_pagination.current_page = res.data.data.current_page
            lc_pagination.total = res.data.data.total
        }
    } finally {
        lc_loading.value = false
    }
}

async function lc_generateReport() {
    lc_generating.value = true
    try {
        await createComplianceReport(lc_createForm)
        ElMessage.success(t('license_compliance_page.messages.generate_ok'))
        lc_showCreateDialog.value = false
        lc_createForm.type = 'compliance_summary'
        lc_createForm.format = 'xlsx'
        lc_createForm.customer_id = null
        lc_createForm.report_period_start = null
        lc_createForm.report_period_end = null
        lc_createForm.filters = { status: '', product_id: null }
        await lc_loadData()
    } catch (e) {
        ElMessage.error(t('license_compliance_page.messages.generate_fail'))
    } finally {
        lc_generating.value = false
    }
}

function lc_downloadReport(row) {
    const token = localStorage.getItem('auth_token')
    const url = getComplianceReportDownloadUrl(row.id)
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
        .then(res => {
            if (!res.ok) throw new Error('Download failed')
            return res.blob()
        })
        .then(blob => {
            const a = document.createElement('a')
            a.href = URL.createObjectURL(blob)
            a.download = row.file_name || `report.${row.format}`
            a.click()
            URL.revokeObjectURL(a.href)
        })
        .catch(() => ElMessage.error(t('license_compliance_page.messages.download_fail')))
}

async function lc_viewReport(row) {
    try {
        const res = await getComplianceReport(row.id)
        if (res.data?.success) {
            lc_detail.value = res.data.data
            lc_showDetailDialog.value = true
        }
    } catch (e) {
        ElMessage.error(t('license_compliance_page.messages.detail_load_fail'))
    }
}

async function lc_deleteReport(row) {
    try {
        await deleteComplianceReport(row.id)
        ElMessage.success(t('license_compliance_page.messages.delete_ok'))
        await lc_loadData()
    } catch (e) {
        ElMessage.error(t('license_compliance_page.messages.delete_fail'))
    }
}

// 懒加载：切换到 license tab 时首次加载数据
watch(activeTab, (tab) => {
    if (tab === 'license' && !lc_tabVisited.value) {
        lc_tabVisited.value = true
        lc_loadData()
    }
}, { immediate: true })
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }

/* ── License 合规样式 ── */
.lc-content .lc-page-header { margin-bottom: 24px; }
</style>
