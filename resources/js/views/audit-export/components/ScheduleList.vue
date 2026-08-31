<template>
  <div>
    <el-row :gutter="12" class="mb-4" justify="space-between" align="middle">
      <el-col :span="12"><span class="text-sm text-gray-400">{{ t('schedule_list.hint') }}</span></el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" size="small" @click="openCreate">
          <el-icon><Plus /></el-icon> {{ t('schedule_list.create') }}
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="schedules" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" :label="t('schedule_list.cols.name')" min-width="150" />
      <el-table-column prop="cron_expression" label="Cron" width="130">
        <template #default="{ row }"><code>{{ row.cron_expression }}</code></template>
      </el-table-column>
      <el-table-column :label="t('schedule_list.cols.format')" width="60" align="center">
        <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.format }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('schedule_list.cols.run_count')" width="80" align="center" prop="run_count" />
      <el-table-column :label="t('schedule_list.cols.last_run')" width="150">
        <template #default="{ row }">{{ row.last_run_at || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('schedule_list.cols.next_run')" width="150">
        <template #default="{ row }">{{ row.next_run_at || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('schedule_list.cols.status')" width="80" align="center">
        <template #default="{ row }">
          <el-switch :model-value="row.is_active" @click="toggleSchedule(row)" />
        </template>
      </el-table-column>
      <el-table-column :label="t('schedule_list.cols.actions')" width="160" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editSchedule(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('schedule_list.confirm_delete')" @confirm="deleteSchedule(row)">
              <template #reference>
                <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchSchedules(page)" @size-change="s => { perPage = s; fetchSchedules() }" />
    </div>

    <ScheduleDialog ref="dialogRef" @saved="fetchSchedules" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getSchedules, toggleSchedule as apiToggleSchedule, deleteSchedule as apiDeleteSchedule } from '../../../api/auditExport'
import ScheduleDialog from './ScheduleDialog.vue'

const { t } = useI18n()
const schedules = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const dialogRef = ref(null)

async function fetchSchedules(page = 1) {
  loading.value = true
  try {
    const { data } = await getSchedules({ page, per_page: perPage.value })
    schedules.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error(t('schedule_list.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

function openCreate() { dialogRef.value?.open('create') }
function editSchedule(row) { dialogRef.value?.open('edit', row) }

async function toggleSchedule(row) {
  try {
    const { data } = await apiToggleSchedule(row.id)
    row.is_active = data?.is_active ?? !row.is_active
    ElMessage.success(row.is_active ? t('schedule_list.messages.enabled') : t('schedule_list.messages.paused'))
  } catch (e) {
    ElMessage.error(t('schedule_list.messages.failed'))
  }
}

async function deleteSchedule(row) {
  try {
    await apiDeleteSchedule(row.id)
    ElMessage.success(t('schedule_list.messages.deleted'))
    fetchSchedules()
  } catch (e) {
    ElMessage.error(t('schedule_list.messages.delete_failed'))
  }
}

onMounted(() => fetchSchedules())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-sm { font-size: 13px; }
.text-gray-400 { color: #909399; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
</style>
