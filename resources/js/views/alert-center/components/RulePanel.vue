<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('alert_rule_panel.total', { n: total }) }}</span>
      <el-button type="primary" size="small" @click="showCreate">{{ t('alert_rule_panel.create') }}</el-button>
    </div>
    <el-table :data="rules" stripe v-loading="loading">
      <el-table-column :label="t('alert_rule_panel.cols.name')" prop="name" min-width="160" />
      <el-table-column :label="t('alert_rule_panel.cols.type')" prop="metric_type" width="120">
        <template #default="{ row }">{{ typeLabel(row.metric_type) }}</template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.condition')" min-width="140">
        <template #default="{ row }">{{ row.condition_operator }} {{ row.threshold }}（{{ row.duration_minutes }}min）</template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.severity')" prop="severity" width="90">
        <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.cooldown')" prop="cooldown_minutes" width="80">
        <template #default="{ row }">{{ row.cooldown_minutes }}min</template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.status')" width="70">
        <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('alert_rule_panel.active') : t('alert_rule_panel.inactive') }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.channels')" min-width="120">
        <template #default="{ row }">
          <el-tag v-for="ch in row.channels || []" :key="ch.id" size="small" class="mr-1">{{ channelLabel(ch.type) }}</el-tag>
          <span v-if="!row.channels?.length" class="text-gray-400">-</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('alert_rule_panel.cols.actions')" width="140" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">{{ t('actions.edit') }}</el-button>
          <el-popconfirm :title="t('alert_rule_panel.confirm_delete')" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getRules, deleteRule } from '@/api/alerting'

const { t } = useI18n()
const emit = defineEmits(['edit'])

const rules = ref([])
const total = ref(0)
const loading = ref(false)

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function severityLabel(s) {
  const key = { info: 'info', warning: 'warning', critical: 'critical' }[s]
  return key ? t(`alert_rule_panel.severities.${key}`) : s
}
function typeLabel(type) {
  const key = {
    license_expiry: 'license_expiry', certificate_expiry: 'certificate_expiry', quota_exceeded: 'quota_exceeded',
    failed_payment: 'failed_payment', audit_anomaly: 'audit_anomaly', system_health: 'system_health',
    activation_burst: 'activation_burst', heartbeat_missed: 'heartbeat_missed', apm_slow: 'apm_slow',
    sdk_deprecated: 'sdk_deprecated', custom: 'custom',
  }[type]
  return key ? t(`alert_rule_panel.types.${key}`) : type
}
function channelLabel(type) {
  const key = ['slack', 'dingtalk', 'feishu', 'webhook', 'email', 'sms', 'wechat', 'custom'].includes(type) ? type : null
  return key ? t(`alert_rule_panel.channels.${key}`) : type
}

function showCreate() { emit('edit', null) }
function edit(rule) { emit('edit', rule) }

async function remove(rule) {
  try {
    await deleteRule(rule.id)
    ElMessage.success(t('alert_rule_panel.messages.deleted'))
    await load()
  } catch (e) { ElMessage.error(t('alert_rule_panel.messages.delete_failed')) }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getRules()
    rules.value = Array.isArray(data) ? data : data?.data || []
    total.value = rules.value.length
  } catch (e) { ElMessage.error(t('alert_rule_panel.messages.load_failed')) } finally { loading.value = false }
}

onMounted(load)
</script>
