<template>
  <el-dialog v-model="visible" :title="t('batch_generate_dialog.title')" width="800px" :close-on-click-modal="false" @close="reset">
    <div class="mb-3">
      <b>{{ t('batch_generate_dialog.template') }}:</b>{{ template?.name }}
      <el-tag size="small" class="ml-2">{{ typeLabel(template?.type) }}</el-tag>
    </div>

    <el-steps :active="step" finish-status="success" class="mb-4">
      <el-step :title="t('batch_generate_dialog.steps.fill')" />
      <el-step :title="t('batch_generate_dialog.steps.preview')" />
      <el-step :title="t('batch_generate_dialog.steps.execute')" />
    </el-steps>

    <div v-if="step === 1">
      <div class="mb-3 flex items-center justify-between">
        <span class="text-sm text-gray-500">{{ t('batch_generate_dialog.fill_hint') }}</span>
        <div>
          <el-button size="small" @click="addRow">{{ t('batch_generate_dialog.add_row') }}</el-button>
          <el-button size="small" @click="addBatchRows">{{ t('batch_generate_dialog.add_batch') }}</el-button>
          <el-button size="small" type="danger" @click="clearRows">{{ t('batch_generate_dialog.clear') }}</el-button>
        </div>
      </div>

      <el-table :data="rows" stripe size="small" max-height="360">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column v-for="v in variables" :key="v.key" :label="v.label || v.key" min-width="140">
          <template #default="{ row }">
            <el-input v-model="row[v.key]" :placeholder="v.default_value || v.key" size="small"
              :required="v.is_required" />
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-3">
        <el-form :model="form" label-width="100px">
          <el-form-item :label="t('batch_generate_dialog.job_name')">
            <el-input v-model="form.name" :placeholder="t('batch_generate_dialog.job_name_ph')" maxlength="200" />
          </el-form-item>
          <el-form-item :label="t('batch_generate_dialog.customer')">
            <el-select v-model="form.customer_id" clearable filterable :placeholder="t('batch_generate_dialog.optional')">
              <el-option v-for="c in customers" :key="c.id" :label="c.name || c.email" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-form>
      </div>
    </div>

    <div v-if="step === 2">
      <div v-loading="previewLoading">
        <div class="mb-2 text-sm text-gray-500">{{ t('batch_generate_dialog.preview_hint') }}</div>
        <el-table :data="previewData" stripe size="small" v-if="previewData.length">
          <el-table-column :label="t('batch_generate_dialog.cols.product_id')" prop="product_id" width="80" />
          <el-table-column :label="t('batch_generate_dialog.cols.type')" prop="type" width="80" />
          <el-table-column :label="t('batch_generate_dialog.cols.seats')" prop="seats" width="70" />
          <el-table-column :label="t('batch_generate_dialog.cols.devices')" prop="max_devices" width="70" />
          <el-table-column :label="t('batch_generate_dialog.cols.expires')" prop="expires_at" width="160" />
          <el-table-column :label="t('batch_generate_dialog.cols.metadata')" min-width="200" show-overflow-tooltip>
            <template #default="{ row }">{{ JSON.stringify(row.metadata) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="!previewLoading" :description="t('batch_generate_dialog.preview_empty')" />
      </div>
    </div>

    <div v-if="step === 3">
      <div v-loading="generating">
        <el-result v-if="result" :icon="result.success > 0 ? 'success' : 'error'" :title="resultMsg">
          <template #extra>
            <div class="text-sm">
              <p>{{ t('batch_generate_dialog.result_counts', { success: result.success, failed: result.failed, total: result.total }) }}</p>
            </div>
          </template>
        </el-result>
      </div>
    </div>

    <template #footer>
      <el-button v-if="step > 1 && step < 3" @click="step--">{{ t('actions.prev') }}</el-button>
      <el-button v-if="step === 1" type="primary" :disabled="!form.name || rows.length === 0" @click="doPreview">
        {{ t('batch_generate_dialog.preview') }}
      </el-button>
      <el-button v-if="step === 2" type="primary" :disabled="previewData.length === 0" @click="doGenerate">
        {{ t('batch_generate_dialog.start') }}
      </el-button>
      <el-button v-if="step === 3" type="primary" @click="close">{{ t('batch_generate_dialog.done') }}</el-button>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getLicenseTemplateVariables,
  previewLicenseGeneration,
  batchGenerateLicenses,
} from '../../../api/licenseTemplate'
import customerApi from '../../../api/customer'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  template: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const step = ref(1)
const variables = ref([])
const rows = ref([])
const customers = ref([])
const previewData = ref([])
const previewLoading = ref(false)
const generating = ref(false)
const result = ref(null)

