<template>
  <div class="churn-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><WarningFilled /></el-icon>{{ t(`${P}.title`) }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.total_at_risk }}</div>
          <div class="stat-label">{{ t(`${P}.stats.total_at_risk`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.total_low_risk }}</div>
          <div class="stat-label">{{ t(`${P}.stats.total_low_risk`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.interventions?.in_progress || 0 }}</div>
          <div class="stat-label">{{ t(`${P}.stats.in_progress`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.positive_rate }}%</div>
          <div class="stat-label">{{ t(`${P}.stats.positive_rate`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 干预类型分布 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>{{ t(`${P}.charts.risk_distribution`) }}</span></template>
          <div ref="riskChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>{{ t(`${P}.charts.intervention_distribution`) }}</span></template>
          <div ref="interventionChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.list`)" name="list">
          <div class="tab-toolbar">
            <el-select v-model="listFilter.risk_level" :placeholder="t(`${P}.filters.risk_level`)" clearable style="width:140px;margin-right:8px">
              <el-option :label="t(`${P}.filters.all_risk_levels`)" value="" />
              <el-option v-for="(label, key) in riskLabels" :key="key" :label="label" :value="key" />
            </el-select>
            <el-input v-model="listFilter.search" :placeholder="t(`${P}.filters.search_ph`)" clearable style="width:200px" @clear="loadList" @keyup.enter="loadList" />
          </div>
          <el-table :data="churnList" stripe v-loading="listLoading" @row-click="showCustomerDetail">
            <el-table-column :label="t(`${P}.cols.customer`)" min-width="160">
              <template #default="{ row }">
                <div class="customer-cell">
                  <span class="customer-name">{{ row.customer_name }}</span>
                  <span class="customer-email">{{ row.customer_email }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.churn_risk`)" width="120">
              <template #default="{ row }">
                <el-tag :type="riskTag(row.risk_level)" size="small">{{ riskLabel(row.risk_level) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.risk_score`)" width="100">
              <template #default="{ row }">
                <el-progress :percentage="Math.round((row.churn_probability || 0) * 100)" :stroke-width="12" :status="row.churn_probability > 0.5 ? 'exception' : 'success'" />
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.health_score`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.health_grade === 'healthy' ? 'success' : row.health_grade === 'warning' ? 'warning' : 'danger'" size="small">
                  {{ row.health_score || '-' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.active_interventions`)" width="90" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.active_interventions > 0" type="warning" size="small">{{ row.active_interventions }}</el-tag>
                <span v-else class="no-data">0</span>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.main_signals`)" min-width="160">
              <template #default="{ row }">
                <el-tag v-for="s in (row.signals || [])" :key="s" size="small" style="margin:1px 2px">{{ signalLabel(s) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.last_assessed`)" width="150">
              <template #default="{ row }">{{ formatTime(row.predicted_at) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.interventions`)" name="interventions">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showNewIntervention = true">
              <el-icon><Plus /></el-icon> {{ t(`${P}.buttons.new_intervention`) }}
            </el-button>
            <el-select v-model="intFilter.status" :placeholder="t(`${P}.filters.status`)" clearable style="width:130px;margin-left:8px">
              <el-option :label="t(`${P}.filters.all`)" value="" />
              <el-option v-for="(label, key) in statusLabels" :key="key" :label="label" :value="key" />
            </el-select>
            <el-select v-model="intFilter.type" :placeholder="t(`${P}.filters.type`)" clearable style="width:140px;margin-left:8px">
              <el-option :label="t(`${P}.filters.all_types`)" value="" />
              <el-option v-for="(label, key) in interventionTypes" :key="key" :label="label" :value="key" />
            </el-select>
          </div>
          <el-table :data="interventions" stripe v-loading="intLoading">
            <el-table-column :label="t(`${P}.cols.customer`)" min-width="150">
              <template #default="{ row }">
                <div class="customer-cell">
                  <span class="customer-name">{{ row.customer_name }}</span>
                  <span class="customer-email">{{ row.customer_email }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="title" :label="t(`${P}.cols.title`)" min-width="160" show-overflow-tooltip />
            <el-table-column :label="t(`${P}.cols.type`)" width="100">
              <template #default="{ row }">
                <el-tag size="small">{{ typeLabel(row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.status`)" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'in_progress' ? 'warning' : row.status === 'cancelled' ? 'info' : 'danger'" size="small">
                  {{ statusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="assigned_to" :label="t(`${P}.cols.assigned_to`)" width="100" />
            <el-table-column :label="t(`${P}.cols.outcome`)" width="110">
              <template #default="{ row }">
                <el-tag v-if="row.outcome" :type="row.outcome === 'positive' ? 'success' : row.outcome === 'neutral' ? 'info' : 'danger'" size="small">
                  {{ outcomeLabel(row.outcome) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.actions`)" width="200">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editIntervention(row)">{{ t('actions.edit') }}</el-button>
                <el-button v-if="row.status !== 'completed'" size="small" text type="success" @click="completeIntervention(row)">{{ t(`${P}.buttons.complete`) }}</el-button>
                <el-button size="small" text type="danger" @click="deleteIntervention(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建/编辑干预对话框 -->
    <el-dialog v-model="showNewIntervention" :title="editingInt ? t(`${P}.dialogs.edit_intervention`) : t(`${P}.dialogs.new_intervention`)" width="550px">
      <el-form :model="intForm" label-width="110px">
        <el-form-item :label="t(`${P}.dialogs.customer`)" required v-if="!editingInt">
          <el-select v-model="intForm.customer_id" filterable style="width:100%">
            <el-option v-for="c in churnList" :key="c.customer_id" :label="c.customer_name" :value="c.customer_id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.intervention_type`)" required>
          <el-select v-model="intForm.type" style="width:100%">
            <el-option v-for="(label, key) in interventionTypes" :key="key" :label="label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.title`)" required>
          <el-input v-model="intForm.title" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.description`)">
          <el-input v-model="intForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.assigned_to`)">
          <el-input v-model="intForm.assigned_to" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.scheduled_at`)">
          <el-date-picker v-model="intForm.scheduled_at" type="datetime" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showNewIntervention = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveIntervention" :loading="savingInt">{{ editingInt ? t('actions.save') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 完成干预对话框 -->
    <el-dialog v-model="showCompleteDialog" :title="t(`${P}.dialogs.complete_intervention`)" width="450px">
      <el-form :model="completeForm" label-width="90px">
        <el-form-item :label="t(`${P}.dialogs.result`)">
          <el-input v-model="completeForm.result" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialogs.outcome_eval`)">
          <el-select v-model="completeForm.outcome" style="width:100%">
            <el-option v-for="(label, key) in outcomeLabels" :key="key" :label="label" :value="key" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCompleteDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="confirmComplete" :loading="completing">{{ t(`${P}.buttons.confirm_complete`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { WarningFilled, Refresh, Plus } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '../../api/churnPrediction'

const P = 'churn_prediction_page'
const { t, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('list')
const stats = ref({})

const churnList = ref([])
const listLoading = ref(false)
const listFilter = ref({ risk_level: '', search: '' })

const interventions = ref([])
const intLoading = ref(false)
const intFilter = ref({ status: '', type: '' })

const showNewIntervention = ref(false)
const editingInt = ref(null)
const intForm = ref({ customer_id: '', type: 'renewal_call', title: '', description: '', assigned_to: '', scheduled_at: '' })
const savingInt = ref(false)

const showCompleteDialog = ref(false)
const completingInt = ref(null)
const completeForm = ref({ result: '', outcome: 'positive' })
const completing = ref(false)

const riskChartRef = ref(null)
const interventionChartRef = ref(null)
let riskChart = null
let intChart = null

const riskLabels = computed(() => ({
    critical: t(`${P}.risk_levels.critical`),
    high: t(`${P}.risk_levels.high`),
    medium: t(`${P}.risk_levels.medium`),
    low: t(`${P}.risk_levels.low`),
}))

const interventionTypes = computed(() => ({
    renewal_call: t(`${P}.intervention_types.renewal_call`),
    coupon_offer: t(`${P}.intervention_types.coupon_offer`),
    training_session: t(`${P}.intervention_types.training_session`),
    executive_engagement: t(`${P}.intervention_types.executive_engagement`),
    survey: t(`${P}.intervention_types.survey`),
    product_showcase: t(`${P}.intervention_types.product_showcase`),
    technical_support: t(`${P}.intervention_types.technical_support`),
}))

const statusLabels = computed(() => ({
    pending: t(`${P}.statuses.pending`),
    in_progress: t(`${P}.statuses.in_progress`),
    completed: t(`${P}.statuses.completed`),
    cancelled: t(`${P}.statuses.cancelled`),
}))

const outcomeLabels = computed(() => ({
    positive: t(`${P}.outcomes.positive`),
    neutral: t(`${P}.outcomes.neutral`),
    negative: t(`${P}.outcomes.negative`),
    unknown: t(`${P}.outcomes.unknown`),
}))

const signalLabels = computed(() => ({
    renewal_score_low: t(`${P}.signals.renewal_score_low`),
    payment_overdue: t(`${P}.signals.payment_overdue`),
    low_activation: t(`${P}.signals.low_activation`),
    ticket_frustration: t(`${P}.signals.ticket_frustration`),
    low_usage: t(`${P}.signals.low_usage`),
    expired_subscription: t(`${P}.signals.expired_subscription`),
}))

function formatTime(ts) {
    if (!ts) return '-'
    return new Date(ts).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US')
}

function riskTag(r) {
    return r === 'critical' ? 'danger' : r === 'high' ? 'warning' : r === 'medium' ? 'info' : 'success'
}

function riskLabel(r) {
    return riskLabels.value[r] || r
}

function typeLabel(type) {
    return interventionTypes.value[type] || type
}

function statusLabel(s) {
    return statusLabels.value[s] || s
}

function outcomeLabel(o) {
    return outcomeLabels.value[o] || o
}

function signalLabel(s) {
    return signalLabels.value[s] || s
}

async function loadDashboard() {
    try {
        const res = await api.getDashboard()
        stats.value = res.data || {}
        await nextTick()
        renderCharts()
    } catch (e) {
        console.error('Failed to load churn dashboard', e)
    }
}

async function loadList() {
    listLoading.value = true
    try {
        const params = {}
        if (listFilter.value.risk_level) params.risk_level = listFilter.value.risk_level
        if (listFilter.value.search) params.search = listFilter.value.search
        const res = await api.getChurnList(params)
        churnList.value = res.data?.data || res.data || []
    } catch (e) {
        console.error('Failed to load churn list', e)
    } finally {
        listLoading.value = false
    }
}

async function loadInterventions() {
    intLoading.value = true
    try {
        const params = {}
        if (intFilter.value.status) params.status = intFilter.value.status
        if (intFilter.value.type) params.type = intFilter.value.type
        const res = await api.getInterventions(params)
        interventions.value = res.data?.data || res.data || []
    } catch (e) {
        console.error('Failed to load interventions', e)
    } finally {
        intLoading.value = false
    }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([loadDashboard(), loadList(), loadInterventions()])
    loading.value = false
}

function renderCharts() {
    if (riskChartRef.value && stats.value.churn_by_risk) {
        if (riskChart) riskChart.dispose()
        riskChart = echarts.init(riskChartRef.value)
        const data = Object.entries(stats.value.churn_by_risk).map(([k, v]) => ({
            name: riskLabel(k),
            value: v,
        }))
        riskChart.setOption({
            tooltip: { trigger: 'item' },
            series: [{
                type: 'pie',
                radius: ['40%', '70%'],
                data,
                label: { show: true, formatter: '{b}: {c}' },
                color: ['#f56c6c', '#e6a23c', '#0f172a', '#67c23a'],
            }],
        })
    }

    if (interventionChartRef.value && stats.value.interventions?.by_type) {
        if (intChart) intChart.dispose()
        intChart = echarts.init(interventionChartRef.value)
        const data = Object.entries(stats.value.interventions.by_type).map(([k, v]) => ({
            name: typeLabel(k),
            value: v,
        }))
        intChart.setOption({
            tooltip: { trigger: 'item' },
            xAxis: { type: 'category', data: data.map(d => d.name), axisLabel: { rotate: 20 } },
            yAxis: { type: 'value' },
            series: [{
                type: 'bar',
                data,
                itemStyle: { borderRadius: [4, 4, 0, 0] },
            }],
        })
    }
}

function editIntervention(row) {
    editingInt.value = row
    intForm.value = {
        customer_id: row.customer_id,
        type: row.type,
        title: row.title,
        description: row.description || '',
        assigned_to: row.assigned_to || '',
        scheduled_at: row.scheduled_at || '',
    }
    showNewIntervention.value = true
}

function saveIntervention() {
    savingInt.value = true
    const call = editingInt.value
        ? api.updateIntervention(editingInt.value.id, intForm.value)
        : api.createIntervention(intForm.value)
    call.then(() => {
        ElMessage.success(editingInt.value ? t(`${P}.messages.intervention_updated`) : t(`${P}.messages.intervention_created`))
        showNewIntervention.value = false
        editingInt.value = null
        loadInterventions()
        loadDashboard()
    }).catch(e => ElMessage.error(t(`${P}.messages.operation_failed`, { msg: e.response?.data?.message || e.message })))
    .finally(() => savingInt.value = false)
}

function completeIntervention(row) {
    completingInt.value = row
    completeForm.value = { result: '', outcome: 'positive' }
    showCompleteDialog.value = true
}

function confirmComplete() {
    completing.value = true
    const data = { status: 'completed', result: completeForm.value.result, outcome: completeForm.value.outcome }
    api.updateIntervention(completingInt.value.id, data).then(() => {
        ElMessage.success(t(`${P}.messages.intervention_completed`))
        showCompleteDialog.value = false
        loadInterventions()
        loadDashboard()
    }).catch(e => ElMessage.error(t(`${P}.messages.operation_failed`, { msg: e.response?.data?.message || e.message })))
    .finally(() => completing.value = false)
}

function deleteIntervention(row) {
    ElMessageBox.confirm(t(`${P}.messages.confirm_delete_intervention`, { title: row.title }), t('actions.confirm'), { type: 'warning' }).then(() => {
        api.deleteIntervention(row.id).then(() => {
            ElMessage.success(t(`${P}.messages.deleted`))
            loadInterventions()
        })
    }).catch(() => {})
}

function showCustomerDetail(row) {
    ElMessage.info(t(`${P}.messages.customer_detail`, {
        name: row.customer_name,
        score: (row.churn_probability * 100).toFixed(1),
    }))
}

watch(() => intFilter.value.status, () => loadInterventions())
watch(() => intFilter.value.type, () => loadInterventions())
watch(() => listFilter.value.risk_level, () => loadList())
watch(locale, () => {
    nextTick(() => renderCharts())
})

onMounted(() => {
    refreshAll()
})
</script>

<style scoped>
.churn-page {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; font-size: 22px; }

.header-actions { display: flex; align-items: center; }

.mb-4 { margin-bottom: 16px; }

.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-danger .stat-value { color: #f56c6c; }
.stat-success .stat-value { color: #67c23a; }

.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.customer-cell {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}

.customer-name { font-weight: 600; font-size: 13px; }
.customer-email { font-size: 11px; color: #909399; }

.no-data { color: #c0c4cc; font-size: 12px; }
</style>
