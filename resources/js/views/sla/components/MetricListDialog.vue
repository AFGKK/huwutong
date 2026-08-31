<template>
  <el-dialog v-model="visible" :title="t('metric_list_dialog.title')" width="800px" :close-on-click-modal="false">
    <div class="mb-3">
      {{ t('metric_list_dialog.subtitle', { name: contract?.name }) }}
      <el-button size="small" type="primary" class="ml-4" @click="showAdd">{{ t('metric_list_dialog.add') }}</el-button>
    </div>

    <el-table :data="contract?.metrics || []" stripe size="small" v-loading="loading">
      <el-table-column :label="t('metric_list_dialog.cols.name')" prop="name" min-width="140" />
      <el-table-column :label="t('metric_list_dialog.cols.type')" prop="metric_key" width="120">
        <template #default="{ row }">{{ metricLabels[row.metric_key] || row.metric_key }}</template>
      </el-table-column>
      <el-table-column :label="t('metric_list_dialog.cols.target')" width="100">
        <template #default="{ row }">{{ row.target_value }} {{ row.unit }}</template>
      </el-table-column>
      <el-table-column :label="t('metric_list_dialog.cols.warning')" prop="warning_threshold" width="100">
        <template #default="{ row }">{{ row.warning_threshold ?? '-' }}%</template>
      </el-table-column>
      <el-table-column :label="t('metric_list_dialog.cols.window')" prop="measurement_window" width="90">
        <template #default="{ row }">{{ windowLabels[row.measurement_window] || row.measurement_window }}</template>
      </el-table-column>
      <el-table-column :label="t('metric_list_dialog.cols.source')" prop="data_source" width="90" />
      <el-table-column :label="t('metric_list_dialog.cols.status')" width="70">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('metric_list_dialog.active') : t('metric_list_dialog.inactive') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('metric_list_dialog.cols.actions')" width="140" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="editMetric(row)">{{ t('actions.edit') }}</el-button>
          <el-popconfirm :title="t('metric_list_dialog.confirm_delete')" @confirm="removeMetric(row)">
            <template #reference>
              <el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <MetricDialog v-model:visible="metricFormVisible" :contract-id="contract?.id"
      :metric="editingMetric" @saved="onMetricSaved" />
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { deleteMetric, getContract } from '../../../api/sla'
import MetricDialog from './MetricDialog.vue'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  contract: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({
  get: () => props.visible,
  set: v => emit('update:visible', v),
})

const loading = ref(false)
const metricFormVisible = ref(false)
const editingMetric = ref(null)

const metricLabels = computed(() => ({
  response_time: t('metric_list_dialog.metrics.response_time'),
  resolution_time: t('metric_list_dialog.metrics.resolution_time'),
  uptime: t('metric_list_dialog.metrics.uptime'),
  availability: t('metric_list_dialog.metrics.availability'),
  ticket_backlog: t('metric_list_dialog.metrics.ticket_backlog'),
}))
const windowLabels = computed(() => ({
  daily: t('metric_list_dialog.windows.daily'),
  weekly: t('metric_list_dialog.windows.weekly'),
  monthly: t('metric_list_dialog.windows.monthly'),
  quarterly: t('metric_list_dialog.windows.quarterly'),
}))

function showAdd() {
  editingMetric.value = null
  metricFormVisible.value = true
}

function editMetric(metric) {
  editingMetric.value = { ...metric }
  metricFormVisible.value = true
}

async function removeMetric(metric) {
  try {
    await deleteMetric(metric.id)
    ElMessage.success(t('metric_list_dialog.messages.deleted'))
    await refreshMetrics()
  } catch (e) {
    ElMessage.error(t('metric_list_dialog.messages.delete_failed'))
  }
}

async function onMetricSaved() {
  metricFormVisible.value = false
  editingMetric.value = null
  await refreshMetrics()
}

async function refreshMetrics() {
  if (!props.contract?.id) return
  loading.value = true
  try {
    const { data } = await getContract(props.contract.id)
    if (props.contract) {
      props.contract.metrics = data.metrics || []
    }
  } catch { } finally {
    loading.value = false
  }
}

watch(() => props.visible, (v) => {
  if (v) refreshMetrics()
})
</script>
