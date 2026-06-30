<template>
  <el-dialog v-model="visible" title="批量生成 License" width="800px" :close-on-click-modal="false" @close="reset">
    <div class="mb-3">
      <b>模板：</b>{{ template?.name }}
      <el-tag size="small" class="ml-2">{{ typeLabel(template?.type) }}</el-tag>
    </div>

    <el-steps :active="step" finish-status="success" class="mb-4">
      <el-step title="填充变量" />
      <el-step title="预览" />
      <el-step title="执行" />
    </el-steps>

    <!-- Step 1: 填充变量 -->
    <div v-if="step === 1">
      <div class="mb-3 flex items-center justify-between">
        <span class="text-sm text-gray-500">每行代表一个要生成的 License，填入变量值</span>
        <div>
          <el-button size="small" @click="addRow">添加行</el-button>
          <el-button size="small" @click="addBatchRows">批量添加</el-button>
          <el-button size="small" type="danger" @click="clearRows">清空</el-button>
        </div>
      </div>

      <el-table :data="rows" stripe size="small" max-height="360">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column v-for="v in variables" :key="v.key" :label="v.label || v.key" min-width="140">
          <template #default="{ row, $index }">
            <el-input v-model="row[v.key]" :placeholder="v.default_value || v.key" size="small"
              :required="v.is_required" />
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-3">
        <el-form :model="form" label-width="100px">
          <el-form-item label="任务名称">
            <el-input v-model="form.name" placeholder="例如：客户批量激活" maxlength="200" />
          </el-form-item>
          <el-form-item label="关联客户">
            <el-select v-model="form.customer_id" clearable filterable placeholder="可选">
              <el-option v-for="c in customers" :key="c.id" :label="c.name || c.email" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-form>
      </div>
    </div>

    <!-- Step 2: 预览 -->
    <div v-if="step === 2">
      <div v-loading="previewLoading">
        <div class="mb-2 text-sm text-gray-500">预览基于模板和变量生成的 License 数据（最多显示前10条）</div>
        <el-table :data="previewData" stripe size="small" v-if="previewData.length">
          <el-table-column label="产品ID" prop="product_id" width="80" />
          <el-table-column label="类型" prop="type" width="80" />
          <el-table-column label="座位数" prop="seats" width="70" />
          <el-table-column label="设备数" prop="max_devices" width="70" />
          <el-table-column label="到期时间" prop="expires_at" width="160" />
          <el-table-column label="元数据" min-width="200" show-overflow-tooltip>
            <template #default="{ row }">{{ JSON.stringify(row.metadata) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="!previewLoading" description="点击下方按钮生成预览" />
      </div>
    </div>

    <!-- Step 3: 执行结果 -->
    <div v-if="step === 3">
      <div v-loading="generating">
        <el-result v-if="result" :icon="result.success > 0 ? 'success' : 'error'" :title="resultMsg">
          <template #extra>
            <div class="text-sm">
              <p>成功：{{ result.success }} / 失败：{{ result.failed }} / 总数：{{ result.total }}</p>
            </div>
          </template>
        </el-result>
      </div>
    </div>

    <template #footer>
      <el-button v-if="step > 1 && step < 3" @click="step--">上一步</el-button>
      <el-button v-if="step === 1" type="primary" :disabled="!form.name || rows.length === 0" @click="doPreview">
        预览
      </el-button>
      <el-button v-if="step === 2" type="primary" :disabled="previewData.length === 0" @click="doGenerate">
        开始生成
      </el-button>
      <el-button v-if="step === 3" type="primary" @click="close">完成</el-button>
      <el-button @click="visible = false">取消</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getLicenseTemplateVariables,
  previewLicenseGeneration,
  batchGenerateLicenses,
} from '../../../api/licenseTemplate'
import customerApi from '../../../api/customer'

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
  if (result.value.failed === 0) return `成功生成 ${result.value.success} 个 License`
  if (result.value.success === 0) return `全部失败：${result.value.failed} 个`
  return `部分成功：${result.value.success} 成功，${result.value.failed} 失败`
})

function typeLabel(t) { return { trial: '试用', standard: '标准', enterprise: '企业', development: '开发' }[t] || t }

function addRow() {
  const row = {}
  variables.value.forEach(v => { row[v.key] = v.default_value || '' })
  rows.value.push(row)
}

function addBatchRows() {
  ElMessageBox.prompt('批量添加行数（每行一个变量组），用逗号分隔值，每行换行', '批量添加', {
    inputType: 'textarea',
    inputPlaceholder: '客户A,客户A@example.com\n客户B,客户B@example.com',
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
      { key: 'customer_name', label: '客户名称', variable_type: 'string', is_required: true, default_value: '' },
      { key: 'customer_email', label: '客户邮箱', variable_type: 'string', is_required: false, default_value: '' },
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
  } catch (e) { ElMessage.error('预览失败') } finally { previewLoading.value = false }
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
  } catch (e) { ElMessage.error('批量生成失败') } finally { generating.value = false }
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
