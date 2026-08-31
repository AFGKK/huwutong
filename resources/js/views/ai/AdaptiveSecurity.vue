<template>
  <div class="ai-feature-page">
    <el-page-header :content="t('adaptive_security_page.title')" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">{{ t('adaptive_security_page.subtitle') }}</p>

    <div style="margin-bottom:16px">
      <el-button type="primary" :loading="loading" @click="loadData">
        <el-icon><Refresh /></el-icon> {{ t('adaptive_security_page.fetch') }}
      </el-button>
      <el-button :disabled="!config" @click="clearCache">{{ t('adaptive_security_page.clear_cache') }}</el-button>
    </div>

    <el-alert v-if="config?.risk_level === 'critical'" type="error" :description="t('adaptive_security_page.risk_critical')" show-icon style="margin-bottom:16px" />
    <el-alert v-if="config?.risk_level === 'high'" type="warning" :description="t('adaptive_security_page.risk_high')" show-icon style="margin-bottom:16px" />

    <el-card v-if="config" shadow="hover">
      <template #header>{{ t('adaptive_security_page.config_title') }}</template>
      <el-descriptions :column="2" border>
        <el-descriptions-item :label="t('adaptive_security_page.rate_limit')">
          <el-tag>{{ config.recommended_config?.rate_limit_per_minute }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('adaptive_security_page.trust_threshold')">
          <el-tag>{{ config.recommended_config?.trust_score_threshold }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('adaptive_security_page.cb_failure')">
          <el-tag>{{ config.recommended_config?.circuit_breaker_failure_threshold }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('adaptive_security_page.cb_recovery')">
          <el-tag>{{ config.recommended_config?.circuit_breaker_recovery_time }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('adaptive_security_page.auto_blacklist')">
          <el-tag>{{ config.recommended_config?.auto_blacklist_score }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('adaptive_security_page.risk_level')">
          <el-tag :type="config.risk_level === 'critical' ? 'danger' : config.risk_level === 'high' ? 'warning' : 'info'">{{ config.risk_level }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>
      <div v-if="config.reasoning" class="mt-2">{{ config.reasoning }}</div>
    </el-card>

    <el-card v-if="config?.alerts?.length" shadow="hover" style="margin-top:20px">
      <template #header>{{ t('adaptive_security_page.alerts') }} <el-tag type="danger" size="small">{{ config.alerts.length }}</el-tag></template>
      <ul><li v-for="(alert, i) in config.alerts" :key="i">{{ alert }}</li></ul>
    </el-card>

    <el-empty v-if="!loading && !config" :description="t('adaptive_security_page.empty')" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getAdaptiveSecurity, clearAdaptiveSecurityCache } from '@/api/aiIntelligence'

const { t } = useI18n()

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
    ElMessage.success(t('adaptive_security_page.messages.cleared'))
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
