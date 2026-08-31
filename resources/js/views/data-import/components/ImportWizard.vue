<template>
  <el-dialog v-model="visible" :title="task?.name || t('import_wizard.title')" width="800px" destroy-on-close>
    <div v-loading="loading">
      <el-steps :active="step" finish-status="success" class="mb-6">
        <el-step :title="t('import_wizard.steps.parse')" :status="step > 0 ? 'success' : step === 0 ? 'process' : 'wait'" />
        <el-step :title="t('import_wizard.steps.mapping')" :status="step > 1 ? 'success' : step === 1 ? 'process' : 'wait'" />
        <el-step :title="t('import_wizard.steps.validate')" :status="step > 2 ? 'success' : step === 2 ? 'process' : 'wait'" />
        <el-step :title="t('import_wizard.steps.execute')" :status="step > 3 ? 'success' : step === 3 ? 'process' : 'wait'" />
      </el-steps>

      <!-- Step 0: 解析 -->
      <div v-if="step === 0 && task">
        <el-alert :title="t('import_wizard.step0.alert')" type="info" :closable="false" />
        <div class="mt-4">
          <p class="text-sm"><strong>{{ t('import_wizard.file_label') }}</strong>{{ task.original_filename }}</p>
          <p class="text-sm"><strong>{{ t('import_wizard.type_label') }}</strong>{{ task.entity_type }}</p>
          <p class="text-sm"><strong>{{ t('import_wizard.size_label') }}</strong>{{ (task.file_size / 1024).toFixed(1) }} KB</p>
        </div>
        <div class="mt-4 text-center">
          <el-button type="primary" @click="parseFile" :loading="parsing">{{ t('import_wizard.parse_btn') }}</el-button>
        </div>
      </div>

      <!-- Step 1: 字段映射 -->
      <div v-if="step === 1 && task?.mappings">
        <el-alert :title="t('import_wizard.step1.alert')" type="info" :closable="false" class="mb-4" />
        <el-table :data="task.mappings" size="small" max-height="360">
          <el-table-column prop="source_field" :label="t('import_wizard.col_source')" min-width="140" />
          <el-table-column :label="t('import_wizard.col_target')" min-width="180">
            <template #default="{ row, $index }">
              <el-select v-model="row.target_field" filterable clearable style="width:160px"
                @change="mappingChanged">
                <el-option v-for="f in availableFields" :key="f.key" :label="f.label" :value="f.key">
                  <span>{{ f.label }}</span>
                  <span class="text-gray-400 ml-2 text-xs">{{ f.key }}</span>
                </el-option>
                <el-option :label="t('import_wizard.skip_import')" value="" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column :label="t('import_wizard.col_required')" width="50" align="center">
            <template #default="{ row }">
              <el-checkbox v-model="row.is_required" />
            </template>
          </el-table-column>
          <el-table-column :label="t('import_wizard.col_default')" width="120">
            <template #default="{ row }">
              <el-input v-model="row.default_value" size="small" :placeholder="t('import_wizard.col_default_ph')" />
            </template>
          </el-table-column>
        </el-table>

        <!-- 预设模板 -->
        <div class="mt-4">
          <el-space>
            <span class="text-sm text-gray-500">{{ t('import_wizard.apply_template') }}</span>
            <el-select v-model="selectedTemplate" :placeholder="t('import_wizard.select_template_ph')" style="width:200px" @change="applyTemplate">
              <el-option v-for="tpl in templates" :key="tpl.id" :label="tpl.name" :value="tpl.id" />
            </el-select>
          </el-space>
        </div>
      </div>

      <!-- Step 2: 验证预览 -->
      <div v-if="step === 2">
        <el-alert v-if="validationResult" :title="validationMessage" :type="validationType" :closable="false" class="mb-4" />

        <!-- 预览数据 -->
        <div v-if="task?.preview_data?.length">
          <h4 class="font-medium mb-2">{{ t('import_wizard.preview_title', { n: task.preview_data.length }) }}</h4>
          <el-table :data="task.preview_data" size="small" max-height="260" border stripe>
            <el-table-column v-for="col in previewColumns" :key="col" :prop="col" :label="col" min-width="100" show-overflow-tooltip />
          </el-table>
        </div>
      </div>

      <!-- Step 3: 执行导入 -->
      <div v-if="step === 3">
        <el-alert :title="t('import_wizard.step3.alert')" type="warning" :closable="false" class="mb-4" />
        <div v-if="task?.validation_errors">
          <p class="text-sm"><strong>{{ t('import_wizard.total_rows_label') }}</strong>{{ task.validation_errors.total_rows }}</p>
          <p class="text-sm"><strong>{{ t('import_wizard.error_rows_label') }}</strong>{{ task.validation_errors.error_rows }}</p>
          <p class="text-sm"><strong>{{ t('import_wizard.warning_rows_label') }}</strong>{{ task.validation_errors.warning_rows }}</p>
        </div>
        <div class="mt-4 text-center">
          <el-button type="primary" @click="startImport" :loading="importing" size="large">
            {{ importing ? t('import_wizard.importing_btn') : t('import_wizard.start_import_btn') }}
          </el-button>
        </div>

        <!-- 进度 -->
        <div v-if="importing" class="mt-4">
          <el-progress :percentage="importProgress" :stroke-width="24" striped>
            <span>{{ task?.processed_rows || 0 }} / {{ task?.total_rows || 0 }}</span>
          </el-progress>
        </div>
      </div>

      <!-- 完成 -->
      <div v-if="step === 4" class="text-center py-8">
        <el-result v-if="task?.status === 'completed'" icon="success" :title="t('import_wizard.completed_title')">
          <template #extra>
            <p class="text-sm">{{ t('import_wizard.completed_summary', {
              success: task?.success_rows,
              failed: task?.error_rows,
              total: task?.total_rows,
            }) }}</p>
            <el-button class="mt-4" type="primary" @click="viewLogs">{{ t('import_wizard.view_detail_logs') }}</el-button>
          </template>
        </el-result>
        <el-result v-else icon="error" :title="t('import_wizard.failed_title')">
          <template #extra>
            <p class="text-sm text-red">{{ task?.import_result?.error || t('import_wizard.default_error') }}</p>
            <el-button class="mt-4" @click="viewLogs">{{ t('import_wizard.view_error_logs') }}</el-button>
          </template>
        </el-result>
      </div>
    </div>

    <template #footer>
      <el-space>
        <el-button v-if="step > 0 && step < 4" @click="prevStep" :disabled="step === 3 && importing">{{ t('actions.prev') }}</el-button>
        <el-button v-if="step === 1" type="primary" @click="nextStep('validate')">{{ t('import_wizard.validate_btn') }}</el-button>
        <el-button v-if="step === 2 && task?.validation_errors?.error_rows === 0" type="primary" @click="nextStep('execute')">
          {{ t('import_wizard.next_execute_btn') }}
        </el-button>
        <el-button v-if="step === 4 || step === 0" @click="visible = false">{{ t('actions.close') }}</el-button>
      </el-space>
    </template>

    <!-- 日志对话框 -->
    <LogDialog ref="logDialogRef" :task-id="taskId" />
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { parseFile as apiParseFile, getImportTask, updateMappings, validateData, executeImport, getMappingTemplates, applyTemplate as apiApplyTemplate, getEntityFields } from '../../../api/dataImport'
import LogDialog from './LogDialog.vue'

