<template>
  <el-dialog v-model="visible" :title="contract?.id ? t('sla_contract_dialog.edit_title') : t('sla_contract_dialog.create_title')" width="640px" :close-on-click-modal="false"
    @close="reset">
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px" v-loading="saving">
      <el-form-item :label="t('sla_contract_dialog.name')" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.level')" prop="level">
        <el-select v-model="form.level">
          <el-option :label="t('sla_contract_dialog.levels.standard')" value="standard" />
          <el-option :label="t('sla_contract_dialog.levels.premium')" value="premium" />
          <el-option :label="t('sla_contract_dialog.levels.enterprise')" value="enterprise" />
          <el-option :label="t('sla_contract_dialog.levels.custom')" value="custom" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.customer')">
        <el-select v-model="form.customer_id" filterable clearable :placeholder="t('sla_contract_dialog.optional')">
          <el-option v-for="c in customers" :key="c.id" :label="c.name || c.email" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.effective_date')" prop="effective_date">
        <el-date-picker v-model="form.effective_date" type="date" value-format="YYYY-MM-DD" />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.expiry_date')">
        <el-date-picker v-model="form.expiry_date" type="date" value-format="YYYY-MM-DD" clearable />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.terms')">
        <el-input v-model="form.terms" type="textarea" :rows="3" placeholder='{"response_time": 30, "availability": 99.9}' />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.penalties')">
        <el-input v-model="form.penalties" type="textarea" :rows="2" placeholder='{"credits": 5, "escalation": true}' />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.business_hours')">
        <el-input v-model="form.business_hours" type="textarea" :rows="2"
          placeholder='{"timezone": "Asia/Shanghai", "workdays": [1,2,3,4,5]}' />
      </el-form-item>
      <el-form-item :label="t('sla_contract_dialog.scope')">
        <el-input v-model="form.scope" type="textarea" :rows="2" placeholder='{"modules": ["tickets", "support"]}' />
      </el-form-item>
      <el-form-item v-if="!contract?.id" :label="t('sla_contract_dialog.save_as_template')">
        <el-switch v-model="form.is_template" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ contract?.id ? t('actions.save') : t('actions.create') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { createContract, updateContract } from '../../../api/sla'
import customerApi from '../../../api/customer'

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

const formRef = ref(null)
const saving = ref(false)
const customers = ref([])

const form = reactive({
  name: '', level: 'standard', customer_id: null, description: '',
  effective_date: '', expiry_date: null, is_active: true, is_template: false,
  terms: '', penalties: '', business_hours: '', scope: '',
})

const formRules = computed(() => ({
  name: [{ required: true, message: t('sla_contract_dialog.rules.name'), trigger: 'blur' }],
  effective_date: [{ required: true, message: t('sla_contract_dialog.rules.effective_date'), trigger: 'blur' }],
}))

function reset() {
  form.name = ''
  form.level = 'standard'
  form.customer_id = null
  form.description = ''
  form.effective_date = ''
  form.expiry_date = null
  form.is_active = true
  form.is_template = false
  form.terms = ''
  form.penalties = ''
  form.business_hours = ''
  form.scope = ''
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
    const payload = {
      ...form,
      terms: parseJson(form.terms),
      penalties: parseJson(form.penalties),
      business_hours: parseJson(form.business_hours),
      scope: parseJson(form.scope),
    }
    if (props.contract?.id) {
      await updateContract(props.contract.id, payload)
      ElMessage.success(t('sla_contract_dialog.messages.updated'))
    } else {
      await createContract(payload)
      ElMessage.success(t('sla_contract_dialog.messages.created'))
    }
    emit('saved')
  } catch (e) {
    ElMessage.error(t('sla_contract_dialog.messages.failed'))
  } finally {
    saving.value = false
  }
}

watch(() => props.contract, (val) => {
  if (val) {
    form.name = val.name || ''
    form.level = val.level || 'standard'
    form.customer_id = val.customer_id || null
    form.description = val.description || ''
    form.effective_date = val.effective_date || ''
    form.expiry_date = val.expiry_date || null
    form.is_active = val.is_active !== false
    form.is_template = val.is_template || false
    form.terms = val.terms ? JSON.stringify(val.terms) : ''
    form.penalties = val.penalties ? JSON.stringify(val.penalties) : ''
    form.business_hours = val.business_hours ? JSON.stringify(val.business_hours) : ''
    form.scope = val.scope ? JSON.stringify(val.scope) : ''
  } else {
    reset()
  }
}, { immediate: true })

onMounted(async () => {
  try {
    const { data } = await customerApi.list()
    customers.value = Array.isArray(data) ? data : data?.data || []
  } catch { }
})
</script>
