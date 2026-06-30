<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI 客户流失预警'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">基于激活频率、续费历史、工单量等多维特征识别高风险客户</p>

    <el-button type="primary" :loading="loading" @click="loadData" style="margin-bottom:16px">
      <el-icon><Refresh /></el-icon> 开始分析
    </el-button>

    <el-row :gutter="20" v-if="result">
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ result.total_customers }}</div>
          <div class="stat-label">总客户数</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card danger">
          <div class="stat-value" style="color:#f56c6c">{{ result.high_risk?.length || 0 }}</div>
          <div class="stat-label">高风险</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :style="{ color: riskColor }">{{ riskLabel }}</div>
          <div class="stat-label">整体风险等级</div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-if="result" type="border-card" style="margin-top:20px">
      <el-tab-pane :label="`高风险 (${result.high_risk?.length || 0})`" name="high">
        <el-table :data="result.high_risk || []" stripe size="small">
          <el-table-column prop="name" label="客户" min-width="150" />
          <el-table-column prop="risk_score" label="风险评分" width="100">
            <template #default="{ row }"><el-tag :type="row.risk_score > 80 ? 'danger' : 'warning'">{{ row.risk_score }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="suggested_action" label="建议操作" min-width="200" />
        </el-table>
      </el-tab-pane>
      <el-tab-pane :label="`中风险 (${result.medium_risk?.length || 0})`" name="medium">
        <el-table :data="result.medium_risk || []" stripe size="small">
          <el-table-column prop="name" label="客户" min-width="150" />
          <el-table-column prop="risk_score" label="风险评分" width="100">
            <template #default="{ row }"><el-tag type="warning">{{ row.risk_score }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="suggested_action" label="建议操作" min-width="200" />
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <el-row :gutter="20" style="margin-top:20px" v-if="result">
      <el-col :span="12">
        <el-card v-if="result.insights?.length" shadow="hover">
          <template #header>洞察</template>
          <ul><li v-for="(item, i) in result.insights" :key="i">{{ item }}</li></ul>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card v-if="result.recommendations?.length" shadow="hover">
          <template #header>建议</template>
          <ul><li v-for="(item, i) in result.recommendations" :key="i">{{ item }}</li></ul>
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && !result" description="点击「开始分析」进行流失预警" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getChurnPrediction } from '@/api/aiIntelligence'

const loading = ref(false)
const result = ref(null)

const riskLabel = computed(() => {
  const map = { low: '低风险', medium: '中风险', high: '高风险' }
  return map[result.value?.overall_churn_risk] || result.value?.overall_churn_risk || '-'
})
const riskColor = computed(() => {
  const map = { low: '#67c23a', medium: '#e6a23c', high: '#f56c6c' }
  return map[result.value?.overall_churn_risk] || '#909399'
})

async function loadData() {
  loading.value = true
  try {
    const res = await getChurnPrediction({ customer_limit: 50 })
    result.value = res.data
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
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
