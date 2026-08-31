<template>
  <el-dialog v-model="visible" :title="channel?.id ? t('alert_channel_dialog.edit_title') : t('alert_channel_dialog.create_title')" width="540px" :close-on-click-modal="false" @close="reset">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" v-loading="saving">
      <el-form-item :label="t('alert_channel_dialog.name')" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item :label="t('alert_channel_dialog.type')" prop="type">
            <el-select v-model="form.type" style="width:100%">
              <el-option label="Slack" value="slack" />
              <el-option :label="t('alert_channel_dialog.types.dingtalk')" value="dingtalk" />
              <el-option :label="t('alert_channel_dialog.types.feishu')" value="feishu" />
              <el-option :label="t('alert_channel_dialog.types.wechat')" value="wechat" />
              <el-option label="Webhook" value="webhook" />
              <el-option :label="t('alert_channel_dialog.types.email')" value="email" />
              <el-option :label="t('alert_channel_dialog.types.sms')" value="sms" />
              <el-option :label="t('alert_channel_dialog.types.custom')" value="custom" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('alert_channel_dialog.enabled')">
            <el-switch v-model="form.is_enabled" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="Webhook URL" prop="webhook_url">
        <el-input v-model="form.config.webhook_url" placeholder="https://hooks.slack.com/..." />
      </el-form-item>
      <el-form-item v-if="['email','sms'].includes(form.type)" :label="t('alert_channel_dialog.recipients')">
        <el-input v-model="form.config.recipients" :placeholder="t('alert_channel_dialog.recipients_placeholder')" />
      </el-form-item>
      <el-form-item :label="t('alert_channel_dialog.description')">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ channel?.id ? t('actions.save') : t('actions.create') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { createChannel, updateChannel } from '@/api/alerting'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  channel: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const formRef = ref(null)
const saving = ref(false)

const form = reactive({
  name: '', type: 'slack', is_enabled: true, description: '',
  config: { webhook_url: '', recipients: '' },
})

const rules = computed(() => ({
  name: [{ required: true, message: t('alert_channel_dialog.rules.name'), trigger: 'blur' }],
  type: [{ required: true, message: t('alert_channel_dialog.rules.type'), trigger: 'change' }],
}))

function reset() {
  form.name = ''; form.type = 'slack'; form.is_enabled = true
  form.description = ''; form.config = { webhook_url: '', recipients: '' }
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = { name: form.name, type: form.type, is_enabled: form.is_enabled, description: form.description, config: { ...form.config } }
    if (form.type !== 'email' && form.type !== 'sms') { delete payload.config.recipients }
    if (props.channel?.id) {
      await updateChannel(props.channel.id, payload)
      ElMessage.success(t('alert_channel_dialog.messages.updated'))
    } else {
      await createChannel(payload)
      ElMessage.success(t('alert_channel_dialog.messages.created'))
    }
    emit('saved')
  } catch (e) { ElMessage.error(t('alert_channel_dialog.messages.failed')) } finally { saving.value = false }
}

watch(() => props.channel, (val) => {
  if (val) {
    form.name = val.name || ''; form.type = val.type || 'slack'; form.is_enabled = val.is_enabled !== false
    form.description = val.description || ''
    form.config = { webhook_url: val.config?.webhook_url || '', recipients: val.config?.recipients || '' }
  } else { reset() }
}, { immediate: true })
</script>
