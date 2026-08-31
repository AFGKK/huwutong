<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('alert_channel_panel.total', { n: total }) }}</span>
      <el-button type="primary" size="small" @click="showCreate">{{ t('alert_channel_panel.create') }}</el-button>
    </div>
    <el-table :data="channels" stripe v-loading="loading">
      <el-table-column :label="t('alert_channel_panel.cols.name')" prop="name" min-width="160" />
      <el-table-column :label="t('alert_channel_panel.cols.type')" prop="type" width="120">
        <template #default="{ row }">{{ typeLabel(row.type) }}</template>
      </el-table-column>
      <el-table-column :label="t('alert_channel_panel.cols.config')" prop="config" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="text-xs text-gray-500">{{ configSummary(row.config) }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('alert_channel_panel.cols.rules')" prop="rules_count" width="90">
        <template #default="{ row }">{{ t('alert_channel_panel.rules_count', { n: row.rules_count || 0 }) }}</template>
      </el-table-column>
      <el-table-column :label="t('alert_channel_panel.cols.status')" width="70">
        <template #default="{ row }"><el-tag :type="row.is_enabled ? 'success' : 'danger'" size="small">{{ row.is_enabled ? t('alert_channel_panel.enabled') : t('alert_channel_panel.disabled') }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('alert_channel_panel.cols.actions')" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">{{ t('actions.edit') }}</el-button>
          <el-button size="small" type="warning" @click="test(row)" :loading="testingId === row.id">{{ t('alert_channel_panel.test') }}</el-button>
          <el-popconfirm :title="t('alert_channel_panel.confirm_delete')" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <ChannelDialog v-model:visible="dialog.visible" :channel="dialog.channel" @saved="load" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getChannels, deleteChannel, testChannel } from '@/api/alerting'
import ChannelDialog from './ChannelDialog.vue'

const { t } = useI18n()
const channels = ref([])
const total = ref(0)
const loading = ref(false)
const testingId = ref(null)
const dialog = reactive({ visible: false, channel: null })

function typeLabel(type) {
  const key = ['email', 'slack', 'webhook', 'sms', 'dingtalk', 'feishu', 'wechat', 'custom'].includes(type) ? type : null
  return key ? t(`alert_channel_panel.types.${key}`) : type
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
      ElMessage.success(t('alert_channel_panel.messages.test_ok'))
    } else {
      ElMessage.warning(t('alert_channel_panel.messages.test_fail', { error: data?.error || t('alert_channel_panel.unknown_error') }))
    }
  } catch (e) { ElMessage.error(t('alert_channel_panel.messages.test_error')) } finally { testingId.value = null }
}

async function remove(ch) {
  try {
    await deleteChannel(ch.id)
    ElMessage.success(t('alert_channel_panel.messages.deleted'))
    await load()
  } catch (e) { ElMessage.error(t('alert_channel_panel.messages.delete_failed')) }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getChannels()
    channels.value = Array.isArray(data) ? data : data?.data || []
    total.value = channels.value.length
  } catch (e) { ElMessage.error(t('alert_channel_panel.messages.load_failed')) } finally { loading.value = false }
}

onMounted(load)
</script>
