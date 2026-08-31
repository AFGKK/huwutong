<template>
  <el-dialog v-model="visible" :title="t('import_log_dialog.title')" width="900px" destroy-on-close>
    <div class="mb-4">
      <el-radio-group v-model="filterLevel" size="small" @change="fetchLogs">
        <el-radio-button label="">{{ t('import_log_dialog.all') }}</el-radio-button>
        <el-radio-button label="info">{{ t('import_log_dialog.success') }}</el-radio-button>
        <el-radio-button label="warning">{{ t('import_log_dialog.warning') }}</el-radio-button>
        <el-radio-button label="error">{{ t('import_log_dialog.error') }}</el-radio-button>
      </el-radio-group>
    </div>
    <el-table :data="logs" size="small" v-loading="loading" max-height="480" border stripe>
      <el-table-column label="#" type="index" width="50" />
      <el-table-column prop="row_number" :label="t('import_log_dialog.cols.row')" width="70" align="center" />
      <el-table-column :label="t('import_log_dialog.cols.level')" width="70" align="center">
        <template #default="{ row }">
          <el-tag :type="row.level === 'error' ? 'danger' : row.level === 'warning' ? 'warning' : 'success'" size="small">
            {{ row.level }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="action" :label="t('import_log_dialog.cols.action')" width="80" align="center" />
      <el-table-column prop="message" :label="t('import_log_dialog.cols.message')" min-width="200" show-overflow-tooltip />
      <el-table-column :label="t('import_log_dialog.cols.raw')" min-width="160">
        <template #default="{ row }">
          <pre class="log-data">{{ JSON.stringify(row.original_data, null, 1)?.slice(0, 100) }}</pre>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('import_log_dialog.cols.time')" width="140" />
    </el-table>
    <div v-if="total > perPage" class="mt-4 text-center">
      <el-pagination v-model:page="page" :total="total" :page-size="perPage" layout="prev, pager, next"
        @current-change="fetchLogs" small />
    </div>
  </el-dialog>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getImportLogs } from '../../../api/dataImport'

const { t } = useI18n()
const visible = ref(false)
const loading = ref(false)
const taskId = ref(null)
const logs = ref([])
const filterLevel = ref('')
const page = ref(1)
const perPage = ref(50)
const total = ref(0)

async function fetchLogs() {
  loading.value = true
  try {
    const { data } = await getImportLogs(taskId.value, {
      level: filterLevel.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    logs.value = data?.data || data || []
    total.value = data?.total || 0
  } catch (e) {
    // ignore
  } finally {
    loading.value = false
  }
}

async function open(id) {
  taskId.value = id
  visible.value = true
  page.value = 1
  filterLevel.value = ''
  await fetchLogs()
}

defineExpose({ open })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-center { text-align: center; }
.log-data { font-size: 11px; background: #f5f7fa; padding: 4px; border-radius: 3px; margin: 0; max-height: 40px; overflow: hidden; }
</style>
