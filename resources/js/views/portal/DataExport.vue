<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getExportTypes, createExport, getMyExports, downloadExport, deleteExport } from '../../api/dataExport.js'

const { t, locale } = useI18n()

const exportTypes = ref([])
const myExports = ref([])
const loading = ref(false)
const typesLoading = ref(false)

const exportDialogVisible = ref(false)
const exportForm = ref({
    type: '',
    format: 'csv',
    filters: {},
})
const submitting = ref(false)

async function loadTypes() {
    typesLoading.value = true
    try {
        const res = await getExportTypes()
        exportTypes.value = res.data || []
    } catch (e) {
        console.error('Failed to load export types:', e)
    } finally {
        typesLoading.value = false
    }
}

async function loadExports() {
    loading.value = true
    try {
        const res = await getMyExports()
        myExports.value = res.data || []
    } catch (e) {
        console.error('Failed to load exports:', e)
    } finally {
        loading.value = false
    }
}

function openExportDialog(type) {
    exportForm.value = { type, format: 'csv', filters: {} }
    exportDialogVisible.value = true
}

async function submitExport() {
    submitting.value = true
    try {
        const res = await createExport(exportForm.value)
        ElMessage.success(res.data?.message || t('portal.export_created'))
        exportDialogVisible.value = false
        loadExports()
    } catch (e) {
        if (e.response?.status === 429) {
            ElMessage.warning(t('portal.export_rate_limit'))
        } else {
            ElMessage.error(e.response?.data?.message || t('portal.export_create_failed'))
        }
    } finally {
        submitting.value = false
    }
}

async function handleDownload(exp) {
    if (exp.status !== 'completed') {
        ElMessage.info(t('portal.export_not_ready'))
        return
    }
    try {
        const res = await downloadExport(exp.id)
        const url = window.URL.createObjectURL(new Blob([res.data], { type: res.headers?.['content-type'] || 'text/csv' }))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', exp.file_name || `export_${exp.type}.csv`)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('portal.download_started'))
    } catch (e) {
        if (e.response?.status === 410) {
            ElMessage.error(t('portal.file_expired'))
        } else {
            ElMessage.error(t('portal.download_failed'))
        }
    }
}

async function handleDelete(exp) {
    try {
        await ElMessageBox.confirm(t('portal.delete_irreversible'), t('actions.confirm'))
        await deleteExport(exp.id)
        ElMessage.success(t('portal.deleted_ok'))
        loadExports()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('portal.delete_failed_msg'))
    }
}

function statusType(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' }
    return map[status] || 'info'
}

function statusText(status) {
    const map = {
        pending: t('portal.exp_pending'),
        processing: t('portal.exp_processing'),
        completed: t('portal.exp_completed'),
        failed: t('portal.exp_failed'),
    }
    return map[status] || status
}

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '-'
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0
    let size = bytes
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
    return `${size.toFixed(1)} ${units[i]}`
}

function formatDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}

onMounted(() => {
    loadTypes()
    loadExports()
})
</script>

<template>
    <div>
        <div class="mb-4">
            <h1 class="text-xl font-semibold">{{ $t('portal.export_title') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $t('portal.export_subtitle') }}</p>
        </div>

        <el-card class="mb-6">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ $t('portal.select_export_type') }}</span>
                    <el-button size="small" @click="loadTypes" :icon="'Refresh'" circle />
                </div>
            </template>
            <div v-loading="typesLoading">
                <el-row :gutter="16">
                    <el-col :span="6" v-for="item in exportTypes" :key="item.type">
                        <el-card shadow="hover" class="export-type-card" :class="{ 'cursor-pointer': item.can_export }"
                            @click="item.can_export && openExportDialog(item.type)">
                            <div class="text-center py-3">
                                <div class="font-semibold">{{ item.label }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ $t('portal.records_n', { n: item.record_count }) }}</div>
                                <div class="mt-2">
                                    <el-tag v-if="!item.can_export" type="info" size="small">{{ $t('portal.no_data_tag') }}</el-tag>
                                    <el-tag v-else type="primary" size="small" effect="dark">{{ $t('portal.export_csv') }}</el-tag>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-empty v-if="!typesLoading && exportTypes.length === 0" :description="$t('portal.no_export_types')" />
            </div>
        </el-card>

        <el-card>
            <template #header>
                <span class="font-semibold">{{ $t('portal.export_history') }}</span>
            </template>

            <el-table :data="myExports" v-loading="loading" stripe>
                <el-table-column :label="$t('portal.type')" width="120">
                    <template #default="{ row }">
                        <el-tag type="primary" size="small">{{ row.type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.format')" width="80">
                    <template #default="{ row }">.{{ row.format }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.record_count')" width="90">
                    <template #default="{ row }">{{ row.record_count || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.file_size')" width="100">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.created_at')" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.expires_at_col')" width="170">
                    <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'completed'" size="small" type="primary"
                            @click="handleDownload(row)">{{ $t('portal.download') }}</el-button>
                        <el-button v-else-if="row.status === 'failed'" size="small" type="danger"
                            @click="ElMessage.error(row.error_message || $t('portal.exp_failed'))">{{ $t('portal.fail_detail') }}</el-button>
                        <el-button v-else size="small" disabled>{{ $t('portal.generating') }}</el-button>
                        <el-popconfirm :title="$t('portal.delete_export_confirm')" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">{{ $t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!loading && myExports.length === 0" :description="$t('portal.no_export_records')" />
        </el-card>

        <el-dialog v-model="exportDialogVisible" :title="$t('portal.confirm_export')" width="400px">
            <div class="text-center py-4">
                <div class="text-lg font-semibold mb-2">
                    {{ $t('portal.export_type_title', { label: exportTypes.find(x => x.type === exportForm.type)?.label || exportForm.type }) }}
                </div>
                <div class="text-gray-500 text-sm mb-4">
                    {{ $t('portal.export_records_hint', { n: exportTypes.find(x => x.type === exportForm.type)?.record_count || 0 }) }}
                </div>
                <div class="text-gray-400 text-xs">
                    {{ $t('portal.export_ttl_hint') }}
                </div>
            </div>
            <template #footer>
                <el-button @click="exportDialogVisible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitExport" :loading="submitting">{{ $t('portal.confirm_export_btn') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.export-type-card {
    transition: all .2s;
    border-radius: 10px;
}
.export-type-card.cursor-pointer:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    border-color: #0f172a;
}
</style>
