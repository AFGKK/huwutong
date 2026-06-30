<template>
  <div class="cross-border">
    <h2 class="mb-4">跨境支付与多币种管理</h2>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ formatCny(dashboard.monthly_revenue_cny) }}</div><div class="stat-label">本月跨境收入 (CNY)</div></div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ dashboard.monthly_transactions }}</div><div class="stat-label">本月交易数</div></div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-primary">{{ dashboard.active_currencies }}</div><div class="stat-label">活跃货币</div></div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-warning">{{ dashboard.compliance_warnings }}</div><div class="stat-label">合规警告</div></div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ dashboard.total_conversions }}</div><div class="stat-label">转换次数</div></div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- 跨境支付 -->
      <el-tab-pane label="跨境支付" name="payments">
        <div class="mb-3 flex gap-2">
          <el-input v-model="filters.currency" placeholder="货币..." clearable style="width:120px" @clear="loadPayments" @keyup.enter="loadPayments" />
          <el-select v-model="filters.status" placeholder="状态" clearable style="width:140px" @change="loadPayments">
            <el-option label="已完成" value="completed" />
            <el-option label="待处理" value="pending" />
            <el-option label="失败" value="failed" />
            <el-option label="已退款" value="refunded" />
          </el-select>
        </div>

        <el-table :data="payments" stripe v-loading="loading.payments" empty-text="暂无跨境支付记录">
          <el-table-column label="ID" prop="id" width="60" />
          <el-table-column label="货币" prop="currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.currency }}</el-tag></template>
          </el-table-column>
          <el-table-column label="金额" prop="amount" width="120">
            <template #default="{ row }">{{ row.currency }} {{ row.amount }}</template>
          </el-table-column>
          <el-table-column label="折合CNY" prop="amount_cny" width="120">
            <template #default="{ row }">¥ {{ row.amount_cny }}</template>
          </el-table-column>
          <el-table-column label="汇率" prop="exchange_rate" width="100" />
          <el-table-column label="网关" prop="payment_gateway" width="100" />
          <el-table-column label="类型" prop="transaction_type" width="90">
            <template #default="{ row }">
              <el-tag :type="row.transaction_type === 'refund' ? 'danger' : 'success'" size="small">{{ row.transaction_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="客户国家" prop="customer_country" width="100" />
          <el-table-column label="手续费" prop="gateway_fee" width="100">
            <template #default="{ row }">{{ row.gateway_fee }} {{ row.currency }}</template>
          </el-table-column>
          <el-table-column label="状态" prop="status" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="时间" prop="created_at" width="160" />
        </el-table>

        <!-- 分页 -->
        <div class="mt-3 flex justify-center" v-if="paymentPagination.total">
          <el-pagination v-model:current-page="paymentPagination.current" :page-size="paymentPagination.pageSize" :total="paymentPagination.total" layout="prev, pager, next" @current-change="loadPayments" />
        </div>
      </el-tab-pane>

      <!-- 货币转换审计 -->
      <el-tab-pane label="货币转换审计" name="conversions">
        <div class="mb-3 flex gap-2">
          <el-select v-model="convFilters.from_currency" placeholder="源货币" clearable style="width:120px" @change="loadConversions">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
          <el-select v-model="convFilters.to_currency" placeholder="目标货币" clearable style="width:120px" @change="loadConversions">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
          <el-select v-model="convFilters.source" placeholder="来源" clearable style="width:140px" @change="loadConversions">
            <el-option label="定价" value="pricing" />
            <el-option label="发票" value="invoice" />
            <el-option label="退款" value="refund" />
            <el-option label="订阅" value="subscription" />
          </el-select>
        </div>

        <el-table :data="conversionLogs" stripe v-loading="loading.conversions" empty-text="暂无转换记录">
          <el-table-column label="源货币" prop="from_currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.from_currency }}</el-tag></template>
          </el-table-column>
          <el-table-column label="源金额" prop="from_amount" width="120" />
          <el-table-column label="→" width="40" align="center">→</el-table-column>
          <el-table-column label="目标货币" prop="to_currency" width="80">
            <template #default="{ row }"><el-tag size="small" type="success">{{ row.to_currency }}</el-tag></template>
          </el-table-column>
          <el-table-column label="目标金额" prop="to_amount" width="120" />
          <el-table-column label="汇率" prop="rate_used" width="100" />
          <el-table-column label="加点" prop="rate_markup" width="80">
            <template #default="{ row }">{{ (row.rate_markup * 100).toFixed(2) }}%</template>
          </el-table-column>
          <el-table-column label="类型" prop="conversion_type" width="80">
            <template #default="{ row }">
              <el-tag :type="row.conversion_type === 'manual' ? 'warning' : 'info'" size="small">{{ row.conversion_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="来源" prop="source" width="100" />
          <el-table-column label="时间" prop="created_at" width="160" />
        </el-table>
      </el-tab-pane>

      <!-- 月度报表 -->
      <el-tab-pane label="月度报表" name="reports">
        <div class="mb-3 flex gap-2 items-center">
          <el-button type="primary" :loading="loading.genReport" @click="doGenerateReport">生成本月报表</el-button>
          <el-select v-model="reportFilters.currency" placeholder="货币" clearable style="width:120px" @change="loadReports">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
        </div>

        <el-table :data="reports" stripe v-loading="loading.reports" empty-text="暂无月度报表数据">
          <el-table-column label="月份" prop="report_month" width="110" />
          <el-table-column label="货币" prop="currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.currency }}</el-tag></template>
          </el-table-column>
          <el-table-column label="总收入(原币)" prop="total_revenue" width="140" />
          <el-table-column label="总收入(CNY)" prop="total_revenue_cny" width="140">
            <template #default="{ row }">¥ {{ row.total_revenue_cny }}</template>
          </el-table-column>
          <el-table-column label="退款" prop="total_refunds" width="120" />
          <el-table-column label="手续费(原币)" prop="total_fees" width="130" />
          <el-table-column label="手续费(CNY)" prop="total_fees_cny" width="130">
            <template #default="{ row }">¥ {{ row.total_fees_cny }}</template>
          </el-table-column>
          <el-table-column label="净收入(原币)" prop="net_revenue" width="140">
            <template #default="{ row }">
              <span :class="row.net_revenue >= 0 ? 'text-success' : 'text-danger'">{{ row.net_revenue }}</span>
            </template>
          </el-table-column>
          <el-table-column label="交易数" prop="transaction_count" width="90" align="center" />
          <el-table-column label="客户数" prop="customer_count" width="90" align="center" />
          <el-table-column label="Top国家" prop="top_countries" min-width="160">
            <template #default="{ row }">
              <span v-if="row.top_countries && Object.keys(row.top_countries).length">
                {{ Object.keys(row.top_countries).join(', ') }}
              </span>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 合规检查工具 -->
      <el-tab-pane label="合规检查" name="compliance">
        <el-card shadow="hover">
          <template #header>跨境支付合规检查工具</template>
          <el-form :model="complianceForm" label-position="top" inline>
            <el-form-item label="货币">
              <el-select v-model="complianceForm.currency" style="width:120px">
                <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
              </el-select>
            </el-form-item>
            <el-form-item label="金额">
              <el-input-number v-model="complianceForm.amount" :min="0" style="width:180px" />
            </el-form-item>
            <el-form-item label="客户国家">
              <el-input v-model="complianceForm.customer_country" placeholder="如 US, JP" style="width:120px" />
            </el-form-item>
            <el-form-item label="交易类型">
              <el-select v-model="complianceForm.transaction_type" style="width:140px">
                <el-option label="支付" value="payment" />
                <el-option label="退款" value="refund" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="complianceLoading" @click="doCheckCompliance">检查合规</el-button>
            </el-form-item>
          </el-form>

          <el-result v-if="complianceResult" :icon="complianceResult.passed ? 'success' : 'warning'" :title="complianceResult.passed ? '合规检查通过' : '需关注合规问题'">
            <template #extra>
              <el-timeline>
                <el-timeline-item
                  v-for="(check, i) in complianceResult.checks" :key="i"
                  :type="check.status === 'warning' ? 'warning' : 'info'"
                  :timestamp="check.type"
                >
                  {{ check.message }}
                </el-timeline-item>
              </el-timeline>
            </template>
          </el-result>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getCrossBorderDashboard, getConversionLogs, getCrossBorderPayments,
  getMonthlyReports, generateReport, checkCompliance,
} from '../../api/crossBorder'

const activeTab = ref('payments')
const loading = reactive({ payments: false, conversions: false, reports: false, genReport: false })
const complianceLoading = ref(false)
const dashboard = ref({})
const payments = ref([])
const conversionLogs = ref([])
const reports = ref([])
const complianceResult = ref(null)

const currencies = ['CNY', 'USD', 'EUR', 'JPY', 'GBP', 'HKD', 'SGD', 'KRW']

const filters = reactive({ currency: '', status: '' })
const convFilters = reactive({ from_currency: '', to_currency: '', source: '' })
const reportFilters = reactive({ currency: '' })
const complianceForm = reactive({ currency: 'USD', amount: 1000, customer_country: 'US', transaction_type: 'payment' })

const paymentPagination = reactive({ current: 1, pageSize: 20, total: 0 })

function formatCny(val) {
  if (!val) return '¥ 0'
  return '¥ ' + Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
}

async function loadDashboard() {
  try {
    const { data } = await getCrossBorderDashboard()
    dashboard.value = data || {}
  } catch (e) {}
}

async function loadPayments() {
  loading.payments = true
  try {
    const params = { page: paymentPagination.current, per_page: paymentPagination.pageSize }
    if (filters.currency) params.currency = filters.currency
    if (filters.status) params.status = filters.status
    const { data: res } = await getCrossBorderPayments(params)
    payments.value = Array.isArray(res.data) ? res.data : (res.data?.data || [])
    if (res.data?.meta) {
      paymentPagination.current = res.data.meta.current_page
      paymentPagination.total = res.data.meta.total
    }
  } catch (e) { ElMessage.error('加载支付记录失败') }
  finally { loading.payments = false }
}

async function loadConversions() {
  loading.conversions = true
  try {
    const params = {}
    if (convFilters.from_currency) params.from_currency = convFilters.from_currency
    if (convFilters.to_currency) params.to_currency = convFilters.to_currency
    if (convFilters.source) params.source = convFilters.source
    const { data: res } = await getConversionLogs(params)
    conversionLogs.value = Array.isArray(res.data) ? res.data : (res.data?.data || [])
  } catch (e) {}
  finally { loading.conversions = false }
}

async function loadReports() {
  loading.reports = true
  try {
    const params = {}
    if (reportFilters.currency) params.currency = reportFilters.currency
    const { data } = await getMonthlyReports(params)
    reports.value = Array.isArray(data) ? data : []
  } catch (e) {}
  finally { loading.reports = false }
}

async function doGenerateReport() {
  loading.genReport = true
  try {
    await generateReport()
    ElMessage.success('月度报表已生成')
    loadReports()
  } catch (e) { ElMessage.error('生成失败') }
  finally { loading.genReport = false }
}

async function doCheckCompliance() {
  complianceLoading.value = true
  try {
    const { data } = await checkCompliance(complianceForm)
    complianceResult.value = data
  } catch (e) { ElMessage.error('检查失败') }
  finally { complianceLoading.value = false }
}

onMounted(() => {
  loadDashboard()
  loadPayments()
  loadConversions()
  loadReports()
})
</script>

<style scoped>
.cross-border { min-height: 400px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
.text-primary { color: #409eff !important; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.text-muted { color: #c0c4cc; }
</style>
