<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 条规则</span>
      <el-button type="primary" size="small" @click="showCreate">新建规则</el-button>
    </div>
    <el-table :data="rules" stripe v-loading="loading">
      <el-table-column label="名称" prop="name" min-width="160" />
      <el-table-column label="类型" prop="metric_type" width="120">
        <template #default="{ row }">{{ typeLabel(row.metric_type) }}</template>
      </el-table-column>
      <el-table-column label="条件" min-width="140">
        <template #default="{ row }">{{ row.condition_operator }} {{ row.threshold }}（{{ row.duration_minutes }}min）</template>
      </el-table-column>
      <el-table-column label="严重程度" prop="severity" width="90">
        <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
      </el-table-column>
      <el-table-column label="冷却" prop="cooldown_minutes" width="80">
        <template #default="{ row }">{{ row.cooldown_minutes }}min</template>
      </el-table-column>
      <el-table-column label="状态" width="70">
        <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
      </el-table-column>
      <el-table-column label="渠道" min-width="120">
        <template #default="{ row }">
          <el-tag v-for="ch in row.channels || []" :key="ch.id" size="small" class="mr-1">{{ channelIcon(ch.type) }}</el-tag>
          <span v-if="!row.channels?.length" class="text-gray-400">-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="140" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">编辑</el-button>
          <el-popconfirm title="确认删除？" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>删除</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getRules, deleteRule } from '../../../api/alerting'

const emit = defineEmits(['edit'])

const rules = ref([])
const total = ref(0)
const loading = ref(false)

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function severityLabel(s) { return { info: '提示', warning: '警告', critical: '严重' }[s] || s }
function typeLabel(t) { const map = { license_expiry: '许可证到期', certificate_expiry: '证书到期', quota_exceeded: '配额超限', failed_payment: '支付失败', audit_anomaly: '审计异常', system_health: '系统健康', activation_burst: '激活暴增', heartbeat_missed: '心跳丢失', apm_slow: 'APM慢请求', sdk_deprecated: 'SDK版本过期', custom: '自定义' }; return map[t] || t }
function channelIcon(t) { const icons = { slack: '💬', dingtalk: '🔔', feishu: '✈️', webhook: '🔗', email: '📧', sms: '📱', wechat: '💚', custom: '⚙️' }; return icons[t] || '📨' }

function showCreate() { emit('edit', null) }
function edit(rule) { emit('edit', rule) }

async function remove(rule) {
  try {
    await deleteRule(rule.id)
    ElMessage.success('已删除')
    await load()
  } catch (e) { ElMessage.error('删除失败') }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getRules()
    rules.value = Array.isArray(data) ? data : data?.data || []
    total.value = rules.value.length
  } catch (e) { ElMessage.error('加载规则失败') } finally { loading.value = false }
}

onMounted(load)
</script>
