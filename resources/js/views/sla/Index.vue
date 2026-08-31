<template>
  <div class="sla-management-center">
    <h2 class="mb-4">{{ t('sla_page.title') }}</h2>

    <el-tabs v-model="slaMainTab" type="border-card">
      <!-- Tab1: SLA 追踪 -->
      <el-tab-pane label="SLA 追踪" name="tracking">
        <div class="sla-dashboard">
          <!-- 概览统计 -->
          <el-row :gutter="20" class="mb-4">
            <el-col :span="6" v-for="item in statCards" :key="item.key">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div :class="['stat-value', item.valueClass]">{{ item.value }}</div>
                  <div class="stat-label">{{ item.label }}</div>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <!-- 按等级分布 -->
          <el-row :gutter="20" class="mb-4" v-if="Object.keys(stats.by_level || {}).length">
            <el-col :span="24">
              <el-card shadow="hover">
                <template #header>{{ t('sla_page.level_distribution') }}</template>
                <el-row :gutter="16">
                  <el-col :span="6" v-for="(cnt, level) in stats.by_level" :key="level">
                    <el-tag :type="tagType(level)" size="large" class="level-tag">
                      {{ t('sla_page.level_count', { label: levelLabel(level), count: cnt }) }}
                    </el-tag>
                  </el-col>
                </el-row>
              </el-card>
            </el-col>
          </el-row>

          <!-- 最近违约 -->
          <el-card shadow="hover" class="mb-4" v-if="stats.recent_breaches?.length">
            <template #header>{{ t('sla_page.recent_breaches') }}</template>
            <el-table :data="stats.recent_breaches" stripe size="small">
              <el-table-column :label="t('sla_page.cols.contract')" prop="contract.name" min-width="140" />
              <el-table-column :label="t('sla_page.cols.type')" prop="breach_type" width="120">
                <template #default="{ row }">
                  <el-tag :type="breachTag(row.severity)" size="small">{{ breachTypeLabel(row.breach_type) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column :label="t('sla_page.cols.severity')" prop="severity" width="100">
                <template #default="{ row }">
                  <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column :label="t('sla_page.cols.description')" prop="description" min-width="240" show-overflow-tooltip />
              <el-table-column :label="t('sla_page.cols.time')" prop="created_at" width="160">
                <template #default="{ row }">{{ row.created_at }}</template>
              </el-table-column>
            </el-table>
          </el-card>

          <!-- Tabs: 合约 / 违约 / 补偿 -->
          <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('sla_page.tabs.contracts')" name="contracts">
              <ContractPanel @select="editContract" />
            </el-tab-pane>
            <el-tab-pane :label="t('sla_page.tabs.breaches')" name="breaches">
              <BreachPanel />
            </el-tab-pane>
            <el-tab-pane :label="t('sla_page.tabs.compensations')" name="compensations">
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
      </el-tab-pane>

      <!-- Tab2: SLA 拨测（懒加载） -->
      <el-tab-pane label="SLA 拨测" name="probes">
        <div v-if="sp_tabVisited" class="sla-probes-page">
          <div class="page-header">
            <div>
              <el-button type="primary" @click="sp_openDialog()"><el-icon><Plus /></el-icon> {{ t('sla_probes_page.create_btn') }}</el-button>
              <el-button @click="sp_refreshAll" :loading="sp_loading"><el-icon><Refresh /></el-icon> {{ t('sla_probes_page.refresh') }}</el-button>
            </div>
          </div>

          <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" v-for="item in sp_statCards" :key="item.key">
              <el-card shadow="hover">
                <div class="stat-value">{{ sp_dashboard[item.key] ?? 0 }}</div>
                <div class="stat-label">{{ item.label }}</div>
              </el-card>
            </el-col>
          </el-row>

          <el-card shadow="never">
            <el-table :data="sp_probes" v-loading="sp_loading" stripe>
              <el-table-column prop="name" :label="t('sla_probes_page.col_name')" min-width="140" />
              <el-table-column prop="url" :label="t('sla_probes_page.col_url')" min-width="220" show-overflow-tooltip />
              <el-table-column prop="method" :label="t('sla_probes_page.col_method')" width="80" />
              <el-table-column prop="interval_minutes" :label="t('sla_probes_page.col_interval')" width="90" />
              <el-table-column :label="t('sla_probes_page.col_status')" width="90">
                <template #default="{ row }">
                  <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('sla_probes_page.status_active') : t('sla_probes_page.status_inactive') }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column :label="t('sla_probes_page.col_actions')" width="220" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" link @click="sp_handleToggle(row)">{{ row.is_active ? t('actions.disable') : t('actions.enable') }}</el-button>
                  <el-button size="small" type="primary" link @click="sp_handleRun(row)">{{ t('sla_probes_page.run_now') }}</el-button>
                  <el-button size="small" type="danger" link @click="sp_handleDelete(row)">{{ t('actions.delete') }}</el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>

          <el-dialog v-model="sp_dialog.visible" :title="t('sla_probes_page.create_dialog_title')" width="520px">
            <el-form :model="sp_form" label-width="100px">
              <el-form-item :label="t('sla_probes_page.form_name')"><el-input v-model="sp_form.name" /></el-form-item>
              <el-form-item :label="t('sla_probes_page.form_url')"><el-input v-model="sp_form.url" :placeholder="t('sla_probes_page.url_ph')" /></el-form-item>
              <el-form-item :label="t('sla_probes_page.form_method')">
                <el-select v-model="sp_form.method" style="width: 100%">
                  <el-option label="GET" value="GET" />
                  <el-option label="POST" value="POST" />
                  <el-option label="HEAD" value="HEAD" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('sla_probes_page.form_interval')"><el-input-number v-model="sp_form.interval_minutes" :min="1" :max="1440" /></el-form-item>
            </el-form>
            <template #footer>
              <el-button @click="sp_dialog.visible = false">{{ t('actions.cancel') }}</el-button>
              <el-button type="primary" :loading="sp_saving" @click="sp_handleSave">{{ t('actions.save') }}</el-button>
            </template>
          </el-dialog>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import { getSlaDashboard } from '../../api/sla'
import slaProbeApi from '@/api/slaProbe'
import ContractPanel from './components/ContractPanel.vue'
import BreachPanel from './components/BreachPanel.vue'
import CompensationPanel from './components/CompensationPanel.vue'
import ContractDialog from './components/ContractDialog.vue'
import MetricDialog from './components/MetricDialog.vue'

const { t } = useI18n()

// ── Tab 切换 ──
const slaMainTab = ref('tracking')

// ── Tab2 懒加载 ──
const sp_tabVisited = ref(false)
watch(slaMainTab, (val) => {
  if (val === 'probes' && !sp_tabVisited.value) {
    sp_tabVisited.value = true
  }
})

// ══════════════════ SLA 追踪（原 sla） ══════════════════
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

const statCardMeta = [
  { key: 'total_contracts', labelKey: 'sla_page.stats.total_contracts' },
  { key: 'active_contracts', labelKey: 'sla_page.stats.active_contracts', valueClass: 'text-success' },
  { key: 'monthly_compliance_rate', labelKey: 'sla_page.stats.monthly_compliance_rate', suffix: '%', valueClassFrom: 'complianceColor' },
  { key: 'open_breaches', labelKey: 'sla_page.stats.open_breaches', valueClass: 'text-danger' },
]

const statCards = computed(() => statCardMeta.map((m) => ({
  key: m.key,
  label: t(m.labelKey),
  value: `${stats.value[m.key] ?? 0}${m.suffix || ''}`,
  valueClass: m.valueClassFrom ? complianceColor.value : (m.valueClass || ''),
})))

const levelLabels = computed(() => ({
  standard: t('sla_page.levels.standard'),
  premium: t('sla_page.levels.premium'),
  enterprise: t('sla_page.levels.enterprise'),
  custom: t('sla_page.levels.custom'),
}))

const breachTypeLabels = computed(() => ({
  response_time: t('sla_page.breach_types.response_time'),
  resolution_time: t('sla_page.breach_types.resolution_time'),
  uptime: t('sla_page.breach_types.uptime'),
  availability: t('sla_page.breach_types.availability'),
}))

const severityLabels = computed(() => ({
  minor: t('sla_page.severity.minor'),
  major: t('sla_page.severity.major'),
  critical: t('sla_page.severity.critical'),
}))

function tagType(level) {
  const map = { standard: '', premium: 'success', enterprise: 'warning', custom: 'info' }
  return map[level] || ''
}

function levelLabel(level) {
  return levelLabels.value[level] || level
}

function severityTag(s) {
  const map = { minor: 'info', major: 'warning', critical: 'danger' }
  return map[s] || 'info'
}

function severityLabel(s) {
  return severityLabels.value[s] || s
}

function breachTypeLabel(type) {
  return breachTypeLabels.value[type] || type
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
    ElMessage.error(t('messages.load_failed'))
  }
}

