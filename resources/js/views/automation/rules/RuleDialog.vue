<template>
  <el-dialog v-model="visible" :title="dialogTitle" width="780px" top="5vh" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('automation_rule_dialog.tabs.basic')" name="basic">
          <el-form-item :label="t('automation_rule_dialog.fields.name')" prop="name">
            <el-input v-model="form.name" :placeholder="t('automation_rule_dialog.fields.name_ph')" maxlength="200" show-word-limit />
          </el-form-item>
          <el-form-item :label="t('automation_rule_dialog.fields.description')">
            <el-input v-model="form.description" type="textarea" :rows="2" :placeholder="t('automation_rule_dialog.fields.description_ph')" />
          </el-form-item>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item :label="t('automation_rule_dialog.fields.category')" prop="category">
                <el-select v-model="form.category" style="width:100%">
                  <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('automation_rule_dialog.fields.priority')" prop="priority">
                <el-input-number v-model="form.priority" :min="0" :max="9999" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item :label="t('automation_rule_dialog.fields.initial_status')" prop="status">
                <el-select v-model="form.status" style="width:100%">
                  <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('automation_rule_dialog.fields.tags')">
                <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%"
                  :placeholder="t('automation_rule_dialog.fields.tags_ph')">
                  <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane :label="t('flow_designer_page.node_types.trigger')" name="trigger">
          <el-form-item :label="t('automation_rule_dialog.fields.trigger_type')" prop="trigger_type">
            <el-select v-model="form.trigger_type" style="width:100%" @change="onTriggerTypeChange">
              <el-option v-for="opt in triggerTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </el-form-item>

          <template v-if="form.trigger_type === 'event'">
            <el-form-item :label="t('automation_rule_dialog.fields.event_type')" prop="trigger_config.event_type">
              <el-select v-model="eventType" filterable allow-create style="width:100%" :placeholder="t('automation_rule_dialog.fields.event_type_ph')">
                <el-option-group v-for="(group, key) in eventOptions" :key="key" :label="group.label">
                  <el-option v-for="(evLabel, evKey) in group.events" :key="evKey" :label="`${evLabel} (${evKey})`" :value="evKey" />
                </el-option-group>
              </el-select>
            </el-form-item>
            <el-alert class="mt-2" type="info" show-icon :closable="false">
              <template #title>
                <i18n-t keypath="automation_rule_dialog.alerts.wildcard_hint" tag="span">
                  <template #license><code>license.*</code></template>
                  <template #subscription><code>subscription.*</code></template>
                </i18n-t>
              </template>
            </el-alert>
          </template>

          <template v-if="form.trigger_type === 'schedule'">
            <el-form-item :label="t('automation_rule_dialog.fields.cron')">
              <el-input v-model="form.trigger_config.cron" placeholder="*/5 * * * *" />
            </el-form-item>
            <el-alert type="info" show-icon :closable="false">
              <i18n-t keypath="automation_rule_dialog.alerts.cron_hint" tag="span">
                <template #daily><code>0 2 * * *</code></template>
                <template #every30><code>*/30 * * * *</code></template>
              </i18n-t>
            </el-alert>
          </template>
        </el-tab-pane>

        <el-tab-pane :label="t('flow_designer_page.node_types.condition')" name="conditions">
          <el-form-item :label="t('automation_rule_dialog.fields.condition_logic')">
            <el-radio-group v-model="form.condition_logic">
              <el-radio v-for="opt in conditionLogicOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
            </el-radio-group>
          </el-form-item>

          <div v-for="(cond, idx) in form.conditions" :key="idx" class="condition-row">
            <el-row :gutter="8" align="middle">
              <el-col :span="7">
                <el-input v-model="cond.field" :placeholder="t('automation_rule_dialog.fields.field_name_ph')" size="small" />
              </el-col>
              <el-col :span="5">
                <el-select v-model="cond.operator" size="small" style="width:100%">
                  <el-option v-for="opt in operatorOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-col>
              <el-col :span="8">
                <el-input v-model="cond.value"
                  :placeholder="['exists','not_exists'].includes(cond.operator) ? t('automation_rule_dialog.fields.no_value_ph') : t('automation_rule_dialog.fields.value_ph')"
                  size="small" :disabled="['exists','not_exists'].includes(cond.operator)" />
              </el-col>
              <el-col :span="2">
                <el-button size="small" type="danger" link @click="form.conditions.splice(idx, 1)">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </el-col>
            </el-row>
          </div>
          <el-button type="primary" link @click="form.conditions.push({ field: '', operator: 'eq', value: '' })" class="mt-2">
            + {{ t('automation_rule_dialog.add_condition') }}
          </el-button>
        </el-tab-pane>

        <el-tab-pane :label="t('flow_designer_page.node_types.action')" name="actions">
          <el-form-item :label="t('automation_rule_dialog.fields.execution_mode')">
            <el-radio-group v-model="form.action_execution">
              <el-radio v-for="opt in executionModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
            </el-radio-group>
          </el-form-item>

          <div v-for="(action, idx) in form.actions" :key="idx" class="action-row">
            <el-card shadow="never" class="mb-2">
              <div class="flex justify-between items-center mb-2">
                <strong>{{ t('automation_rule_dialog.action_label', { n: idx + 1 }) }}</strong>
                <el-button size="small" type="danger" link @click="form.actions.splice(idx, 1)">
                  <el-icon><Delete /></el-icon> {{ t('automation_rule_dialog.remove') }}
                </el-button>
              </div>
              <el-row :gutter="12">
                <el-col :span="6">
                  <el-form-item :label="t('automation_rule_dialog.fields.type')" :prop="'actions.' + idx + '.type'">
                    <el-select v-model="action.type" style="width:100%" @change="onActionTypeChange(action)">
                      <el-option v-for="(act, key) in availableActions" :key="key" :label="act.label" :value="key" />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="18">
                  <template v-if="action.type === 'webhook'">
                    <el-form-item :label="t('automation_rule_dialog.fields.webhook')">
                      <el-select v-model="action.config.webhook_id" style="width:100%" :placeholder="t('automation_rule_dialog.fields.webhook_endpoint_ph')">
                        <el-option v-for="wh in webhooks" :key="wh.id" :label="wh.name" :value="wh.id" />
                      </el-select>
                    </el-form-item>
                  </template>
                  <template v-else-if="action.type === 'send_email'">
                    <el-row :gutter="8">
                      <el-col :span="8"><el-input v-model="action.config.to" :placeholder="t('automation_rule_dialog.fields.email_to_ph')" size="small" /></el-col>
                      <el-col :span="8"><el-input v-model="action.config.subject" :placeholder="t('automation_rule_dialog.fields.email_subject_ph')" size="small" /></el-col>
                      <el-col :span="8"><el-input v-model="action.config.template" :placeholder="t('automation_rule_dialog.fields.email_template_ph')" size="small" /></el-col>
                    </el-row>
                  </template>
                  <template v-else>
                    <el-input v-model="action.config._raw" type="textarea" :rows="2"
                      :placeholder="t('automation_rule_dialog.fields.config_json_ph')" size="small" />
                  </template>
                </el-col>
              </el-row>
            </el-card>
          </div>
          <el-button type="primary" link @click="addAction" class="mt-2">
            + {{ t('automation_rule_dialog.add_action') }}
          </el-button>
        </el-tab-pane>

        <el-tab-pane :label="t('automation_rule_dialog.tabs.limits')" name="limits">
          <el-form-item :label="t('automation_rule_dialog.fields.cooldown_minutes')">
            <el-input-number v-model="form.cooldown_minutes" :min="0" :max="1440" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">{{ t('automation_rule_dialog.fields.cooldown_hint') }}</span>
          </el-form-item>
          <el-form-item :label="t('automation_rule_dialog.fields.max_per_hour')">
            <el-input-number v-model="form.max_executions_per_hour" :min="0" :max="10000" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">{{ t('automation_rule_dialog.fields.no_limit_hint') }}</span>
          </el-form-item>
          <el-form-item :label="t('automation_rule_dialog.fields.max_per_day')">
            <el-input-number v-model="form.max_executions_per_day" :min="0" :max="100000" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">{{ t('automation_rule_dialog.fields.no_limit_hint') }}</span>
          </el-form-item>
        </el-tab-pane>

        <el-tab-pane :label="t('automation_rule_dialog.tabs.webhooks')" name="webhooks">
          <el-form-item :label="t('automation_rule_dialog.fields.webhook_endpoints')">
            <el-select v-model="form.webhook_ids" multiple style="width:100%" :placeholder="t('automation_rule_dialog.fields.webhooks_ph')">
              <el-option v-for="wh in webhooks" :key="wh.id" :label="wh.name" :value="wh.id">
                <span>{{ wh.name }}</span>
                <span class="text-gray-400 text-xs ml-2">{{ wh.url }}</span>
              </el-option>
            </el-select>
          </el-form-item>
          <el-alert type="info" show-icon :closable="false" :title="t('automation_rule_dialog.alerts.webhooks_hint')" />
        </el-tab-pane>
      </el-tabs>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" @click="save" :loading="saving">{{ t('actions.save') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import api from '../../../api/automation'

