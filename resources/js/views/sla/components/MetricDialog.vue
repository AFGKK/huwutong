<template>
  <el-dialog v-model="visible" :title="metric?.id ? '编辑指标' : '添加指标'" width="500px" :close-on-click-modal="false"
    @close="reset">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-form-item label="指标类型" prop="metric_key">
        <el-select v-model="form.metric_key" @change="onKeyChange">
          <el-option v-for="(label, key) in metricLabels" :key="key" :label="label" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item label="目标值" prop="target_value">
        <el-input-number v-model="form.target_value" :min="0" :precision="2" />
        <span class="ml-2 text-gray-400">{{ unitLabel(form.unit) }}</span>
      </el-form-item>
      <el-form-item label="告警阈值 %">
        <el-input-number v-model="form.warning_threshold" :min="0" :max="100" :precision="1" :step="5" />
      </el-form-item>
      <el-form-item label="统计周期" prop="measurement_window">
        <el-select v-model="form.measurement_window">
          <el-option label="每日" value="daily" />
          <el-option label="每周" value="weekly" />
          <el-option label="每月" value="monthly" />
          <el-option label="每季度" value="quarterly" />
        </el-select>
      </el-form-item>
      <el-form-item label="数据源" prop="data_source">
        <el-select v-model="form.data_source">
          <el-option label="工单系统" value="tickets" />
          <el-option label="技术支持" value="support" />
          <el-option label="运行时间" value="uptime" />
          <el-option label="自定义" value="custom" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ metric?.id ? '保存' : '添加' }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { createMetric, updateMetric } from '../../../api/sla'

const props = defineProps({
  visible: { type: Boolean, default: false },
  contractId: { type: Number, default: null },
  metric: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({
  get: () => props.visible,
  set: v => emit('update:visible', v),
})

const formRef = ref(null)
const saving = ref(false)

const metricLabels = { response_time: '响应时间', resolution_time: '解决时间', uptime: '正常运行', availability: '可用性', ticket_backlog: '工单积压' }
const unitMap = { response_time: 'minutes', resolution_time: 'minutes', uptime: 'percentage', availability: 'percentage', ticket_backlog: 'count' }

function unitLabel(u) {
  const map = { minutes: '分钟', hours: '小时', percentage: '%', count: '个' }
  return map[u] || u
}

const form = reactive({
  metric_key: 'response_time', name: '', target_value: 0,
  warning_threshold: 80, measurement_window: 'monthly', data_source: 'tickets',
  unit: 'minutes',
})

const rules = {
  metric_key: [{ required: true, message: '请选择指标类型', trigger: 'change' }],
  name: [{ required: true, message: '请输入指标名称', trigger: 'blur' }],
  target_value: [{ required: true, message: '请输入目标值', trigger: 'blur' }],
}

function onKeyChange(key) {
  form.unit = unitMap[key] || 'minutes'
  if (!form.name) {
    form.name = metricLabels[key] || key
  }
}

function reset() {
  form.metric_key = 'response_time'
  form.name = ''
  form.target_value = 0
  form.warning_threshold = 80
  form.measurement_window = 'monthly'
  form.data_source = 'tickets'
  form.unit = 'minutes'
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (props.metric?.id) {
      await updateMetric(props.metric.id, form)
      ElMessage.success('已更新')
    } else {
      await createMetric(props.contractId, form)
      ElMessage.success('已添加')
    }
    emit('saved')
  } catch (e) {
    ElMessage.error('操作失败')
  } finally {
    saving.value = false
  }
}

watch(() => props.metric, (val) => {
  if (val) {
    form.metric_key = val.metric_key || 'response_time'
    form.name = val.name || ''
    form.target_value = val.target_value || 0
    form.warning_threshold = val.warning_threshold ?? 80
    form.measurement_window = val.measurement_window || 'monthly'
    form.data_source = val.data_source || 'tickets'
    form.unit = val.unit || 'minutes'
  } else {
    reset()
  }
}, { immediate: true })
</script>
