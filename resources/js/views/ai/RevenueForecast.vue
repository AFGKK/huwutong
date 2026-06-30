<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI 收入预测'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">基于历史订阅/发票数据，使用大模型分析月度/季度 MRR/ARR 趋势</p>

    <el-button type="primary" :loading="loading" @click="loadData" style="margin-bottom:16px">
      <el-icon><Refresh /></el-icon> 生成预测
    </el-button>

    <el-row :gutter="20" v-if="report">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.confidence_score }}%</div><div class="stat-label">置信度</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.historical_data?.license_stats?.total_active || '-' }}</div><div class="stat-label">活跃 License</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.historical_data?.license_stats?.expiring_next_30d || 0 }}</div><div class="stat-label">30天内到期</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ report.historical_data?.total_months || 0 }}月</div><div class="stat-label">历史周期</div></el-card>
      </el-col>
    </el-row>

    <!-- 预测表格 -->
    <el-card v-if="report?.forecast?.length" shadow="hover" style="margin-top:20px">
      <template #header>收入预测（未来 {{ report.horizon || 6 }} 个月）</template>
      <el-table :data="report.forecast" stripe size="small">
        <el-table-column prop="month" label="月份" width="120" />
        <el-table-column label="预测收入" width="150">
          <template #default="{ row }"><strong>{{ formatMoney(row.predicted_revenue) }}</strong></template>
        </el-table-column>
        <el-table-column label="下限" width="150"><template #default="{ row }">{{ formatMoney(row.lower_bound) }}</template></el-table-column>
        <el-table-column label="上限" width="150"><template #default="{ row }">{{ formatMoney(row.upper_bound) }}</template></el-table-column>
        <el-table-column label="置信度" width="100"><template #default="{ row }">{{ row.confidence }}%</template></el-table-column>
      </el-table>
    </el-card>

    <el-row :gutter="20" style="margin-top:20px">
      <el-col :span="12">
        <el-card v-if="report?.insights?.length" shadow="hover">
          <template #header>洞察分析</template>
          <ul><li v-for="(item, i) in report.insights" :key="i">{{ item }}</li></ul>
          <el-empty v-if="!report.insights.length" description="暂无洞察" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card v-if="report?.recommendations?.length" shadow="hover">
          <template #header>建议</template>
          <ul><li v-for="(item, i) in report.recommendations" :key="i">{{ item }}</li></ul>
          <el-empty v-if="!report.recommendations.length" description="暂无建议" />
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && !report" description="点击「生成预测」开始分析" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getRevenueForecast } from '@/api/aiIntelligence'

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
  return '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
}
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 14px; }
ul { padding-left: 20px; }
li { margin-bottom: 8px; line-height: 1.6; }
</style>
