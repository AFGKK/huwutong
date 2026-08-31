<template>
  <div>
    <div class="mb-4">
      <el-row :gutter="12">
        <el-col :span="5">
          <el-select v-model="filterType" clearable :placeholder="t('security_event_panel.event_type')" style="width:100%" @change="fetchEvents">
            <el-option v-for="et in eventTypes" :key="et" :label="et" :value="et" />
          </el-select>
        </el-col>
        <el-col :span="3">
          <el-select v-model="filterSeverity" clearable :placeholder="t('security_event_panel.severity')" style="width:100%" @change="fetchEvents">
            <el-option label="Info" value="info" />
            <el-option label="Warning" value="warning" />
            <el-option label="Critical" value="critical" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-input v-model="filterIp" :placeholder="t('security_event_panel.ip')" clearable @clear="fetchEvents" @keyup.enter="fetchEvents" />
        </el-col>
        <el-col :span="4">
          <el-date-picker v-model="dateRange" type="daterange" :range-separator="t('security_event_panel.to')" :start-placeholder="t('security_event_panel.start')" :end-placeholder="t('security_event_panel.end')"
            style="width:100%" @change="fetchEvents" />
        </el-col>
        <el-col :span="8" class="text-right">
          <el-button size="small" @click="refresh">{{ t('actions.refresh') }}</el-button>
        </el-col>
      </el-row>
    </div>

    <el-table :data="events" v-loading="loading" size="small" max-height="420">
      <el-table-column :label="t('security_event_panel.cols.user')" width="140">
        <template #default="{ row }">{{ row.user?.name || row.user?.email || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('security_event_panel.cols.event_type')" width="140">
        <template #default="{ row }">
          <el-tag :type="eventTagType(row.event_type)" size="small">{{ row.event_type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('security_event_panel.cols.severity')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'warning' ? 'warning' : 'info'" size="small" effect="dark">
            {{ row.severity }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="ip_address" label="IP" width="130" />
      <el-table-column prop="description" :label="t('security_event_panel.cols.description')" min-width="200" show-overflow-tooltip />
      <el-table-column :label="t('security_event_panel.cols.time')" width="150">
        <template #default="{ row }">{{ row.created_at?.slice(0, 16) }}</template>
      </el-table-column>
    </el-table>

    <div v-if="total > perPage" class="mt-4 text-center">
      <el-pagination v-model:page="page" :total="total" :page-size="perPage" layout="prev, pager, next"
        @current-change="fetchEvents" small />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getSecurityEvents, getEventTypes } from '../../../api/securityCenter'

const { t } = useI18n()
const loading = ref(false)
const events = ref([])
const eventTypes = ref([])
const filterType = ref('')
const filterSeverity = ref('')
const filterIp = ref('')
const dateRange = ref(null)
const page = ref(1)
const perPage = ref(50)
const total = ref(0)

function eventTagType(type) {
  if (type?.includes('failed') || type?.includes('blocked')) return 'danger'
  if (type?.includes('suspicious') || type?.includes('anomaly')) return 'warning'
  if (type?.includes('success') || type?.includes('logout')) return 'success'
  return 'info'
}

async function fetchEvents() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    if (filterType.value) params.event_type = filterType.value
    if (filterSeverity.value) params.severity = filterSeverity.value
    if (filterIp.value) params.ip_address = filterIp.value
    if (dateRange.value) {
      params.date_from = dateRange.value[0]?.toISOString().slice(0, 10)
      params.date_to = dateRange.value[1]?.toISOString().slice(0, 10)
    }
    const { data } = await getSecurityEvents(params)
    events.value = data?.data || data || []
    total.value = data?.total || 0
  } catch (e) { /* ignore */ }
  finally { loading.value = false }
}

async function fetchEventTypes() {
  try {
    const { data } = await getEventTypes()
    eventTypes.value = data || []
  } catch (e) { /* ignore */ }
}

function refresh() {
  page.value = 1
  fetchEvents()
}

onMounted(() => {
  fetchEventTypes()
  fetchEvents()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-right { text-align: right; }
.mt-4 { margin-top: 16px; }
.text-center { text-align: center; }
</style>
