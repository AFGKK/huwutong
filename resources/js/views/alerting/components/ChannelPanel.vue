<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 个渠道</span>
      <el-button type="primary" size="small" @click="showCreate">新建渠道</el-button>
    </div>
    <el-table :data="channels" stripe v-loading="loading">
      <el-table-column label="名称" prop="name" min-width="160" />
      <el-table-column label="类型" prop="type" width="100">
        <template #default="{ row }">{{ typeIcon(row.type) }} {{ typeLabel(row.type) }}</template>
      </el-table-column>
      <el-table-column label="配置" prop="config" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="text-xs text-gray-500">{{ configSummary(row.config) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="关联规则" prop="rules_count" width="90">
        <template #default="{ row }">{{ row.rules_count || 0 }} 条</template>
      </el-table-column>
      <el-table-column label="状态" width="70">
        <template #default="{ row }"><el-tag :type="row.is_enabled ? 'success' : 'danger'" size="small">{{ row.is_enabled ? '启用' : '停用' }}</el-tag></template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">编辑</el-button>
          <el-button size="small" type="warning" @click="test(row)" :loading="testingId === row.id">测试</el-button>
          <el-popconfirm title="确认删除？" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>删除</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <ChannelDialog v-model:visible="dialog.visible" :channel="dialog.channel" @saved="load" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getChannels, deleteChannel, testChannel } from '../../../api/alerting'
import ChannelDialog from './ChannelDialog.vue'

const channels = ref([])
const total = ref(0)
const loading = ref(false)
const testingId = ref(null)
const dialog = reactive({ visible: false, channel: null })

function typeLabel(t) {
  const map = { email: '邮件', slack: 'Slack', webhook: 'Webhook', sms: '短信', dingtalk: '钉钉', feishu: '飞书', wechat: '企业微信', custom: '自定义' }
  return map[t] || t
}
function typeIcon(t) {
  const icons = { slack: '💬', dingtalk: '🔔', feishu: '✈️', webhook: '🔗', email: '📧', sms: '📱', wechat: '💚', custom: '⚙️' }
  return icons[t] || '📨'
}
function configSummary(cfg) {
  if (!cfg) return '-'
  return cfg.webhook_url || cfg.recipients?.join(', ') || JSON.stringify(cfg).substring(0, 60)
}

function showCreate() { dialog.channel = null; dialog.visible = true }
function edit(ch) { dialog.channel = { ...ch }; dialog.visible = true }

async function test(ch) {
  testingId.value = ch.id
  try {
    const { data } = await testChannel(ch.id)
    if (data?.success) {
      ElMessage.success('测试通知发送成功')
    } else {
      ElMessage.warning(`测试失败: ${data?.error || '未知错误'}`)
    }
  } catch (e) { ElMessage.error('测试发送异常') } finally { testingId.value = null }
}

async function remove(ch) {
  try {
    await deleteChannel(ch.id)
    ElMessage.success('已删除')
    await load()
  } catch (e) { ElMessage.error('删除失败') }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getChannels()
    channels.value = Array.isArray(data) ? data : data?.data || []
    total.value = channels.value.length
  } catch (e) { ElMessage.error('加载渠道失败') } finally { loading.value = false }
}

onMounted(load)
</script>
