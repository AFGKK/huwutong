<template>
  <el-dialog v-model="visible" :title="metric?.id ? t('sla_metric_dialog.edit_title') : t('sla_metric_dialog.add_title')" width="500px" :close-on-click-modal="false"
    @close="reset">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-form-item :label="t('sla_metric_dialog.metric_type')" prop="metric_key">
        <el-select v-model="form.metric_key" @change="onKeyChange">
          <el-option v-for="(label, key) in metricLabels" :key="key" :label="label" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('sla_metric_dialog.name')" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item :label="t('sla_metric_dialog.target_value')" prop="target_value">
        <el-input-number v-model="form.target_value" :min="0" :precision="2" />
        <span class="ml-2 text-gray-400">{{ unitLabel(form.unit) }}</span>
      </el-form-item>
      <el-form-item :label="t('sla_metric_dialog.warning_threshold')">
        <el-input-number v-model="form.warning_threshold" :min="0" :max="100" :precision="1" :step="5" />
      </el-form-item>
      <el-form-item :label="t('sla_metric_dialog.measurement_window')" prop="measurement_window">
        <el-select v-model="form.measurement_window">
          <el-option :label="t('sla_metric_dialog.windows.daily')" value="daily" />
          <el-option :label="t('sla_metric_dialog.windows.weekly')" value="weekly" />
          <el-option :label="t('sla_metric_dialog.windows.monthly')" value="monthly" />
          <el-option :label="t('sla_metric_dialog.windows.quarterly')" value="quarterly" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('sla_metric_dialog.data_source')" prop="data_source">
        <el-select v-model="form.data_source">
          <el-option :label="t('sla_metric_dialog.sources.tickets')" value="tickets" />
          <el-option :label="t('sla_metric_dialog.sources.support')" value="support" />
          <el-option :label="t('sla_metric_dialog.sources.uptime')" value="uptime" />
          <el-option :label="t('sla_metric_dialog.sources.custom')" value="custom" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ metric?.id ? t('actions.save') : t('sla_metric_dialog.add_btn') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { createMetric, updateMetric } from '../../../api/sla'

const { t } = useI18n()

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

const metricLabels = computed(() => ({
  response_time: t('sla_metric_dialog.metrics.response_time'),
  resolution_time: t('sla_metric_dialog.metrics.resolution_time'),
  uptime: t('sla_metric_dialog.metrics.uptime'),
  availability: t('sla_metric_dialog.metrics.availability'),
  ticket_backlog: t('sla_metric_dialog.metrics.ticket_backlog'),
}))
const unitMap = { response_time: 'minutes', resolution_time: 'minutes', uptime: 'percentage', availability: 'percentage', ticket_backlog: 'count' }

function unitLabel(u) {
  const key = { minutes: 'minutes', hours: 'hours', percentage: 'percentage', count: 'count' }[u]
  return key ? t(`sla_metric_dialog.units.${key}`) : u
}

const form = reactive({
  metric_key: 'response_time', name: '', target_value: 0,
  warning_threshold: 80, measurement_window: 'monthly', data_source: 'tickets',
  unit: 'minutes',
})

const rules = computed(() => ({
  metric_key: [{ required: true, message: t('sla_metric_dialog.rules.metric_key'), trigger: 'change' }],
  name: [{ required: true, message: t('sla_metric_dialog.rules.name'), trigger: 'blur' }],
  target_value: [{ required: true, message: t('sla_metric_dialog.rules.target_value'), trigger: 'blur' }],
}))

function onKeyChange(key) {
  form.unit = unitMap[key] || 'minutes'
  if (!form.name) {
    form.name = metricLabels.value[key] || key
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
      ElMessage.success(t('sla_metric_dialog.messages.updated'))
    } else {
      await createMetric(props.contractId, form)
      ElMessage.success(t('sla_metric_dialog.messages.added'))
    }
    emit('saved')
  } catch (e) {
    ElMessage.error(t('sla_metric_dialog.messages.failed'))
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
