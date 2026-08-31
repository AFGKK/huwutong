<template>
  <div>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="8">
        <el-input v-model="searchText" :placeholder="t('export_task_list.search_ph')" clearable size="small"
          @clear="fetchTasks" @keyup.enter="fetchTasks" />
      </el-col>
      <el-col :span="4">
        <el-select v-model="filterStatus" :placeholder="t('export_task_list.cols.status')" clearable size="small" style="width:100%" @change="fetchTasks">
          <el-option :label="t('export_task_list.all')" value="" />
          <el-option v-for="(lb, key) in statuses" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" size="small" @click="openCreateDialog">
          <el-icon><Plus /></el-icon> {{ t('export_task_list.new_export') }}
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="tasks" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" :label="t('export_task_list.cols.name')" min-width="160" />
      <el-table-column prop="format" :label="t('export_task_list.cols.format')" width="70" align="center">
        <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.format }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="status" :label="t('export_task_list.cols.status')" width="110">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">
            <el-icon v-if="row.status === 'processing'" class="is-loading"><Loading /></el-icon>
            {{ statuses[row.status] || row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('export_task_list.cols.progress')" width="200">
        <template #default="{ row }">
          <el-progress :percentage="progressPercent(row)" :stroke-width="16"
            :status="row.status === 'failed' ? 'exception' : ''" striped>
            {{ row.exported_records }}/{{ row.total_records }}
          </el-progress>
        </template>
      </el-table-column>
      <el-table-column :label="t('export_task_list.cols.size')" width="100" align="center">
        <template #default="{ row }">{{ formatSize(row.file_size_bytes) }}</template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('export_task_list.cols.created')" width="150" />
      <el-table-column :label="t('export_task_list.cols.actions')" width="180" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button v-if="row.status === 'completed' && row.file_path" size="small" type="primary" link
              @click="downloadFile(row)">
              <el-icon><Download /></el-icon> {{ t('export_task_list.download') }}
            </el-button>
            <el-popconfirm :title="t('export_task_list.confirm_delete')" @confirm="deleteTask(row)">
              <template #reference>
                <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchTasks(page)" @size-change="s => { perPage = s; fetchTasks() }" />
    </div>

    <el-dialog v-model="dialogVisible" :title="t('export_task_list.create_title')" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="t('export_task_list.cols.name')">
          <el-input v-model="form.name" :placeholder="t('export_task_list.name_ph')" maxlength="200" />
        </el-form-item>
        <el-form-item :label="t('export_task_list.export_format')">
          <el-radio-group v-model="form.format">
            <el-radio value="csv">CSV</el-radio>
            <el-radio value="json">JSON</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-divider>{{ t('export_task_list.filters') }}</el-divider>
        <el-form-item :label="t('export_task_list.log_type')">
          <el-select v-model="form.filters.type" clearable :placeholder="t('export_task_list.all_types')" style="width:100%">
            <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('export_task_list.action_prefix')">
          <el-select v-model="form.filters.action_prefix" clearable :placeholder="t('export_task_list.prefix_ph')" style="width:100%">
            <el-option v-for="p in actionPrefixes" :key="p" :label="p" :value="p" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item :label="t('export_task_list.date_from')">
              <el-date-picker v-model="form.filters.date_from" type="date" :placeholder="t('export_task_list.start')" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('export_task_list.date_to')">
              <el-date-picker v-model="form.filters.date_to" type="date" :placeholder="t('export_task_list.end')" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('export_task_list.search')">
          <el-input v-model="form.filters.search" :placeholder="t('export_task_list.fulltext_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">{{ t('export_task_list.create_btn') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus, Download, Loading } from '@element-plus/icons-vue'
import { getExportTasks, createExportTask, deleteExportTask, downloadExportFileUrl } from '../../../api/auditExport'

const { t } = useI18n()

const tasks = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const searchText = ref('')
const filterStatus = ref('')
const dialogVisible = ref(false)
const creating = ref(false)

const form = ref({ name: '', format: 'csv', filters: {} })

const statuses = computed(() => ({
  pending: t('export_task_list.statuses.pending'),
  processing: t('export_task_list.statuses.processing'),
  completed: t('export_task_list.statuses.completed'),
  failed: t('export_task_list.statuses.failed'),
  cancelled: t('export_task_list.statuses.cancelled'),
}))
const logTypes = computed(() => ({
  audit: t('export_task_list.log_types.audit'),
  security: t('export_task_list.log_types.security'),
  error: t('export_task_list.log_types.error'),
  system: t('export_task_list.log_types.system'),
}))
const actionPrefixes = ['license.*', 'subscription.*', 'customer.*', 'invoice.*', 'device.*', 'user.*', 'security.*']

function statusTag(s) { return { pending: '', processing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info' }[s] || '' }
function progressPercent(row) {
  if (!row.total_records) return 0
  return Math.min(100, Math.round((row.exported_records / row.total_records) * 100))
}
function formatSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / 1048576).toFixed(1) + 'MB'
}

async function fetchTasks(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (searchText.value) params.search = searchText.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await getExportTasks(params)
    tasks.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error(t('export_task_list.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  form.value = { name: '', format: 'csv', filters: {} }
  dialogVisible.value = true
}

async function handleCreate() {
  if (!form.value.name) { ElMessage.warning(t('export_task_list.messages.name_required')); return }
  creating.value = true
  try {
    await createExportTask({ ...form.value })
    ElMessage.success(t('export_task_list.messages.created'))
    dialogVisible.value = false
    fetchTasks()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('export_task_list.messages.create_failed'))
  } finally {
    creating.value = false
  }
}

async function deleteTask(row) {
  try {
    await deleteExportTask(row.id)
    ElMessage.success(t('export_task_list.messages.deleted'))
    fetchTasks()
  } catch (e) {
    ElMessage.error(t('export_task_list.messages.delete_failed'))
  }
}

function downloadFile(row) {
  window.open(downloadExportFileUrl(row.id), '_blank')
}

onMounted(() => fetchTasks())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
:deep(.el-progress__text) { font-size: 12px !important; }
</style>
