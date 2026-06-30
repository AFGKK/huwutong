<template>
  <div class="sla-dashboard">
    <h2 class="mb-4">SLA 服务等级协议</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_contracts }}</div>
            <div class="stat-label">SLA 合约总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ stats.active_contracts }}</div>
            <div class="stat-label">当前有效合约</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div :class="['stat-value', complianceColor]">{{ stats.monthly_compliance_rate }}%</div>
            <div class="stat-label">本月达标率</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-danger">{{ stats.open_breaches }}</div>
            <div class="stat-label">未处理违约</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 按等级分布 -->
    <el-row :gutter="20" class="mb-4" v-if="Object.keys(stats.by_level || {}).length">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>SLA 等级分布</template>
          <el-row :gutter="16">
            <el-col :span="6" v-for="(cnt, level) in stats.by_level" :key="level">
              <el-tag :type="tagType(level)" size="large" class="level-tag">
                {{ levelLabel(level) }}: {{ cnt }} 个
              </el-tag>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
    </el-row>

    <!-- 最近违约 -->
    <el-card shadow="hover" class="mb-4" v-if="stats.recent_breaches?.length">
      <template #header>最近违约事件</template>
      <el-table :data="stats.recent_breaches" stripe size="small">
        <el-table-column label="合约" prop="contract.name" min-width="140" />
        <el-table-column label="类型" prop="breach_type" width="120">
          <template #default="{ row }">
            <el-tag :type="breachTag(row.severity)" size="small">{{ breachTypeLabel(row.breach_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="严重程度" prop="severity" width="100">
          <template #default="{ row }">
            <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="描述" prop="description" min-width="240" show-overflow-tooltip />
        <el-table-column label="时间" prop="created_at" width="160">
          <template #default="{ row }">{{ row.created_at }}</template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Tabs: 合约 / 违约 / 补偿 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="SLA 合约" name="contracts">
        <ContractPanel @select="editContract" />
      </el-tab-pane>
      <el-tab-pane label="违约记录" name="breaches">
        <BreachPanel />
      </el-tab-pane>
      <el-tab-pane label="违约补偿" name="compensations">
        <CompensationPanel />
      </el-tab-pane>
    </el-tabs>

    <!-- 合约编辑对话框 -->
    <ContractDialog v-model:visible="contractDialog.visible" :contract="contractDialog.contract"
      @saved="onContractSaved" />

    <!-- 指标编辑对话框 -->
    <MetricDialog v-model:visible="metricDialog.visible" :contract-id="metricDialog.contractId"
      :metric="metricDialog.metric" @saved="onMetricSaved" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getSlaDashboard } from '../../api/sla'
import ContractPanel from './components/ContractPanel.vue'
import BreachPanel from './components/BreachPanel.vue'
import CompensationPanel from './components/CompensationPanel.vue'
import ContractDialog from './components/ContractDialog.vue'
import MetricDialog from './components/MetricDialog.vue'

const stats = ref({ by_level: {}, recent_breaches: [] })
const activeTab = ref('contracts')
const contractDialog = reactive({ visible: false, contract: null })
const metricDialog = reactive({ visible: false, contractId: null, metric: null })

const complianceColor = computed(() => {
  const v = stats.value.monthly_compliance_rate
  if (v >= 95) return 'text-success'
  if (v >= 80) return 'text-warning'
  return 'text-danger'
})

function tagType(level) {
  const map = { standard: '', premium: 'success', enterprise: 'warning', custom: 'info' }
  return map[level] || ''
}

function levelLabel(level) {
  const map = { standard: '标准', premium: '高级', enterprise: '企业', custom: '自定义' }
  return map[level] || level
}

function severityTag(s) {
  const map = { minor: 'info', major: 'warning', critical: 'danger' }
  return map[s] || 'info'
}

function breachTypeLabel(t) {
  const map = { response_time: '响应时间', resolution_time: '解决时间', uptime: '正常运行', availability: '可用性' }
  return map[t] || t
}

function breachTag(s) {
  const map = { minor: 'info', major: 'warning', critical: 'danger' }
  return map[s] || 'info'
}

function editContract(contract) {
  contractDialog.contract = contract
  contractDialog.visible = true
}

function onContractSaved() {
  contractDialog.visible = false
  contractDialog.contract = null
  loadDashboard()
}

function onMetricSaved() {
  metricDialog.visible = false
  metricDialog.metric = null
}

async function loadDashboard() {
  try {
    const { data } = await getSlaDashboard()
    stats.value = data
  } catch (e) {
    console.error('Failed to load SLA dashboard', e)
  }
}

onMounted(loadDashboard)
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.level-tag { display: inline-flex; align-items: center; font-size: 14px; padding: 8px 16px; }
</style>
