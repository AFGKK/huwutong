<template>
  <el-dialog v-model="visible" :title="channel?.id ? '编辑通知渠道' : '新建通知渠道'" width="540px" :close-on-click-modal="false" @close="reset">
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="110px" v-loading="saving">
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="类型" prop="type">
            <el-select v-model="form.type" style="width:100%">
              <el-option label="Slack" value="slack" />
              <el-option label="钉钉" value="dingtalk" />
              <el-option label="飞书" value="feishu" />
              <el-option label="企业微信" value="wechat" />
              <el-option label="Webhook" value="webhook" />
              <el-option label="邮件" value="email" />
              <el-option label="短信" value="sms" />
              <el-option label="自定义" value="custom" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="启用">
            <el-switch v-model="form.is_enabled" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="Webhook URL" prop="webhook_url">
        <el-input v-model="form.config.webhook_url" placeholder="https://hooks.slack.com/..." />
      </el-form-item>
      <el-form-item v-if="['email','sms'].includes(form.type)" label="收件人">
        <el-input v-model="form.config.recipients" placeholder="多个用逗号分隔" />
      </el-form-item>
      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ channel?.id ? '保存' : '创建' }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { createChannel, updateChannel } from '../../../api/alerting'

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

const formRules = {
  name: [{ required: true, message: '请输入渠道名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择渠道类型', trigger: 'change' }],
}

function reset() {
  form.name = ''
  form.type = 'slack'
  form.is_enabled = true
  form.description = ''
  form.config = { webhook_url: '', recipients: '' }
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = {
      name: form.name,
      type: form.type,
      is_enabled: form.is_enabled,
      description: form.description,
      config: { ...form.config },
    }
    if (form.type !== 'email' && form.type !== 'sms') {
      delete payload.config.recipients
    }
    if (props.channel?.id) {
      await updateChannel(props.channel.id, payload)
      ElMessage.success('已更新')
    } else {
      await createChannel(payload)
      ElMessage.success('已创建')
    }
    emit('saved')
  } catch (e) { ElMessage.error('操作失败') } finally { saving.value = false }
}

watch(() => props.channel, (val) => {
  if (val) {
    form.name = val.name || ''
    form.type = val.type || 'slack'
    form.is_enabled = val.is_enabled !== false
    form.description = val.description || ''
    form.config = { webhook_url: val.config?.webhook_url || '', recipients: val.config?.recipients || '' }
  } else { reset() }
}, { immediate: true })
</script>
