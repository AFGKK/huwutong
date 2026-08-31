<template>
  <div class="telemetry-management">
    <el-tabs v-model="activeTab">
      <!-- 概览仪表盘 -->
      <el-tab-pane :label="t('apm_page.tabs.overview')" name="overview">
        <div v-loading="loading" class="dashboard-grid">
          <el-row :gutter="16">
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.total_heartbeats) }}</div>
                  <div class="stat-label">{{ t('telemetry_page.stats.total_heartbeats') }}</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.today_heartbeats) }}</div>
                  <div class="stat-label">{{ t('telemetry_page.stats.today_heartbeats') }}</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.unique_licenses) }}</div>
                  <div class="stat-label">{{ t('telemetry_page.stats.reporting_licenses') }}</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.unique_devices) }}</div>
                  <div class="stat-label">{{ t('telemetry_page.stats.reporting_devices') }}</div>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <el-row :gutter="16" style="margin-top: 16px">
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>{{ t('telemetry_page.sections.language_breakdown') }}</span>
                </template>
                <el-table :data="stats.language_breakdown || []" stripe size="small">
                  <el-table-column prop="sdk_language" :label="t('telemetry_page.columns.language')" width="120" />
                  <el-table-column prop="count" :label="t('telemetry_page.columns.heartbeat_count')" align="right">
                    <template #default="{ row }">{{ formatNumber(row.count) }}</template>
                  </el-table-column>
                </el-table>
                <el-empty v-if="!stats.language_breakdown?.length" :description="t('messages.no_data')" />
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>{{ t('telemetry_page.sections.latest_versions') }}</span>
                </template>
                <div v-if="stats.latest_versions && Object.keys(stats.latest_versions).length > 0">
                  <div v-for="(versions, lang) in stats.latest_versions" :key="lang" class="version-item">
                    <el-tag size="small" type="primary">{{ lang }}</el-tag>
                    <span v-for="ver in versions" :key="ver" class="version-tag">
                      <el-tag size="small">{{ ver }}</el-tag>
                    </span>
                  </div>
                </div>
                <el-empty v-else :description="t('telemetry_page.empty.no_versions')" />
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- 版本分布 -->
      <el-tab-pane :label="t('telemetry_page.tabs.versions')" name="versions">
        <div v-loading="versionsLoading">
          <div v-if="versionDistribution && Object.keys(versionDistribution).length > 0">
            <el-card v-for="(items, dimension) in versionDistribution" :key="dimension" style="margin-bottom: 16px">
              <template #header>
                <span>{{ dimensionLabel(dimension) }}</span>
              </template>
              <el-table :data="items" stripe size="small">
                <el-table-column
                  :label="dimension === 'sdk_language' ? t('telemetry_page.columns.language') : t('telemetry_page.columns.version')"
                  min-width="200"
                >
                  <template #default="{ row }">
                    <el-tag>{{ row.value }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="count" :label="t('telemetry_page.columns.instance_count')" width="120" align="right">
                  <template #default="{ row }">
                    <strong>{{ formatNumber(row.count) }}</strong>
                  </template>
                </el-table-column>
              </el-table>
            </el-card>
          </div>
          <el-empty v-else :description="t('telemetry_page.empty.no_version_distribution')" />
        </div>
      </el-tab-pane>

      <!-- 心跳历史 -->
      <el-tab-pane :label="t('telemetry_page.tabs.heartbeats')" name="heartbeats">
        <div class="filter-bar">
          <el-input
            v-model="heartbeatFilter.license_id"
            :placeholder="t('telemetry_page.filters.license_id_ph')"
            clearable
            style="width: 200px"
          />
          <el-date-picker
            v-model="heartbeatFilter.dateRange"
            type="daterange"
            :range-separator="t('licenses_page.date_range_sep')"
            :start-placeholder="t('telemetry_page.filters.start_date')"
            :end-placeholder="t('telemetry_page.filters.end_date')"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
          />
          <el-button type="primary" @click="loadHeartbeats">{{ t('telemetry_page.actions.query') }}</el-button>
        </div>

        <el-table v-loading="heartbeatLoading" :data="heartbeatList" stripe style="width: 100%; margin-top: 16px">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="sdk_language" :label="t('telemetry_page.columns.sdk')" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.sdk_language || '-' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sdk_version" :label="t('telemetry_page.columns.version')" width="80" />
          <el-table-column prop="sdk_platform" :label="t('telemetry_page.columns.platform')" width="90" />
          <el-table-column prop="sdk_arch" :label="t('telemetry_page.columns.arch')" width="70" />
          <el-table-column prop="hostname" :label="t('telemetry_page.columns.hostname')" width="120" />
          <el-table-column prop="runtime_version" :label="t('telemetry_page.columns.runtime')" width="90" />
          <el-table-column prop="uptime_seconds" :label="t('telemetry_page.columns.uptime')" width="90">
            <template #default="{ row }">{{ formatUptime(row.uptime_seconds) }}</template>
          </el-table-column>
          <el-table-column prop="reported_at" :label="t('telemetry_page.columns.reported_at')" width="140">
            <template #default="{ row }">{{ formatDate(row.reported_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('telemetry_page.columns.health')" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.health_status" :type="healthType(row.health_status)" size="small">
                {{ healthLabel(row.health_status) }}
              </el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!heartbeatLoading && heartbeatList.length === 0" :description="t('telemetry_page.empty.no_heartbeats')" />
      </el-tab-pane>

      <!-- 事件统计 -->
      <el-tab-pane :label="t('telemetry_page.tabs.events')" name="events">
        <div v-loading="eventsLoading">
          <el-table v-if="eventStats.length > 0" :data="eventStats" stripe style="width: 100%">
            <el-table-column prop="event_type" :label="t('telemetry_page.columns.event_type')" width="130">
              <template #default="{ row }">
                <el-tag size="small">{{ row.event_type }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="event_name" :label="t('telemetry_page.columns.event_name')" min-width="180" />
            <el-table-column prop="total_count" :label="t('telemetry_page.columns.total_count')" width="120" align="right">
              <template #default="{ row }">
                <strong>{{ formatNumber(row.total_count) }}</strong>
              </template>
            </el-table-column>
            <el-table-column prop="unique_licenses" :label="t('telemetry_page.columns.unique_licenses')" width="120" align="right" />
          </el-table>
          <el-empty v-if="!eventsLoading && eventStats.length === 0" :description="t('telemetry_page.empty.no_events')" />
        </div>
      </el-tab-pane>

      <!-- 异常心跳 -->
      <el-tab-pane :label="t('telemetry_page.tabs.unhealthy')" name="unhealthy">
        <el-table v-loading="unhealthyLoading" :data="unhealthyList" stripe style="width: 100%">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="sdk_language" :label="t('telemetry_page.columns.sdk')" width="80" />
          <el-table-column prop="hostname" :label="t('telemetry_page.columns.hostname')" width="120" />
          <el-table-column :label="t('telemetry_page.columns.cpu')" width="80">
            <template #default="{ row }">{{ row.health_status?.cpu ?? '-' }}%</template>
          </el-table-column>
          <el-table-column :label="t('telemetry_page.columns.memory')" width="80">
            <template #default="{ row }">{{ row.health_status?.memory ?? '-' }}%</template>
          </el-table-column>
          <el-table-column :label="t('telemetry_page.columns.disk')" width="80">
            <template #default="{ row }">{{ row.health_status?.disk ?? '-' }}%</template>
          </el-table-column>
          <el-table-column prop="reported_at" :label="t('telemetry_page.columns.reported_at')" width="140">
            <template #default="{ row }">{{ formatDate(row.reported_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!unhealthyLoading && unhealthyList.length === 0" :description="t('telemetry_page.empty.no_unhealthy')" />
      </el-tab-pane>

      <!-- 版本趋势 -->
      <el-tab-pane :label="t('telemetry_page.tabs.trend')" name="trend">
        <div class="filter-bar">
          <el-select v-model="trendDays" style="width: 150px">
            <el-option
              v-for="opt in trendPeriodOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
          <el-button type="primary" @click="loadTrend">{{ t('telemetry_page.actions.query') }}</el-button>
        </div>

        <el-table v-loading="trendLoading" v-if="trendData.length > 0" :data="trendData" stripe style="width: 100%; margin-top: 16px">
          <el-table-column prop="snapshot_date" :label="t('telemetry_page.columns.date')" width="120" />
          <el-table-column prop="sdk_language" :label="t('telemetry_page.columns.language')" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.sdk_language }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sdk_version" :label="t('telemetry_page.columns.version')" width="100" />
          <el-table-column prop="total_instances" :label="t('telemetry_page.columns.instance_count')" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.total_instances) }}</strong>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!trendLoading && trendData.length === 0" :description="t('apm_page.empty.no_trend_data')" />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '../../api/telemetry'

