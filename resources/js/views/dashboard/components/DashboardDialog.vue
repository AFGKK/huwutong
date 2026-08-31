<template>
  <el-dialog v-model="visible" :title="isEdit ? t('dashboard_dialog.edit_title') : t('dashboard_dialog.create_title')" width="500px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" v-loading="saving">
      <el-form-item :label="t('dashboard_dialog.name')" prop="name">
        <el-input v-model="form.name" :placeholder="t('dashboard_dialog.name_placeholder')" maxlength="200" />
      </el-form-item>
      <el-form-item :label="t('dashboard_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('dashboard_dialog.layout_type')">
            <el-select v-model="form.layout_type" style="width:100%">
              <el-option :label="t('dashboard_dialog.layouts.grid')" value="grid" />
              <el-option :label="t('dashboard_dialog.layouts.free')" value="free" />
              <el-option :label="t('dashboard_dialog.layouts.flex')" value="flex" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('dashboard_dialog.columns')">
            <el-input-number v-model="form.columns" :min="1" :max="24" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('dashboard_dialog.tags')">
        <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%">
          <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
        </el-select>
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
import { createDashboard, updateDashboard } from '@/api/dashboard'

const { t } = useI18n()
const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const tagOptions = computed(() => [
  t('dashboard_dialog.tag_options.ops'),
  t('dashboard_dialog.tag_options.admin'),
  t('dashboard_dialog.tag_options.tech'),
  t('dashboard_dialog.tag_options.biz'),
])

const defForm = () => ({ name: '', description: '', layout_type: 'grid', columns: 12, tags: [] })
const form = reactive(defForm())
const formRules = computed(() => ({
  name: [{ required: true, message: t('dashboard_dialog.rules.name'), trigger: 'blur' }],
}))

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.description = row.description || ''
    form.layout_type = row.layout_type || 'grid'
    form.columns = row.columns || 12
    form.tags = row.tags || []
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await updateDashboard(editId.value, { ...form })
      ElMessage.success(t('dashboard_dialog.messages.updated'))
    } else {
      await createDashboard({ ...form })
      ElMessage.success(t('dashboard_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('dashboard_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
