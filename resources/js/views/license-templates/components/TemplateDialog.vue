<template>
  <el-dialog v-model="visible" :title="templateData?.id ? t('license_template_dialog.edit_title') : t('license_template_dialog.create_title')" width="640px"
    :close-on-click-modal="false" @close="reset">
    <el-tabs v-model="formTab" type="border-card">
      <el-tab-pane :label="t('license_template_dialog.tabs.basic')" name="basic">
        <el-form ref="formRef" :model="form" :rules="formRules" label-width="110px" v-loading="saving" class="mt-3">
          <el-row :gutter="16">
            <el-col :span="16">
              <el-form-item :label="t('license_template_dialog.name')" prop="name">
                <el-input v-model="form.name" maxlength="200" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item :label="t('actions.enable')">
                <el-switch v-model="form.is_active" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item :label="t('license_template_dialog.product')" prop="product_id">
            <el-select v-model="form.product_id" clearable style="width:100%">
              <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('license_template_dialog.description')">
            <el-input v-model="form.description" type="textarea" :rows="2" />
          </el-form-item>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item :label="t('license_template_dialog.type')" prop="type">
                <el-select v-model="form.type" style="width:100%">
                  <el-option :label="t('license_template_dialog.types.trial')" value="trial" />
                  <el-option :label="t('license_template_dialog.types.standard')" value="standard" />
                  <el-option :label="t('license_template_dialog.types.enterprise')" value="enterprise" />
                  <el-option :label="t('license_template_dialog.types.development')" value="development" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item :label="t('license_template_dialog.seats')" prop="seats">
                <el-input-number v-model="form.seats" :min="1" :max="10000" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item :label="t('license_template_dialog.max_devices')" prop="max_devices">
                <el-input-number v-model="form.max_devices" :min="1" :max="10000" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item :label="t('license_template_dialog.expiry_days')" prop="expiry_days">
            <el-input-number v-model="form.expiry_days" :min="0" style="width:100%" />
            <span class="ml-2 text-gray-400">{{ t('license_template_dialog.expiry_hint') }}</span>
          </el-form-item>
          <el-form-item :label="t('license_template_dialog.metadata')">
            <el-input v-model="form.metadata" type="textarea" :rows="3" placeholder='{"key": "value"}' />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <el-tab-pane :label="t('license_template_dialog.tabs.variables')" name="variables" v-if="templateData?.id || savedTemplateId">
        <div class="mb-3 text-sm text-gray-500">{{ t('license_template_dialog.vars_hint') }}</div>
        <div v-for="(v, i) in variables" :key="i" class="variable-row mb-2 p-2 bg-gray-50 rounded">
          <el-row :gutter="12">
            <el-col :span="5">
              <el-input v-model="v.key" :placeholder="t('license_template_dialog.var_key')" size="small" />
            </el-col>
            <el-col :span="5">
              <el-input v-model="v.label" :placeholder="t('license_template_dialog.var_label')" size="small" />
            </el-col>
            <el-col :span="4">
              <el-select v-model="v.variable_type" size="small" style="width:100%">
                <el-option :label="t('license_template_dialog.var_types.string')" value="string" />
                <el-option :label="t('license_template_dialog.var_types.number')" value="number" />
                <el-option :label="t('license_template_dialog.var_types.date')" value="date" />
                <el-option :label="t('license_template_dialog.var_types.boolean')" value="boolean" />
                <el-option :label="t('license_template_dialog.var_types.select')" value="select" />
              </el-select>
            </el-col>
            <el-col :span="3">
              <el-checkbox v-model="v.is_required">{{ t('license_template_dialog.required') }}</el-checkbox>
            </el-col>
            <el-col :span="5">
              <el-input v-model="v.default_value" :placeholder="t('license_template_dialog.default_value')" size="small" />
            </el-col>
            <el-col :span="2">
              <el-button type="danger" :icon="Delete" size="small" circle @click="variables.splice(i, 1)" />
            </el-col>
          </el-row>
        </div>
        <el-button size="small" @click="addVariable">{{ t('license_template_dialog.add_var') }}</el-button>
        <el-button size="small" type="primary" @click="saveVariables" :loading="savingVars">{{ t('license_template_dialog.save_vars') }}</el-button>
      </el-tab-pane>
    </el-tabs>

    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ templateData?.id ? t('actions.save') : t('actions.create') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import {
  createLicenseTemplate, updateLicenseTemplate,
  getLicenseTemplateVariables, saveLicenseTemplateVariables,
} from '../../../api/licenseTemplate'
import productApi from '../../../api/product'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  templateData: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const formRef = ref(null)
const saving = ref(false)
const savingVars = ref(false)
const savedTemplateId = ref(null)
const products = ref([])
const formTab = ref('basic')

