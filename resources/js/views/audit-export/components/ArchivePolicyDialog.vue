<template>
  <el-dialog v-model="visible" :title="isEdit ? t('archive_policy_dialog.edit_title') : t('archive_policy_dialog.create_title')" width="520px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="130px" v-loading="saving">
      <el-form-item :label="t('archive_policy_dialog.name')" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item :label="t('archive_policy_dialog.log_type')" prop="type">
        <el-select v-model="form.type" style="width:100%" :disabled="isEdit">
          <el-option v-for="(lb, key) in typeOptions" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('archive_policy_dialog.archive_days')" prop="archive_after_days">
            <el-input-number v-model="form.archive_after_days" :min="1" :max="3650" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('archive_policy_dialog.delete_days')" prop="delete_after_days">
            <el-input-number v-model="form.delete_after_days" :min="1" :max="3650" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('archive_policy_dialog.disk')">
            <el-select v-model="form.archive_disk" style="width:100%">
              <el-option :label="t('archive_policy_dialog.disks.local')" value="local" />
              <el-option label="S3" value="s3" />
              <el-option :label="t('archive_policy_dialog.disks.public')" value="public" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('archive_policy_dialog.enabled')">
            <el-switch v-model="form.is_active" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('archive_policy_dialog.compress')">
        <el-switch v-model="form.compress_archive" />
      </el-form-item>
      <el-form-item :label="t('archive_policy_dialog.description')">
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
import { upsertArchivePolicy, updateArchivePolicy } from '../../../api/auditExport'

const { t } = useI18n()
const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const typeOptions = computed(() => ({
  audit: t('archive_policy_dialog.types.audit'),
  security: t('archive_policy_dialog.types.security'),
  error: t('archive_policy_dialog.types.error'),
  system: t('archive_policy_dialog.types.system'),
}))

const defForm = () => ({
  name: '', type: 'audit', archive_after_days: 90, delete_after_days: 365,
  archive_disk: 'local', compress_archive: true, is_active: true, description: '',
})

const form = reactive(defForm())
const formRules = computed(() => ({
  name: [{ required: true, message: t('archive_policy_dialog.rules.name'), trigger: 'blur' }],
  type: [{ required: true, message: t('archive_policy_dialog.rules.type'), trigger: 'change' }],
  archive_after_days: [{ required: true, message: t('archive_policy_dialog.rules.archive_days'), trigger: 'blur' }],
}))

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.type = row.type
    form.archive_after_days = row.archive_after_days
    form.delete_after_days = row.delete_after_days
    form.archive_disk = row.archive_disk || 'local'
    form.compress_archive = row.compress_archive ?? true
    form.is_active = row.is_active ?? true
    form.description = row.description || ''
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await updateArchivePolicy(editId.value, { ...form })
      ElMessage.success(t('archive_policy_dialog.messages.updated'))
    } else {
      await upsertArchivePolicy({ ...form })
      ElMessage.success(t('archive_policy_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('archive_policy_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
