<template>
  <div class="lifecycle-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Histogram /></el-icon>{{ t('lifecycle_page.title') }}</h2>
      <div class="header-actions">
        <el-button @click="handleAutoEvaluate" :loading="evaluating" style="margin-right:8px">
          <el-icon><DataAnalysis /></el-icon> {{ t('lifecycle_page.auto_evaluate') }}
        </el-button>
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('lifecycle_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 阶段分布卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col v-for="s in stages" :key="s.name" :span="3">
        <el-card shadow="hover" class="stage-card" :class="'stage-' + s.name" @click="filterStage = s.name; activeTab='transitions'">
          <div class="stage-value">{{ s.count }}</div>
          <div class="stage-label">{{ stageLabels[s.name] || s.label }}</div>
          <div class="stage-pct">{{ s.percentage }}%</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 总览图 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>{{ t('lifecycle_page.stage_distribution') }}</span></template>
          <div ref="stageChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>{{ t('lifecycle_page.mrr_by_stage') }}</span></template>
          <div ref="mrrChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容区 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('lifecycle_page.tabs.transitions')" name="transitions">
          <div class="tab-toolbar">
            <el-select v-model="filterStage" :placeholder="t('lifecycle_page.filters.stage_ph')" clearable style="width:130px">
              <el-option :label="t('lifecycle_page.filters.all_stages')" value="" />
              <el-option v-for="(l, k) in stageLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterTrigger" :placeholder="t('lifecycle_page.filters.trigger_ph')" clearable style="width:130px;margin-left:8px">
              <el-option v-for="opt in triggerFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
          <el-table :data="transitions" stripe v-loading="transLoading">
            <el-table-column :label="t('lifecycle_page.cols.customer')" width="100">
              <template #default="{ row }">{{ row.customer_name }}</template>
            </el-table-column>
            <el-table-column :label="t('lifecycle_page.cols.stage')" width="100">
              <template #default="{ row }">
                <el-tag :type="stageTag(row.stage)" size="small">{{ stageLabels[row.stage] || row.stage }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('lifecycle_page.cols.previous_stage')" width="100">
              <template #default="{ row }">
                <span v-if="row.previous_stage">{{ stageLabels[row.previous_stage] || row.previous_stage }}</span>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="reason" :label="t('lifecycle_page.cols.reason')" min-width="160" show-overflow-tooltip />
            <el-table-column :label="t('lifecycle_page.cols.trigger')" width="80">
              <template #default="{ row }">{{ triggerLabel(row.triggered_by) }}</template>
            </el-table-column>
            <el-table-column :label="t('lifecycle_page.cols.duration_days')" width="90" align="center">
              <template #default="{ row }">{{ row.duration_days }} {{ t('lifecycle_page.days_unit') }}</template>
            </el-table-column>
            <el-table-column :label="t('lifecycle_page.cols.entered_at')" width="150">
              <template #default="{ row }">{{ formatTime(row.entered_at) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 手动迁移对话框 -->
    <el-dialog v-model="showTransitionDialog" :title="t('lifecycle_page.dialog.title')" width="420px">
      <el-form :model="transitionForm" label-width="90px">
        <el-form-item :label="t('lifecycle_page.dialog.customer_id')" required>
          <el-input-number v-model="transitionForm.customer_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('lifecycle_page.dialog.target_stage')" required>
          <el-select v-model="transitionForm.stage" style="width:100%">
            <el-option v-for="(l, k) in stageLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('lifecycle_page.dialog.reason')">
          <el-input v-model="transitionForm.reason" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransitionDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="confirmTransition" :loading="transitioning">{{ t('lifecycle_page.dialog.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Histogram, Refresh, DataAnalysis } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '../../api/lifecycle'

const { t, locale } = useI18n()

const loading = ref(false)
const evaluating = ref(false)
const activeTab = ref('transitions')

const stages = ref([])
const stageKeys = ['prospect', 'onboarding', 'active', 'growing', 'mature', 'at_risk', 'churned']

const stageLabels = computed(() => Object.fromEntries(
    stageKeys.map((k) => [k, t(`lifecycle_page.stages.${k}`)]),
))

const triggerFilterOptions = computed(() => [
    { label: t('lifecycle_page.filters.all'), value: '' },
    { label: t('lifecycle_page.triggers.manual'), value: 'manual' },
    { label: t('lifecycle_page.triggers.auto'), value: 'auto' },
    { label: t('lifecycle_page.triggers.workflow'), value: 'workflow' },
])

const transitions = ref([])
const transLoading = ref(false)
const filterStage = ref('')
const filterTrigger = ref('')

const showTransitionDialog = ref(false)
const transitionForm = ref({ customer_id: '', stage: 'active', reason: '' })
const transitioning = ref(false)

const stageChartRef = ref(null)
const mrrChartRef = ref(null)
let stageChart = null
let mrrChart = null

function triggerLabel(value) {
    const key = `lifecycle_page.triggers.${value}`
    const translated = t(key)
    return translated !== key ? translated : value
}

function formatTime(time) {
    if (!time) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(time).toLocaleString(loc)
}

function stageTag(stage) {
    const map = { prospect: 'info', onboarding: '', active: 'success', growing: 'success', mature: '', at_risk: 'warning', churned: 'danger' }
    return map[stage] || 'info'
}

async function loadDashboard() {
    try {
        const res = await api.getDashboard()
        const data = res.data || {}
        stages.value = Object.values(data.stages || {})
    } catch (e) { console.error('Failed to load lifecycle dashboard', e) }
}

async function loadTransitions() {
    transLoading.value = true
    try {
        const params = {}
        if (filterStage.value) params.stage = filterStage.value
        if (filterTrigger.value) params.triggered_by = filterTrigger.value
        const res = await api.getTransitions(params)
        transitions.value = res.data?.data || res.data || []
    } catch (e) { console.error('Failed to load transitions', e) }
    finally { transLoading.value = false }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([loadDashboard(), loadTransitions()])
    await nextTick()
    renderCharts()
    loading.value = false
}

function renderCharts() {
    if (stageChartRef.value && stages.value.length) {
        if (stageChart) stageChart.dispose()
        stageChart = echarts.init(stageChartRef.value)
        const data = stages.value.filter(s => s.count > 0).map(s => ({
            name: stageLabels.value[s.name] || s.label,
            value: s.count,
        }))
        stageChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            series: [{
                type: 'pie', radius: ['35%', '65%'],
                data,
                label: { show: true, formatter: '{b}: {c}' },
                color: ['#909399', '#0f172a', '#67c23a', '#e6a23c', '#f56c6c', '#783887', '#c0c4cc'],
            }],
        })
    }

    if (mrrChartRef.value && stages.value.length) {
        if (mrrChart) mrrChart.dispose()
        mrrChart = echarts.init(mrrChartRef.value)
        const data = stages.value.filter(s => s.total_mrr > 0).map(s => ({
            name: stageLabels.value[s.name] || s.label,
            value: s.total_mrr,
        }))
        mrrChart.setOption({
            tooltip: { trigger: 'axis', formatter: (p) => `${p[0].name}: ¥${p[0].value.toLocaleString()}` },
            xAxis: { type: 'category', data: data.map(d => d.name) },
            yAxis: { type: 'value', axisLabel: { formatter: '¥{value}' } },
            series: [{ type: 'bar', data, itemStyle: { borderRadius: [4, 4, 0, 0] } }],
        })
    }
}

async function handleAutoEvaluate() {
    evaluating.value = true
    try {
        const res = await api.autoEvaluate()
        const d = res.data || {}
        ElMessage.success(t('lifecycle_page.messages.auto_evaluate_done', {
            evaluated: d.evaluated || 0,
            changed: d.changed || 0,
        }))
        loadDashboard()
        loadTransitions()
    } catch (e) { ElMessage.error(t('lifecycle_page.messages.auto_evaluate_failed')) }
    finally { evaluating.value = false }
}

async function confirmTransition() {
    transitioning.value = true
    try {
        await api.transitionCustomer(transitionForm.value)
        ElMessage.success(t('lifecycle_page.messages.transition_success'))
        showTransitionDialog.value = false
        transitionForm.value = { customer_id: '', stage: 'active', reason: '' }
        loadDashboard()
        loadTransitions()
    } catch (e) {
        ElMessage.error(t('lifecycle_page.messages.transition_failed', {
            error: e.response?.data?.message || e.message,
        }))
    }
    finally { transitioning.value = false }
}

watch(filterStage, () => loadTransitions())
watch(filterTrigger, () => loadTransitions())
watch(locale, () => {
    nextTick(() => renderCharts())
})

onMounted(() => { refreshAll() })
</script>

<style scoped>
.lifecycle-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stage-card { text-align: center; cursor: pointer; transition: transform .2s; }
.stage-card:hover { transform: translateY(-3px); }
.stage-value { font-size: 26px; font-weight: 700; }
.stage-label { font-size: 13px; margin-top: 2px; }
.stage-pct { font-size: 11px; color: #909399; margin-top: 1px; }
.stage-prospect .stage-value { color: #909399; }
.stage-onboarding .stage-value { color: #0f172a; }
.stage-active .stage-value { color: #67c23a; }
.stage-growing .stage-value { color: #67c23a; }
.stage-mature .stage-value { color: #e6a23c; }
.stage-at_risk .stage-value { color: #f56c6c; }
.stage-churned .stage-value { color: #c0c4cc; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.no-data { color: #c0c4cc; }
</style>
