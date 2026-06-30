<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI 自适应安全阈值'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">根据实时威胁情报动态调整限流 QPS、信任分阈值、熔断参数</p>

    <div style="margin-bottom:16px">
      <el-button type="primary" :loading="loading" @click="loadData">
        <el-icon><Refresh /></el-icon> 获取推荐配置
      </el-button>
      <el-button :disabled="!config" @click="clearCache">清除缓存</el-button>
    </div>

    <el-alert v-if="config?.risk_level === 'critical'" type="error" :description="'当前安全风险等级: 严重'" show-icon style="margin-bottom:16px" />
    <el-alert v-if="config?.risk_level === 'high'" type="warning" :description="'当前安全风险等级: 高'" show-icon style="margin-bottom:16px" />

    <el-card v-if="config" shadow="hover">
      <template #header>推荐安全配置</template>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="限流 (QPS/分钟)">
          <el-tag>{{ config.recommended_config?.rate_limit_per_minute }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="信任分阈值">
          <el-tag>{{ config.recommended_config?.trust_score_threshold }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="熔断失败阈值">
          <el-tag>{{ config.recommended_config?.circuit_breaker_failure_threshold }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="熔断恢复时间(秒)">
          <el-tag>{{ config.recommended_config?.circuit_breaker_recovery_time }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="自动黑名单阈值">
          <el-tag>{{ config.recommended_config?.auto_blacklist_score }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="风险等级">
          <el-tag :type="config.risk_level === 'critical' ? 'danger' : config.risk_level === 'high' ? 'warning' : 'info'">{{ config.risk_level }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>
      <div v-if="config.reasoning" class="mt-2">{{ config.reasoning }}</div>
    </el-card>

    <el-card v-if="config?.alerts?.length" shadow="hover" style="margin-top:20px">
      <template #header>告警 <el-tag type="danger" size="small">{{ config.alerts.length }}</el-tag></template>
      <ul><li v-for="(alert, i) in config.alerts" :key="i">{{ alert }}</li></ul>
    </el-card>

    <el-empty v-if="!loading && !config" description="点击「获取推荐配置」开始分析" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getAdaptiveSecurity, clearAdaptiveSecurityCache } from '@/api/aiIntelligence'

const loading = ref(false)
const config = ref(null)

async function loadData() {
  loading.value = true
  try {
    const res = await getAdaptiveSecurity()
    config.value = res.data
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
}

async function clearCache() {
  try {
    await clearAdaptiveSecurityCache()
    ElMessage.success('缓存已清除')
    config.value = null
  } catch (_) { /* ignore */ }
}
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.text-muted { color: #909399; font-size: 14px; }
.mt-2 { margin-top: 8px; color: #606266; }
ul { padding-left: 20px; }
li { margin-bottom: 6px; }
</style>
