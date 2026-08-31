<template>
  <el-dialog v-model="visible" :title="isEdit ? t('channel_dialog.edit_title') : t('channel_dialog.create_title')" width="620px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" v-loading="saving">
      <el-form-item :label="t('channel_dialog.name')" prop="name">
        <el-input v-model="form.name" :placeholder="t('channel_dialog.name_placeholder')" maxlength="200" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('channel_dialog.slug')" prop="slug">
            <el-input v-model="form.slug" :placeholder="t('channel_dialog.slug_placeholder')" maxlength="100" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('channel_dialog.type')" prop="type">
            <el-select v-model="form.type" style="width:100%">
              <el-option v-for="item in types" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('channel_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item :label="t('channel_dialog.status')">
            <el-select v-model="form.status" style="width:100%">
              <el-option :label="t('channel_dialog.status_active')" value="active" />
              <el-option :label="t('channel_dialog.status_inactive')" value="inactive" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('channel_dialog.public_reg')">
            <el-switch v-model="form.is_public" :active-text="t('channel_dialog.public')" :inactive-text="t('channel_dialog.private')" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item :label="t('channel_dialog.tags')">
        <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%">
          <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
        </el-select>
      </el-form-item>
      <el-divider>{{ t('channel_dialog.utm_defaults') }}</el-divider>
      <el-row :gutter="12">
        <el-col :span="8">
          <el-input v-model="utm.source" placeholder="UTM Source" size="small" />
        </el-col>
        <el-col :span="8">
          <el-input v-model="utm.medium" placeholder="UTM Medium" size="small" />
        </el-col>
        <el-col :span="8">
          <el-input v-model="utm.campaign" placeholder="UTM Campaign" size="small" />
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
import { createChannel, updateChannel } from '../../../api/invite-codes'

const { t } = useI18n()
const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const utm = reactive({ source: '', medium: '', campaign: '' })

const types = computed(() => [
  { value: 'promotional', label: t('channel_dialog.types.promotional') },
  { value: 'marketing', label: t('channel_dialog.types.marketing') },
  { value: 'partner', label: t('channel_dialog.types.partner') },
  { value: 'event', label: t('channel_dialog.types.event') },
  { value: 'social', label: t('channel_dialog.types.social') },
  { value: 'internal', label: t('channel_dialog.types.internal') },
])

const tagOptions = computed(() => [
  t('channel_dialog.tag_options.promo'),
  t('channel_dialog.tag_options.paid'),
  t('channel_dialog.tag_options.organic'),
  t('channel_dialog.tag_options.social'),
  t('channel_dialog.tag_options.email'),
])

const defForm = () => ({
  name: '', slug: '', description: '', type: 'promotional',
  status: 'active', is_public: false, tags: [],
  landing_config: {}, utm_defaults: {},
})

const form = reactive(defForm())

const rules = computed(() => ({
  name: [{ required: true, message: t('channel_dialog.rules.name'), trigger: 'blur' }],
  type: [{ required: true, message: t('channel_dialog.rules.type'), trigger: 'change' }],
}))

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  utm.source = ''; utm.medium = ''; utm.campaign = ''
  if (row) {
    form.name = row.name
    form.slug = row.slug || ''
    form.description = row.description || ''
    form.type = row.type || 'promotional'
    form.status = row.status || 'active'
    form.is_public = row.is_public ?? false
    form.tags = row.tags || []
    if (row.utm_defaults) {
      utm.source = row.utm_defaults.source || ''
      utm.medium = row.utm_defaults.medium || ''
      utm.campaign = row.utm_defaults.campaign || ''
    }
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    form.utm_defaults = { ...utm }
    if (isEdit.value) {
      await updateChannel(editId.value, { ...form })
      ElMessage.success(t('channel_dialog.messages.updated'))
    } else {
      await createChannel({ ...form })
      ElMessage.success(t('channel_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('channel_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>

<style scoped>
.el-divider { margin: 16px 0; }
</style>
