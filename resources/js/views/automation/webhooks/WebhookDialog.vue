<template>
  <el-dialog v-model="visible" :title="isEdit ? t('webhook_dialog.edit_title') : t('webhook_dialog.create_title')" width="600px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" v-loading="saving">
      <el-form-item :label="t('webhook_dialog.name')" prop="name">
        <el-input v-model="form.name" :placeholder="t('webhook_dialog.name_placeholder')" maxlength="200" />
      </el-form-item>
      <el-form-item label="URL" prop="url">
        <el-input v-model="form.url" placeholder="https://example.com/webhook" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="8">
          <el-form-item :label="t('webhook_dialog.method')">
            <el-select v-model="form.method" style="width:100%">
              <el-option label="POST" value="POST" />
              <el-option label="GET" value="GET" />
              <el-option label="PUT" value="PUT" />
              <el-option label="PATCH" value="PATCH" />
              <el-option label="DELETE" value="DELETE" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('webhook_dialog.auth_type')">
            <el-select v-model="form.auth_type" style="width:100%">
              <el-option :label="t('webhook_dialog.auth.none')" value="none" />
              <el-option label="Basic" value="basic" />
              <el-option label="Bearer" value="bearer" />
              <el-option :label="t('webhook_dialog.auth.custom')" value="custom" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('webhook_dialog.enabled')">
            <el-switch v-model="form.is_active" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item :label="t('webhook_dialog.headers')">
        <div v-for="(header, idx) in form.headers" :key="idx" class="header-row mb-1">
          <el-row :gutter="8">
            <el-col :span="10">
              <el-input v-model="header.key" placeholder="Header" size="small" />
            </el-col>
            <el-col :span="12">
              <el-input v-model="header.value" placeholder="Value" size="small" />
            </el-col>
            <el-col :span="2">
              <el-button size="small" type="danger" link @click="form.headers.splice(idx, 1)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-col>
          </el-row>
        </div>
        <el-button type="primary" link @click="form.headers.push({ key: '', value: '' })">{{ t('webhook_dialog.add_header') }}</el-button>
      </el-form-item>

      <el-form-item :label="t('webhook_dialog.body_template')">
        <el-input v-model="bodyJson" type="textarea" :rows="4" placeholder='{"key": "value"}' />
      </el-form-item>

      <el-form-item :label="t('webhook_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>

      <el-form-item :label="t('webhook_dialog.retry_config')">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-input-number v-model="retryConfig.max_retries" :min="0" :max="10" style="width:100%" :placeholder="t('webhook_dialog.max_retries')" />
          </el-col>
          <el-col :span="12">
            <el-input-number v-model="retryConfig.delay_seconds" :min="0" :max="300" style="width:100%" :placeholder="t('webhook_dialog.delay_seconds')" />
          </el-col>
        </el-row>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" @click="save" :loading="saving">{{ t('actions.save') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import api from '../../../api/automation'

const { t } = useI18n()
const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const saving = ref(false)
const editId = ref(null)
const formRef = ref(null)
const bodyJson = ref('')

const defaultForm = () => ({
  name: '',
  url: '',
  method: 'POST',
  auth_type: 'none',
  auth_config: {},
  headers: [],
  body_template: {},
  is_active: true,
  description: '',
  retry_config: { max_retries: 3, delay_seconds: 10 },
  timeout_config: {},
})

const form = reactive(defaultForm())
const retryConfig = reactive({ max_retries: 3, delay_seconds: 10 })

const formRules = computed(() => ({
  name: [{ required: true, message: t('webhook_dialog.rules.name'), trigger: 'blur' }],
  url: [
    { required: true, message: t('webhook_dialog.rules.url'), trigger: 'blur' },
    { type: 'url', message: t('webhook_dialog.rules.url_valid'), trigger: 'blur' },
  ],
}))

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defaultForm())
  retryConfig.max_retries = 3
  retryConfig.delay_seconds = 10
  bodyJson.value = ''

  if (row) {
    form.name = row.name
    form.url = row.url
    form.method = row.method || 'POST'
    form.auth_type = row.auth_type || 'none'
    form.headers = row.headers || []
    form.body_template = row.body_template || {}
    form.is_active = row.is_active ?? true
    form.description = row.description || ''
    retryConfig.max_retries = row.retry_config?.max_retries ?? 3
    retryConfig.delay_seconds = row.retry_config?.delay_seconds ?? 10
    bodyJson.value = JSON.stringify(form.body_template, null, 2)
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  try {
    if (bodyJson.value.trim()) {
      form.body_template = JSON.parse(bodyJson.value)
    }
  } catch (e) {
    ElMessage.warning(t('webhook_dialog.messages.body_invalid'))
    form.body_template = {}
  }

  const headerObj = {}
  for (const h of form.headers) {
    if (h.key) headerObj[h.key] = h.value
  }

  form.retry_config = { ...retryConfig }
  form.auth_config = form.auth_type !== 'none' ? { key: '', value: '' } : {}

  saving.value = true
  try {
    const payload = { ...form, headers: headerObj }
    if (isEdit.value) {
      await api.updateWebhook(editId.value, payload)
      ElMessage.success(t('webhook_dialog.messages.updated'))
    } else {
      await api.createWebhook(payload)
      ElMessage.success(t('webhook_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('webhook_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>

<style scoped>
.mb-1 { margin-bottom: 4px; }
</style>
