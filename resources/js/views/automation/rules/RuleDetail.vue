<template>
  <el-drawer v-model="visible" :title="'规则详情: ' + (rule?.name ?? '')" size="700px" destroy-on-close>
    <template v-if="rule">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item label="规则名称" :span="2">{{ rule.name }}</el-descriptions-item>
        <el-descriptions-item label="分类">
          <el-tag size="small" :type="categoryTagType(rule.category)">{{ categoryLabel(rule.category) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="rule.status === 'active' ? 'success' : rule.status === 'paused' ? 'warning' : 'info'">
            {{ { draft: '草稿', active: '启用', paused: '暂停', archived: '归档' }[rule.status] }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="触发器" :span="2">
          <el-tag>{{ triggerLabel(rule.trigger_type) }}</el-tag>
          <span class="ml-2 text-xs text-gray-400" v-if="rule.trigger_config?.event_type">
            事件: <code>{{ rule.trigger_config.event_type }}</code>
          </span>
        </el-descriptions-item>
        <el-descriptions-item label="优先级">{{ rule.priority }}</el-descriptions-item>
        <el-descriptions-item label="执行次数">{{ rule.execution_count }}</el-descriptions-item>
        <el-descriptions-item label="成功率" v-if="rule.execution_count > 0">
          <el-progress :percentage="Math.round(rule.success_count / rule.execution_count * 100)" :stroke-width="12" />
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ rule.created_at }}</el-descriptions-item>
        <el-descriptions-item label="描述" :span="2" v-if="rule.description">{{ rule.description }}</el-descriptions-item>
      </el-descriptions>

      <!-- 条件 -->
      <el-divider>条件 ({{ rule.condition_logic === 'all' ? 'AND' : 'OR' }})</el-divider>
      <div v-if="!rule.conditions?.length" class="text-gray-400 text-sm">无条件限制</div>
      <el-timeline v-else>
        <el-timeline-item v-for="(cond, idx) in rule.conditions" :key="idx">
          <span class="text-sm"><code>{{ cond.field }}</code> {{ operatorLabel(cond.operator) }} <code>{{ cond.value }}</code></span>
        </el-timeline-item>
      </el-timeline>

      <!-- 动作 -->
      <el-divider>动作 ({{ rule.action_execution === 'sequential' ? '顺序执行' : '首个成功' }})</el-divider>
      <el-timeline>
        <el-timeline-item v-for="(action, idx) in rule.actions" :key="idx" :color="'#409eff'">
          <div class="text-sm font-medium">{{ actionLabel(action.type) }}</div>
          <div class="text-xs text-gray-400" v-if="action.config">配置: {{ JSON.stringify(action.config) }}</div>
        </el-timeline-item>
      </el-timeline>

      <!-- 关联 Webhook -->
      <el-divider v-if="rule.webhooks?.length">关联 Webhook</el-divider>
      <div v-if="rule.webhooks?.length">
        <el-tag v-for="wh in rule.webhooks" :key="wh.id" class="mr-2 mb-2">{{ wh.name }}</el-tag>
      </div>

      <!-- 最近执行 -->
      <el-divider>最近执行</el-divider>
      <el-table :data="rule.executions || []" size="small" max-height="300">
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="action_count" label="动作数" width="80" align="center" />
        <el-table-column prop="execution_time_ms" label="耗时(ms)" width="100" align="center" />
      </el-table>
    </template>
  </el-drawer>
</template>

<script setup>
import { ref } from 'vue'
import api from '../../../api/automation'

const visible = ref(false)
const rule = ref(null)

function categoryTagType(cat) {
  return { license: 'success', billing: 'warning', customer: 'primary', security: 'danger', system: 'info' }[cat] || ''
}
function categoryLabel(cat) { return { license: 'License', billing: '账单', customer: '客户', security: '安全', system: '系统', custom: '自定义' }[cat] || cat }
function triggerLabel(t) { return { event: '事件触发', schedule: '定时触发', webhook: 'Webhook触发', condition: '条件触发' }[t] || t }
function actionLabel(t) { return { webhook: '发送 Webhook', send_email: '发送邮件', update_license: '更新 License', update_subscription: '更新订阅', create_log: '创建审计日志', notify_admin: '通知管理员', suspend_tenant: '暂停租户', toggle_feature_flag: '切换功能开关' }[t] || t }
function operatorLabel(op) {
  return { eq: '等于', neq: '不等于', gt: '大于', gte: '大于等于', lt: '小于', lte: '小于等于', contains: '包含', starts_with: '以...开始', ends_with: '以...结束', in: '在集合中', not_in: '不在集合中', exists: '存在', not_exists: '不存在' }[op] || op
}
function statusTagType(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function statusLabel(s) { return { completed: '成功', failed: '失败', running: '运行中', skipped: '跳过', pending: '待处理' }[s] || s }

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
