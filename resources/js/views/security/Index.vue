<template>
  <div>
    <el-tabs v-model="secMainTab">
      <el-tab-pane label="安全概览" name="overview">
        <el-card shadow="never" class="mb-4">
          <el-row :gutter="16" justify="space-between" align="middle">
            <el-col :span="12">
              <span class="text-lg font-medium">{{ t('security_page.title') }}</span>
              <span class="text-gray-400 text-sm ml-4">{{ t('security_page.active_sessions', { count: dashboardData?.active_sessions || 0 }) }}</span>
            </el-col>
            <el-col :span="12" class="text-right">
              <el-button type="primary" size="small" @click="refreshAll">{{ t('security_page.refresh') }}</el-button>
            </el-col>
          </el-row>
        </el-card>

        <!-- 安全评分 -->
        <el-card shadow="never" class="mb-4">
          <el-row :gutter="24" align="middle">
            <el-col :span="4" class="text-center">
              <div class="score-circle" :class="scoreLevel">
                <span class="score-value">{{ securityScore?.score ?? '—' }}</span>
                <div class="score-label">{{ t('security_page.score_label') }}</div>
              </div>
            </el-col>
            <el-col :span="20">
              <div v-if="securityScore?.checks">
                <div v-for="c in securityScore.checks" :key="c.item" class="score-item">
                  <span class="score-status" :class="c.deduction > 0 ? 'text-orange' : 'text-green'">
                    {{ c.deduction > 0 ? t('security_page.checks.warn') : t('security_page.checks.ok') }}
                  </span>
                  <span class="score-item-label">{{ checkItemLabel(c.item) }}{{ t('security_page.checks.label_suffix') }}</span>
                  <span class="score-item-value">{{ checkStatusLabel(c) }}</span>
                </div>
              </div>
            </el-col>
          </el-row>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
          <el-col :span="4" v-for="stat in statCards" :key="stat.key">
            <el-card shadow="never" class="stat-card">
              <div class="stat-value">{{ stat.value }}</div>
              <div class="stat-label">{{ stat.label }}</div>
            </el-card>
          </el-col>
        </el-row>

        <!-- Tabs -->
        <el-card shadow="never">
          <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('security_page.tabs.whitelist')" name="whitelist">
              <IpWhitelistPanel />
            </el-tab-pane>
            <el-tab-pane :label="t('security_page.tabs.policies')" name="policies">
              <PolicyPanel />
            </el-tab-pane>
            <el-tab-pane :label="t('security_page.tabs.sessions')" name="sessions">
              <SessionPanel />
            </el-tab-pane>
            <el-tab-pane :label="t('security_page.tabs.events')" name="events">
              <EventPanel />
            </el-tab-pane>
            <el-tab-pane :label="t('security_page.tabs.sop')" name="sop">
              <SecuritySopPanel />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('security_headers_page.title')" name="headers">
        <div v-if="sh_tabVisited" class="security-headers-page">
          <div class="page-header">
            <div class="header-left">
              <h2>{{ t('security_headers_page.title') }}</h2>
              <span class="header-subtitle">{{ t('security_headers_page.subtitle') }}</span>
            </div>
            <div class="header-right">
              <el-button @click="sh_handleReset">{{ t('security_headers_page.reset_defaults') }}</el-button>
              <el-button type="primary" @click="sh_handleSave" :loading="sh_saving">{{ t('actions.save') }}</el-button>
            </div>
          </div>

          <el-row :gutter="16">
            <el-col :span="16">
              <el-card shadow="never">
                <template #header><span>{{ t('security_headers_page.config_card') }}</span></template>
                <el-form :model="sh_form" label-width="200px" label-position="left">
                  <!-- HSTS -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.hsts') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.enable_hsts')">
                    <el-switch v-model="sh_form.hsts" />
                  </el-form-item>
                  <el-form-item v-if="sh_form.hsts" :label="t('security_headers_page.form.max_age_seconds')">
                    <el-input-number v-model="sh_form.hsts_max_age" :min="0" :max="31536000" :step="86400" />
                    <span class="ml-2 text-muted">{{ t('security_headers_page.form.max_age_default_hint') }}</span>
                  </el-form-item>
                  <el-form-item v-if="sh_form.hsts" :label="t('security_headers_page.form.include_subdomains')">
                    <el-switch v-model="sh_form.hsts_include_subdomains" />
                  </el-form-item>

                  <!-- X-Frame-Options -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.x_frame_options') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.policy')">
                    <el-select v-model="sh_form.x_frame_options" style="width:200px">
                      <el-option
                        v-for="opt in sh_xFrameOptions"
                        :key="opt.value"
                        :label="opt.label"
                        :value="opt.value"
                      />
                    </el-select>
                  </el-form-item>
                  <el-form-item v-if="sh_form.x_frame_options === 'ALLOW-FROM'" :label="t('security_headers_page.form.allowed_origin')">
                    <el-input v-model="sh_form.x_frame_options_origin" :placeholder="t('security_headers_page.form.allowed_origin_ph')" />
                  </el-form-item>

                  <!-- X-Content-Type-Options -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.x_content_type_options') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.policy')">
                    <el-select v-model="sh_form.x_content_type_options" style="width:200px">
                      <el-option
                        v-for="opt in sh_xContentTypeOptions"
                        :key="opt.value"
                        :label="opt.label"
                        :value="opt.value"
                      />
                    </el-select>
                  </el-form-item>

                  <!-- Referrer-Policy -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.referrer_policy') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.policy')">
                    <el-select v-model="sh_form.referrer_policy" style="width:300px">
                      <el-option
                        v-for="opt in sh_referrerPolicyOptions"
                        :key="opt.value"
                        :label="opt.label"
                        :value="opt.value"
                      />
                    </el-select>
                  </el-form-item>

                  <!-- Permissions-Policy -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.permissions_policy') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.enable')">
                    <el-switch v-model="sh_form.permissions_policy_enabled" />
                  </el-form-item>
                  <el-form-item v-if="sh_form.permissions_policy_enabled" :label="t('security_headers_page.form.policy_value')">
                    <el-input v-model="sh_form.permissions_policy" type="textarea" :rows="2" />
                  </el-form-item>

                  <!-- X-XSS-Protection -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.x_xss_protection') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.policy')">
                    <el-select v-model="sh_form.x_xss_protection" style="width:200px">
                      <el-option
                        v-for="opt in sh_xXssProtectionOptions"
                        :key="opt.value"
                        :label="opt.label"
                        :value="opt.value"
                      />
                    </el-select>
                  </el-form-item>

                  <!-- Cache-Control -->
                  <el-divider content-position="left">{{ t('security_headers_page.sections.cache_control') }}</el-divider>
                  <el-form-item :label="t('security_headers_page.form.enable')">
                    <el-switch v-model="sh_form.cache_control_enabled" />
                  </el-form-item>
                  <el-form-item v-if="sh_form.cache_control_enabled" :label="t('security_headers_page.form.policy_value')">
                    <el-input v-model="sh_form.cache_control" />
                  </el-form-item>
                </el-form>
              </el-card>
            </el-col>

            <el-col :span="8">
              <el-card shadow="never">
                <template #header>
                  <span>{{ t('security_headers_page.preview_card') }}</span>
                  <el-button size="small" @click="sh_loadPreview">{{ t('security_headers_page.refresh') }}</el-button>
                </template>
                <div v-if="sh_previewHeaders">
                  <div v-for="(val, key) in sh_previewHeaders" :key="key" class="header-item">
                    <div class="header-key">{{ key }}</div>
                    <div class="header-value">{{ val }}</div>
                  </div>
                </div>
                <el-empty v-else :description="t('security_headers_page.preview_empty')" />
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getSecurityDashboard, getSecurityScore } from '../../api/securityCenter'
import securityHeadersApi from '@/api/securityHeaders'
import IpWhitelistPanel from './components/IpWhitelistPanel.vue'
import PolicyPanel from './components/PolicyPanel.vue'
import SessionPanel from './components/SessionPanel.vue'
import EventPanel from './components/EventPanel.vue'
import SecuritySopPanel from './components/SecuritySopPanel.vue'

