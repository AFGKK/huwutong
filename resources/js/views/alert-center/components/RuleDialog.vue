<template>
  <el-dialog v-model="visible" :title="rule?.id ? t('alert_rule_dialog.edit_title') : t('alert_rule_dialog.create_title')" width="640px" :close-on-click-modal="false" @close="reset">
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px" v-loading="saving">
      <el-row :gutter="16">
        <el-col :span="16">
          <el-form-item :label="t('alert_rule_dialog.name')" prop="name">
            <el-input v-model="form.name" maxlength="200" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('alert_rule_dialog.enabled')" prop="is_active">
            <el-switch v-model="form.is_active" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item :label="t('alert_rule_dialog.metric_type')" prop="metric_type">
            <el-select v-model="form.metric_type" style="width:100%">
              <el-option v-for="(label, key) in metricTypes" :key="key" :label="label" :value="key" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item :label="t('alert_rule_dialog.severity')" prop="severity">
            <el-select v-model="form.severity" style="width:100%">
              <el-option :label="t('alert_rule_dialog.severities.info')" value="info" />
              <el-option :label="t('alert_rule_dialog.severities.warning')" value="warning" />
              <el-option :label="t('alert_rule_dialog.severities.critical')" value="critical" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item :label="t('alert_rule_dialog.operator')" prop="condition_operator">
            <el-select v-model="form.condition_operator" style="width:100%">
              <el-option label=">" value="gt" />
              <el-option label=">=" value="gte" />
              <el-option label="<" value="lt" />
              <el-option label="<=" value="lte" />
              <el-option label="=" value="eq" />
              <el-option label="!=" value="neq" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item :label="t('alert_rule_dialog.threshold')" prop="threshold">
            <el-input-number v-model="form.threshold" :min="0" :precision="2" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('alert_rule_dialog.duration')" prop="duration_minutes">
            <el-input-number v-model="form.duration_minutes" :min="0" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('alert_rule_dialog.cooldown')" prop="cooldown_minutes">
            <el-input-number v-model="form.cooldown_minutes" :min="0" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item :label="t('alert_rule_dialog.daily_limit')" prop="max_alert_per_day">
            <el-input-number v-model="form.max_alert_per_day" :min="0" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('alert_rule_dialog.channels')">
            <el-select v-model="form.channel_ids" multiple :placeholder="t('alert_rule_dialog.channels_placeholder')" style="width:100%">
              <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('alert_rule_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-form-item :label="t('alert_rule_dialog.filters')">
        <el-input v-model="form.filters" type="textarea" :rows="2" placeholder='{"tenant_id": 1, "plan": "enterprise"}' />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ rule?.id ? t('actions.save') : t('actions.create') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { createRule, updateRule, getChannels, getMetricTypes } from '@/api/alerting'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  rule: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const formRef = ref(null)
const saving = ref(false)
const channels = ref([])
const metricTypes = ref({})

const form = reactive({
  name: '', metric_type: 'custom', severity: 'warning', is_active: true,
  condition_operator: 'gte', threshold: 0, duration_minutes: 0,
  cooldown_minutes: 60, max_alert_per_day: 10,
  channel_ids: [], description: '', filters: '',
})

const formRules = computed(() => ({
  name: [{ required: true, message: t('alert_rule_dialog.rules.name'), trigger: 'blur' }],
  metric_type: [{ required: true, message: t('alert_rule_dialog.rules.metric_type'), trigger: 'change' }],
}))

function reset() {
  form.name = ''; form.metric_type = 'custom'; form.severity = 'warning'; form.is_active = true
  form.condition_operator = 'gte'; form.threshold = 0; form.duration_minutes = 0
  form.cooldown_minutes = 60; form.max_alert_per_day = 10
  form.channel_ids = []; form.description = ''; form.filters = ''
}

function parseJson(str) {
  if (!str || str.trim() === '') return null
  try { return JSON.parse(str) } catch { return str }
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = { ...form, filters: parseJson(form.filters) }
    if (props.rule?.id) {
      await updateRule(props.rule.id, payload)
      ElMessage.success(t('alert_rule_dialog.messages.updated'))
    } else {
      await createRule(payload)
      ElMessage.success(t('alert_rule_dialog.messages.created'))
    }
    emit('saved')
  } catch (e) { ElMessage.error(t('alert_rule_dialog.messages.failed')) } finally { saving.value = false }
}

watch(() => props.rule, (val) => {
  if (val) {
    form.name = val.name || ''; form.metric_type = val.metric_type || 'custom'; form.severity = val.severity || 'warning'
    form.is_active = val.is_active !== false; form.condition_operator = val.condition_operator || 'gte'
    form.threshold = val.threshold || 0; form.duration_minutes = val.duration_minutes || 0
    form.cooldown_minutes = val.cooldown_minutes || 60; form.max_alert_per_day = val.max_alert_per_day ?? 10
    form.channel_ids = val.channels?.map(c => c.id) || []; form.description = val.description || ''
    form.filters = val.filters ? JSON.stringify(val.filters) : ''
  } else { reset() }
}, { immediate: true })

onMounted(async () => {
  try {
    const [chRes, mtRes] = await Promise.all([getChannels(), getMetricTypes()])
    channels.value = Array.isArray(chRes.data) ? chRes.data : chRes.data?.data || []
    metricTypes.value = mtRes.data || {}
  } catch { }
})
</script>
