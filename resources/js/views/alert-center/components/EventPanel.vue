<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('alert_event_panel.total', { n: total }) }}</span>
      <div>
        <el-select v-model="filters.severity" :placeholder="t('alert_event_panel.severity')" clearable size="small" style="width:110px" @change="load" class="mr-2">
          <el-option :label="t('alert_event_panel.severities.info')" value="info" />
          <el-option :label="t('alert_event_panel.severities.warning')" value="warning" />
          <el-option :label="t('alert_event_panel.severities.critical')" value="critical" />
        </el-select>
        <el-select v-model="filters.status" :placeholder="t('alert_event_panel.cols.status')" clearable size="small" style="width:110px" @change="load" class="mr-2">
          <el-option :label="t('alert_event_panel.statuses.firing')" value="firing" />
          <el-option :label="t('alert_event_panel.statuses.acknowledged')" value="acknowledged" />
          <el-option :label="t('alert_event_panel.statuses.resolved')" value="resolved" />
        </el-select>
        <el-button size="small" @click="load" type="primary">{{ t('actions.refresh') }}</el-button>
      </div>
    </div>
    <el-table :data="events" stripe v-loading="loading" style="cursor:pointer" @row-click="emit('detail', $event)">
      <el-table-column :label="t('alert_event_panel.cols.title')" prop="title" min-width="200" show-overflow-tooltip />
      <el-table-column :label="t('alert_event_panel.cols.rule')" prop="rule?.name" width="130" />
      <el-table-column :label="t('alert_event_panel.cols.type')" prop="event_type" width="100">
        <template #default="{ row }">{{ typeLabel(row.event_type) }}</template>
      </el-table-column>
      <el-table-column :label="t('alert_event_panel.severity')" prop="severity" width="80">
        <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('alert_event_panel.cols.status')" prop="status" width="80">
        <template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('alert_event_panel.cols.fired_at')" prop="fired_at" width="160" />
      <el-table-column :label="t('alert_event_panel.cols.actions')" width="160" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click.stop="emit('detail', row)">{{ t('alert_event_panel.detail') }}</el-button>
          <el-button v-if="row.status === 'firing'" size="small" type="warning" @click.stop="acknowledge(row)">{{ t('alert_event_panel.ack') }}</el-button>
          <el-button v-if="['firing','acknowledged'].includes(row.status)" size="small" type="success" @click.stop="resolve(row)">{{ t('alert_event_panel.resolve') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="flex justify-center mt-3" v-if="pagination.total > pagination.per_page">
      <el-pagination background layout="prev, pager, next" v-model:current-page="pagination.page"
        :page-size="pagination.per_page" :total="pagination.total" @current-change="load" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getEvents, acknowledgeEvent, resolveEvent } from '@/api/alerting'

const { t } = useI18n()
const emit = defineEmits(['detail'])

const events = ref([])
const total = ref(0)
const loading = ref(false)

const filters = reactive({ severity: '', status: '' })
const pagination = reactive({ page: 1, per_page: 50, total: 0 })

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function severityLabel(s) {
  const key = { info: 'info', warning: 'warning', critical: 'critical' }[s]
  return key ? t(`alert_event_panel.severities.${key}`) : s
}
function statusTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info' }
function statusLabel(s) {
  const key = { firing: 'firing', acknowledged: 'acknowledged', resolved: 'resolved' }[s]
  return key ? t(`alert_event_panel.statuses.${key}`) : s
}
function typeLabel(type) {
  const key = {
    license_expiry: 'license_expiry', certificate_expiry: 'certificate_expiry', quota_exceeded: 'quota_exceeded',
    failed_payment: 'failed_payment', audit_anomaly: 'audit_anomaly', system_health: 'system_health',
    activation_burst: 'activation_burst', heartbeat_missed: 'heartbeat_missed', apm_slow: 'apm_slow',
    sdk_deprecated: 'sdk_deprecated', custom: 'custom',
  }[type]
  return key ? t(`alert_event_panel.types.${key}`) : type
}

async function acknowledge(event) {
  try {
    await acknowledgeEvent(event.id)
    ElMessage.success(t('alert_event_panel.messages.acked'))
    await load()
  } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function resolve(event) {
  try {
    await resolveEvent(event.id)
    ElMessage.success(t('alert_event_panel.messages.resolved'))
    await load()
  } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function load() {
  loading.value = true
  try {
    const params = { page: pagination.page, per_page: pagination.per_page, ...filters }
    const { data } = await getEvents(params)
    const list = Array.isArray(data) ? data : data?.data || []
    events.value = list
    pagination.total = data?.total || list.length
    total.value = pagination.total
  } catch (e) { ElMessage.error(t('alert_event_panel.messages.load_failed')) } finally { loading.value = false }
}

onMounted(load)
</script>