const form = reactive({ name: '', customer_id: null })

const resultMsg = computed(() => {
  if (!result.value) return ''
  if (result.value.failed === 0) return t('batch_generate_dialog.messages.all_ok', { n: result.value.success })
  if (result.value.success === 0) return t('batch_generate_dialog.messages.all_fail', { n: result.value.failed })
  return t('batch_generate_dialog.messages.partial', { success: result.value.success, failed: result.value.failed })
})

function typeLabel(type) {
  const key = { trial: 'trial', standard: 'standard', enterprise: 'enterprise', development: 'development' }[type]
  return key ? t(`batch_generate_dialog.types.${key}`) : type
}

function addRow() {
  const row = {}
  variables.value.forEach(v => { row[v.key] = v.default_value || '' })
  rows.value.push(row)
}

function addBatchRows() {
  ElMessageBox.prompt(t('batch_generate_dialog.batch_prompt'), t('batch_generate_dialog.add_batch'), {
    inputType: 'textarea',
    inputPlaceholder: t('batch_generate_dialog.batch_ph'),
  }).then(({ value }) => {
    const lines = value.trim().split('\n').filter(Boolean)
    lines.forEach(line => {
      const parts = line.split(',').map(s => s.trim())
      const row = {}
      variables.value.forEach((v, i) => { row[v.key] = parts[i] || v.default_value || '' })
      rows.value.push(row)
    })
  }).catch(() => {})
}

function clearRows() { rows.value = [] }

async function loadVariables() {
  if (!props.template?.id) return
  try {
    const { data } = await getLicenseTemplateVariables(props.template.id)
    variables.value = Array.isArray(data) && data.length > 0 ? data : [
      { key: 'customer_name', label: t('batch_generate_dialog.default_vars.customer_name'), variable_type: 'string', is_required: true, default_value: '' },
      { key: 'customer_email', label: t('batch_generate_dialog.default_vars.customer_email'), variable_type: 'string', is_required: false, default_value: '' },
    ]
    if (rows.value.length === 0) addRow()
  } catch { variables.value = [] }
}

async function doPreview() {
  previewLoading.value = true
  try {
    const { data } = await previewLicenseGeneration(props.template.id, {
      rows: rows.value.slice(0, 10),
      customer_id: form.customer_id,
    })
    previewData.value = Array.isArray(data) ? data : []
    step.value = 2
  } catch (e) { ElMessage.error(t('batch_generate_dialog.messages.preview_failed')) } finally { previewLoading.value = false }
}

async function doGenerate() {
  generating.value = true
  try {
    const { data } = await batchGenerateLicenses(props.template.id, {
      name: form.name,
      rows: rows.value,
      customer_id: form.customer_id || undefined,
    })
    result.value = {
      success: data.success_count || 0,
      failed: data.failed_count || 0,
      total: data.total_count || 0,
    }
    step.value = 3
  } catch (e) { ElMessage.error(t('batch_generate_dialog.messages.generate_failed')) } finally { generating.value = false }
}

function reset() {
  step.value = 1
  rows.value = []
  previewData.value = []
  result.value = null
  form.name = ''
  form.customer_id = null
}

function close() {
  emit('saved')
  visible.value = false
}

watch(() => props.template, (val) => {
  reset()
  if (val) loadVariables()
}, { immediate: true })

onMounted(async () => {
  try {
    const { data } = await customerApi.list({ per_page: 200 })
    customers.value = Array.isArray(data) ? data : data?.data || []
  } catch { }
})
</script>
