<template>
  <div class="telemetry-management">
    <el-tabs v-model="activeTab">
      <!-- 概览仪表盘 -->
      <el-tab-pane label="概览" name="overview">
        <div v-loading="loading" class="dashboard-grid">
          <el-row :gutter="16">
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.total_heartbeats) }}</div>
                  <div class="stat-label">总心跳次数</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.today_heartbeats) }}</div>
                  <div class="stat-label">今日心跳</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.unique_licenses) }}</div>
                  <div class="stat-label">活跃 License</div>
                </div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-value">{{ formatNumber(stats.unique_devices) }}</div>
                  <div class="stat-label">活跃设备</div>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <el-row :gutter="16" style="margin-top: 16px">
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>SDK 语言分布</span>
                </template>
                <el-table :data="stats.language_breakdown || []" stripe size="small">
                  <el-table-column prop="sdk_language" label="语言" width="120" />
                  <el-table-column prop="count" label="心跳数" align="right">
                    <template #default="{ row }">{{ formatNumber(row.count) }}</template>
                  </el-table-column>
                </el-table>
                <el-empty v-if="!stats.language_breakdown?.length" description="暂无数据" />
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>SDK 最新版本</span>
                </template>
                <div v-if="stats.latest_versions && Object.keys(stats.latest_versions).length > 0">
                  <div v-for="(versions, lang) in stats.latest_versions" :key="lang" class="version-item">
                    <el-tag size="small" type="primary">{{ lang }}</el-tag>
                    <span v-for="ver in versions" :key="ver" class="version-tag">
                      <el-tag size="small">{{ ver }}</el-tag>
                    </span>
                  </div>
                </div>
                <el-empty v-else description="暂无版本信息" />
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- 版本分布 -->
      <el-tab-pane label="版本分布" name="versions">
        <div v-loading="versionsLoading">
          <div v-if="versionDistribution && Object.keys(versionDistribution).length > 0">
            <el-card v-for="(items, dimension) in versionDistribution" :key="dimension" style="margin-bottom: 16px">
              <template #header>
                <span>{{ dimensionLabel(dimension) }}</span>
              </template>
              <el-table :data="items" stripe size="small">
                <el-table-column :label="dimension === 'sdk_language' ? '语言' : '版本'" min-width="200">
                  <template #default="{ row }">
                    <el-tag>{{ row.value }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="count" label="实例数" width="120" align="right">
                  <template #default="{ row }">
                    <strong>{{ formatNumber(row.count) }}</strong>
                  </template>
                </el-table-column>
              </el-table>
            </el-card>
          </div>
          <el-empty v-else description="暂无版本分布数据" />
        </div>
      </el-tab-pane>

      <!-- 心跳历史 -->
      <el-tab-pane label="心跳历史" name="heartbeats">
        <div class="filter-bar">
          <el-input v-model="heartbeatFilter.license_id" placeholder="License ID" clearable style="width: 200px" />
          <el-date-picker
            v-model="heartbeatFilter.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
          />
          <el-button type="primary" @click="loadHeartbeats">查询</el-button>
        </div>

        <el-table v-loading="heartbeatLoading" :data="heartbeatList" stripe style="width: 100%; margin-top: 16px">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="sdk_language" label="SDK" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.sdk_language || '-' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sdk_version" label="版本" width="80" />
          <el-table-column prop="sdk_platform" label="平台" width="90" />
          <el-table-column prop="sdk_arch" label="架构" width="70" />
          <el-table-column prop="hostname" label="主机名" width="120" />
          <el-table-column prop="runtime_version" label="运行时" width="90" />
          <el-table-column prop="uptime_seconds" label="运行时长" width="90">
            <template #default="{ row }">{{ formatUptime(row.uptime_seconds) }}</template>
          </el-table-column>
          <el-table-column prop="reported_at" label="上报时间" width="140">
            <template #default="{ row }">{{ formatDate(row.reported_at) }}</template>
          </el-table-column>
          <el-table-column label="健康" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.health_status" :type="healthType(row.health_status)" size="small">
                {{ healthLabel(row.health_status) }}
              </el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!heartbeatLoading && heartbeatList.length === 0" description="暂无心跳数据" />
      </el-tab-pane>

      <!-- 事件统计 -->
      <el-tab-pane label="事件统计" name="events">
        <div v-loading="eventsLoading">
          <el-table v-if="eventStats.length > 0" :data="eventStats" stripe style="width: 100%">
            <el-table-column prop="event_type" label="事件类型" width="130">
              <template #default="{ row }">
                <el-tag size="small">{{ row.event_type }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="event_name" label="事件名称" min-width="180" />
            <el-table-column prop="total_count" label="总次数" width="120" align="right">
              <template #default="{ row }">
                <strong>{{ formatNumber(row.total_count) }}</strong>
              </template>
            </el-table-column>
            <el-table-column prop="unique_licenses" label="唯一 License" width="120" align="right" />
          </el-table>
          <el-empty v-if="!eventsLoading && eventStats.length === 0" description="暂无事件数据" />
        </div>
      </el-tab-pane>

      <!-- 异常心跳 -->
      <el-tab-pane label="异常心跳" name="unhealthy">
        <el-table v-loading="unhealthyLoading" :data="unhealthyList" stripe style="width: 100%">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="sdk_language" label="SDK" width="80" />
          <el-table-column prop="hostname" label="主机名" width="120" />
          <el-table-column label="CPU" width="80">
            <template #default="{ row }">{{ row.health_status?.cpu ?? '-' }}%</template>
          </el-table-column>
          <el-table-column label="内存" width="80">
            <template #default="{ row }">{{ row.health_status?.memory ?? '-' }}%</template>
          </el-table-column>
          <el-table-column label="磁盘" width="80">
            <template #default="{ row }">{{ row.health_status?.disk ?? '-' }}%</template>
          </el-table-column>
          <el-table-column prop="reported_at" label="上报时间" width="140">
            <template #default="{ row }">{{ formatDate(row.reported_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!unhealthyLoading && unhealthyList.length === 0" description="暂无异常心跳" />
      </el-tab-pane>

      <!-- 版本趋势 -->
      <el-tab-pane label="版本趋势" name="trend">
        <div class="filter-bar">
          <el-select v-model="trendDays" style="width: 150px">
            <el-option label="最近 7 天" :value="7" />
            <el-option label="最近 14 天" :value="14" />
            <el-option label="最近 30 天" :value="30" />
            <el-option label="最近 60 天" :value="60" />
          </el-select>
          <el-button type="primary" @click="loadTrend">查询</el-button>
        </div>

        <el-table v-loading="trendLoading" v-if="trendData.length > 0" :data="trendData" stripe style="width: 100%; margin-top: 16px">
          <el-table-column prop="snapshot_date" label="日期" width="120" />
          <el-table-column prop="sdk_language" label="语言" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.sdk_language }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sdk_version" label="版本" width="100" />
          <el-table-column prop="total_instances" label="实例数" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.total_instances) }}</strong>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!trendLoading && trendData.length === 0" description="暂无趋势数据" />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/telemetry'

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
        ElMessage.error('获取仪表盘数据失败')
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
        ElMessage.error('获取版本分布失败')
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
        ElMessage.error('获取心跳历史失败')
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
        ElMessage.error('获取事件统计失败')
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
        ElMessage.error('获取异常心跳失败')
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
        ElMessage.error('获取趋势数据失败')
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
    return d.toLocaleDateString('zh-CN') + ' ' + d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
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
    if (!health) return '未知'
    const cpu = health.cpu || 0
    const mem = health.memory || 0
    const disk = health.disk || 0
    if (cpu > 90 || mem > 90 || disk > 95) return '异常'
    if (cpu > 70 || mem > 70 || disk > 80) return '告警'
    return '正常'
}

function dimensionLabel(dim) {
    const map = { sdk_language: 'SDK 语言', sdk_version: 'SDK 版本', platform: '运行平台', runtime: '运行时版本' }
    return map[dim] || dim
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
    color: #409eff;
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