// ══════════════════ SLA 拨测（原 sla-probes） ══════════════════
const sp_loading = ref(false)
const sp_saving = ref(false)
const sp_probes = ref([])
const sp_dashboard = reactive({})
const sp_dialog = reactive({ visible: false })
const sp_form = reactive({ name: '', url: '', method: 'GET', interval_minutes: 5 })

const sp_statCardMeta = [
  { key: 'total_probes', labelKey: 'sla_probes_page.stats.total_probes' },
  { key: 'active_probes', labelKey: 'sla_probes_page.stats.active_probes' },
  { key: 'success_rate_24h', labelKey: 'sla_probes_page.stats.success_rate_24h' },
  { key: 'failed_24h', labelKey: 'sla_probes_page.stats.failed_24h' },
]

const sp_statCards = computed(() => sp_statCardMeta.map((m) => ({
  key: m.key,
  label: t(m.labelKey),
})))

async function sp_fetchDashboard() {
  const { data: res } = await slaProbeApi.dashboard()
  Object.assign(sp_dashboard, res.data || {})
}

async function sp_fetchList() {
  const { data: res } = await slaProbeApi.list()
  sp_probes.value = res.data?.data || res.data || []
}

async function sp_refreshAll() {
  sp_loading.value = true
  try {
    await Promise.all([sp_fetchDashboard(), sp_fetchList()])
  } catch {
    ElMessage.error(t('messages.load_failed'))
  } finally {
    sp_loading.value = false
  }
}

