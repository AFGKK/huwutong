<template>
  <div class="usage-dashboard-page">
    <div class="page-header">
      <h2>{{ t('usage_dashboard_page.title') }}</h2>
      <p class="text-muted">{{ t('usage_dashboard_page.subtitle') }}</p>
    </div>

    <el-row :gutter="16" class="stats-row">
      <el-col :span="6" v-for="s in statsCards" :key="s.key">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-num">{{ s.value }}</div>
            <div class="stat-label">{{ s.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="filter-card">
      <el-radio-group v-model="period" @change="loadData">
        <el-radio-button v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </el-radio-button>
      </el-radio-group>
    </el-card>

    <el-row :gutter="16">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header><span>{{ t('usage_dashboard_page.api_trend') }}</span></template>
          <div v-if="apiTrend?.length" style="height:300px;position:relative">
            <div v-for="(item, i) in apiTrend" :key="i" class="bar-item">
              <div class="bar-label">{{ item.date || item.period }}</div>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: barWidth(item.count) + '%' }"></div>
              </div>
              <div class="bar-value">{{ item.count }}</div>
            </div>
          </div>
          <el-empty v-else :description="t('messages.no_data')" />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header><span>{{ t('usage_dashboard_page.feature_ranking') }}</span></template>
          <div v-if="featureUsage?.length">
            <div v-for="(f, i) in featureUsage" :key="i" class="feature-item">
              <span class="feature-rank">#{{ i + 1 }}</span>
              <span class="feature-name">{{ f.feature || f.name }}</span>
              <el-tag>{{ f.count || f.usage_count }}</el-tag>
            </div>
          </div>
          <el-empty v-else :description="t('messages.no_data')" />
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" style="margin-top:16px">
      <template #header><span>{{ t('usage_dashboard_page.endpoint_stats') }}</span></template>
      <el-table :data="endpointData" border v-loading="loading" style="width:100%">
        <el-table-column prop="endpoint" :label="t('usage_dashboard_page.columns.endpoint')" min-width="200" />
        <el-table-column prop="method" :label="t('usage_dashboard_page.columns.method')" width="80">
          <template #default="{row}"><el-tag>{{ row.method || 'GET' }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="count" :label="t('usage_dashboard_page.columns.count')" width="120" sortable />
        <el-table-column prop="percentage" :label="t('usage_dashboard_page.columns.percentage')" width="100">
          <template #default="{row}">{{ (row.percentage || row.count).toFixed?.(1) || '-' }}%</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getUsageOverview,
  getUsageApiCalls,
  getUsageEndpointStats,
  getUsageFeatures,
} from '@/api/usageDashboard'

const { t } = useI18n()

const period = ref('month')
const loading = ref(false)
const overview = ref({})
const apiTrend = ref([])
const endpointData = ref([])
const featureUsage = ref([])

const periodKeys = ['7d', '30d', 'month', 'last_month', 'quarter']

const periodOptions = computed(() =>
  periodKeys.map((value) => ({ value, label: t(`usage_dashboard_page.periods.${value}`) }))
)

const statsCards = computed(() => [
  {
    key: 'total_calls',
    label: t('usage_dashboard_page.stats.total_calls'),
    value: overview.value.total_calls ?? overview.value.total ?? 0,
  },
  {
    key: 'active_endpoints',
    label: t('usage_dashboard_page.stats.active_endpoints'),
    value: overview.value.active_endpoints ?? overview.value.endpoints ?? 0,
  },
  {
    key: 'active_features',
    label: t('usage_dashboard_page.stats.active_features'),
    value: overview.value.active_features ?? overview.value.features ?? 0,
  },
])

function barWidth(count) {
  const max = Math.max(...apiTrend.value.map(i => i.count), 1)
  return (count / max) * 100
}

async function loadData() {
  loading.value = true
  try {
    const [ov, trend, ep, feat] = await Promise.all([
      getUsageOverview({ period: period.value }),
      getUsageApiCalls({ period: period.value }),
      getUsageEndpointStats({ period: period.value }),
      getUsageFeatures({ period: period.value }),
    ])
    overview.value = ov.data?.data || {}
    apiTrend.value = trend.data?.data?.trend || trend.data?.data || []
    endpointData.value = ep.data?.data?.endpoints || ep.data?.data || []
    featureUsage.value = feat.data?.data?.usage || feat.data?.data || []
  } catch (e) {
    console.error(t('messages.load_failed'), e)
  }
  loading.value = false
}

onMounted(() => { loadData() })
</script>

<style scoped>
.usage-dashboard-page { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.text-muted { color: #909399; font-size: 13px; margin-top: 4px; }
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-num { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.filter-card { margin-bottom: 16px; padding: 12px 16px; }
.bar-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.bar-label { width: 80px; font-size: 12px; color: #606266; text-align: right; flex-shrink: 0; }
.bar-track { flex: 1; height: 20px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.bar-fill { height: 100%; background: linear-gradient(90deg, #0f172a, #1e293b); border-radius: 4px; transition: width 0.5s; }
.bar-value { width: 50px; font-size: 12px; color: #606266; text-align: left; }
.feature-item { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f2f5; }
.feature-item:last-child { border-bottom: none; }
.feature-rank { font-weight: 600; color: #0f172a; min-width: 24px; }
.feature-name { flex: 1; font-size: 13px; }
</style>
