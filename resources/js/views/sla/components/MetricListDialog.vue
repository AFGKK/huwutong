<template>
  <el-dialog v-model="visible" title="指标管理" width="800px" :close-on-click-modal="false">
    <div class="mb-3">
      <b>{{ contract?.name }}</b> 的 SLA 指标
      <el-button size="small" type="primary" class="ml-4" @click="showAdd">添加指标</el-button>
    </div>

    <el-table :data="contract?.metrics || []" stripe size="small" v-loading="loading">
      <el-table-column label="指标名称" prop="name" min-width="140" />
      <el-table-column label="类型" prop="metric_key" width="120">
        <template #default="{ row }">{{ metricLabels[row.metric_key] || row.metric_key }}</template>
      </el-table-column>
      <el-table-column label="目标值" width="100">
        <template #default="{ row }">{{ row.target_value }} {{ row.unit }}</template>
      </el-table-column>
      <el-table-column label="告警阈值" prop="warning_threshold" width="100">
        <template #default="{ row }">{{ row.warning_threshold ?? '-' }}%</template>
      </el-table-column>
      <el-table-column label="周期" prop="measurement_window" width="90">
        <template #default="{ row }">{{ windowLabels[row.measurement_window] || row.measurement_window }}</template>
      </el-table-column>
      <el-table-column label="数据源" prop="data_source" width="90" />
      <el-table-column label="状态" width="70">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="140" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="editMetric(row)">编辑</el-button>
          <el-popconfirm title="确认删除此指标？" @confirm="removeMetric(row)">
            <template #reference>
              <el-button size="small" type="danger" @click.stop>删除</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 指标编辑对话框 -->
    <MetricDialog v-model:visible="metricFormVisible" :contract-id="contract?.id"
      :metric="editingMetric" @saved="onMetricSaved" />
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { deleteMetric, getContract } from '../../../api/sla'
import MetricDialog from './MetricDialog.vue'

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
const metrics = ref([])
const metricFormVisible = ref(false)
const editingMetric = ref(null)

const metricLabels = { response_time: '响应时间', resolution_time: '解决时间', uptime: '正常运行', availability: '可用性', ticket_backlog: '工单积压' }
const windowLabels = { daily: '日', weekly: '周', monthly: '月', quarterly: '季度' }

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
    ElMessage.success('已删除')
    await refreshMetrics()
  } catch (e) {
    ElMessage.error('删除失败')
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
