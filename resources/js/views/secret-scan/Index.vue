<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">{{ t('secret_scan_page.breadcrumb_home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('secret_scan_page.breadcrumb_security') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('secret_scan_page.breadcrumb_current') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-gray-800">{{ stats.total_findings ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">{{ t('secret_scan_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-danger">{{ stats.critical ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">{{ t('secret_scan_page.stats.critical') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-warning">{{ stats.open ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">{{ t('secret_scan_page.stats.open') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-success">{{ stats.dismissed ?? 0 }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">{{ t('secret_scan_page.stats.handled') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card class="mb-4">
            <el-row :gutter="16" align="middle">
                <el-col :span="12">
                    <el-button type="primary" @click="handleFullScan" :loading="scanning">
                        <el-icon class="mr-1"><Search /></el-icon>{{ t('secret_scan_page.full_scan') }}
                    </el-button>
                    <el-button @click="handleQuickScan" :loading="quickScanning" class="ml-2">
                        {{ t('secret_scan_page.quick_scan') }}
                    </el-button>
                </el-col>
                <el-col :span="12" class="text-right">
                    <el-select v-model="filters.severity" :placeholder="t('secret_scan_page.filter_severity')" clearable class="mr-2" style="width:120px" @change="fetchEntries">
                        <el-option :label="t('secret_scan_page.severities.critical')" value="critical" />
                        <el-option :label="t('secret_scan_page.severities.high')" value="high" />
                        <el-option :label="t('secret_scan_page.severities.medium')" value="medium" />
                    </el-select>
                    <el-select v-model="filters.status" :placeholder="t('secret_scan_page.cols.status')" clearable style="width:120px" @change="fetchEntries">
                        <el-option :label="t('secret_scan_page.statuses.open')" value="open" />
                        <el-option :label="t('secret_scan_page.statuses.dismissed')" value="dismissed" />
                        <el-option :label="t('secret_scan_page.statuses.revoked')" value="revoked" />
                    </el-select>
                </el-col>
            </el-row>
        </el-card>

        <el-card>
            <el-table :data="entries" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column prop="file" :label="t('secret_scan_page.cols.file')" min-width="250">
                    <template #default="{ row }">
                        <el-tooltip :content="row.file" placement="top">
                            <span class="text-sm font-mono">{{ row.file }}</span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="pattern_label" :label="t('secret_scan_page.cols.type')" width="180" />
                <el-table-column prop="matched_preview" :label="t('secret_scan_page.cols.match')" width="160">
                    <template #default="{ row }">
                        <el-tag type="danger" class="font-mono text-xs">{{ row.matched_preview }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="severity" :label="t('secret_scan_page.cols.severity')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="severityTag(row.severity)" size="small">
                            {{ severityLabel(row.severity) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('secret_scan_page.cols.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('secret_scan_page.cols.found_at')" width="170" />
                <el-table-column :label="t('secret_scan_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'open'" type="warning" size="small" @click="handleResolve(row, 'dismissed')">{{ t('secret_scan_page.dismiss') }}</el-button>
                        <el-button v-if="row.status === 'open'" type="danger" size="small" @click="handleResolve(row, 'revoked')">{{ t('secret_scan_page.mark_revoked') }}</el-button>
                        <span v-else class="text-gray-400 text-sm">{{ row.resolver?.name ?? '-' }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center" v-if="total > perPage">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>

        <el-dialog v-model="scanDialog.visible" :title="t('secret_scan_page.scan_result')" width="600px">
            <el-alert v-if="scanDialog.error" :title="scanDialog.error" type="error" show-icon class="mb-3" />
            <div v-else>
                <el-result
                    :icon="scanDialog.total > 0 ? 'warning' : 'success'"
                    :title="scanDialog.total > 0 ? t('secret_scan_page.result_found') : t('secret_scan_page.result_clean')"
                    :sub-title="t('secret_scan_page.scanned_n', { n: scanDialog.scanned })"
                >
                    <template #extra>
                        <p v-if="scanDialog.total > 0" class="text-danger font-bold text-lg">
                            {{ t('secret_scan_page.found_n', { n: scanDialog.total }) }}
                        </p>
                    </template>
                </el-result>
            </div>
            <template #footer>
                <el-button @click="scanDialog.visible = false">{{ t('actions.close') }}</el-button>
                <el-button v-if="scanDialog.total > 0" type="primary" @click="scanDialog.visible = false; fetchEntries()">{{ t('secret_scan_page.view_details') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import secretScanApi from '../../api/secret-scan'

const { t } = useI18n()

const stats = ref({})
const entries = ref([])
const loading = ref(false)
const scanning = ref(false)
const quickScanning = ref(false)
const currentPage = ref(1)
const perPage = ref(20)
const total = ref(0)

const filters = reactive({
    severity: '',
    status: '',
})

const scanDialog = reactive({
    visible: false,
    scanned: 0,
    total: 0,
    error: '',
})

function severityTag(val) {
    return { critical: 'danger', high: 'warning', medium: 'info' }[val] || 'info'
}

function severityLabel(val) {
    const key = { critical: 'critical', high: 'high', medium: 'medium' }[val]
    return key ? t(`secret_scan_page.severities.${key}`) : val
}

function statusTag(val) {
    return { open: 'danger', dismissed: 'info', revoked: 'success' }[val] || 'info'
}

function statusLabel(val) {
    const key = { open: 'open', dismissed: 'dismissed', revoked: 'revoked' }[val]
    return key ? t(`secret_scan_page.statuses.${key}`) : val
}

async function fetchDashboard() {
    try {
        const res = await secretScanApi.dashboard()
        stats.value = res.data
    } catch {
        // ignore
    }
}

async function fetchEntries() {
    loading.value = true
    try {
        const res = await secretScanApi.entries({
            page: currentPage.value,
            per_page: perPage.value,
            search: '',
            severity: filters.severity || undefined,
            status: filters.status || undefined,
        })
        entries.value = res.data.data
        total.value = res.data.total
    } catch {
        entries.value = []
    } finally {
        loading.value = false
    }
}

async function handleFullScan() {
    scanning.value = true
    scanDialog.visible = true
    scanDialog.error = ''
    try {
        const res = await secretScanApi.scan()
        scanDialog.scanned = res.data.scanned
        scanDialog.total = res.data.total_findings
        ElMessage.success(t('secret_scan_page.messages.scan_done', { n: res.data.new_findings }))
        await fetchDashboard()
        await fetchEntries()
    } catch (e) {
        scanDialog.error = e.message || t('secret_scan_page.messages.scan_failed')
    } finally {
        scanning.value = false
    }
}

async function handleQuickScan() {
    quickScanning.value = true
    try {
        const res = await secretScanApi.quickScan()
        ElMessage.success(t('secret_scan_page.messages.quick_done', { n: res.data.new_findings }))
        await fetchDashboard()
        await fetchEntries()
    } catch (e) {
        ElMessage.error(e.message || t('secret_scan_page.messages.quick_failed'))
    } finally {
        quickScanning.value = false
    }
}

async function handleResolve(row, action) {
    const label = action === 'dismissed' ? t('secret_scan_page.dismiss') : t('secret_scan_page.mark_revoked')
    try {
        await ElMessageBox.confirm(
            t('secret_scan_page.resolve_confirm', { label }),
            t('actions.confirm'),
            { type: 'warning', confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel') }
        )
        await secretScanApi.resolve(row.id, { action })
        ElMessage.success(t('secret_scan_page.messages.resolved', { label }))
        await fetchEntries()
        await fetchDashboard()
    } catch {
        // cancelled
    }
}

function onPageChange(page) {
    currentPage.value = page
    fetchEntries()
}

onMounted(() => {
    fetchDashboard()
    fetchEntries()
})
</script>

<style scoped>
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
.font-mono { font-family: 'Courier New', Courier, monospace; }
</style>