const { t, te } = useI18n()

// ── 顶层 tab ──
const secMainTab = ref('overview')

// ── 安全概览 ──
const activeTab = ref('whitelist')
const dashboardData = ref({})
const securityScore = ref(null)

const statCards = computed(() => [
  { key: 'ip_rules', label: t('security_page.stats.ip_rules'), value: dashboardData.value?.whitelist_count ?? 0 },
  { key: 'active_rules', label: t('security_page.stats.active_rules'), value: dashboardData.value?.active_whitelist ?? 0 },
  { key: 'active_sessions', label: t('security_page.stats.active_sessions'), value: dashboardData.value?.active_sessions ?? 0 },
  { key: 'failed_logins_24h', label: t('security_page.stats.failed_logins_24h'), value: dashboardData.value?.failed_logins_24h ?? 0 },
  { key: 'policies_applied', label: t('security_page.stats.policies_applied'), value: dashboardData.value?.policies_applied ?? 0 },
  { key: 'today_events', label: t('security_page.stats.today_events'), value: dashboardData.value?.recent_events?.length ?? 0 },
])

function checkItemLabel(item) {
  const key = `security_page.checks.items.${item}`
  return te(key) ? t(key) : item
}

function checkStatusLabel(check) {
  const status = typeof check === 'string' ? check : check?.status
  const params = (typeof check === 'object' && check?.status_params) || {}
  const key = `security_page.checks.status.${status}`
  if (te(key)) return t(key, params)
  return status || ''
}

const scoreLevel = computed(() => {
  if (!securityScore.value) return ''
  return securityScore.value.level === 'good' ? 'score-good' : securityScore.value.level === 'fair' ? 'score-fair' : 'score-poor'
})

async function fetchDashboard() {
  try {
    const { data } = await getSecurityDashboard()
    dashboardData.value = data || {}
  } catch (e) { /* ignore */ }
}

async function fetchScore() {
  try {
    const { data } = await getSecurityScore()
    securityScore.value = data
  } catch (e) { /* ignore */ }
}

