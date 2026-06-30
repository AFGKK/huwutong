<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">安全中心</span>
          <span class="text-gray-400 text-sm ml-4">{{ dashboardData?.active_sessions || 0 }} 活跃会话</span>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button type="primary" size="small" @click="refreshAll">刷新</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 安全评分 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="24" align="middle">
        <el-col :span="4" class="text-center">
          <div class="score-circle" :class="scoreLevel">
            <span class="score-value">{{ securityScore?.score ?? '—' }}</span>
            <div class="score-label">安全评分</div>
          </div>
        </el-col>
        <el-col :span="20">
          <div v-if="securityScore?.checks">
            <div v-for="c in securityScore.checks" :key="c.item" class="score-item">
              <span class="score-status" :class="c.deduction > 0 ? 'text-orange' : 'text-green'">
                {{ c.deduction > 0 ? '⚠' : '✓' }}
              </span>
              <span class="score-item-label">{{ c.item }}：</span>
              <span class="score-item-value">{{ c.status }}</span>
            </div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4" v-for="stat in statCards" :key="stat.label">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="IP 白名单" name="whitelist">
          <IpWhitelistPanel />
        </el-tab-pane>
        <el-tab-pane label="登录策略" name="policies">
          <PolicyPanel />
        </el-tab-pane>
        <el-tab-pane label="会话管理" name="sessions">
          <SessionPanel />
        </el-tab-pane>
        <el-tab-pane label="安全事件" name="events">
          <EventPanel />
        </el-tab-pane>
        <el-tab-pane label="SOP响应编排" name="sop">
          <SecuritySopPanel />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getSecurityDashboard, getSecurityScore } from '../../api/securityCenter'
import IpWhitelistPanel from './components/IpWhitelistPanel.vue'
import PolicyPanel from './components/PolicyPanel.vue'
import SessionPanel from './components/SessionPanel.vue'
import EventPanel from './components/EventPanel.vue'
import SecuritySopPanel from './components/SecuritySopPanel.vue'

const activeTab = ref('whitelist')
const dashboardData = ref({})
const securityScore = ref(null)

const statCards = computed(() => [
  { label: 'IP 规则', value: dashboardData.value?.whitelist_count ?? 0 },
  { label: '活跃规则', value: dashboardData.value?.active_whitelist ?? 0 },
  { label: '活跃会话', value: dashboardData.value?.active_sessions ?? 0 },
  { label: '失败登录(24h)', value: dashboardData.value?.failed_logins_24h ?? 0 },
  { label: '启用策略', value: dashboardData.value?.policies_applied ?? 0 },
  { label: '今日事件', value: dashboardData.value?.recent_events?.length ?? 0 },
])

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

onMounted(() => {
  fetchDashboard()
  fetchScore()
})
</script>

<style scoped>
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
.stat-value { font-size: 22px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
