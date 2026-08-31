<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('sla_breach_panel.total', { n: total }) }}</span>
      <div>
        <el-select v-model="filters.severity" :placeholder="t('sla_breach_panel.severity')" clearable size="small" style="width:120px"
          @change="load" class="mr-2">
          <el-option :label="t('sla_breach_panel.severities.minor')" value="minor" />
          <el-option :label="t('sla_breach_panel.severities.major')" value="major" />
          <el-option :label="t('sla_breach_panel.severities.critical')" value="critical" />
        </el-select>
        <el-select v-model="filters.status" :placeholder="t('sla_breach_panel.cols.status')" clearable size="small" style="width:120px" @change="load"
          class="mr-2">
          <el-option :label="t('sla_breach_panel.statuses.open')" value="open" />
          <el-option :label="t('sla_breach_panel.statuses.acknowledged')" value="acknowledged" />
          <el-option :label="t('sla_breach_panel.statuses.resolved')" value="resolved" />
          <el-option :label="t('sla_breach_panel.statuses.escalated')" value="escalated" />
        </el-select>
        <el-button size="small" @click="load" type="primary">{{ t('actions.refresh') }}</el-button>
      </div>
    </div>

    <el-table :data="breaches" stripe v-loading="loading">
      <el-table-column :label="t('sla_breach_panel.cols.contract')" prop="contract?.name" min-width="140" />
      <el-table-column :label="t('sla_breach_panel.cols.type')" prop="breach_type" width="110">
        <template #default="{ row }">{{ typeLabel(row.breach_type) }}</template>
      </el-table-column>
      <el-table-column :label="t('sla_breach_panel.severity')" prop="severity" width="90">
        <template #default="{ row }">
          <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('sla_breach_panel.cols.description')" prop="description" min-width="200" show-overflow-tooltip />
      <el-table-column :label="t('sla_breach_panel.cols.expected')" width="130">
        <template #default="{ row }">
          {{ row.expected_value }} vs {{ row.actual_value }}
        </template>
      </el-table-column>
      <el-table-column :label="t('sla_breach_panel.cols.status')" prop="status" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('sla_breach_panel.cols.time')" prop="created_at" width="160" />
      <el-table-column :label="t('sla_breach_panel.cols.actions')" width="140" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 'open'" size="small" @click="acknowledge(row)">{{ t('sla_breach_panel.ack') }}</el-button>
          <el-button v-if="['open', 'acknowledged'].includes(row.status)" size="small" type="success"
            @click="showResolve(row)">{{ t('sla_breach_panel.resolve') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="flex justify-center mt-3" v-if="pagination.total > pagination.per_page">
      <el-pagination background layout="prev, pager, next" v-model:current-page="pagination.page"
        :page-size="pagination.per_page" :total="pagination.total" @current-change="load" />
    </div>

    <el-dialog v-model="resolveDialog.visible" :title="t('sla_breach_panel.resolve_title')" width="400px">
      <el-input v-model="resolveDialog.notes" type="textarea" :rows="3" :placeholder="t('sla_breach_panel.notes_ph')" />
      <template #footer>
        <el-button @click="resolveDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="resolving" @click="confirmResolve">{{ t('sla_breach_panel.confirm_resolve') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getBreaches, acknowledgeBreach, resolveBreach } from '../../../api/sla'

const { t } = useI18n()

const breaches = ref([])
const total = ref(0)
const loading = ref(false)
const resolving = ref(false)

const filters = reactive({ severity: '', status: '' })
const pagination = reactive({ page: 1, per_page: 50, total: 0 })
const resolveDialog = reactive({ visible: false, breach: null, notes: '' })

function typeLabel(type) {
  const key = { response_time: 'response_time', resolution_time: 'resolution_time', uptime: 'uptime', availability: 'availability' }[type]
  return key ? t(`sla_breach_panel.types.${key}`) : type
}
function severityLabel(s) {
  const key = { minor: 'minor', major: 'major', critical: 'critical' }[s]
  return key ? t(`sla_breach_panel.severities.${key}`) : s
}
function statusLabel(s) {
  const key = { open: 'open', acknowledged: 'acknowledged', resolved: 'resolved', escalated: 'escalated' }[s]
  return key ? t(`sla_breach_panel.statuses.${key}`) : s
}
function severityTag(s) {
  return { minor: 'info', major: 'warning', critical: 'danger' }[s] || 'info'
}
function statusTag(s) {
  return { open: 'danger', acknowledged: 'warning', resolved: 'success', escalated: 'info' }[s] || 'info'
}

async function acknowledge(breach) {
  try {
    await acknowledgeBreach(breach.id)
    ElMessage.success(t('sla_breach_panel.messages.acked'))
    await load()
  } catch (e) {
    ElMessage.error(t('messages.failed'))
  }
}

function showResolve(breach) {
  resolveDialog.breach = breach
  resolveDialog.notes = ''
  resolveDialog.visible = true
}

async function confirmResolve() {
  resolving.value = true
  try {
    await resolveBreach(resolveDialog.breach.id, resolveDialog.notes)
    ElMessage.success(t('sla_breach_panel.messages.resolved'))
    resolveDialog.visible = false
    await load()
  } catch (e) {
    ElMessage.error(t('messages.failed'))
  } finally {
    resolving.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const params = { page: pagination.page, per_page: pagination.per_page, ...filters }
    const { data } = await getBreaches(params)
    const list = Array.isArray(data) ? data : data?.data || []
    breaches.value = list
    pagination.total = data?.total || list.length
    total.value = pagination.total
  } catch (e) {
    ElMessage.error(t('sla_breach_panel.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
