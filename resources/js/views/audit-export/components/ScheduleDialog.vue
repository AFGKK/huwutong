<template>
  <el-dialog v-model="visible" :title="isEdit ? t('schedule_dialog.edit_title') : t('schedule_dialog.create_title')" width="550px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-form-item :label="t('schedule_dialog.name')" prop="name">
        <el-input v-model="form.name" :placeholder="t('schedule_dialog.name_ph')" maxlength="200" />
      </el-form-item>
      <el-form-item :label="t('schedule_dialog.cron')" prop="cron_expression">
        <el-input v-model="form.cron_expression" :placeholder="t('schedule_dialog.cron_ph')" />
        <div class="text-xs text-gray-400 mt-1">{{ t('schedule_dialog.cron_hint') }}</div>
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('schedule_dialog.format')">
            <el-select v-model="form.format" style="width:100%">
              <el-option label="CSV" value="csv" />
              <el-option label="JSON" value="json" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('schedule_dialog.max_records')">
            <el-input-number v-model="form.max_records" :min="100" :max="100000" :step="1000" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('schedule_dialog.notify_emails')">
        <el-select v-model="form.notification_emails" multiple filterable allow-create default-first-option
          :placeholder="t('schedule_dialog.email_ph')" style="width:100%">
        </el-select>
      </el-form-item>
      <el-form-item :label="t('schedule_dialog.compression')">
        <el-select v-model="form.compression" style="width:100%">
          <el-option :label="t('schedule_dialog.none')" value="none" />
          <el-option label="Gzip" value="gzip" />
          <el-option label="Zip" value="zip" />
        </el-select>
      </el-form-item>
      <el-divider>{{ t('schedule_dialog.filters') }}</el-divider>
      <el-form-item :label="t('schedule_dialog.log_type')">
        <el-select v-model="form.filters.type" clearable :placeholder="t('schedule_dialog.all')" style="width:100%">
          <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('schedule_dialog.action_prefix')">
        <el-input v-model="form.filters.action_prefix" :placeholder="t('schedule_dialog.prefix_ph')" />
      </el-form-item>
      <el-form-item :label="t('schedule_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
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
import { createSchedule, updateSchedule } from '../../../api/auditExport'

const { t } = useI18n()
const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const defForm = () => ({
  name: '', cron_expression: '0 2 * * *', format: 'csv',
  max_records: 50000, notification_emails: [], compression: 'none',
  filters: {}, description: '',
})

const form = reactive(defForm())
const logTypes = computed(() => ({
  audit: t('schedule_dialog.log_types.audit'),
  security: t('schedule_dialog.log_types.security'),
  error: t('schedule_dialog.log_types.error'),
  system: t('schedule_dialog.log_types.system'),
}))
const rules = computed(() => ({
  name: [{ required: true, message: t('schedule_dialog.validation.name'), trigger: 'blur' }],
  cron_expression: [{ required: true, message: t('schedule_dialog.validation.cron'), trigger: 'blur' }],
}))

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.cron_expression = row.cron_expression
    form.format = row.format || 'csv'
    form.max_records = row.max_records || 50000
    form.notification_emails = row.notification_emails || []
    form.compression = row.compression || 'none'
    form.filters = row.filters || {}
    form.description = row.description || ''
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const data = { ...form, is_active: true }
    if (isEdit.value) {
      await updateSchedule(editId.value, data)
      ElMessage.success(t('schedule_dialog.messages.updated'))
    } else {
      await createSchedule(data)
      ElMessage.success(t('schedule_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('schedule_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