const { t, locale } = useI18n()

const activeTab = ref('overview')
const loading = ref(false)
const stats = ref({})

// 版本分布
const versionsLoading = ref(false)
const versionDistribution = ref(null)

// 心跳历史
const heartbeatLoading = ref(false)
const heartbeatList = ref([])
const heartbeatFilter = reactive({
    license_id: '',
    dateRange: [],
})

// 事件统计
const eventsLoading = ref(false)
const eventStats = ref([])

// 异常心跳
const unhealthyLoading = ref(false)
const unhealthyList = ref([])

// 趋势
const trendLoading = ref(false)
const trendData = ref([])
const trendDays = ref(30)

const trendPeriodSpec = [
    { value: 7, key: 'd7' },
    { value: 14, key: 'd14' },
    { value: 30, key: 'd30' },
    { value: 60, key: 'd60' },
]

const trendPeriodOptions = computed(() =>
    trendPeriodSpec.map(({ value, key }) => ({
        value,
        label: t(`telemetry_page.periods.${key}`),
    }))
)

const dimensionKeys = ['sdk_language', 'sdk_version', 'platform', 'runtime']

const dimensionLabels = computed(() =>
    Object.fromEntries(
        dimensionKeys.map((key) => [key, t(`telemetry_page.dimensions.${key}`)])
    )
)

