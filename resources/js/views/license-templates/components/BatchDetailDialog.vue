<template>
  <el-dialog v-model="visible" title="批量生成详情" width="800px" :close-on-click-modal="false">
    <div v-loading="loading" v-if="task">
      <el-descriptions :column="3" border size="small" class="mb-4">
        <el-descriptions-item label="任务名">{{ task.name }}</el-descriptions-item>
        <el-descriptions-item label="模板">{{ task.template?.name }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(task.status)" size="small">{{ statusLabel(task.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="总数">{{ task.total_count }}</el-descriptions-item>
        <el-descriptions-item label="成功">
          <span class="text-success">{{ task.success_count }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="失败">
          <span class="text-danger">{{ task.failed_count }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="开始时间">{{ task.started_at }}</el-descriptions-item>
        <el-descriptions-item label="完成时间">{{ task.completed_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="错误">{{ task.error_message || '-' }}</el-descriptions-item>
      </el-descriptions>

      <el-table :data="task.items || []" stripe size="small" max-height="400">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column label="变量值" min-width="200">
          <template #default="{ row }">
            <span class="text-xs">{{ JSON.stringify(row.variables) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="License Key" prop="license?.license_key" width="200" />
        <el-table-column label="状态" prop="status" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="错误" prop="error_message" min-width="200" show-overflow-tooltip />
      </el-table>
    </div>
    <el-empty v-else-if="!loading" description="未找到任务" />
    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { getBatchTask } from '../../../api/licenseTemplate'

const props = defineProps({
  visible: { type: Boolean, default: false },
  taskId: { type: Number, default: null },
})
const emit = defineEmits(['update:visible'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const task = ref(null)
const loading = ref(false)

function statusTag(s) { return { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger', partial: 'warning' }[s] || 'info' }
function statusLabel(s) { return { pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', partial: '部分成功' }[s] || s }

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
