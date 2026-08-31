<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getAdminExports, getExportStats, adminCreateExport } from '../../api/dataExport.js'

const { t, locale } = useI18n()

const exports = ref([])
const stats = ref(null)
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const loading = ref(false)

const filters = ref({
    type: '',
    status: '',
    customer_id: '',
})

const createDialogVisible = ref(false)
const createForm = ref({
    customer_id: '',
    type: 'licenses',
    format: 'csv',
})
const submitting = ref(false)

const typeOptions = computed(() => [
    { value: 'licenses', label: t('data_export_page.types.licenses') },
    { value: 'invoices', label: t('data_export_page.types.invoices') },
    { value: 'activations', label: t('data_export_page.types.activations') },
    { value: 'customers', label: t('data_export_page.types.customers') },
])

const statusOptions = computed(() => [
    { value: 'pending', label: t('data_export_page.statuses.pending'), type: 'info' },
    { value: 'processing', label: t('data_export_page.statuses.processing'), type: 'warning' },
    { value: 'completed', label: t('data_export_page.statuses.completed'), type: 'success' },
    { value: 'failed', label: t('data_export_page.statuses.failed'), type: 'danger' },
])

const statusMap = computed(() => statusOptions.value.reduce((m, o) => { m[o.value] = o; return m }, {}))

async function loadStats() {
    try {
        const res = await getExportStats()
        stats.value = res.data
    } catch (e) {
        console.error('Failed to load stats:', e)
    }
}

async function loadExports(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page }
        const res = await getAdminExports(params)
        exports.value = res.data.data || []
        pagination.value = {
            total: res.data.total || 0,
            current_page: res.data.current_page || page,
            per_page: res.data.per_page || 15,
        }
    } catch (e) {
        console.error('Failed to load exports:', e)
    } finally {
        loading.value = false
    }
}

function handleSearch() { loadExports(1) }
function resetFilters() {
    filters.value = { type: '', status: '', customer_id: '' }
    loadExports(1)
}

async function handleCreate() {
    submitting.value = true
    try {
        await adminCreateExport(createForm.value)
        ElMessage.success(t('data_export_page.messages.created'))
        createDialogVisible.value = false
        loadExports(1)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('data_export_page.messages.create_failed'))
    } finally {
        submitting.value = false
    }
}

function statusType(st) { return statusMap.value[st]?.type || 'info' }
function statusText(st) { return statusMap.value[st]?.label || st }
function typeLabel(type) { return typeOptions.value.find(o => o.value === type)?.label || type }

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '-'
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0; let size = bytes
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
    return `${size.toFixed(1)} ${units[i]}`
}

function formatDate(d) {
    if (!d) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(d).toLocaleString(loc)
}

function handlePageChange(page) { loadExports(page) }

onMounted(() => {
    loadStats()
    loadExports()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('data_export_page.breadcrumb_home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('data_export_page.breadcrumb_system') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('data_export_page.breadcrumb_current') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">{{ t('data_export_page.title') }}</span>
                    <el-button type="primary" @click="createDialogVisible = true">{{ t('data_export_page.create') }}</el-button>
                </div>
            </template>

            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ t('data_export_page.stats.total') }}</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.total_exports || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6" v-for="(count, type) in stats.by_type" :key="type">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ typeLabel(type) }}</div>
                        <div class="text-2xl font-bold mt-1">{{ count }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <div class="flex gap-3 mb-4 flex-wrap items-center">
                <el-select v-model="filters.type" :placeholder="t('data_export_page.filter_type')" clearable style="width:150px">
                    <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-select v-model="filters.status" :placeholder="t('data_export_page.filter_status')" clearable style="width:140px">
                    <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-input v-model="filters.customer_id" :placeholder="t('data_export_page.customer_id')" clearable style="width:160px" />
                <el-button type="primary" @click="handleSearch">{{ t('actions.search') }}</el-button>
                <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
            </div>

            <el-table :data="exports" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column :label="t('data_export_page.cols.customer')" min-width="160">
                    <template #default="{ row }">
                        <span v-if="row.customer">{{ row.customer.name || row.customer.email || '#' + row.customer_id }}</span>
                        <span v-else class="text-gray-400">#{{ row.customer_id }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.type')" width="120">
                    <template #default="{ row }">
                        <el-tag type="primary" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.format')" width="70">.{{ row.format }}</el-table-column>
                <el-table-column :label="t('data_export_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.records')" width="80">
                    <template #default="{ row }">{{ row.record_count || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.size')" width="100">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.created')" width="160">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.completed')" width="160">
                    <template #default="{ row }">{{ formatDate(row.completed_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('data_export_page.cols.expires')" width="160">
                    <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="flex justify-center mt-4">
                <el-pagination v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page" :total="pagination.total"
                    layout="prev, pager, next, total" @current-change="handlePageChange" />
            </div>
        </el-card>

        <el-dialog v-model="createDialogVisible" :title="t('data_export_page.create_title')" width="450px">
            <el-form :model="createForm" label-position="top">
                <el-form-item :label="t('data_export_page.customer_id')" required>
                    <el-input v-model="createForm.customer_id" :placeholder="t('data_export_page.customer_id_ph')" />
                </el-form-item>
                <el-form-item :label="t('data_export_page.data_type')" required>
                    <el-select v-model="createForm.type" style="width:100%">
                        <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('data_export_page.cols.format')">
                    <el-radio-group v-model="createForm.format">
                        <el-radio value="csv">CSV</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
</style>
