<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16">
        <el-col :span="6" v-for="stat in dashboardStats" :key="stat.key">
          <div class="dashboard-stat">
            <div class="stat-label">{{ t(`automation_page.stats.${stat.key}`) }}</div>
            <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('automation_page.tabs.rules')" name="rules">
          <RuleList @view-rule="viewRule" />
        </el-tab-pane>
        <el-tab-pane :label="t('automation_page.tabs.history')" name="history">
          <ExecutionHistory />
        </el-tab-pane>
        <el-tab-pane :label="t('automation_page.tabs.webhooks')" name="webhooks">
          <WebhookList />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <RuleDetail ref="detailRef" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../api/automation'
import RuleList from './rules/RuleList.vue'
import RuleDetail from './rules/RuleDetail.vue'
import ExecutionHistory from './rules/ExecutionHistory.vue'
import WebhookList from './webhooks/WebhookList.vue'

const { t } = useI18n()
const activeTab = ref('rules')
const detailRef = ref(null)
const dashboardStats = reactive([
  { key: 'total_rules', value: 0, color: 'text-blue-500' },
  { key: 'active_rules', value: 0, color: 'text-green-500' },
  { key: 'today_runs', value: 0, color: 'text-purple-500' },
  { key: 'failed', value: 0, color: 'text-red-500' },
])

async function loadDashboard() {
  try {
    const { data } = await api.dashboard()
    if (data?.stats) {
      dashboardStats[0].value = data.stats.total_rules
      dashboardStats[1].value = data.stats.active_rules
      dashboardStats[2].value = data.stats.recent_executions ?? data.stats.total_executions
      dashboardStats[3].value = data.stats.failed_executions
    }
  } catch (e) { /* ignore */ }
}

function viewRule(row) {
  detailRef.value?.open(row)
}

onMounted(() => loadDashboard())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.dashboard-stat { padding: 8px 0; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.text-blue-500 { color: #0f172a; }
.text-green-500 { color: #67c23a; }
.text-purple-500 { color: #b37feb; }
.text-red-500 { color: #f56c6c; }
</style>
