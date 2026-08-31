<template>
  <div class="ai-feature-page">
    <el-page-header :content="t('revenue_forecast_page.title')" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">{{ t('revenue_forecast_page.desc') }}</p>

    <el-button type="primary" :loading="loading" @click="loadData" style="margin-bottom:16px">
      <el-icon><Refresh /></el-icon> {{ t('revenue_forecast_page.generate') }}
    </el-button>

    <el-row :gutter="20" v-if="report">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.confidence_score }}%</div><div class="stat-label">{{ t('revenue_forecast_page.confidence') }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.historical_data?.license_stats?.total_active || '-' }}</div><div class="stat-label">{{ t('revenue_forecast_page.active_licenses') }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.historical_data?.license_stats?.expiring_next_30d || 0 }}</div><div class="stat-label">{{ t('revenue_forecast_page.expiring_30d') }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ t('revenue_forecast_page.months_n', { n: report.historical_data?.total_months || 0 }) }}</div><div class="stat-label">{{ t('revenue_forecast_page.history_period') }}</div></el-card>
      </el-col>
    </el-row>

    <el-card v-if="report?.forecast?.length" shadow="hover" style="margin-top:20px">
      <template #header>{{ t('revenue_forecast_page.forecast_header', { n: report.horizon || 6 }) }}</template>
      <el-table :data="report.forecast" stripe size="small">
        <el-table-column prop="month" :label="t('revenue_forecast_page.cols.month')" width="120" />
        <el-table-column :label="t('revenue_forecast_page.cols.predicted')" width="150">
          <template #default="{ row }"><strong>{{ formatMoney(row.predicted_revenue) }}</strong></template>
        </el-table-column>
        <el-table-column :label="t('revenue_forecast_page.cols.lower')" width="150"><template #default="{ row }">{{ formatMoney(row.lower_bound) }}</template></el-table-column>
        <el-table-column :label="t('revenue_forecast_page.cols.upper')" width="150"><template #default="{ row }">{{ formatMoney(row.upper_bound) }}</template></el-table-column>
        <el-table-column :label="t('revenue_forecast_page.cols.confidence')" width="100"><template #default="{ row }">{{ row.confidence }}%</template></el-table-column>
      </el-table>
    </el-card>

    <el-row :gutter="20" style="margin-top:20px">
      <el-col :span="12">
        <el-card v-if="report?.insights?.length" shadow="hover">
          <template #header>{{ t('revenue_forecast_page.insights') }}</template>
          <ul><li v-for="(item, i) in report.insights" :key="i">{{ item }}</li></ul>
          <el-empty v-if="!report.insights.length" :description="t('revenue_forecast_page.no_insights')" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card v-if="report?.recommendations?.length" shadow="hover">
          <template #header>{{ t('revenue_forecast_page.recommendations') }}</template>
          <ul><li v-for="(item, i) in report.recommendations" :key="i">{{ item }}</li></ul>
          <el-empty v-if="!report.recommendations.length" :description="t('revenue_forecast_page.no_recommendations')" />
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && !report" :description="t('revenue_forecast_page.empty')" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import { getRevenueForecast } from '@/api/aiIntelligence'

const { t, locale } = useI18n()
const loading = ref(false)
const report = ref(null)

async function loadData() {
  loading.value = true
  try {
    const res = await getRevenueForecast({ horizon: 6 })
    report.value = res.data
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
}

function formatMoney(v) {
  if (v === null || v === undefined) return '-'
  const loc = locale.value === 'en' || locale.value?.startsWith('en') ? 'en-US' : 'zh-CN'
  return '¥' + Number(v).toLocaleString(loc, { minimumFractionDigits: 2 })
}
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 14px; }
ul { padding-left: 20px; }
li { margin-bottom: 8px; line-height: 1.6; }
</style>
