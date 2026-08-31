<template>
  <div class="ai-ops-page">
    <el-tabs tab-position="left" v-model="activeTab">
      <!-- Tab 0: 智能分析 (merged from ai-ops) -->
      <el-tab-pane label="智能分析" name="analysis">
        <div v-if="a_analysisLoaded">
          <div class="page-header">
            <h2><el-icon><MagicStick /></el-icon> {{ t('ai_ops_page.title') }}</h2>
            <p class="text-muted">{{ t('ai_ops_page.subtitle') }}</p>
          </div>

          <!-- 看板概览 -->
          <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="(val, key) in a_metrics" :key="key">
              <el-card shadow="never" @click="a_quickQuestion(key)" style="cursor:pointer">
                <div class="metric-card">
                  <div class="metric-value">{{ val }}</div>
                  <div class="metric-label">{{ a_metricLabels[key] || key }}</div>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <!-- 聊天式分析区 -->
          <el-row :gutter="16">
            <el-col :span="6">
              <!-- 预置模板 -->
              <el-card shadow="never">
                <template #header><span>{{ t('ai_ops_page.templates.title') }}</span></template>
                <el-menu :default-active="a_activeTemplate" @select="a_onSelectTemplate">
                  <el-menu-item-group :title="t('ai_ops_page.templates.groups.license')">
                    <el-menu-item index="activation_trend">{{ t('ai_ops_page.templates.items.activation_trend') }}</el-menu-item>
                    <el-menu-item index="activation_by_product">{{ t('ai_ops_page.templates.items.activation_by_product') }}</el-menu-item>
                    <el-menu-item index="license_status_dist">{{ t('ai_ops_page.templates.items.license_status_dist') }}</el-menu-item>
                    <el-menu-item index="expiring_soon">{{ t('ai_ops_page.templates.items.expiring_soon') }}</el-menu-item>
                  </el-menu-item-group>
                  <el-menu-item-group :title="t('ai_ops_page.templates.groups.customer')">
                    <el-menu-item index="top_customers">{{ t('ai_ops_page.templates.items.top_customers') }}</el-menu-item>
                    <el-menu-item index="customer_growth">{{ t('ai_ops_page.templates.items.customer_growth') }}</el-menu-item>
                  </el-menu-item-group>
                  <el-menu-item-group :title="t('ai_ops_page.templates.groups.device')">
                    <el-menu-item index="device_by_platform">{{ t('ai_ops_page.templates.items.device_by_platform') }}</el-menu-item>
                    <el-menu-item index="active_devices">{{ t('ai_ops_page.templates.items.active_devices') }}</el-menu-item>
                  </el-menu-item-group>
                  <el-menu-item-group :title="t('ai_ops_page.templates.groups.subscription')">
                    <el-menu-item index="subscription_by_plan">{{ t('ai_ops_page.templates.items.subscription_by_plan') }}</el-menu-item>
                    <el-menu-item index="churn_rate">{{ t('ai_ops_page.templates.items.churn_rate') }}</el-menu-item>
                  </el-menu-item-group>
                </el-menu>
              </el-card>
            </el-col>

            <el-col :span="18">
              <!-- 提问区 -->
              <el-card shadow="never" class="mb-4">
                <el-input
                  v-model="a_question"
                  type="textarea"
                  :rows="2"
                  :placeholder="t('ai_ops_page.question_placeholder')"
                  @keyup.enter="a_submitQuestion"
                />
                <div class="ask-actions">
                  <div class="suggestions">
                    <el-tag
                      v-for="s in a_suggestions"
                      :key="s"
                      size="small"
                      class="suggestion-tag"
                      @click="a_question = s; a_submitQuestion()"
                    >{{ s }}</el-tag>
                  </div>
                  <el-button type="primary" :loading="a_loading" @click="a_submitQuestion" icon="Search">{{ t('ai_ops_page.analyze_btn') }}</el-button>
                </div>
              </el-card>

              <!-- 结果区 -->
              <el-card shadow="never" v-if="a_result">
                <template #header>
                  <div class="result-header">
                    <span>{{ a_result.explanation || t('ai_ops_page.result.default_title') }}</span>
                    <div class="result-meta">
                      <el-tag size="small" type="info">{{ a_chartTypeLabel }}</el-tag>
                      <el-tag size="small" type="success">{{ t('ai_ops_page.result.rows', { count: a_result.count }) }}</el-tag>
                      <el-tag size="small" type="warning">{{ a_result.elapsed_ms }}ms</el-tag>
                    </div>
                  </div>
                </template>

                <!-- 数值卡片 -->
                <div v-if="a_result.chart_type === 'number' && a_result.data?.length" class="number-display">
                  <div v-for="(val, key) in a_result.data[0]" :key="key" class="number-item">
                    <div class="number-label">{{ key }}</div>
                    <div class="number-value">{{ val }}</div>
                  </div>
                </div>

                <!-- ECharts 图表 -->
                <div v-if="a_showChart" ref="a_chartRef" style="width:100%;height:400px"></div>

                <!-- 数据表格 -->
                <el-table v-if="a_result.chart_type === 'table' || a_result.data?.length > 0" :data="a_result.data || []" stripe size="small" max-height="400" class="mt-3">
                  <el-table-column v-for="col in a_tableColumns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                </el-table>

                <!-- SQL 详情 -->
                <div class="sql-detail" v-if="a_result.sql">
                  <el-divider />
                  <div class="sql-header">
                    <span class="text-muted">{{ t('ai_ops_page.sql.generated') }}</span>
                    <el-button text size="small" @click="a_copied = true; a_copySql(a_result.sql)">{{ a_copied ? t('ai_ops_page.copied') : t('actions.copy') }}</el-button>
                  </div>
                  <pre class="sql-code">{{ a_result.sql }}</pre>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </div>
        <div v-else class="tab-loading-placeholder">
          <el-skeleton :rows="8" animated />
        </div>
      </el-tab-pane>

      <!-- Tab 1: 知识库自增长 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.kb')" name="kb">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.kb') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in kbStats" :key="s.key">
            <el-card shadow="hover"><div class="stat-card">
              <div class="stat-num">{{ s.value }}</div>
              <div class="stat-label">{{ s.label }}</div>
            </div></el-card>
          </el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="primary" @click="runKbAutoGrow" :loading="kbRunning">{{ t('ai_operations_page.kb.manual_scan') }}</el-button>
        </div>
        <el-table :data="kbDrafts" border stripe v-loading="kbLoading" style="width:100%">
          <el-table-column prop="id" :label="t('ai_operations_page.cols.id')" width="60"/>
          <el-table-column prop="title" :label="t('ai_operations_page.cols.title')" min-width="180"/>
          <el-table-column prop="source_type" :label="t('ai_operations_page.cols.source')" width="120">
            <template #default="{row}">{{ sourceLabel(row.source_type) }}</template>
          </el-table-column>
          <el-table-column prop="confidence" :label="t('ai_operations_page.cols.confidence')" width="100">
            <template #default="{row}"><el-tag :type="confidenceType(row.confidence)">{{ row.confidence }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('ai_operations_page.cols.actions')" width="160" fixed="right">
            <template #default="{row}">
              <el-button size="small" type="success" @click="approveDraft(row.id)">{{ t('actions.approve') }}</el-button>
              <el-button size="small" type="danger" @click="rejectDraft(row.id)">{{ t('actions.reject') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab 2: 深度研究 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.research')" name="research">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.research') }}</h3></div>
        <el-card shadow="never" class="input-card">
          <el-input v-model="researchQuery" type="textarea" :rows="3" :placeholder="t('ai_operations_page.research.query_placeholder')"/>
          <el-button type="primary" @click="startResearch" :loading="researchLoading" style="margin-top:12px">{{ t('ai_operations_page.research.start') }}</el-button>
        </el-card>
        <el-table :data="researchHistory" border v-loading="researchListLoading" style="width:100%;margin-top:16px">
          <el-table-column prop="id" :label="t('ai_operations_page.cols.id')" width="60"/>
          <el-table-column prop="query" :label="t('ai_operations_page.cols.query')" min-width="250"/>
          <el-table-column prop="status" :label="t('ai_operations_page.cols.status')" width="100">
            <template #default="{row}">
              <el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'">
                {{ researchStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="source_count" :label="t('ai_operations_page.cols.source_count')" width="80"/>
          <el-table-column prop="created_at" :label="t('ai_operations_page.cols.time')" width="170"/>
          <el-table-column :label="t('ai_operations_page.cols.actions')" width="120">
            <template #default="{row}">
              <el-button size="small" @click="viewResearch(row)">{{ t('actions.view') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-dialog v-model="researchDialog" :title="researchDialogTitle" width="70%" top="5vh">
          <div class="report-content" v-html="renderMarkdown(currentResearch?.report || '')"></div>
        </el-dialog>
      </el-tab-pane>

      <!-- Tab 3: 搜索增强 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.search')" name="search">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.search') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.total_documents || 0 }}</div><div class="stat-label">{{ t('ai_operations_page.search.stats.total_documents') }}</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.indexed_documents || 0 }}</div><div class="stat-label">{{ t('ai_operations_page.search.stats.indexed_documents') }}</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.index_coverage || 0 }}%</div><div class="stat-label">{{ t('ai_operations_page.search.stats.index_coverage') }}</div></div></el-card></el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="warning" @click="rebuildIndex" :loading="rebuilding">{{ t('ai_operations_page.search.rebuild_index') }}</el-button>
        </div>
        <el-card shadow="never" class="input-card" style="margin-top:16px">
          <el-input v-model="searchQuery" :placeholder="t('ai_operations_page.search.query_placeholder')"/>
          <el-button type="primary" @click="testSearch" :loading="searchLoading" style="margin-top:12px">{{ t('actions.search') }}</el-button>
          <div v-if="searchResults?.results" style="margin-top:12px">
            <el-tag>{{ t('ai_operations_page.search.results_found', { count: searchResults.total }) }}</el-tag>
            <div v-for="r in searchResults.results" :key="r.id" class="search-item">
              <strong>{{ r.title }}</strong><br>
              <span class="text-muted">{{ r.content?.substring(0,150) }}...</span>
              <el-tag size="small" :type="r.score > 0.7 ? 'success' : 'warning'" style="margin-left:8px">{{ r.score }}</el-tag>
            </div>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 4: 幻觉检测 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.hallucination')" name="hallucination">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.hallucination') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in hcStatsArr" :key="s.key">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <el-card shadow="never" class="input-card">
          <el-input v-model="hcText" type="textarea" :rows="4" :placeholder="t('ai_operations_page.hallucination.text_placeholder')"/>
          <el-button type="primary" @click="testHallucination" :loading="hcLoading" style="margin-top:12px">{{ t('ai_operations_page.hallucination.inspect') }}</el-button>
          <div v-if="hcResult" style="margin-top:12px">
            <el-tag :type="hcResult.verdict==='trustworthy'?'success':'danger'" size="large">
              {{ t('ai_operations_page.hallucination.verdict_label') }}: {{ hcVerdictLabel(hcResult.verdict) }}
            </el-tag>
            <el-tag style="margin-left:8px">{{ t('ai_operations_page.hallucination.credibility_label') }}: {{ hcResult.overall_score }}</el-tag>
            <div v-for="r in hcResult.results" :key="r.claim" class="hc-item">
              <el-tag size="small" :type="r.status==='verified'?'success':'warning'">{{ r.status }}</el-tag>
              <span>{{ r.claim?.substring(0,80) }}...</span>
            </div>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 5: 内容溯源 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.signature')" name="signature">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.signature') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ sigStats?.total_signed || 0 }}</div><div class="stat-label">{{ t('ai_operations_page.signature.stats.total_signed') }}</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ Object.keys(sigStats?.by_source || {}).length }}</div><div class="stat-label">{{ t('ai_operations_page.signature.stats.source_types') }}</div></div></el-card></el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-card shadow="never" class="input-card">
              <h4>{{ t('ai_operations_page.signature.sign_title') }}</h4>
              <el-input v-model="signContent" type="textarea" :rows="3" :placeholder="t('ai_operations_page.signature.sign_placeholder')"/>
              <el-button type="primary" @click="doSign" :loading="signLoading" style="margin-top:12px">{{ t('ai_operations_page.signature.sign_btn') }}</el-button>
              <div v-if="signResult" style="margin-top:8px">
                <div>{{ t('ai_operations_page.signature.hash_label') }}: <code>{{ signResult.hash?.substring(0,20) }}...</code></div>
                <el-tag>{{ t('ai_operations_page.signature.recorded') }}</el-tag>
              </div>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="never" class="input-card">
              <h4>{{ t('ai_operations_page.signature.verify_title') }}</h4>
              <el-input v-model="verifyContent" type="textarea" :rows="3" :placeholder="t('ai_operations_page.signature.verify_placeholder')"/>
              <el-button type="success" @click="doVerify" :loading="verifyLoading" style="margin-top:12px">{{ t('ai_operations_page.signature.verify_btn') }}</el-button>
              <div v-if="verifyResult" style="margin-top:8px">
                <el-tag :type="verifyResult.verified ? 'success' : 'danger'">
                  {{ verifyResult.verified ? t('ai_operations_page.signature.verified_ok') : verifyResult.message }}
                </el-tag>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- Tab 6: 自动化运营 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.quality')" name="quality">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.quality') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in qualityStatsArr" :key="s.key">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="primary" @click="runQualityOps" :loading="qualityRunning">{{ t('ai_operations_page.quality.run_ops') }}</el-button>
        </div>
        <el-card shadow="never" class="input-card">
          <el-input v-model="qualityText" :placeholder="t('ai_operations_page.quality.text_placeholder')"/>
          <el-button @click="testQuality" :loading="qualityTestLoading" style="margin-top:12px">{{ t('ai_operations_page.quality.test_score') }}</el-button>
          <div v-if="qualityScore" style="margin-top:8px">
            <el-tag :type="qualityScore.score>0.7?'success':'danger'">{{ t('ai_operations_page.quality.score_label') }}: {{ qualityScore.score }}</el-tag>
            <el-tag v-for="i in qualityScore.issues" :key="i" type="warning" style="margin-left:4px">{{ i }}</el-tag>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 7: 电子签名 -->
      <el-tab-pane :label="t('ai_operations_page.tabs.esign')" name="esign">
        <div class="tab-header"><h3>{{ t('ai_operations_page.headers.esign') }}</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in esignStatsArr" :key="s.key">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <el-table :data="esignPending" border v-loading="esignLoading" style="width:100%">
          <el-table-column :label="t('ai_operations_page.cols.sign_type')" width="100">
            <template #default="{row}">{{ esignTypeLabel(row.type) }}</template>
          </el-table-column>
          <el-table-column :label="t('ai_operations_page.cols.signer')" width="120">
            <template #default="{row}">{{ row.user?.name || t('ai_operations_page.esign.unknown_signer') }}</template>
          </el-table-column>
          <el-table-column prop="sequence" :label="t('ai_operations_page.cols.sequence')" width="60"/>
          <el-table-column prop="status" :label="t('ai_operations_page.cols.status')" width="100">
            <template #default="{row}">
              <el-tag :type="row.status==='signed'?'success':row.status==='rejected'?'danger':'warning'">
                {{ esignStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" :label="t('ai_operations_page.cols.created_at')" width="170"/>
        </el-table>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '@/api/aiOperations'
import { getAIOpsDashboard, runAIOpsTemplate, askAIOpsQuestion } from '@/api/aiOps'
import * as echarts from 'echarts'

const { t } = useI18n()

const activeTab = ref('kb')

// ── 智能分析 (merged from ai-ops) ──
const a_analysisLoaded = ref(false)
const a_loading = ref(false)
const a_question = ref('')
const a_result = ref(null)
const a_activeTemplate = ref('')
const a_metrics = ref({})
const a_chartRef = ref(null)
let a_chartInstance = null
const a_copied = ref(false)

const a_metricLabels = computed(() => ({
  total_licenses: t('ai_ops_page.metrics.total_licenses'),
  active_licenses: t('ai_ops_page.metrics.active_licenses'),
  total_customers: t('ai_ops_page.metrics.total_customers'),
  today_activations: t('ai_ops_page.metrics.today_activations'),
  expiring_soon: t('ai_ops_page.metrics.expiring_soon'),
}))

const a_suggestions = computed(() => [
  t('ai_ops_page.suggestions.s1'),
  t('ai_ops_page.suggestions.s2'),
  t('ai_ops_page.suggestions.s3'),
  t('ai_ops_page.suggestions.s4'),
  t('ai_ops_page.suggestions.s5'),
])

const a_chartTypeLabel = computed(() => {
  const type = a_result.value?.chart_type
  if (!type) return ''
  const key = `ai_ops_page.chart_types.${type}`
  const label = t(key)
  return label !== key ? label : type
})

const a_showChart = computed(() => {
  return a_result.value?.chart_type && ['line', 'bar', 'pie', 'trend'].includes(a_result.value?.chart_type)
})

const a_tableColumns = computed(() => {
  if (!a_result.value?.data?.length) return []
  return Object.keys(a_result.value.data[0])
})

const a_promptMap = computed(() => ({
  total_licenses: t('ai_ops_page.prompts.total_licenses'),
  active_licenses: t('ai_ops_page.prompts.active_licenses'),
  total_customers: t('ai_ops_page.prompts.total_customers'),
  today_activations: t('ai_ops_page.prompts.today_activations'),
  expiring_soon: t('ai_ops_page.prompts.expiring_soon'),
}))

const a_loadDashboard = async () => {
  try {
    const res = await getAIOpsDashboard()
    if (res.data.success) a_metrics.value = res.data.data
  } catch (e) { /* ignore */ }
}

const a_submitQuestion = async () => {
  if (!a_question.value.trim()) { ElMessage.warning(t('ai_ops_page.messages.enter_question')); return }
  a_loading.value = true
  a_result.value = null
  a_activeTemplate.value = ''
  try {
    const res = await askAIOpsQuestion(a_question.value)
    if (res.data.success) {
      a_result.value = res.data.data
      await nextTick()
      a_renderChart()
    } else {
      ElMessage.error(res.data.message || t('ai_ops_page.messages.analysis_failed'))
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('messages.network_error'))
  } finally { a_loading.value = false }
}

const a_onSelectTemplate = async (key) => {
  a_activeTemplate.value = key
  a_loading.value = true
  a_result.value = null
  try {
    const res = await runAIOpsTemplate(key, { days: 30 })
    if (res.data.success) {
      a_result.value = res.data.data
      await nextTick()
      a_renderChart()
    } else {
      ElMessage.error(res.data.message || t('ai_ops_page.messages.template_failed'))
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('messages.network_error'))
  } finally { a_loading.value = false }
}

const a_quickQuestion = (key) => {
  a_question.value = a_promptMap.value[key] || ''
  if (a_question.value) a_submitQuestion()
}

const a_renderChart = () => {
  if (!a_chartRef.value || !a_result.value?.data?.length) return
  if (a_chartInstance) a_chartInstance.dispose()

  a_chartInstance = echarts.init(a_chartRef.value)
  const data = a_result.value.data
  const chartType = a_result.value.chart_type
  const columns = Object.keys(data[0] || {})
  if (columns.length < 2) return

  const xCol = columns[0]
  const yCol = columns[1]

  let option = {}

  if (chartType === 'line') {
    option = {
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
      xAxis: { type: 'category', data: data.map(d => d[xCol]), axisLabel: { rotate: 45 } },
      yAxis: { type: 'value' },
      series: [{ data: data.map(d => Number(d[yCol]) || 0), type: 'line', smooth: true, areaStyle: { opacity: 0.15 } }],
    }
  } else if (chartType === 'bar') {
    option = {
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
      xAxis: { type: 'category', data: data.map(d => d[xCol]), axisLabel: { rotate: data.length > 8 ? 45 : 0 } },
      yAxis: { type: 'value' },
      series: [{ data: data.map(d => Number(d[yCol]) || 0), type: 'bar', itemStyle: { color: '#0f172a' } }],
    }
  } else if (chartType === 'pie') {
    option = {
      tooltip: { trigger: 'item' },
      series: [{
        type: 'pie',
        radius: ['30%', '60%'],
        data: data.map(d => ({ name: d[xCol], value: Number(d[yCol]) || 0 })),
        emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.5)' } },
      }],
    }
  }

  if (Object.keys(option).length) {
    a_chartInstance.setOption(option)
    window.addEventListener('resize', () => a_chartInstance?.resize())
  }
}

const a_copySql = (sql) => {
  navigator.clipboard.writeText(sql).then(() => {
    setTimeout(() => { a_copied.value = false }, 2000)
  })
}

watch(activeTab, (val) => {
  if (val === 'analysis' && !a_analysisLoaded.value) {
    a_analysisLoaded.value = true
    nextTick(() => {
      a_loadDashboard()
    })
  }
})

// ── KB Auto Grow ──
const kbStatsRaw = ref({ pending: 0, approved: 0, rejected: 0 })
const kbDrafts = ref([])
const kbLoading = ref(false)
const kbRunning = ref(false)

const kbStats = computed(() => [
  { key: 'pending', label: t('ai_operations_page.kb.stats.pending'), value: kbStatsRaw.value.pending },
  { key: 'approved', label: t('ai_operations_page.kb.stats.approved'), value: kbStatsRaw.value.approved },
  { key: 'rejected', label: t('ai_operations_page.kb.stats.rejected'), value: kbStatsRaw.value.rejected },
])

function sourceLabel(s) {
  const key = `ai_operations_page.source_type.${s}`
  const translated = t(key)
  return translated !== key ? translated : s
}
function confidenceType(v) { return v > 0.7 ? 'success' : v > 0.4 ? 'warning' : 'danger' }

async function loadKb() {
  try {
    const [sr, dr] = await Promise.all([api.kbAutoGrowStats(), api.kbAutoGrowPending()])
    const s = sr.data?.data || {}
    kbStatsRaw.value = {
      pending: s.pending || 0,
      approved: s.approved || 0,
      rejected: s.rejected || 0,
    }
    kbDrafts.value = dr.data?.data?.data || []
  } catch { /* ignore */ }
}
async function runKbAutoGrow() {
  kbRunning.value = true
  await api.kbAutoGrowRun()
  ElMessage.success(t('ai_operations_page.messages.scan_done'))
  kbRunning.value = false
  loadKb()
}
async function approveDraft(id) {
  await api.kbAutoGrowApprove(id)
  ElMessage.success(t('ai_operations_page.messages.approved'))
  loadKb()
}
async function rejectDraft(id) {
  await api.kbAutoGrowReject(id)
  ElMessage.success(t('ai_operations_page.messages.rejected'))
  loadKb()
}

// ── Deep Research ──
const researchQuery = ref('')
const researchLoading = ref(false)
const researchHistory = ref([])
const researchListLoading = ref(false)
const researchDialog = ref(false)
const currentResearch = ref(null)

function researchStatusLabel(status) {
  const key = `ai_operations_page.research_status.${status}`
  const translated = t(key)
  return translated !== key ? translated : status
}

const researchDialogTitle = computed(() => {
  const query = currentResearch.value?.query || ''
  return query ? `${t('ai_operations_page.research.report_prefix')}: ${query}` : t('ai_operations_page.research.report_prefix')
})

async function loadResearch() {
  researchListLoading.value = true
  try {
    const r = await api.deepResearchHistory()
    researchHistory.value = r.data?.data?.data || []
  } catch {}
  researchListLoading.value = false
}
async function startResearch() {
  if (!researchQuery.value) return
  researchLoading.value = true
  try {
    await api.deepResearchStart(researchQuery.value)
    ElMessage.success(t('ai_operations_page.messages.research_started'))
    researchQuery.value = ''
    loadResearch()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('ai_operations_page.messages.research_start_failed'))
  }
  researchLoading.value = false
}
async function viewResearch(row) {
  try {
    const r = await api.deepResearchDetail(row.id)
    currentResearch.value = r.data?.data
    researchDialog.value = true
  } catch {}
}
function renderMarkdown(text) {
  if (!text) return ''
  return text.replace(/\n/g, '<br>').replace(/## (.+)/g, '<h3>$1</h3>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
}

// ── Vector Search ──
const searchStats = ref({})
const searchQuery = ref('')
const searchLoading = ref(false)
const searchResults = ref(null)
const rebuilding = ref(false)

async function loadSearchStats() {
  try { const r = await api.vectorSearchStats(); searchStats.value = r.data?.data || {} } catch {}
}
async function testSearch() {
  if (!searchQuery.value) return
  searchLoading.value = true
  try { const r = await api.vectorSearch(searchQuery.value); searchResults.value = r.data?.data } catch {}
  searchLoading.value = false
}
async function rebuildIndex() {
  rebuilding.value = true
  await api.vectorSearchRebuild(true)
  ElMessage.success(t('ai_operations_page.messages.index_rebuilt'))
  rebuilding.value = false
  loadSearchStats()
}

// ── Hallucination ──
const hcText = ref('')
const hcLoading = ref(false)
const hcResult = ref(null)
const hcStats = ref({})

const hcStatsArr = computed(() => [
  { key: 'total_checks', label: t('ai_operations_page.hallucination.stats.total_checks'), value: hcStats.value.total_checks || 0 },
  { key: 'avg_score', label: t('ai_operations_page.hallucination.stats.avg_score'), value: hcStats.value.avg_score || 0 },
  { key: 'total_claims', label: t('ai_operations_page.hallucination.stats.total_claims'), value: hcStats.value.total_claims || 0 },
])

function hcVerdictLabel(verdict) {
  const key = `ai_operations_page.hallucination.verdict.${verdict}`
  const translated = t(key)
  return translated !== key ? translated : verdict
}

async function loadHcStats() {
  try { const r = await api.hallucinationStats(); hcStats.value = r.data?.data || {} } catch {}
}
async function testHallucination() {
  if (!hcText.value) return
  hcLoading.value = true
  try { const r = await api.hallucinationInspect(hcText.value); hcResult.value = r.data?.data } catch {}
  hcLoading.value = false
}

// ── Content Signature ──
const sigStats = ref({})
const signContent = ref('')
const signLoading = ref(false)
const signResult = ref(null)
const verifyContent = ref('')
const verifyLoading = ref(false)
const verifyResult = ref(null)

async function loadSigStats() {
  try { const r = await api.contentStats(); sigStats.value = r.data?.data || {} } catch {}
}
async function doSign() {
  if (!signContent.value) return
  signLoading.value = true
  try { const r = await api.contentSign(signContent.value); signResult.value = r.data?.data } catch {}
  signLoading.value = false
}
async function doVerify() {
  if (!verifyContent.value) return
  verifyLoading.value = true
  try { const r = await api.contentVerify(verifyContent.value); verifyResult.value = r.data?.data } catch {}
  verifyLoading.value = false
}

// ── Content Quality ──
const qualityStats = ref({})
const qualityRunning = ref(false)
const qualityText = ref('')
const qualityTestLoading = ref(false)
const qualityScore = ref(null)

const qualityStatsArr = computed(() => [
  { key: 'total_records', label: t('ai_operations_page.quality.stats.total_records'), value: qualityStats.value.total_records || 0 },
  { key: 'avg_quality', label: t('ai_operations_page.quality.stats.avg_quality'), value: qualityStats.value.avg_quality || 0 },
])

async function loadQualityStats() {
  try { const r = await api.qualityStats(); qualityStats.value = r.data?.data || {} } catch {}
}
async function runQualityOps() {
  qualityRunning.value = true
  await api.qualityRun()
  ElMessage.success(t('ai_operations_page.messages.ops_done'))
  qualityRunning.value = false
  loadQualityStats()
}
async function testQuality() {
  if (!qualityText.value) return
  qualityTestLoading.value = true
  try { const r = await api.qualityRate(qualityText.value); qualityScore.value = r.data?.data } catch {}
  qualityTestLoading.value = false
}

// ── Electronic Signature ──
const esignStats = ref({})
const esignPending = ref([])
const esignLoading = ref(false)

const esignStatsArr = computed(() => [
  { key: 'total', label: t('ai_operations_page.esign.stats.total'), value: esignStats.value.total || 0 },
])

function esignTypeLabel(type) {
  const key = `ai_operations_page.esign.type.${type}`
  const translated = t(key)
  return translated !== key ? translated : type
}
function esignStatusLabel(status) {
  const key = `ai_operations_page.esign.status.${status}`
  const translated = t(key)
  return translated !== key ? translated : status
}

async function loadEsign() {
  esignLoading.value = true
  try {
    const [sr, pr] = await Promise.all([api.esignStats(), api.esignMyPending()])
    esignStats.value = sr.data?.data || {}
    esignPending.value = pr.data?.data?.data || []
  } catch {}
  esignLoading.value = false
}

onMounted(() => {
  loadKb(); loadResearch(); loadSearchStats(); loadHcStats(); loadSigStats(); loadQualityStats(); loadEsign()
})
</script>

<style scoped>
.ai-ops-page { padding: 8px; }
.tab-header { margin-bottom: 16px; }
.tab-header h3 { margin: 0; font-size: 18px; }
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-num { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: #888; margin-top: 4px; }
.action-bar { margin-bottom: 16px; }
.input-card { margin-bottom: 16px; }
.search-item { padding: 8px 0; border-bottom: 1px solid #eee; }
.search-item .text-muted { color: #999; font-size: 12px; }
.hc-item { padding: 4px 0; }
.report-content { line-height: 1.8; font-size: 14px; }
.report-content h3 { margin: 16px 0 8px; color: var(--el-color-primary); }

/* ── 智能分析 (merged from ai-ops) ── */
.page-header { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.page-header h2 { margin: 0; display: flex; align-items: center; gap: 6px; }
.tab-loading-placeholder { padding: 32px; }
.text-muted { color: #909399; font-size: 13px; margin: 0; }
.metric-card { text-align: center; padding: 8px 0; cursor: pointer; }
.metric-value { font-size: 24px; font-weight: 700; color: #0f172a; }
.metric-label { font-size: 12px; color: #909399; margin-top: 2px; }
.ask-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 8px; }
.suggestions { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; }
.suggestion-tag { cursor: pointer; }
.suggestion-tag:hover { opacity: 0.8; }
.result-header { display: flex; justify-content: space-between; align-items: center; }
.result-meta { display: flex; gap: 4px; }
.number-display { display: flex; gap: 16px; flex-wrap: wrap; }
.number-item { text-align: center; padding: 16px 24px; background: #f5f7fa; border-radius: 8px; flex: 1; min-width: 120px; }
.number-label { font-size: 13px; color: #909399; }
.number-value { font-size: 28px; font-weight: 700; color: #303133; margin-top: 4px; }
.sql-detail { margin-top: 8px; }
.sql-header { display: flex; justify-content: space-between; align-items: center; }
.sql-code { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
.mt-3 { margin-top: 12px; }
.mb-4 { margin-bottom: 16px; }
</style>