const form = reactive({
  name: '', description: '', product_id: null, type: 'standard',
  seats: 1, max_devices: 1, expiry_days: null, metadata: '',
  is_active: true, sort_order: 0,
})

const formRules = computed(() => ({
  name: [{ required: true, message: t('license_template_dialog.validation.name'), trigger: 'blur' }],
}))

const variables = ref([])

function addVariable() {
  variables.value.push({ key: '', label: '', variable_type: 'string', is_required: false, default_value: '', options: null, description: '' })
}

function parseJson(str) {
  if (!str || str.trim() === '') return null
  try { return JSON.parse(str) } catch { return str }
}

async function save() {
  if (formTab.value !== 'basic') {
    formTab.value = 'basic'
    await nextTick()
  }
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    const payload = { ...form, metadata: parseJson(form.metadata), expiry_days: form.expiry_days || null }
    if (props.templateData?.id) {
      await updateLicenseTemplate(props.templateData.id, payload)
      ElMessage.success(t('license_template_dialog.messages.updated'))
      savedTemplateId.value = props.templateData.id
    } else {
      const { data } = await createLicenseTemplate(payload)
      ElMessage.success(t('license_template_dialog.messages.created'))
      savedTemplateId.value = data?.id || data?.data?.id
    }
    emit('saved')
  } catch (e) { ElMessage.error(t('messages.failed')) } finally { saving.value = false }
}

async function saveVariables() {
  if (!savedTemplateId.value) return
  savingVars.value = true
  try {
    await saveLicenseTemplateVariables(savedTemplateId.value, variables.value)
    ElMessage.success(t('license_template_dialog.messages.vars_saved'))
  } catch (e) { ElMessage.error(t('license_template_dialog.messages.vars_failed')) } finally { savingVars.value = false }
}

function reset() {
  form.name = ''
  form.description = ''
  form.product_id = null
  form.type = 'standard'
  form.seats = 1
  form.max_devices = 1
  form.expiry_days = null
  form.metadata = ''
  form.is_active = true
  form.sort_order = 0
  variables.value = []
  savedTemplateId.value = null
  formTab.value = 'basic'
}

watch(() => props.templateData, async (val) => {
  if (val) {
    form.name = val.name || ''
    form.description = val.description || ''
    form.product_id = val.product_id || null
    form.type = val.type || 'standard'
    form.seats = val.seats || 1
    form.max_devices = val.max_devices || 1
    form.expiry_days = val.expiry_days || null
    form.metadata = val.metadata ? JSON.stringify(val.metadata) : ''
    form.is_active = val.is_active !== false
    form.sort_order = val.sort_order || 0

    try {
      const { data } = await getLicenseTemplateVariables(val.id)
      variables.value = Array.isArray(data) ? data : []
    } catch { variables.value = [] }
    savedTemplateId.value = val.id
  } else {
    reset()
  }
}, { immediate: true })

onMounted(async () => {
  try {
    const { data } = await productApi.list({ per_page: 100 })
    products.value = Array.isArray(data) ? data : data?.data || []
  } catch { }
})
</script>
