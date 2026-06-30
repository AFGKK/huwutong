<template>
  <el-dialog v-model="visible" :title="'执行详情 #' + (exec?.id ?? '')" width="700px" destroy-on-close>
    <template v-if="exec">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item label="规则">{{ exec.rule?.name ?? exec.rule_id }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(exec.status)">{{ statusLabel(exec.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="动作">{{ exec.successful_actions }}/{{ exec.action_count }}</el-descriptions-item>
        <el-descriptions-item label="耗时">{{ exec.execution_time_ms }}ms</el-descriptions-item>
        <el-descriptions-item label="触发源">{{ exec.trigger_source || '—' }}</el-descriptions-item>
        <el-descriptions-item label="执行时间">{{ exec.executed_at || exec.created_at }}</el-descriptions-item>
        <el-descriptions-item label="错误信息" :span="2" v-if="exec.error_message">
          <el-alert :title="exec.error_message" type="error" show-icon :closable="false" />
        </el-descriptions-item>
        <el-descriptions-item label="条件结果" :span="2" v-if="exec.conditions_result">
          <div class="text-xs">{{ conditionSummary(exec.conditions_result) }}</div>
        </el-descriptions-item>
      </el-descriptions>

      <el-divider>动作执行明细</el-divider>
      <el-table :data="exec.action_logs || exec.actionLogs || []" size="small" stripe>
        <el-table-column prop="action_index" label="#" width="50" align="center" />
        <el-table-column prop="action_type" label="类型" width="140">
          <template #default="{ row }">{{ actionLabel(row.action_type) }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration_ms" label="耗时" width="80" align="center" />
        <el-table-column prop="error_message" label="错误" min-width="150">
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
import api from '../../../api/automation'

const visible = ref(false)
const exec = ref(null)

function statusTag(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function statusLabel(s) { return { completed: '完成', failed: '失败', running: '运行中', skipped: '跳过', pending: '待处理' }[s] || s }
function actionLabel(t) {
  return { webhook: 'Webhook', send_email: '邮件', update_license: '更新License', update_subscription: '更新订阅', create_log: '审计日志', notify_admin: '通知管理员', suspend_tenant: '暂停租户', toggle_feature_flag: '功能开关' }[t] || t
}
function conditionSummary(condResult) {
  return condResult.passed ? '✓ 条件满足' : '✗ 条件不满足'
}

async function open(row) {
  visible.value = true
  try {
    // Try to get full detail from the parent rule's execution
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
