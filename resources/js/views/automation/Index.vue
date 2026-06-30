<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16">
        <el-col :span="6" v-for="stat in dashboardStats" :key="stat.label">
          <div class="dashboard-stat">
            <div class="stat-label">{{ stat.label }}</div>
            <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="规则列表" name="rules">
          <RuleList @view-rule="viewRule" />
        </el-tab-pane>
        <el-tab-pane label="执行历史" name="history">
          <ExecutionHistory />
        </el-tab-pane>
        <el-tab-pane label="Webhook 管理" name="webhooks">
          <WebhookList />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <RuleDetail ref="detailRef" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/automation'
import RuleList from './rules/RuleList.vue'
import RuleDetail from './rules/RuleDetail.vue'
import ExecutionHistory from './rules/ExecutionHistory.vue'
import WebhookList from './webhooks/WebhookList.vue'

const activeTab = ref('rules')
const detailRef = ref(null)
const dashboardStats = reactive([
  { label: '规则总数', value: 0, color: 'text-blue-500' },
  { label: '活跃规则', value: 0, color: 'text-green-500' },
  { label: '今日执行', value: 0, color: 'text-purple-500' },
  { label: '失败次数', value: 0, color: 'text-red-500' },
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
.text-blue-500 { color: #409eff; }
.text-green-500 { color: #67c23a; }
.text-purple-500 { color: #b37feb; }
.text-red-500 { color: #f56c6c; }
</style>
