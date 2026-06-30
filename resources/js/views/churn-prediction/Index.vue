<template>
  <div class="churn-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><WarningFilled /></el-icon>客户流失预测与干预</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.total_at_risk }}</div>
          <div class="stat-label">高风险客户</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.total_low_risk }}</div>
          <div class="stat-label">低风险客户</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.interventions?.in_progress || 0 }}</div>
          <div class="stat-label">进行中干预</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.positive_rate }}%</div>
          <div class="stat-label">干预有效率</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 干预类型分布 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>风险等级分布</span></template>
          <div ref="riskChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>干预类型分布</span></template>
          <div ref="interventionChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="流失客户列表" name="list">
          <div class="tab-toolbar">
            <el-select v-model="listFilter.risk_level" placeholder="风险等级" clearable style="width:140px;margin-right:8px">
              <el-option label="全部风险等级" value="" />
              <el-option label="高危" value="critical" />
              <el-option label="高" value="high" />
              <el-option label="中" value="medium" />
              <el-option label="低" value="low" />
            </el-select>
            <el-input v-model="listFilter.search" placeholder="搜索客户..." clearable style="width:200px" @clear="loadList" @keyup.enter="loadList" />
          </div>
          <el-table :data="churnList" stripe v-loading="listLoading" @row-click="showCustomerDetail">
            <el-table-column label="客户" min-width="160">
              <template #default="{ row }">
                <div class="customer-cell">
                  <span class="customer-name">{{ row.customer_name }}</span>
                  <span class="customer-email">{{ row.customer_email }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="流失风险" width="120">
              <template #default="{ row }">
                <el-tag :type="riskTag(row.risk_level)" size="small">{{ riskLabel(row.risk_level) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="流失概率" width="100">
              <template #default="{ row }">
                <el-progress :percentage="Math.round((row.churn_probability || 0) * 100)" :stroke-width="12" :status="row.churn_probability > 0.5 ? 'exception' : 'success'" />
              </template>
            </el-table-column>
            <el-table-column label="健康分" width="80">
              <template #default="{ row }">
                <el-tag :type="row.health_grade === 'healthy' ? 'success' : row.health_grade === 'warning' ? 'warning' : 'danger'" size="small">
                  {{ row.health_score || '-' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="活跃干预" width="90" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.active_interventions > 0" type="warning" size="small">{{ row.active_interventions }}</el-tag>
                <span v-else class="no-data">0</span>
              </template>
            </el-table-column>
            <el-table-column label="主要信号" min-width="160">
              <template #default="{ row }">
                <el-tag v-for="s in (row.signals || [])" :key="s" size="small" style="margin:1px 2px">{{ signalLabel(s) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="预测时间" width="150">
              <template #default="{ row }">{{ formatTime(row.predicted_at) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="干预管理" name="interventions">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showNewIntervention = true">
              <el-icon><Plus /></el-icon> 新建干预
            </el-button>
            <el-select v-model="intFilter.status" placeholder="状态" clearable style="width:130px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option label="待处理" value="pending" />
              <el-option label="进行中" value="in_progress" />
              <el-option label="已完成" value="completed" />
              <el-option label="已取消" value="cancelled" />
            </el-select>
            <el-select v-model="intFilter.type" placeholder="类型" clearable style="width:140px;margin-left:8px">
              <el-option label="全部类型" value="" />
              <el-option label="续费电话" value="renewal_call" />
              <el-option label="优惠券" value="coupon_offer" />
              <el-option label="培训辅导" value="training_session" />
              <el-option label="高层介入" value="executive_engagement" />
              <el-option label="满意度调研" value="survey" />
              <el-option label="产品演示" value="product_showcase" />
              <el-option label="技术支持" value="technical_support" />
            </el-select>
          </div>
          <el-table :data="interventions" stripe v-loading="intLoading">
            <el-table-column label="客户" min-width="150">
              <template #default="{ row }">
                <div class="customer-cell">
                  <span class="customer-name">{{ row.customer_name }}</span>
                  <span class="customer-email">{{ row.customer_email }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
            <el-table-column label="类型" width="100">
              <template #default="{ row }">
                <el-tag size="small">{{ typeLabel(row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'in_progress' ? 'warning' : row.status === 'cancelled' ? 'info' : 'danger'" size="small">
                  {{ statusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="assigned_to" label="责任人" width="100" />
            <el-table-column label="结果" width="110">
              <template #default="{ row }">
                <el-tag v-if="row.outcome" :type="row.outcome === 'positive' ? 'success' : row.outcome === 'neutral' ? 'info' : 'danger'" size="small">
                  {{ outcomeLabel(row.outcome) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="200">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editIntervention(row)">编辑</el-button>
                <el-button v-if="row.status !== 'completed'" size="small" text type="success" @click="completeIntervention(row)">完成</el-button>
                <el-button size="small" text type="danger" @click="deleteIntervention(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建/编辑干预对话框 -->
    <el-dialog v-model="showNewIntervention" :title="editingInt ? '编辑干预' : '新建干预'" width="550px">
      <el-form :model="intForm" label-width="110px">
        <el-form-item label="客户" required v-if="!editingInt">
          <el-select v-model="intForm.customer_id" filterable style="width:100%">
            <el-option v-for="c in churnList" :key="c.customer_id" :label="c.customer_name" :value="c.customer_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="干预类型" required>
          <el-select v-model="intForm.type" style="width:100%">
            <el-option label="续费电话" value="renewal_call" />
            <el-option label="优惠券" value="coupon_offer" />
            <el-option label="培训辅导" value="training_session" />
            <el-option label="高层介入" value="executive_engagement" />
            <el-option label="满意度调研" value="survey" />
            <el-option label="产品演示" value="product_showcase" />
            <el-option label="技术支持" value="technical_support" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题" required>
          <el-input v-model="intForm.title" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="intForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="责任人">
          <el-input v-model="intForm.assigned_to" />
        </el-form-item>
        <el-form-item label="计划时间">
          <el-date-picker v-model="intForm.scheduled_at" type="datetime" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showNewIntervention = false">取消</el-button>
        <el-button type="primary" @click="saveIntervention" :loading="savingInt">{{ editingInt ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 完成干预对话框 -->
    <el-dialog v-model="showCompleteDialog" title="完成干预" width="450px">
      <el-form :model="completeForm" label-width="90px">
        <el-form-item label="结果描述">
          <el-input v-model="completeForm.result" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="效果评估">
          <el-select v-model="completeForm.outcome" style="width:100%">
            <el-option label="积极" value="positive" />
            <el-option label="中性" value="neutral" />
            <el-option label="消极" value="negative" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCompleteDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmComplete" :loading="completing">确认完成</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { WarningFilled, Refresh, Plus } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '../../api/churnPrediction'

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

function formatTime(t) {
    if (!t) return '-'
    return new Date(t).toLocaleString('zh-CN')
}

function riskTag(r) {
    return r === 'critical' ? 'danger' : r === 'high' ? 'warning' : r === 'medium' ? 'info' : 'success'
}

function riskLabel(r) {
    const map = { critical: '高危', high: '高', medium: '中', low: '低' }
    return map[r] || r
}

function typeLabel(t) {
    const map = { renewal_call: '续费电话', coupon_offer: '优惠券', training_session: '培训辅导', executive_engagement: '高层介入', survey: '满意度调研', product_showcase: '产品演示', technical_support: '技术支持' }
    return map[t] || t
}

function statusLabel(s) {
    const map = { pending: '待处理', in_progress: '进行中', completed: '已完成', cancelled: '已取消' }
    return map[s] || s
}

function outcomeLabel(o) {
    const map = { positive: '积极', neutral: '中性', negative: '消极', unknown: '未知' }
    return map[o] || o
}

function signalLabel(s) {
    const map = {
        renewal_score_low: '续费分低', payment_overdue: '支付逾期', low_activation: '低激活',
        ticket_frustration: '工单不满', low_usage: '使用不足', expired_subscription: '订阅过期'
    }
    return map[s] || s
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
    // 风险等级分布
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
                color: ['#f56c6c', '#e6a23c', '#409eff', '#67c23a'],
            }],
        })
    }

    // 干预类型分布
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

// 干预 CRUD
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
        ElMessage.success(editingInt.value ? '干预已更新' : '干预已创建')
        showNewIntervention.value = false
        editingInt.value = null
        loadInterventions()
        loadDashboard()
    }).catch(e => ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message)))
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
        ElMessage.success('干预已标记完成')
        showCompleteDialog.value = false
        loadInterventions()
        loadDashboard()
    }).catch(e => ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message)))
    .finally(() => completing.value = false)
}

function deleteIntervention(row) {
    ElMessageBox.confirm(`确定删除干预"${row.title}"?`, '确认', { type: 'warning' }).then(() => {
        api.deleteIntervention(row.id).then(() => {
            ElMessage.success('已删除')
            loadInterventions()
        })
    }).catch(() => {})
}

function showCustomerDetail(row) {
    ElMessage.info(`客户 ${row.customer_name}: 流失概率 ${(row.churn_probability * 100).toFixed(1)}%`)
}

watch(() => intFilter.value.status, () => loadInterventions())
watch(() => intFilter.value.type, () => loadInterventions())
watch(() => listFilter.value.risk_level, () => loadList())

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
