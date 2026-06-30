<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑规则' : '创建规则'" width="780px" top="5vh" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-tabs v-model="activeTab">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-form-item label="规则名称" prop="name">
            <el-input v-model="form.name" placeholder="输入规则名称" maxlength="200" show-word-limit />
          </el-form-item>
          <el-form-item label="描述">
            <el-input v-model="form.description" type="textarea" :rows="2" placeholder="规则描述(可选)" />
          </el-form-item>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="分类" prop="category">
                <el-select v-model="form.category" style="width:100%">
                  <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="优先级" prop="priority">
                <el-input-number v-model="form.priority" :min="0" :max="9999" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="初始状态" prop="status">
                <el-select v-model="form.status" style="width:100%">
                  <el-option label="草稿" value="draft" />
                  <el-option label="启用" value="active" />
                  <el-option label="暂停" value="paused" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="标签">
                <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%"
                  placeholder="添加标签...">
                  <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- 触发器 -->
        <el-tab-pane label="触发器" name="trigger">
          <el-form-item label="触发类型" prop="trigger_type">
            <el-select v-model="form.trigger_type" style="width:100%" @change="onTriggerTypeChange">
              <el-option label="事件触发" value="event" />
              <el-option label="定时触发" value="schedule" />
              <el-option label="Webhook 触发" value="webhook" />
              <el-option label="条件触发" value="condition" />
            </el-select>
          </el-form-item>

          <!-- 事件触发配置 -->
          <template v-if="form.trigger_type === 'event'">
            <el-form-item label="事件类型" prop="trigger_config.event_type">
              <el-select v-model="eventType" filterable allow-create style="width:100%" placeholder="选择或输入事件类型">
                <el-option-group v-for="(group, key) in eventOptions" :key="key" :label="group.label">
                  <el-option v-for="(evLabel, evKey) in group.events" :key="evKey" :label="`${evLabel} (${evKey})`" :value="evKey" />
                </el-option-group>
              </el-select>
            </el-form-item>
            <el-alert class="mt-2" type="info" show-icon :closable="false">
              <template #title>
                支持通配符匹配，如 <code>license.*</code> 匹配所有 License 事件，<code>subscription.*</code> 匹配所有订阅事件
              </template>
            </el-alert>
          </template>

          <!-- 定时触发配置 -->
          <template v-if="form.trigger_type === 'schedule'">
            <el-form-item label="Cron 表达式">
              <el-input v-model="form.trigger_config.cron" placeholder="*/5 * * * *" />
            </el-form-item>
            <el-alert type="info" show-icon :closable="false">
              格式：分 时 日 月 周。示例：<code>0 2 * * *</code> 每天凌晨2点，<code>*/30 * * * *</code> 每30分钟
            </el-alert>
          </template>
        </el-tab-pane>

        <!-- 条件 -->
        <el-tab-pane label="条件" name="conditions">
          <el-form-item label="条件逻辑">
            <el-radio-group v-model="form.condition_logic">
              <el-radio value="all">满足所有条件(AND)</el-radio>
              <el-radio value="any">满足任一条件(OR)</el-radio>
            </el-radio-group>
          </el-form-item>

          <div v-for="(cond, idx) in form.conditions" :key="idx" class="condition-row">
            <el-row :gutter="8" align="middle">
              <el-col :span="7">
                <el-input v-model="cond.field" placeholder="字段名" size="small" />
              </el-col>
              <el-col :span="5">
                <el-select v-model="cond.operator" size="small" style="width:100%">
                  <el-option label="等于" value="eq" />
                  <el-option label="不等于" value="neq" />
                  <el-option label="大于" value="gt" />
                  <el-option label="大于等于" value="gte" />
                  <el-option label="小于" value="lt" />
                  <el-option label="小于等于" value="lte" />
                  <el-option label="包含" value="contains" />
                  <el-option label="以...开始" value="starts_with" />
                  <el-option label="以...结束" value="ends_with" />
                  <el-option label="在集合中" value="in" />
                  <el-option label="不在集合中" value="not_in" />
                  <el-option label="存在" value="exists" />
                  <el-option label="不存在" value="not_exists" />
                </el-select>
              </el-col>
              <el-col :span="8">
                <el-input v-model="cond.value" :placeholder="['exists','not_exists'].includes(cond.operator) ? '无需值' : '值'"
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
            + 添加条件
          </el-button>
        </el-tab-pane>

        <!-- 动作 -->
        <el-tab-pane label="动作" name="actions">
          <el-form-item label="执行模式">
            <el-radio-group v-model="form.action_execution">
              <el-radio value="sequential">顺序执行(失败停止)</el-radio>
              <el-radio value="first_success">首个成功即停止</el-radio>
            </el-radio-group>
          </el-form-item>

          <div v-for="(action, idx) in form.actions" :key="idx" class="action-row">
            <el-card shadow="never" class="mb-2">
              <div class="flex justify-between items-center mb-2">
                <strong>动作 #{{ idx + 1 }}</strong>
                <el-button size="small" type="danger" link @click="form.actions.splice(idx, 1)">
                  <el-icon><Delete /></el-icon> 移除
                </el-button>
              </div>
              <el-row :gutter="12">
                <el-col :span="6">
                  <el-form-item label="类型" :prop="'actions.' + idx + '.type'">
                    <el-select v-model="action.type" style="width:100%" @change="onActionTypeChange(action)">
                      <el-option v-for="(act, key) in availableActions" :key="key" :label="act.label" :value="key" />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="18">
                  <!-- Webhook 动作 -->
                  <template v-if="action.type === 'webhook'">
                    <el-form-item label="Webhook">
                      <el-select v-model="action.config.webhook_id" style="width:100%" placeholder="选择 Webhook 端点">
                        <el-option v-for="wh in webhooks" :key="wh.id" :label="wh.name" :value="wh.id" />
                      </el-select>
                    </el-form-item>
                  </template>
                  <!-- 发送邮件 -->
                  <template v-else-if="action.type === 'send_email'">
                    <el-row :gutter="8">
                      <el-col :span="8"><el-input v-model="action.config.to" placeholder="收件人" size="small" /></el-col>
                      <el-col :span="8"><el-input v-model="action.config.subject" placeholder="主题" size="small" /></el-col>
                      <el-col :span="8"><el-input v-model="action.config.template" placeholder="模板" size="small" /></el-col>
                    </el-row>
                  </template>
                  <!-- 通用 JSON -->
                  <template v-else>
                    <el-input v-model="action.config._raw" type="textarea" :rows="2"
                      placeholder='{"key": "value"}' size="small" />
                  </template>
                </el-col>
              </el-row>
            </el-card>
          </div>
          <el-button type="primary" link @click="addAction" class="mt-2">
            + 添加动作
          </el-button>
        </el-tab-pane>

        <!-- 速率限制 -->
        <el-tab-pane label="限制" name="limits">
          <el-form-item label="冷却时间(分)">
            <el-input-number v-model="form.cooldown_minutes" :min="0" :max="1440" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">两次执行之间最小间隔</span>
          </el-form-item>
          <el-form-item label="每小时上限">
            <el-input-number v-model="form.max_executions_per_hour" :min="0" :max="10000" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">0 = 不限制</span>
          </el-form-item>
          <el-form-item label="每天上限">
            <el-input-number v-model="form.max_executions_per_day" :min="0" :max="100000" style="width:200px" />
            <span class="ml-2 text-gray-400 text-xs">0 = 不限制</span>
          </el-form-item>
        </el-tab-pane>

        <!-- 关联 Webhook -->
        <el-tab-pane label="关联 Webhook" name="webhooks">
          <el-form-item label="Webhook 端点">
            <el-select v-model="form.webhook_ids" multiple style="width:100%" placeholder="选择关联的 Webhook">
              <el-option v-for="wh in webhooks" :key="wh.id" :label="wh.name" :value="wh.id">
                <span>{{ wh.name }}</span>
                <span class="text-gray-400 text-xs ml-2">{{ wh.url }}</span>
              </el-option>
            </el-select>
          </el-form-item>
          <el-alert type="info" show-icon :closable="false">
            关联的 Webhook 会在规则执行时自动作为动作触发，也可在动作列表中选择使用
          </el-alert>
        </el-tab-pane>
      </el-tabs>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="save" :loading="saving">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import api from '../../../api/automation'

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

const eventOptions = computed(() => {
  if (Array.isArray(props.triggerTypes)) return {}
  return props.triggerTypes?.event?.events
    ? { event: props.triggerTypes.event }
    : {}
})

const formRules = {
  name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
  trigger_type: [{ required: true, message: '请选择触发类型', trigger: 'change' }],
  category: [{ required: true, message: '请选择分类', trigger: 'change' }],
}

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
      ElMessage.success('规则已更新')
    } else {
      payload.webhook_ids = form.webhook_ids
      await api.createRule(payload)
      ElMessage.success('规则已创建')
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败')
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