const { t } = useI18n()

const visible = ref(false)
const loading = ref(false)
const taskId = ref(null)
const task = ref(null)
const step = ref(0)
const parsing = ref(false)
const importing = ref(false)
const availableFields = ref([])
const templates = ref([])
const selectedTemplate = ref(null)
const logDialogRef = ref(null)

const previewColumns = computed(() => {
  if (task.value?.preview_data?.length) {
    return Object.keys(task.value.preview_data[0])
  }
  return []
})

const validationResult = computed(() => task.value?.validation_errors)
const validationMessage = computed(() => {
  if (!validationResult.value) return ''
  const v = validationResult.value
  return t('import_wizard.validation_result', {
    total: v.total_rows,
    errors: v.error_rows,
    warnings: v.warning_rows,
  })
})
const validationType = computed(() => {
  if (!validationResult.value) return 'info'
  return validationResult.value.error_rows > 0 ? 'warning' : 'success'
})

const importProgress = computed(() => {
  if (!task.value?.total_rows) return 0
  return Math.round(((task.value.processed_rows || 0) / task.value.total_rows) * 100)
})

watch(visible, (val) => {
  if (!val) {
    task.value = null
    step.value = 0
  }
})

async function open(id) {
  taskId.value = id
  visible.value = true
  step.value = 0
  await loadTask()
  // 若已完成/失败则跳到结果页
  if (task.value?.status === 'completed' || task.value?.status === 'failed') {
    step.value = 4
  } else if (task.value?.status === 'preview' || task.value?.status === 'validated') {
    step.value = 1
    await loadFields()
    await loadTemplates()
  }
}

