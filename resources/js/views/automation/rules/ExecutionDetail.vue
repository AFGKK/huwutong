<template>
  <el-dialog v-model="visible" :title="t('execution_detail.title', { id: exec?.id ?? '' })" width="700px" destroy-on-close>
    <template v-if="exec">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item :label="t('execution_detail.rule')">{{ exec.rule?.name ?? exec.rule_id }}</el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.status')">
          <el-tag :type="statusTag(exec.status)">{{ statusLabel(exec.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.actions')">{{ exec.successful_actions }}/{{ exec.action_count }}</el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.duration')">{{ exec.execution_time_ms }}ms</el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.trigger')">{{ exec.trigger_source || '—' }}</el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.executed_at')">{{ exec.executed_at || exec.created_at }}</el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.error')" :span="2" v-if="exec.error_message">
          <el-alert :title="exec.error_message" type="error" show-icon :closable="false" />
        </el-descriptions-item>
        <el-descriptions-item :label="t('execution_detail.conditions')" :span="2" v-if="exec.conditions_result">
          <div class="text-xs">{{ conditionSummary(exec.conditions_result) }}</div>
        </el-descriptions-item>
      </el-descriptions>

      <el-divider>{{ t('execution_detail.action_logs') }}</el-divider>
      <el-table :data="exec.action_logs || exec.actionLogs || []" size="small" stripe>
        <el-table-column prop="action_index" label="#" width="50" align="center" />
        <el-table-column prop="action_type" :label="t('execution_detail.cols.type')" width="140">
          <template #default="{ row }">{{ actionLabel(row.action_type) }}</template>
        </el-table-column>
        <el-table-column prop="status" :label="t('execution_detail.cols.status')" width="90">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration_ms" :label="t('execution_detail.cols.duration')" width="80" align="center" />
        <el-table-column prop="error_message" :label="t('execution_detail.cols.error')" min-width="150">
          <template #default="{ row }">
            <span class="text-red-500 text-xs">{{ row.error_message || '—' }}</span>
          </template>
        </el-table-column>
      </el-table>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../../api/automation'

const { t } = useI18n()
const visible = ref(false)
const exec = ref(null)

function statusTag(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function statusLabel(s) {
  const key = { completed: 'completed', failed: 'failed', running: 'running', skipped: 'skipped', pending: 'pending' }[s]
  return key ? t(`execution_detail.statuses.${key}`) : s
}
function actionLabel(type) {
  const key = {
    webhook: 'webhook', send_email: 'send_email', update_license: 'update_license',
    update_subscription: 'update_subscription', create_log: 'create_log',
    notify_admin: 'notify_admin', suspend_tenant: 'suspend_tenant', toggle_feature_flag: 'toggle_feature_flag',
  }[type]
  return key ? t(`execution_detail.action_types.${key}`) : type
}
function conditionSummary(condResult) {
  return condResult.passed ? t('execution_detail.condition_passed') : t('execution_detail.condition_failed')
}

async function open(row) {
  visible.value = true
  try {
    const ruleId = row.rule_id
    const execId = row.id
    const { data } = await api.getExecutions(ruleId)
    const fullExec = data.data?.find(e => e.id === execId)
    exec.value = fullExec || row
  } catch (e) {
    exec.value = row
  }
}

defineExpose({ open })
</script>

<style scoped>
.text-xs { font-size: 12px; }
.text-red-500 { color: #f56c6c; }
</style>