function refreshAll() {
  fetchDashboard()
  fetchScore()
}

// ── 安全响应头 ──
const sh_tabVisited = ref(false)
const sh_form = ref({})
const sh_previewHeaders = ref(null)
const sh_saving = ref(false)

const sh_xFrameOptions = computed(() => [
  { value: 'DENY', label: t('security_headers_page.x_frame_options.deny') },
  { value: 'SAMEORIGIN', label: t('security_headers_page.x_frame_options.sameorigin') },
  { value: 'ALLOW-FROM', label: t('security_headers_page.x_frame_options.allow_from') },
  { value: 'off', label: t('security_headers_page.x_frame_options.off') },
])

const sh_xContentTypeOptions = computed(() => [
  { value: 'nosniff', label: t('security_headers_page.x_content_type_options.nosniff') },
  { value: 'off', label: t('security_headers_page.x_content_type_options.off') },
])

const sh_referrerPolicyOptions = computed(() => [
  { value: 'strict-origin-when-cross-origin', label: 'strict-origin-when-cross-origin' },
  { value: 'no-referrer', label: 'no-referrer' },
  { value: 'same-origin', label: 'same-origin' },
  { value: 'origin', label: 'origin' },
  { value: 'strict-origin', label: 'strict-origin' },
  { value: 'unsafe-url', label: 'unsafe-url' },
  { value: 'off', label: t('security_headers_page.referrer_policy.off') },
])

const sh_xXssProtectionOptions = computed(() => [
  { value: '1; mode=block', label: t('security_headers_page.x_xss_protection.mode_block') },
  { value: '1', label: t('security_headers_page.x_xss_protection.enabled') },
  { value: '0', label: t('security_headers_page.x_xss_protection.disabled') },
  { value: 'off', label: t('security_headers_page.x_xss_protection.off') },
])

function sh_unwrap(res) {
  const body = res?.data ?? res
  return body?.data ?? body
}

async function sh_loadConfig() {
  try {
    const res = await securityHeadersApi.getConfig()
    sh_form.value = sh_unwrap(res) || {}
  } catch (e) {
    ElMessage.error(t('security_headers_page.messages.config_load_failed'))
  }
}

async function sh_loadPreview() {
  try {
    const res = await securityHeadersApi.preview()
    const data = sh_unwrap(res)
    sh_previewHeaders.value = data?.headers || null
  } catch (e) {
    ElMessage.error(t('security_headers_page.messages.preview_load_failed'))
  }
}

async function sh_handleSave() {
  sh_saving.value = true
  try {
    await securityHeadersApi.updateConfig(sh_form.value)
    ElMessage.success(t('security_headers_page.messages.saved'))
    await sh_loadPreview()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('security_headers_page.messages.save_failed'))
  } finally {
    sh_saving.value = false
  }
}

async function sh_handleReset() {
  try {
    await securityHeadersApi.reset()
    await sh_loadConfig()
    await sh_loadPreview()
    ElMessage.success(t('security_headers_page.messages.reset_success'))
  } catch (e) {
    ElMessage.error(t('security_headers_page.messages.reset_failed'))
  }
}

// ── 懒加载：首次切换到安全响应头 tab 时加载数据 ──
watch(secMainTab, (val) => {
  if (val === 'headers' && !sh_tabVisited.value) {
    sh_tabVisited.value = true
    sh_loadConfig()
    sh_loadPreview()
  }
})

onMounted(() => {
  fetchDashboard()
  fetchScore()
})
</script>

<style scoped>
/* ── 安全概览 ── */
.mb-4 { margin-bottom: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
.text-right { text-align: right; }
.text-gray-400 { color: #909399; }
.text-sm { font-size: 13px; }
.text-center { text-align: center; }
.text-green { color: #67c23a; }
.text-orange { color: #e6a23c; }
.ml-4 { margin-left: 16px; }

.score-circle { width: 90px; height: 90px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto; }
.score-good { background: #e1f3d8; color: #67c23a; }
.score-fair { background: #faecd8; color: #e6a23c; }
.score-poor { background: #fde2e2; color: #f56c6c; }
.score-value { font-size: 28px; font-weight: 700; line-height: 1; }
.score-label { font-size: 11px; margin-top: 2px; }

.score-item { display: flex; align-items: center; margin-bottom: 4px; font-size: 13px; }
.score-status { margin-right: 6px; font-size: 14px; }
.score-item-label { color: #606266; min-width: 100px; }
.score-item-value { color: #303133; }

.stat-card { text-align: center; }
.stat-value { font-size: 22px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }

/* ── 安全响应头 ── */
.security-headers-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; display: inline; }
.header-subtitle { font-size: 13px; color: #999; margin-left: 8px; }
.header-item { padding: 8px; margin-bottom: 4px; background: #f5f7fa; border-radius: 4px; word-break: break-all; }
.header-key { font-weight: bold; font-size: 12px; color: #0f172a; margin-bottom: 2px; }
.header-value { font-size: 11px; color: #666; font-family: monospace; }
.text-muted { color: #999; font-size: 12px; }
.ml-2 { margin-left: 8px; }
</style>