async function loadTask() {
  loading.value = true
  try {
    const { data } = await getImportTask(taskId.value)
    task.value = data.data
  } catch (e) {
    ElMessage.error(t('import_wizard.messages.fetch_fail'))
  } finally {
    loading.value = false
  }
}

async function loadFields() {
  if (!task.value?.entity_type) return
  try {
    const { data } = await getEntityFields(task.value.entity_type)
    availableFields.value = data.data || []
  } catch (e) { /* ignore */ }
}

async function loadTemplates() {
  try {
    const { data } = await getMappingTemplates({ entity_type: task.value?.entity_type })
    templates.value = data.data || []
  } catch (e) { /* ignore */ }
}

async function parseFile() {
  parsing.value = true
  try {
    const { data } = await apiParseFile(taskId.value)
    task.value = data.data
    step.value = 1
    await loadFields()
    await loadTemplates()
    ElMessage.success(t('import_wizard.messages.parse_ok'))
  } catch (e) {
    ElMessage.error(t('import_wizard.messages.parse_fail', { msg: e.response?.data?.message || '' }))
  } finally {
    parsing.value = false
  }
}

function mappingChanged() {
  // 标记修改
}

async function applyTemplate() {
  if (!selectedTemplate.value) return
  try {
    const { data } = await apiApplyTemplate(taskId.value, selectedTemplate.value)
    task.value = data.data
    ElMessage.success(t('import_wizard.messages.template_applied'))
  } catch (e) {
    ElMessage.error(t('import_wizard.messages.template_fail'))
  }
}

async function nextStep(target) {
  if (target === 'validate') {
    // 先保存映射
    loading.value = true
    try {
      if (task.value?.mappings?.length) {
        await updateMappings(taskId.value, task.value.mappings)
      }
      const { data } = await validateData(taskId.value)
      task.value = data.data
      step.value = 2
      ElMessage.success(t('import_wizard.messages.validate_ok'))
    } catch (e) {
      ElMessage.error(t('import_wizard.messages.validate_fail'))
    } finally {
      loading.value = false
    }
  } else if (target === 'execute') {
    step.value = 3
  }
}

function prevStep() {
  if (step.value > 0) step.value--
}

async function startImport() {
  importing.value = true
  try {
    const { data } = await executeImport(taskId.value)
    task.value = data.data
    step.value = 4
    if (data.data.status === 'completed') {
      ElMessage.success(t('import_wizard.messages.import_ok'))
    } else {
      ElMessage.error(t('import_wizard.messages.import_fail'))
    }
  } catch (e) {
    ElMessage.error(t('import_wizard.messages.import_exec_fail'))
    step.value = 4
  } finally {
    importing.value = false
  }
}

function viewLogs() {
  logDialogRef.value?.open(taskId.value)
}

defineExpose({ open })
</script>

<style scoped>
.mb-6 { margin-bottom: 24px; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-4 { margin-top: 16px; }
.text-sm { font-size: 13px; }
.text-center { text-align: center; }
.text-gray-500 { color: #909399; }
.text-gray-400 { color: #c0c4cc; }
.text-red { color: #f56c6c; }
.font-medium { font-weight: 500; }
.ml-2 { margin-left: 8px; }
.py-8 { padding: 32px 0; }
</style>
