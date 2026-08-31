<template>
  <el-drawer v-model="visible" :title="t('rule_detail_drawer.title', { name: rule?.name ?? '' })" size="700px" destroy-on-close>
    <template v-if="rule">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item :label="t('rule_detail_drawer.name')" :span="2">{{ rule.name }}</el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.category')">
          <el-tag size="small" :type="categoryTagType(rule.category)">{{ categoryLabel(rule.category) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.status')">
          <el-tag :type="rule.status === 'active' ? 'success' : rule.status === 'paused' ? 'warning' : 'info'">
            {{ statusLabel(rule.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.trigger')" :span="2">
          <el-tag>{{ triggerLabel(rule.trigger_type) }}</el-tag>
          <span class="ml-2 text-xs text-gray-400" v-if="rule.trigger_config?.event_type">
            {{ t('rule_detail_drawer.event') }}: <code>{{ rule.trigger_config.event_type }}</code>
          </span>
        </el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.priority')">{{ rule.priority }}</el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.executions')">{{ rule.execution_count }}</el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.success_rate')" v-if="rule.execution_count > 0">
          <el-progress :percentage="Math.round(rule.success_count / rule.execution_count * 100)" :stroke-width="12" />
        </el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.created')">{{ rule.created_at }}</el-descriptions-item>
        <el-descriptions-item :label="t('rule_detail_drawer.description')" :span="2" v-if="rule.description">{{ rule.description }}</el-descriptions-item>
      </el-descriptions>

      <el-divider>{{ t('rule_detail_drawer.conditions', { logic: rule.condition_logic === 'all' ? 'AND' : 'OR' }) }}</el-divider>
      <div v-if="!rule.conditions?.length" class="text-gray-400 text-sm">{{ t('rule_detail_drawer.no_conditions') }}</div>
      <el-timeline v-else>
        <el-timeline-item v-for="(cond, idx) in rule.conditions" :key="idx">
          <span class="text-sm"><code>{{ cond.field }}</code> {{ operatorLabel(cond.operator) }} <code>{{ cond.value }}</code></span>
        </el-timeline-item>
      </el-timeline>

      <el-divider>{{ t('rule_detail_drawer.actions', { mode: rule.action_execution === 'sequential' ? t('rule_detail_drawer.sequential') : t('rule_detail_drawer.first_success') }) }}</el-divider>
      <el-timeline>
        <el-timeline-item v-for="(action, idx) in rule.actions" :key="idx" :color="'#0f172a'">
          <div class="text-sm font-medium">{{ actionLabel(action.type) }}</div>
          <div class="text-xs text-gray-400" v-if="action.config">{{ t('rule_detail_drawer.config') }}: {{ JSON.stringify(action.config) }}</div>
        </el-timeline-item>
      </el-timeline>

      <el-divider v-if="rule.webhooks?.length">{{ t('rule_detail_drawer.webhooks') }}</el-divider>
      <div v-if="rule.webhooks?.length">
        <el-tag v-for="wh in rule.webhooks" :key="wh.id" class="mr-2 mb-2">{{ wh.name }}</el-tag>
      </div>

      <el-divider>{{ t('rule_detail_drawer.recent_exec') }}</el-divider>
      <el-table :data="rule.executions || []" size="small" max-height="300">
        <el-table-column prop="created_at" :label="t('rule_detail_drawer.cols.time')" width="160" />
        <el-table-column prop="status" :label="t('rule_detail_drawer.cols.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="execStatusTagType(row.status)" size="small">{{ execStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="action_count" :label="t('rule_detail_drawer.cols.action_count')" width="80" align="center" />
        <el-table-column prop="execution_time_ms" :label="t('rule_detail_drawer.cols.duration')" width="100" align="center" />
      </el-table>
    </template>
  </el-drawer>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../../api/automation'

const { t } = useI18n()

const visible = ref(false)
const rule = ref(null)

function categoryTagType(cat) {
  return { license: 'success', billing: 'warning', customer: 'primary', security: 'danger', system: 'info' }[cat] || ''
}
function categoryLabel(cat) {
  const key = { license: 'license', billing: 'billing', customer: 'customer', security: 'security', system: 'system', custom: 'custom' }[cat]
  return key ? t(`rule_list_page.categories.${key}`) : cat
}
function triggerLabel(type) {
  const key = { event: 'event', schedule: 'schedule', webhook: 'webhook', condition: 'condition' }[type]
  return key ? t(`rule_detail_drawer.triggers.${key}`) : type
}
function actionLabel(type) {
  const key = {
    webhook: 'webhook', send_email: 'send_email', update_license: 'update_license',
    update_subscription: 'update_subscription', create_log: 'create_log', notify_admin: 'notify_admin',
    suspend_tenant: 'suspend_tenant', toggle_feature_flag: 'toggle_feature_flag',
  }[type]
  return key ? t(`rule_detail_drawer.action_types.${key}`) : type
}
function operatorLabel(op) {
  const key = {
    eq: 'eq', neq: 'neq', gt: 'gt', gte: 'gte', lt: 'lt', lte: 'lte',
    contains: 'contains', starts_with: 'starts_with', ends_with: 'ends_with',
    in: 'in', not_in: 'not_in', exists: 'exists', not_exists: 'not_exists',
  }[op]
  return key ? t(`rule_detail_drawer.operators.${key}`) : op
}
function statusLabel(s) {
  const key = { draft: 'draft', active: 'active', paused: 'paused', archived: 'archived' }[s]
  return key ? t(`rule_list_page.statuses.${key}`) : s
}
function execStatusTagType(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function execStatusLabel(s) {
  const key = { completed: 'completed', failed: 'failed', running: 'running', skipped: 'skipped', pending: 'pending' }[s]
  return key ? t(`rule_detail_drawer.exec_statuses.${key}`) : s
}

async function open(row) {
  visible.value = true
  try {
    const { data } = await api.getRule(row.id)
    rule.value = data
  } catch (e) {
    rule.value = row
  }
}

defineExpose({ open })
</script>

<style scoped>
.ml-2 { margin-left: 8px; }
.mr-2 { margin-right: 8px; }
.mb-2 { margin-bottom: 8px; }
.text-xs { font-size: 12px; }
.text-sm { font-size: 13px; }
.text-gray-400 { color: #909399; }
.font-medium { font-weight: 500; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
:deep(.el-divider__text) { font-weight: 600; }
</style>
