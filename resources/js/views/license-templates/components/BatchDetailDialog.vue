<template>
  <el-dialog v-model="visible" :title="t('batch_detail_dialog.title')" width="800px" :close-on-click-modal="false">
    <div v-loading="loading" v-if="task">
      <el-descriptions :column="3" border size="small" class="mb-4">
        <el-descriptions-item :label="t('batch_detail_dialog.name')">{{ task.name }}</el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.template')">{{ task.template?.name }}</el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.status')">
          <el-tag :type="statusTag(task.status)" size="small">{{ statusLabel(task.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.total')">{{ task.total_count }}</el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.success')">
          <span class="text-success">{{ task.success_count }}</span>
        </el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.failed')">
          <span class="text-danger">{{ task.failed_count }}</span>
        </el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.started')">{{ task.started_at }}</el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.completed')">{{ task.completed_at || '-' }}</el-descriptions-item>
        <el-descriptions-item :label="t('batch_detail_dialog.error')">{{ task.error_message || '-' }}</el-descriptions-item>
      </el-descriptions>

      <el-table :data="task.items || []" stripe size="small" max-height="400">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column :label="t('batch_detail_dialog.cols.variables')" min-width="200">
          <template #default="{ row }">
            <span class="text-xs">{{ JSON.stringify(row.variables) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="License Key" prop="license?.license_key" width="200" />
        <el-table-column :label="t('batch_detail_dialog.cols.status')" prop="status" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('batch_detail_dialog.cols.error')" prop="error_message" min-width="200" show-overflow-tooltip />
      </el-table>
    </div>
    <el-empty v-else-if="!loading" :description="t('batch_detail_dialog.empty')" />
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.close') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getBatchTask } from '../../../api/licenseTemplate'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  taskId: { type: Number, default: null },
})
const emit = defineEmits(['update:visible'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const task = ref(null)
const loading = ref(false)

function statusTag(s) { return { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger', partial: 'warning' }[s] || 'info' }
function statusLabel(s) {
  const key = { pending: 'pending', processing: 'processing', completed: 'completed', failed: 'failed', partial: 'partial' }[s]
  return key ? t(`batch_detail_dialog.statuses.${key}`) : s
}

async function load() {
  if (!props.taskId) return
  loading.value = true
  try {
    const { data } = await getBatchTask(props.taskId)
    task.value = data
  } catch { task.value = null } finally { loading.value = false }
}

watch(() => props.taskId, (v) => { if (v) load() })
watch(() => props.visible, (v) => { if (v && props.taskId) load() })
</script>
