<template>
  <div class="cross-border">
    <h2 class="mb-4">{{ t('cross_border_page.title') }}</h2>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ formatCny(dashboard.monthly_revenue_cny) }}</div><div class="stat-label">{{ t('cross_border_page.stats.monthly_revenue') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ dashboard.monthly_transactions }}</div><div class="stat-label">{{ t('cross_border_page.stats.monthly_txns') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-primary">{{ dashboard.active_currencies }}</div><div class="stat-label">{{ t('cross_border_page.stats.active_currencies') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-warning">{{ dashboard.compliance_warnings }}</div><div class="stat-label">{{ t('cross_border_page.stats.compliance_warnings') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ dashboard.total_conversions }}</div><div class="stat-label">{{ t('cross_border_page.stats.conversions') }}</div></div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="t('cross_border_page.tabs.payments')" name="payments">
        <div class="mb-3 flex gap-2">
          <el-input v-model="filters.currency" :placeholder="t('cross_border_page.currency_ph')" clearable style="width:120px" @clear="loadPayments" @keyup.enter="loadPayments" />
          <el-select v-model="filters.status" :placeholder="t('cross_border_page.status')" clearable style="width:140px" @change="loadPayments">
            <el-option :label="t('cross_border_page.statuses.completed')" value="completed" />
            <el-option :label="t('cross_border_page.statuses.pending')" value="pending" />
            <el-option :label="t('cross_border_page.statuses.failed')" value="failed" />
            <el-option :label="t('cross_border_page.statuses.refunded')" value="refunded" />
          </el-select>
        </div>

        <el-table :data="payments" stripe v-loading="loading.payments" :empty-text="t('cross_border_page.empty_payments')">
          <el-table-column :label="t('cross_border_page.cols.id')" prop="id" width="60" />
          <el-table-column :label="t('cross_border_page.cols.currency')" prop="currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.currency }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.amount')" prop="amount" width="120">
            <template #default="{ row }">{{ row.currency }} {{ row.amount }}</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.amount_cny')" prop="amount_cny" width="120">
            <template #default="{ row }">¥ {{ row.amount_cny }}</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.rate')" prop="exchange_rate" width="100" />
          <el-table-column :label="t('cross_border_page.cols.gateway')" prop="payment_gateway" width="100" />
          <el-table-column :label="t('cross_border_page.cols.type')" prop="transaction_type" width="90">
            <template #default="{ row }">
              <el-tag :type="row.transaction_type === 'refund' ? 'danger' : 'success'" size="small">{{ row.transaction_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.country')" prop="customer_country" width="100" />
          <el-table-column :label="t('cross_border_page.cols.fee')" prop="gateway_fee" width="100">
            <template #default="{ row }">{{ row.gateway_fee }} {{ row.currency }}</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.status')" prop="status" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.time')" prop="created_at" width="160" />
        </el-table>

        <div class="mt-3 flex justify-center" v-if="paymentPagination.total">
          <el-pagination v-model:current-page="paymentPagination.current" :page-size="paymentPagination.pageSize" :total="paymentPagination.total" layout="prev, pager, next" @current-change="loadPayments" />
        </div>
      </el-tab-pane>

      <el-tab-pane :label="t('cross_border_page.tabs.conversions')" name="conversions">
        <div class="mb-3 flex gap-2">
          <el-select v-model="convFilters.from_currency" :placeholder="t('cross_border_page.from_currency')" clearable style="width:120px" @change="loadConversions">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
          <el-select v-model="convFilters.to_currency" :placeholder="t('cross_border_page.to_currency')" clearable style="width:120px" @change="loadConversions">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
          <el-select v-model="convFilters.source" :placeholder="t('cross_border_page.source')" clearable style="width:140px" @change="loadConversions">
            <el-option :label="t('cross_border_page.sources.pricing')" value="pricing" />
            <el-option :label="t('cross_border_page.sources.invoice')" value="invoice" />
            <el-option :label="t('cross_border_page.sources.refund')" value="refund" />
            <el-option :label="t('cross_border_page.sources.subscription')" value="subscription" />
          </el-select>
        </div>

        <el-table :data="conversionLogs" stripe v-loading="loading.conversions" :empty-text="t('cross_border_page.empty_conversions')">
          <el-table-column :label="t('cross_border_page.cols.from_currency')" prop="from_currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.from_currency }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.from_amount')" prop="from_amount" width="120" />
          <el-table-column label="→" width="40" align="center">→</el-table-column>
          <el-table-column :label="t('cross_border_page.cols.to_currency')" prop="to_currency" width="80">
            <template #default="{ row }"><el-tag size="small" type="success">{{ row.to_currency }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.to_amount')" prop="to_amount" width="120" />
          <el-table-column :label="t('cross_border_page.cols.rate')" prop="rate_used" width="100" />
          <el-table-column :label="t('cross_border_page.cols.markup')" prop="rate_markup" width="80">
            <template #default="{ row }">{{ (row.rate_markup * 100).toFixed(2) }}%</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.type')" prop="conversion_type" width="80">
            <template #default="{ row }">
              <el-tag :type="row.conversion_type === 'manual' ? 'warning' : 'info'" size="small">{{ row.conversion_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.source')" prop="source" width="100" />
          <el-table-column :label="t('cross_border_page.cols.time')" prop="created_at" width="160" />
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('cross_border_page.tabs.reports')" name="reports">
        <div class="mb-3 flex gap-2 items-center">
          <el-button type="primary" :loading="loading.genReport" @click="doGenerateReport">{{ t('cross_border_page.gen_report') }}</el-button>
          <el-select v-model="reportFilters.currency" :placeholder="t('cross_border_page.cols.currency')" clearable style="width:120px" @change="loadReports">
            <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
          </el-select>
        </div>

        <el-table :data="reports" stripe v-loading="loading.reports" :empty-text="t('cross_border_page.empty_reports')">
          <el-table-column :label="t('cross_border_page.cols.month')" prop="report_month" width="110" />
          <el-table-column :label="t('cross_border_page.cols.currency')" prop="currency" width="80">
            <template #default="{ row }"><el-tag size="small">{{ row.currency }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.revenue')" prop="total_revenue" width="140" />
          <el-table-column :label="t('cross_border_page.cols.revenue_cny')" prop="total_revenue_cny" width="140">
            <template #default="{ row }">¥ {{ row.total_revenue_cny }}</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.refunds')" prop="total_refunds" width="120" />
          <el-table-column :label="t('cross_border_page.cols.fees')" prop="total_fees" width="130" />
          <el-table-column :label="t('cross_border_page.cols.fees_cny')" prop="total_fees_cny" width="130">
            <template #default="{ row }">¥ {{ row.total_fees_cny }}</template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.net')" prop="net_revenue" width="140">
            <template #default="{ row }">
              <span :class="row.net_revenue >= 0 ? 'text-success' : 'text-danger'">{{ row.net_revenue }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('cross_border_page.cols.txn_count')" prop="transaction_count" width="90" align="center" />
          <el-table-column :label="t('cross_border_page.cols.customer_count')" prop="customer_count" width="90" align="center" />
          <el-table-column :label="t('cross_border_page.cols.top_countries')" prop="top_countries" min-width="160">
            <template #default="{ row }">
              <span v-if="row.top_countries && Object.keys(row.top_countries).length">
                {{ Object.keys(row.top_countries).join(', ') }}
              </span>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('cross_border_page.tabs.compliance')" name="compliance">
        <el-card shadow="hover">
          <template #header>{{ t('cross_border_page.compliance_title') }}</template>
          <el-form :model="complianceForm" label-position="top" inline>
            <el-form-item :label="t('cross_border_page.cols.currency')">
              <el-select v-model="complianceForm.currency" style="width:120px">
                <el-option v-for="c in currencies" :key="c" :label="c" :value="c" />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('cross_border_page.cols.amount')">
              <el-input-number v-model="complianceForm.amount" :min="0" style="width:180px" />
            </el-form-item>
            <el-form-item :label="t('cross_border_page.cols.country')">
              <el-input v-model="complianceForm.customer_country" :placeholder="t('cross_border_page.country_ph')" style="width:120px" />
            </el-form-item>
            <el-form-item :label="t('cross_border_page.txn_type')">
              <el-select v-model="complianceForm.transaction_type" style="width:140px">
                <el-option :label="t('cross_border_page.txn_types.payment')" value="payment" />
                <el-option :label="t('cross_border_page.txn_types.refund')" value="refund" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="complianceLoading" @click="doCheckCompliance">{{ t('cross_border_page.check_compliance') }}</el-button>
            </el-form-item>
          </el-form>

          <el-result v-if="complianceResult" :icon="complianceResult.passed ? 'success' : 'warning'" :title="complianceResult.passed ? t('cross_border_page.compliance_pass') : t('cross_border_page.compliance_warn')">
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
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
  getCrossBorderDashboard, getConversionLogs, getCrossBorderPayments,
  getMonthlyReports, generateReport, checkCompliance,
} from '../../api/crossBorder'

const { t, locale } = useI18n()

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
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return '¥ ' + Number(val).toLocaleString(loc, { minimumFractionDigits: 2 })
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
  } catch (e) { ElMessage.error(t('cross_border_page.messages.load_payments_failed')) }
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
    ElMessage.success(t('cross_border_page.messages.report_generated'))
    loadReports()
  } catch (e) { ElMessage.error(t('cross_border_page.messages.gen_failed')) }
  finally { loading.genReport = false }
}

async function doCheckCompliance() {
  complianceLoading.value = true
  try {
    const { data } = await checkCompliance(complianceForm)
    complianceResult.value = data
  } catch (e) { ElMessage.error(t('cross_border_page.messages.check_failed')) }
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
.text-primary { color: #0f172a !important; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.text-muted { color: #c0c4cc; }
</style>
