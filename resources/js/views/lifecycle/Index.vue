<template>
  <div class="lifecycle-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Histogram /></el-icon>客户生命周期管理</h2>
      <div class="header-actions">
        <el-button @click="handleAutoEvaluate" :loading="evaluating" style="margin-right:8px">
          <el-icon><DataAnalysis /></el-icon> 自动评估
        </el-button>
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 阶段分布卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col v-for="s in stages" :key="s.name" :span="3">
        <el-card shadow="hover" class="stage-card" :class="'stage-' + s.name" @click="filterStage = s.name; activeTab='transitions'">
          <div class="stage-value">{{ s.count }}</div>
          <div class="stage-label">{{ s.label }}</div>
          <div class="stage-pct">{{ s.percentage }}%</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 总览图 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>阶段分布</span></template>
          <div ref="stageChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>各阶段 MRR</span></template>
          <div ref="mrrChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容区 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="阶段迁移历史" name="transitions">
          <div class="tab-toolbar">
            <el-select v-model="filterStage" placeholder="阶段" clearable style="width:130px">
              <el-option label="全部阶段" value="" />
              <el-option v-for="(l, k) in stageLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterTrigger" placeholder="触发方式" clearable style="width:130px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option label="手动" value="manual" />
              <el-option label="自动" value="auto" />
              <el-option label="工作流" value="workflow" />
            </el-select>
          </div>
          <el-table :data="transitions" stripe v-loading="transLoading">
            <el-table-column label="客户" width="100">
              <template #default="{ row }">{{ row.customer_name }}</template>
            </el-table-column>
            <el-table-column label="阶段" width="100">
              <template #default="{ row }">
                <el-tag :type="stageTag(row.stage)" size="small">{{ stageLabels[row.stage] || row.stage }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="上一阶段" width="100">
              <template #default="{ row }">
                <span v-if="row.previous_stage">{{ stageLabels[row.previous_stage] || row.previous_stage }}</span>
                <span v-else class="no-data">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
            <el-table-column label="触发方式" width="80">
              <template #default="{ row }">{{ row.triggered_by === 'auto' ? '自动' : row.triggered_by === 'manual' ? '手动' : row.triggered_by }}</template>
            </el-table-column>
            <el-table-column label="停留天数" width="90" align="center">
              <template #default="{ row }">{{ row.duration_days }} 天</template>
            </el-table-column>
            <el-table-column label="进入时间" width="150">
              <template #default="{ row }">{{ formatTime(row.entered_at) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 手动迁移对话框 -->
    <el-dialog v-model="showTransitionDialog" title="手动迁移阶段" width="420px">
      <el-form :model="transitionForm" label-width="90px">
        <el-form-item label="客户ID" required>
          <el-input-number v-model="transitionForm.customer_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="目标阶段" required>
          <el-select v-model="transitionForm.stage" style="width:100%">
            <el-option v-for="(l, k) in stageLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="transitionForm.reason" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransitionDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmTransition" :loading="transitioning">确认迁移</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Histogram, Refresh, DataAnalysis } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '../../api/lifecycle'

const loading = ref(false)
const evaluating = ref(false)
const activeTab = ref('transitions')

const stages = ref([])
const stageLabels = {
    prospect: '潜在客户', onboarding: '引导期', active: '活跃期',
    growing: '成长期', mature: '成熟期', at_risk: '风险期', churned: '已流失',
}

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

function formatTime(t) {
    if (!t) return '-'
    return new Date(t).toLocaleString('zh-CN')
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
            name: s.label,
            value: s.count,
        }))
        stageChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            series: [{
                type: 'pie', radius: ['35%', '65%'],
                data,
                label: { show: true, formatter: '{b}: {c}' },
                color: ['#909399', '#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#783887', '#c0c4cc'],
            }],
        })
    }

    if (mrrChartRef.value && stages.value.length) {
        if (mrrChart) mrrChart.dispose()
        mrrChart = echarts.init(mrrChartRef.value)
        const data = stages.value.filter(s => s.total_mrr > 0).map(s => ({
            name: s.label,
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
        ElMessage.success(`自动评估完成：${d.evaluated || 0} 个客户，${d.changed || 0} 个变更`)
        loadDashboard()
        loadTransitions()
    } catch (e) { ElMessage.error('自动评估失败') }
    finally { evaluating.value = false }
}

async function confirmTransition() {
    transitioning.value = true
    try {
        await api.transitionCustomer(transitionForm.value)
        ElMessage.success('阶段迁移成功')
        showTransitionDialog.value = false
        transitionForm.value = { customer_id: '', stage: 'active', reason: '' }
        loadDashboard()
        loadTransitions()
    } catch (e) { ElMessage.error('迁移失败: ' + (e.response?.data?.message || e.message)) }
    finally { transitioning.value = false }
}

watch(filterStage, () => loadTransitions())
watch(filterTrigger, () => loadTransitions())

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
.stage-onboarding .stage-value { color: #409eff; }
.stage-active .stage-value { color: #67c23a; }
.stage-growing .stage-value { color: #67c23a; }
.stage-mature .stage-value { color: #e6a23c; }
.stage-at_risk .stage-value { color: #f56c6c; }
.stage-churned .stage-value { color: #c0c4cc; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.no-data { color: #c0c4cc; }
</style>
