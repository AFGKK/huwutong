<template>
  <el-dialog v-model="visible" :title="escalation?.id ? '编辑升级策略' : '新建升级策略'" width="540px" :close-on-click-modal="false" @close="reset">
    <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px" v-loading="saving">
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="升级级别" prop="escalation_level">
            <el-select v-model="form.escalation_level" style="width:100%">
              <el-option label="Lv.1" :value="1" />
              <el-option label="Lv.2" :value="2" />
              <el-option label="Lv.3" :value="3" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="延迟(分钟)" prop="after_minutes">
            <el-input-number v-model="form.after_minutes" :min="1" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="通知方式" prop="notify_type">
            <el-select v-model="form.notify_type" style="width:100%">
              <el-option label="Slack" value="slack" />
              <el-option label="邮件" value="email" />
              <el-option label="Webhook" value="webhook" />
              <el-option label="短信" value="sms" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="关联规则">
        <el-select v-model="form.alert_rule_id" clearable placeholder="不选则全局" style="width:100%">
          <el-option v-for="r in rules" :key="r.id" :label="r.name" :value="r.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="升级操作" prop="escalate_action">
        <el-select v-model="form.escalate_action" clearable style="width:100%">
          <el-option label="通知管理员" value="notify_admin" />
          <el-option label="创建工单" value="create_ticket" />
          <el-option label="调用 Webhook" value="run_webhook" />
        </el-select>
      </el-form-item>
      <el-form-item label="目标(JSON)">
        <el-input v-model="form.notify_target" type="textarea" :rows="3" placeholder='{"webhook_url": "https://...", "emails": ["admin@example.com"]}' />
      </el-form-item>
      <el-form-item label="消息模板">
        <el-input v-model="form.message_template" type="textarea" :rows="2" placeholder="可使用 {title} {message} {severity} {rule} {time} 变量" />
      </el-form-item>
      <el-form-item label="启用">
        <el-switch v-model="form.is_enabled" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ escalation?.id ? '保存' : '创建' }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { createEscalation, updateEscalation, getRules } from '../../../api/alerting'

const props = defineProps({
  visible: { type: Boolean, default: false },
  escalation: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const formRef = ref(null)
const saving = ref(false)
const rules = ref([])

const form = reactive({
  name: '', escalation_level: 1, after_minutes: 30,
  notify_type: 'slack', alert_rule_id: null,
  escalate_action: 'notify_admin',
  notify_target: '', message_template: '', is_enabled: true,
})

const formRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  escalation_level: [{ required: true, message: '请选择级别', trigger: 'change' }],
  after_minutes: [{ required: true, message: '请设置延迟时间', trigger: 'blur' }],
  notify_type: [{ required: true, message: '请选择通知方式', trigger: 'change' }],
}

function reset() {
  form.name = ''
  form.escalation_level = 1
  form.after_minutes = 30
  form.notify_type = 'slack'
  form.alert_rule_id = null
  form.escalate_action = 'notify_admin'
  form.notify_target = ''
  form.message_template = ''
  form.is_enabled = true
}

function parseJson(str) {
  if (!str || str.trim() === '') return null
  try { return JSON.parse(str) } catch { return str }
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = { ...form, notify_target: parseJson(form.notify_target) }
    if (props.escalation?.id) {
      await updateEscalation(props.escalation.id, payload)
      ElMessage.success('已更新')
    } else {
      await createEscalation(payload)
      ElMessage.success('已创建')
    }
    emit('saved')
  } catch (e) { ElMessage.error('操作失败') } finally { saving.value = false }
}

watch(() => props.escalation, (val) => {
  if (val) {
    form.name = val.name || ''
    form.escalation_level = val.escalation_level || 1
    form.after_minutes = val.after_minutes || 30
    form.notify_type = val.notify_type || 'slack'
    form.alert_rule_id = val.alert_rule_id || null
    form.escalate_action = val.escalate_action || ''
    form.notify_target = val.notify_target ? JSON.stringify(val.notify_target) : ''
    form.message_template = val.message_template || ''
    form.is_enabled = val.is_enabled !== false
  } else { reset() }
}, { immediate: true })

onMounted(async () => {
  try {
    const { data } = await getRules()
    rules.value = Array.isArray(data) ? data : data?.data || []
  } catch { }
})
</script>
