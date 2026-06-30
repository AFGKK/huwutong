<template>
  <el-dialog v-model="visible" :title="templateData?.id ? '编辑 License 模板' : '新建 License 模板'" width="640px"
    :close-on-click-modal="false" @close="reset">
    <el-tabs v-model="formTab" type="border-card">
      <el-tab-pane label="基本信息" name="basic">
        <el-form ref="formRef" :model="form" :rules="formRules" label-width="110px" v-loading="saving" class="mt-3">
          <el-row :gutter="16">
            <el-col :span="16">
              <el-form-item label="模板名称" prop="name">
                <el-input v-model="form.name" maxlength="200" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="启用">
                <el-switch v-model="form.is_active" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="产品" prop="product_id">
            <el-select v-model="form.product_id" clearable style="width:100%">
              <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="描述">
            <el-input v-model="form.description" type="textarea" :rows="2" />
          </el-form-item>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="类型" prop="type">
                <el-select v-model="form.type" style="width:100%">
                  <el-option label="试用" value="trial" />
                  <el-option label="标准" value="standard" />
                  <el-option label="企业" value="enterprise" />
                  <el-option label="开发" value="development" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="座位数" prop="seats">
                <el-input-number v-model="form.seats" :min="1" :max="10000" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="设备数" prop="max_devices">
                <el-input-number v-model="form.max_devices" :min="1" :max="10000" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="有效期(天)" prop="expiry_days">
            <el-input-number v-model="form.expiry_days" :min="0" style="width:100%" />
            <span class="ml-2 text-gray-400">0 或空为永久</span>
          </el-form-item>
          <el-form-item label="元数据(JSON)">
            <el-input v-model="form.metadata" type="textarea" :rows="3" placeholder='{"key": "value"}' />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <el-tab-pane label="变量定义" name="variables" v-if="templateData?.id || savedTemplateId">
        <div class="mb-3 text-sm text-gray-500">定义模板变量，批量生成时每行可指定不同的变量值，在 metadata 中使用 {{变量名}} 引用</div>
        <div v-for="(v, i) in variables" :key="i" class="variable-row mb-2 p-2 bg-gray-50 rounded">
          <el-row :gutter="12">
            <el-col :span="5">
              <el-input v-model="v.key" placeholder="变量名" size="small" />
            </el-col>
            <el-col :span="5">
              <el-input v-model="v.label" placeholder="显示名" size="small" />
            </el-col>
            <el-col :span="4">
              <el-select v-model="v.variable_type" size="small" style="width:100%">
                <el-option label="字符串" value="string" />
                <el-option label="数字" value="number" />
                <el-option label="日期" value="date" />
                <el-option label="布尔" value="boolean" />
                <el-option label="选择" value="select" />
              </el-select>
            </el-col>
            <el-col :span="3">
              <el-checkbox v-model="v.is_required">必填</el-checkbox>
            </el-col>
            <el-col :span="5">
              <el-input v-model="v.default_value" placeholder="默认值" size="small" />
            </el-col>
            <el-col :span="2">
              <el-button type="danger" :icon="Delete" size="small" circle @click="variables.splice(i, 1)" />
            </el-col>
          </el-row>
        </div>
        <el-button size="small" @click="addVariable">添加变量</el-button>
        <el-button size="small" type="primary" @click="saveVariables" :loading="savingVars">保存变量</el-button>
      </el-tab-pane>
    </el-tabs>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ templateData?.id ? '保存' : '创建' }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import {
  createLicenseTemplate, updateLicenseTemplate,
  getLicenseTemplateVariables, saveLicenseTemplateVariables,
  getLicenseTemplateWithExtras,
} from '../../../api/licenseTemplate'
import productApi from '../../../api/product'

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

const formRules = {
  name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
}

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
      ElMessage.success('已更新')
      savedTemplateId.value = props.templateData.id
    } else {
      const { data } = await createLicenseTemplate(payload)
      ElMessage.success('已创建')
      savedTemplateId.value = data?.id || data?.data?.id
    }
    emit('saved')
  } catch (e) { ElMessage.error('操作失败') } finally { saving.value = false }
}

async function saveVariables() {
  if (!savedTemplateId.value) return
  savingVars.value = true
  try {
    await saveLicenseTemplateVariables(savedTemplateId.value, variables.value)
    ElMessage.success('变量已保存')
  } catch (e) { ElMessage.error('保存变量失败') } finally { savingVars.value = false }
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

    // Load variables
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
