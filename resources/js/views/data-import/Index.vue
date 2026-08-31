<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">{{ t('data_import_page.title') }}</span>
          <span class="text-gray-400 text-sm ml-4">{{ t('data_import_page.subtitle') }}</span>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button type="primary" @click="openUploadDialog">
            <el-icon><Upload /></el-icon> {{ t('data_import_page.upload_btn') }}
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- Status filters -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="6">
          <el-select v-model="filterEntity" clearable :placeholder="t('data_import_page.filter_entity_ph')" style="width:100%" @change="fetchTasks">
            <el-option v-for="(lb, key) in entityTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="filterStatus" clearable :placeholder="t('data_import_page.filter_status_ph')" style="width:100%" @change="fetchTasks">
            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-col>
        <el-col :span="12" class="text-right text-sm text-gray-400">
          {{ t('data_import_page.record_count', { n: tasks.length }) }}
        </el-col>
      </el-row>
    </el-card>

    <!-- Task list -->
    <el-card shadow="never" v-loading="loading">
      <el-table :data="tasks" style="width:100%" @row-click="openTaskDetail">
        <el-table-column type="expand">
          <template #default="{ row }">
            <div class="px-4 py-2 text-sm">
              <div v-if="row.import_result">
                <span class="font-medium">{{ t('data_import_page.result_label') }}</span>
                {{ t('data_import_page.result_summary', {
                  success: row.import_result.success || row.success_rows,
                  failed: row.import_result.errors || row.error_rows,
                }) }}
              </div>
              <div v-if="row.validation_errors?.details" class="mt-1">
                <span class="font-medium">{{ t('data_import_page.validation_label') }}</span>
                {{ t('data_import_page.validation_summary', {
                  errors: row.validation_errors.error_rows,
                  warnings: row.validation_errors.warning_rows,
                }) }}
              </div>
              <div v-if="row.validation_errors?.details?.length" class="mt-1">
                <el-tag v-for="e in row.validation_errors.details.slice(0, 5)" :key="e.row" size="small" class="mr-1">
                  {{ t('data_import_page.row_prefix', { row: e.row }) }}{{ e.errors?.join(', ') || '' }}{{ e.warnings?.join(', ') || '' }}
                </el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="name" :label="t('data_import_page.col_name')" min-width="160" />
        <el-table-column prop="entity_type" :label="t('data_import_page.col_entity_type')" width="100">
          <template #default="{ row }">{{ entityTypes[row.entity_type] || row.entity_type }}</template>
        </el-table-column>
        <el-table-column prop="file_type" :label="t('data_import_page.col_format')" width="60">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.file_type?.toUpperCase() }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_rows" :label="t('data_import_page.col_total_rows')" width="80" align="center" />
        <el-table-column :label="t('data_import_page.col_progress')" width="180">
          <template #default="{ row }">
            <div v-if="row.status === 'completed' || row.status === 'failed'">
              <el-progress :percentage="row.total_rows ? Math.round((row.processed_rows / row.total_rows) * 100) : 0"
                :status="row.status === 'failed' ? 'exception' : 'success'" :stroke-width="16">
                <span class="text-xs">{{ row.success_rows }}/{{ row.total_rows }}</span>
              </el-progress>
            </div>
            <div v-else-if="row.status === 'importing'">
              <el-progress :percentage="row.total_rows ? Math.round((row.processed_rows / row.total_rows) * 100) : 0"
                :stroke-width="16" indeterminate>
                <span class="text-xs">{{ row.processed_rows }}/{{ row.total_rows }}</span>
              </el-progress>
            </div>
            <div v-else class="text-gray-400 text-xs">{{ t('data_import_page.waiting') }}</div>
          </template>
        </el-table-column>
        <el-table-column :label="t('data_import_page.col_status')" width="90">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small" effect="dark">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="t('data_import_page.col_time')" width="160">
          <template #default="{ row }">{{ row.created_at?.slice(0, 16) }}</template>
        </el-table-column>
        <el-table-column :label="t('data_import_page.col_actions')" width="200" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" link type="primary" @click.stop="resumeProcess(row)">{{ t('data_import_page.resume') }}</el-button>
              <el-button v-if="row.status === 'uploaded' || row.status === 'preview' || row.status === 'validated'"
                size="small" link type="warning" @click.stop="cancelTask(row)">{{ t('actions.cancel') }}</el-button>
              <el-button size="small" link type="danger" @click.stop="deleteTask(row)">{{ t('actions.delete') }}</el-button>
            </el-space>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!tasks.length && !loading" :description="t('data_import_page.empty')" />
    </el-card>

    <UploadDialog ref="uploadDialogRef" :entity-types="entityTypes" @uploaded="handleUploaded" />

    <ImportWizard ref="wizardRef" @completed="handleCompleted" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload } from '@element-plus/icons-vue'
