<template>
  <div class="usage-dashboard-page">
    <div class="page-header">
      <h2>📈 客户用量看板</h2>
      <p class="text-muted">查看客户的 API 调用趋势、端点分布和功能使用排行</p>
    </div>

    <el-row :gutter="16" class="stats-row">
      <el-col :span="6" v-for="s in statsCards" :key="s.label">
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
        <el-radio-button value="7d">近 7 天</el-radio-button>
        <el-radio-button value="30d">近 30 天</el-radio-button>
        <el-radio-button value="month">本月</el-radio-button>
        <el-radio-button value="last_month">上月</el-radio-button>
        <el-radio-button value="quarter">本季度</el-radio-button>
      </el-radio-group>
    </el-card>

    <el-row :gutter="16">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header><span>📊 API 调用趋势</span></template>
          <div v-if="apiTrend?.length" style="height:300px;position:relative">
            <div v-for="(item, i) in apiTrend" :key="i" class="bar-item">
              <div class="bar-label">{{ item.date || item.period }}</div>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: barWidth(item.count) + '%' }"></div>
              </div>
              <div class="bar-value">{{ item.count }}</div>
            </div>
          </div>
          <el-empty v-else description="暂无数据" />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header><span>🔝 功能使用排行</span></template>
          <div v-if="featureUsage?.length">
            <div v-for="(f, i) in featureUsage" :key="i" class="feature-item">
              <span class="feature-rank">#{{ i + 1 }}</span>
              <span class="feature-name">{{ f.feature || f.name }}</span>
              <el-tag>{{ f.count || f.usage_count }}</el-tag>
            </div>
          </div>
          <el-empty v-else description="暂无数据" />
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" style="margin-top:16px">
      <template #header><span>🎯 端点调用统计</span></template>
      <el-table :data="endpointData" border v-loading="loading" style="width:100%">
        <el-table-column prop="endpoint" label="端点" min-width="200" />
        <el-table-column prop="method" label="方法" width="80">
          <template #default="{row}"><el-tag>{{ row.method || 'GET' }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="count" label="调用次数" width="120" sortable />
        <el-table-column prop="percentage" label="占比" width="100">
          <template #default="{row}">{{ (row.percentage || row.count).toFixed?.(1) || '-' }}%</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const period = ref('month')
const loading = ref(false)
const overview = ref({})
const apiTrend = ref([])
const endpointData = ref([])
const featureUsage = ref([])

const statsCards = ref([
  { label: '总调用次数', value: 0 },
  { label: '活跃端点', value: 0 },
  { label: '活跃功能', value: 0 },
])

function barWidth(count) {
  const max = Math.max(...apiTrend.value.map(i => i.count), 1)
  return (count / max) * 100
}

async function loadData() {
  loading.value = true
  try {
    const [ov, trend, ep, feat] = await Promise.all([
      axios.get(`/api/usage/overview?period=${period.value}`),
      axios.get(`/api/usage/api-calls?period=${period.value}`),
      axios.get(`/api/usage/endpoint-stats?period=${period.value}`),
      axios.get(`/api/usage/features?period=${period.value}`),
    ])
    const ovData = ov.data?.data || {}
    overview.value = ovData
    statsCards.value = [
      { label: '总调用次数', value: ovData.total_calls ?? ovData.total ?? 0 },
      { label: '活跃端点', value: ovData.active_endpoints ?? ovData.endpoints ?? 0 },
      { label: '活跃功能', value: ovData.active_features ?? ovData.features ?? 0 },
    ]
    apiTrend.value = trend.data?.data?.trend || trend.data?.data || []
    endpointData.value = ep.data?.data?.endpoints || ep.data?.data || []
    featureUsage.value = feat.data?.data?.usage || feat.data?.data || []
  } catch (e) {
    console.error('Failed to load usage data', e)
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
.stat-num { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.filter-card { margin-bottom: 16px; padding: 12px 16px; }
.bar-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.bar-label { width: 80px; font-size: 12px; color: #606266; text-align: right; flex-shrink: 0; }
.bar-track { flex: 1; height: 20px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.bar-fill { height: 100%; background: linear-gradient(90deg, #409eff, #337ecc); border-radius: 4px; transition: width 0.5s; }
.bar-value { width: 50px; font-size: 12px; color: #606266; text-align: left; }
.feature-item { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f2f5; }
.feature-item:last-child { border-bottom: none; }
.feature-rank { font-weight: 600; color: #409eff; min-width: 24px; }
.feature-name { flex: 1; font-size: 13px; }
</style>