const { t } = useI18n()

const props = defineProps({
  categories: { type: Array, default: () => [] },
  triggerTypes: { type: [Array, Object], default: () => ({}) },
  availableActions: { type: [Array, Object], default: () => ({}) },
  webhooks: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const saving = ref(false)
const editId = ref(null)
const activeTab = ref('basic')
const formRef = ref(null)
const eventType = ref('')

const defaultForm = () => ({
  name: '',
  description: '',
  category: 'custom',
  trigger_type: 'event',
  trigger_config: {},
  conditions: [],
  condition_logic: 'all',
  actions: [{ type: 'webhook', config: {} }],
  action_execution: 'sequential',
  status: 'draft',
  priority: 0,
  cooldown_minutes: 0,
  max_executions_per_hour: 0,
  max_executions_per_day: 0,
  tags: [],
  webhook_ids: [],
})

const form = reactive(defaultForm())

const tagOptions = ['license', 'billing', 'auto', 'renewal', 'alert', 'compliance', 'security']

const dialogTitle = computed(() =>
  isEdit.value ? t('automation_rule_dialog.title_edit') : t('automation_rule_dialog.title_create')
)

const statusOptions = computed(() => [
  { value: 'draft', label: t('flow_designer_page.status.draft') },
  { value: 'active', label: t('automation_rule_dialog.status.active') },
  { value: 'paused', label: t('automation_rule_dialog.status.paused') },
])

const triggerTypeOptions = computed(() => [
  { value: 'event', label: t('automation_rule_dialog.trigger_types.event') },
  { value: 'schedule', label: t('automation_rule_dialog.trigger_types.schedule') },
  { value: 'webhook', label: t('automation_rule_dialog.trigger_types.webhook') },
  { value: 'condition', label: t('automation_rule_dialog.trigger_types.condition') },
])

const conditionLogicOptions = computed(() => [
  { value: 'all', label: t('automation_rule_dialog.condition_logic.all') },
  { value: 'any', label: t('automation_rule_dialog.condition_logic.any') },
])

const operatorOptions = computed(() => {
  const ops = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'starts_with', 'ends_with', 'in', 'not_in', 'exists', 'not_exists']
  return ops.map(value => ({ value, label: t(`automation_rule_dialog.operators.${value}`) }))
})

const executionModeOptions = computed(() => [
  { value: 'sequential', label: t('automation_rule_dialog.execution_mode.sequential') },
  { value: 'first_success', label: t('automation_rule_dialog.execution_mode.first_success') },
])

const eventOptions = computed(() => {
  if (Array.isArray(props.triggerTypes)) return {}
  return props.triggerTypes?.event?.events
    ? { event: props.triggerTypes.event }
    : {}
})

const rules = computed(() => ({
  name: [{ required: true, message: t('automation_rule_dialog.rules.name_required'), trigger: 'blur' }],
  trigger_type: [{ required: true, message: t('automation_rule_dialog.rules.trigger_type_required'), trigger: 'change' }],
  category: [{ required: true, message: t('automation_rule_dialog.rules.category_required'), trigger: 'change' }],
}))

watch(eventType, (val) => {
  form.trigger_config = { ...form.trigger_config, event_type: val }
})

function onTriggerTypeChange(val) {
  form.trigger_config = {}
}

function onActionTypeChange(action) {
  action.config = {}
}

function addAction() {
  form.actions.push({ type: 'webhook', config: {} })
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  activeTab.value = 'basic'
  Object.assign(form, defaultForm())

  if (row) {
    Object.assign(form, {
      name: row.name,
      description: row.description || '',
      category: row.category || 'custom',
      trigger_type: row.trigger_type || 'event',
      trigger_config: row.trigger_config || {},
      conditions: row.conditions || [],
      condition_logic: row.condition_logic || 'all',
      actions: row.actions || [{ type: 'webhook', config: {} }],
      action_execution: row.action_execution || 'sequential',
      status: row.status || 'draft',
      priority: row.priority ?? 0,
      cooldown_minutes: row.cooldown_minutes ?? 0,
      max_executions_per_hour: row.max_executions_per_hour ?? 0,
      max_executions_per_day: row.max_executions_per_day ?? 0,
      tags: row.tags || [],
      webhook_ids: row.webhooks?.map(w => w.id) || [],
    })
    eventType.value = form.trigger_config?.event_type ?? ''
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    const payload = { ...form }
    delete payload.webhook_ids

    if (isEdit.value) {
      await api.updateRule(editId.value, payload)
      ElMessage.success(t('automation_rule_dialog.messages.updated'))
    } else {
      payload.webhook_ids = form.webhook_ids
      await api.createRule(payload)
      ElMessage.success(t('automation_rule_dialog.messages.created'))
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('automation_rule_dialog.messages.save_failed'))
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>

<style scoped>
.condition-row { margin-bottom: 8px; padding: 4px 0; }
.condition-row:hover { background: #f5f7fa; border-radius: 4px; }
.action-row { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.mb-2 { margin-bottom: 8px; }
.ml-2 { margin-left: 8px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