import { getEntityTypes, getImportTasks, cancelImport, deleteImportTask } from '../../api/dataImport'
import UploadDialog from './components/UploadDialog.vue'
import ImportWizard from './components/ImportWizard.vue'

const { t } = useI18n()

const loading = ref(false)
const tasks = ref([])
const entityTypes = ref({})
const filterEntity = ref('')
const filterStatus = ref('')
const uploadDialogRef = ref(null)
const wizardRef = ref(null)

const statusMap = { uploaded: 'info', preview: '', validated: 'success', importing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info' }
const statusKeys = ['uploaded', 'preview', 'validated', 'importing', 'completed', 'failed', 'cancelled']

const statusLabels = computed(() => ({
  uploaded: t('data_import_page.status.uploaded'),
  preview: t('data_import_page.status.preview'),
  validated: t('data_import_page.status.validated'),
  importing: t('data_import_page.status.importing'),
  completed: t('data_import_page.status.completed'),
  failed: t('data_import_page.status.failed'),
  cancelled: t('data_import_page.status.cancelled'),
}))

const statusOptions = computed(() =>
  statusKeys.map((value) => ({ value, label: statusLabels.value[value] }))
)

function statusType(s) { return statusMap[s] || 'info' }
function statusLabel(s) { return statusLabels.value[s] || s }

async function fetchEntityTypes() {
  try {
    const { data } = await getEntityTypes()
    entityTypes.value = data.data || {}
  } catch (e) { /* ignore */ }
}

async function fetchTasks() {
  loading.value = true
  try {
    const params = {}
    if (filterEntity.value) params.entity_type = filterEntity.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await getImportTasks(params)
    tasks.value = data.data || []
  } catch (e) {
    ElMessage.error(t('data_import_page.messages.fetch_fail'))
  } finally {
    loading.value = false
  }
}

function openUploadDialog() { uploadDialogRef.value?.open() }

function handleUploaded(task) {
  fetchTasks()
  wizardRef.value?.open(task.id)
}

function resumeProcess(row) {
  if (['uploaded', 'preview', 'validated'].includes(row.status)) {
    wizardRef.value?.open(row.id)
  } else {
    ElMessage.info(t('data_import_page.messages.cannot_resume'))
  }
}

function openTaskDetail(row) {
  if (row.status === 'completed' || row.status === 'failed') {
    wizardRef.value?.open(row.id)
  }
}

async function cancelTask(row) {
  try {
    await cancelImport(row.id)
    ElMessage.success(t('data_import_page.messages.cancelled_ok'))
    fetchTasks()
  } catch (e) { ElMessage.error(t('data_import_page.messages.cancel_fail')) }
}

function deleteTask(row) {
  ElMessageBox.confirm(
    t('data_import_page.messages.delete_confirm', { name: row.name }),
    t('actions.confirm'),
    {
      confirmButtonText: t('actions.delete'),
      cancelButtonText: t('actions.cancel'),
      type: 'warning',
    },
  ).then(async () => {
    try {
      await deleteImportTask(row.id)
      ElMessage.success(t('data_import_page.messages.deleted_ok'))
      fetchTasks()
    } catch (e) { ElMessage.error(t('data_import_page.messages.delete_fail')) }
  }).catch(() => {})
}

function handleCompleted() { fetchTasks() }

onMounted(() => {
  fetchEntityTypes()
  fetchTasks()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
.text-right { text-align: right; }
.text-gray-400 { color: #909399; }
.text-sm { font-size: 13px; }
.text-xs { font-size: 12px; }
.ml-4 { margin-left: 16px; }
.mr-1 { margin-right: 4px; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-2 { padding-top: 8px; padding-bottom: 8px; }
.mt-1 { margin-top: 4px; }
</style>