onMounted(() => {
    loadDashboard()
    loadVersions()
    loadHeartbeats()
    loadEvents()
    loadUnhealthy()
    loadTrend()
})

async function loadDashboard() {
    loading.value = true
    try {
        const { data } = await api.dashboard()
        stats.value = data.data || {}
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_dashboard_failed'))
    } finally {
        loading.value = false
    }
}

async function loadVersions() {
    versionsLoading.value = true
    try {
        const { data } = await api.versions()
        versionDistribution.value = data.data || {}
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_versions_failed'))
    } finally {
        versionsLoading.value = false
    }
}

async function loadHeartbeats() {
    heartbeatLoading.value = true
    try {
        const params = {}
        if (heartbeatFilter.license_id) params.license_id = heartbeatFilter.license_id
        if (heartbeatFilter.dateRange?.length) {
            params.date_from = heartbeatFilter.dateRange[0]
            params.date_to = heartbeatFilter.dateRange[1]
        }
        const { data } = await api.heartbeats(params)
        heartbeatList.value = data.data || []
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_heartbeats_failed'))
    } finally {
        heartbeatLoading.value = false
    }
}

async function loadEvents() {
    eventsLoading.value = true
    try {
        const { data } = await api.events()
        eventStats.value = data.data || []
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_events_failed'))
    } finally {
        eventsLoading.value = false
    }
}

async function loadUnhealthy() {
    unhealthyLoading.value = true
    try {
        const { data } = await api.unhealthy()
        unhealthyList.value = data.data || []
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_unhealthy_failed'))
    } finally {
        unhealthyLoading.value = false
    }
}

async function loadTrend() {
    trendLoading.value = true
    try {
        const { data } = await api.trend({ days: trendDays.value })
        trendData.value = data.data || []
    } catch (e) {
        ElMessage.error(t('telemetry_page.messages.fetch_trend_failed'))
    } finally {
        trendLoading.value = false
    }
}

function formatNumber(n) {
    return Number(n || 0).toLocaleString()
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return d.toLocaleDateString(loc) + ' ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' })
}

function formatUptime(seconds) {
    if (!seconds) return '-'
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    if (h > 0) return `${h}h${m}m`
    return `${m}m`
}

function healthType(health) {
    if (!health) return 'info'
    const cpu = health.cpu || 0
    const mem = health.memory || 0
    const disk = health.disk || 0
    if (cpu > 90 || mem > 90 || disk > 95) return 'danger'
    if (cpu > 70 || mem > 70 || disk > 80) return 'warning'
    return 'success'
}

function healthLabel(health) {
    if (!health) return t('telemetry_page.health.unknown')
    const cpu = health.cpu || 0
    const mem = health.memory || 0
    const disk = health.disk || 0
    if (cpu > 90 || mem > 90 || disk > 95) return t('apm_page.health.unhealthy')
    if (cpu > 70 || mem > 70 || disk > 80) return t('telemetry_page.health.warning')
    return t('apm_page.health.healthy')
}

function dimensionLabel(dim) {
    return dimensionLabels.value[dim] || dim
}
</script>

<style scoped>
.telemetry-management {
    padding: 20px;
}

.dashboard-grid {
    max-width: 1400px;
}

.stat-card {
    text-align: center;
    padding: 8px 0;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 8px;
}

.filter-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
}

.version-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.version-tag {
    display: inline-block;
}
</style>
