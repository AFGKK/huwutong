<template>
  <div class="alerting">
    <h2 class="mb-4">智能告警中心</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.active_rules }}</div><div class="stat-label">生效规则</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-danger">{{ stats.firing_events }}</div><div class="stat-label">触发中事件</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ stats.today_events }}</div><div class="stat-label">今日事件</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-success">{{ stats.active_channels }}</div><div class="stat-label">活跃渠道</div></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 严重程度分布及最近事件 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>严重程度分布</template>
          <div v-if="Object.keys(stats.by_severity || {}).length" class="flex flex-col gap-2">
            <div v-for="(cnt, sev) in stats.by_severity" :key="sev" class="flex items-center justify-between">
              <el-tag :type="severityTag(sev)" size="small">{{ severityLabel(sev) }}</el-tag>
              <span class="font-bold">{{ cnt }}</span>
            </div>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>最近告警事件</template>
          <el-table :data="stats.recent_events || []" stripe size="small" v-if="stats.recent_events?.length" style="cursor:pointer"
            @row-click="showEventDetail">
            <el-table-column label="标题" prop="title" min-width="200" show-overflow-tooltip />
            <el-table-column label="规则" prop="rule?.name" width="140" />
            <el-table-column label="严重程度" prop="severity" width="90">
              <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
            </el-table-column>
            <el-table-column label="状态" prop="status" width="80">
              <template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template>
            </el-table-column>
            <el-table-column label="触发时间" prop="fired_at" width="160" />
          </el-table>
          <el-empty v-else description="暂无告警事件" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs: 规则 / 渠道 / 升级策略 / 事件 -->
    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="告警规则" name="rules">
        <RulePanel @edit="editRule" />
      </el-tab-pane>
      <el-tab-pane label="通知渠道" name="channels">
        <ChannelPanel />
      </el-tab-pane>
      <el-tab-pane label="升级策略" name="escalations">
        <EscalationPanel />
      </el-tab-pane>
      <el-tab-pane label="告警事件" name="events">
        <EventPanel @detail="showEventDetail" />
      </el-tab-pane>
    </el-tabs>

    <!-- 规则对话框 -->
    <RuleDialog v-model:visible="ruleDialog.visible" :rule="ruleDialog.rule" @saved="onRuleSaved" />

    <!-- 事件详情对话框 -->
    <EventDetailDialog v-model:visible="eventDialog.visible" :event-id="eventDialog.id" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getAlertDashboard } from '../../api/alerting'
import RulePanel from './components/RulePanel.vue'
import ChannelPanel from './components/ChannelPanel.vue'
import EscalationPanel from './components/EscalationPanel.vue'
import EventPanel from './components/EventPanel.vue'
import RuleDialog from './components/RuleDialog.vue'
import EventDetailDialog from './components/EventDetailDialog.vue'

const stats = ref({ by_severity: {}, recent_events: [] })
const activeTab = ref('rules')
const ruleDialog = reactive({ visible: false, rule: null })
const eventDialog = reactive({ visible: false, id: null })

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function severityLabel(s) { return { info: '提示', warning: '警告', critical: '严重' }[s] || s }
function statusTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info' }
function statusLabel(s) { return { firing: '触发中', acknowledged: '已确认', resolved: '已解决' }[s] || s }

function editRule(rule) {
  ruleDialog.rule = rule
  ruleDialog.visible = true
}

function showEventDetail(event) {
  eventDialog.id = event.id || event
  eventDialog.visible = true
}

function onRuleSaved() {
  ruleDialog.visible = false
  ruleDialog.rule = null
  loadDashboard()
}

async function loadDashboard() {
  try {
    const { data } = await getAlertDashboard()
    stats.value = data
  } catch (e) {
    console.error('Dashboard load failed', e)
  }
}

onMounted(loadDashboard)
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-danger { color: #f56c6c !important; }
.flex { display: flex; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.font-bold { font-weight: 700; }
</style>