function sp_openDialog() {
  sp_form.name = ''
  sp_form.url = ''
  sp_form.method = 'GET'
  sp_form.interval_minutes = 5
  sp_dialog.visible = true
}

async function sp_handleSave() {
  sp_saving.value = true
  try {
    await slaProbeApi.store({ ...sp_form })
    ElMessage.success(t('sla_probes_page.create_ok'))
    sp_dialog.visible = false
    await sp_refreshAll()
  } catch (e) {
    ElMessage.error(e?.response?.data?.error?.message || t('sla_probes_page.save_fail'))
  } finally {
    sp_saving.value = false
  }
}

async function sp_handleToggle(row) {
  await slaProbeApi.toggle(row.id)
  ElMessage.success(t('sla_probes_page.status_updated_ok'))
  await sp_refreshAll()
}

async function sp_handleRun(row) {
  await slaProbeApi.run(row.id)
  ElMessage.success(t('sla_probes_page.run_triggered_ok'))
}

async function sp_handleDelete(row) {
  await ElMessageBox.confirm(t('sla_probes_page.delete_confirm', { name: row.name }), t('actions.confirm'))
  await slaProbeApi.destroy(row.id)
  ElMessage.success(t('sla_probes_page.deleted_ok'))
  await sp_refreshAll()
}

onMounted(loadDashboard)
</script>

<style scoped>
/* SLA 追踪 样式 */
.sla-dashboard .stat-card { text-align: center; padding: 8px 0; }
.sla-dashboard .stat-value { font-size: 32px; font-weight: 700; color: #303133; }
.sla-dashboard .stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.level-tag { display: inline-flex; align-items: center; font-size: 14px; padding: 8px 16px; }

/* SLA 拨测 样式 */
.sla-probes-page .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.sla-probes-page .stat-value { font-size: 28px; font-weight: 700; }
.sla-probes-page .stat-label { color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
</style>
