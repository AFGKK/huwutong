<template>
  <el-dialog v-model="visible" :title="isEdit ? t('widget_dialog.edit_title') : t('widget_dialog.create_title')" width="600px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" v-loading="saving">
      <el-form-item :label="t('widget_dialog.title_label')" prop="title">
        <el-input v-model="form.title" maxlength="200" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('widget_dialog.type')" prop="type">
            <el-select v-model="form.type" style="width:100%" @change="onTypeChange">
              <el-option v-for="(lb, key) in types" :key="key" :label="lb" :value="key" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item :label="t('widget_dialog.width')">
            <el-input-number v-model="form.layout.w" :min="1" :max="12" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item :label="t('widget_dialog.height')">
            <el-input-number v-model="form.layout.h" :min="1" :max="6" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item :label="t('widget_dialog.data_source')">
        <el-select v-model="form.data_source.type" clearable style="width:100%" @change="onSourceChange">
          <el-option :label="t('widget_dialog.sources.stats')" value="stats" />
          <el-option :label="t('widget_dialog.sources.license_stats')" value="license_stats" />
          <el-option :label="t('widget_dialog.sources.recent_licenses')" value="recent_licenses" />
          <el-option :label="t('widget_dialog.sources.recent_tickets')" value="recent_tickets" />
          <el-option :label="t('widget_dialog.sources.subscription_stats')" value="subscription_stats" />
          <el-option :label="t('widget_dialog.sources.audit_stats')" value="audit_stats" />
          <el-option :label="t('widget_dialog.sources.user_stats')" value="user_stats" />
          <el-option :label="t('widget_dialog.sources.custom_query')" value="custom_query" />
        </el-select>
      </el-form-item>

      <el-form-item :label="t('widget_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>

      <el-divider>{{ t('widget_dialog.visual') }}</el-divider>
      <el-row :gutter="12">
        <el-col :span="8">
          <el-form-item :label="t('widget_dialog.border')">
            <el-switch v-model="visual.border" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('widget_dialog.refresh')">
            <el-input-number v-model="visual.refresh_interval" :min="0" :max="3600" :step="30" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
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
import { createWidget, updateWidget } from '@/api/dashboard'

const { t } = useI18n()
const props = defineProps({ dashboardId: { type: Number, default: null } })
const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const types = computed(() => ({
  stat: t('widget_dialog.types.stat'),
  chart: t('widget_dialog.types.chart'),
  list: t('widget_dialog.types.list'),
  metric: t('widget_dialog.types.metric'),
  table: t('widget_dialog.types.table'),
  iframe: t('widget_dialog.types.iframe'),
  html: t('widget_dialog.types.html'),
  alert: t('widget_dialog.types.alert'),
  report: t('widget_dialog.types.report'),
}))

const defForm = () => ({
  title: '', type: 'stat', description: '',
  layout: { w: 4, h: 2 }, data_source: { type: 'stats' },
  config: {}, visual_options: {},
})
const form = reactive(defForm())
const visual = reactive({ border: true, refresh_interval: 300 })
const rules = computed(() => ({
  title: [{ required: true, message: t('widget_dialog.validation.title'), trigger: 'blur' }],
  type: [{ required: true, message: t('widget_dialog.validation.type'), trigger: 'change' }],
}))

function onTypeChange(val) {
  if (val === 'iframe') form.data_source = { type: 'none' }
  else if (val === 'html') form.data_source = { type: 'none' }
  else if (!form.data_source?.type || form.data_source.type === 'none') form.data_source = { type: 'stats' }
}

function onSourceChange() {
  // auto
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  visual.border = true; visual.refresh_interval = 300
  if (row) {
    form.title = row.title
    form.type = row.type
    form.description = row.description || ''
    form.layout = row.layout || { w: 4, h: 2 }
    form.data_source = row.data_source || { type: 'stats' }
    form.config = row.config || {}
    form.visual_options = row.visual_options || {}
    visual.border = row.visual_options?.border ?? true
    visual.refresh_interval = row.visual_options?.refresh_interval ?? 300
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const data = {
      ...form,
      visual_options: { ...visual },
    }
    if (isEdit.value) {
      await updateWidget(editId.value, data)
      ElMessage.success(t('widget_dialog.messages.updated'))
    } else {
      await createWidget(props.dashboardId, data)
      ElMessage.success(t('widget_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('widget_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
