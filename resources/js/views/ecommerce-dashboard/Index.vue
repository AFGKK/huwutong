<template>
    <div class="ecommerce-dashboard-page">
        <div class="page-header">
            <h2>{{ t('ecommerce_dashboard_page.title') }}</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('actions.refresh') }}
                </el-button>
            </div>
        </div>

        <el-tabs v-model="ecMainTab" type="border-card">
            <!-- Tab 1: 电商看板 -->
            <el-tab-pane label="电商看板" name="dashboard">
                <el-row :gutter="16" class="mb-6">
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" class="stat-card revenue">
                            <div class="stat-icon"><el-icon size="28"><Coin /></el-icon></div>
                            <div class="stat-body">
                                <div class="stat-label">{{ t('ecommerce_dashboard_page.today_revenue') }}</div>
                                <div class="stat-value">¥{{ formatNum(today.today_revenue) }}</div>
                                <div class="stat-change" :class="today.revenue_growth >= 0 ? 'up' : 'down'">
                                    {{ today.revenue_growth >= 0 ? '↑' : '↓' }} {{ t('ecommerce_dashboard_page.vs_yesterday', { n: Math.abs(today.revenue_growth) }) }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" class="stat-card orders">
                            <div class="stat-icon"><el-icon size="28"><ShoppingCart /></el-icon></div>
                            <div class="stat-body">
                                <div class="stat-label">{{ t('ecommerce_dashboard_page.today_orders') }}</div>
                                <div class="stat-value">{{ today.today_orders }}</div>
                                <div class="stat-sub">
                                    <el-tag size="small" type="success">{{ t('ecommerce_dashboard_page.paid_n', { n: today.today_paid }) }}</el-tag>
                                    <el-tag size="small" type="warning">{{ t('ecommerce_dashboard_page.pending_n', { n: today.today_pending }) }}</el-tag>
                                    <el-tag size="small" type="info">{{ t('ecommerce_dashboard_page.cancelled_n', { n: today.today_cancelled }) }}</el-tag>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" class="stat-card rate">
                            <div class="stat-icon"><el-icon size="28"><CircleCheck /></el-icon></div>
                            <div class="stat-body">
                                <div class="stat-label">{{ t('ecommerce_dashboard_page.payment_success_rate') }}</div>
                                <div class="stat-value">{{ paymentRate.success_rate }}%</div>
                                <div class="stat-sub">
                                    {{ t('ecommerce_dashboard_page.paid_of_total', { paid: paymentRate.paid, total: paymentRate.total }) }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" class="stat-card refund">
                            <div class="stat-icon"><el-icon size="28"><Refresh /></el-icon></div>
                            <div class="stat-body">
                                <div class="stat-label">{{ t('ecommerce_dashboard_page.refund_rate') }}</div>
                                <div class="stat-value">{{ refundRate.refund_rate }}%</div>
                                <div class="stat-sub">
                                    {{ t('ecommerce_dashboard_page.refund_of_paid', { refunded: refundRate.refunded_orders, paid: refundRate.total_paid_orders }) }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-6">
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" size="small">
                            <div class="sub-stat-label">{{ t('ecommerce_dashboard_page.month_revenue') }}</div>
                            <div class="sub-stat-value">¥{{ formatNum(today.month_revenue) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" size="small">
                            <div class="sub-stat-label">{{ t('ecommerce_dashboard_page.month_orders') }}</div>
                            <div class="sub-stat-value">{{ today.month_orders }}</div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" size="small">
                            <div class="sub-stat-label">{{ t('ecommerce_dashboard_page.total_revenue') }}</div>
                            <div class="sub-stat-value">¥{{ formatNum(today.total_revenue) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :xs="12" :sm="6">
                        <el-card shadow="hover" size="small">
                            <div class="sub-stat-label">{{ t('ecommerce_dashboard_page.total_orders') }}</div>
                            <div class="sub-stat-value">{{ today.total_orders }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-6">
                    <el-col :xs="24" :lg="16" class="mb-4">
                        <el-card shadow="hover">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('ecommerce_dashboard_page.trend_7d') }}</span>
                                    <el-radio-group v-model="trendMetric" size="small" @change="renderTrendChart">
                                        <el-radio-button value="revenue">{{ t('ecommerce_dashboard_page.metrics.revenue') }}</el-radio-button>
                                        <el-radio-button value="orders">{{ t('ecommerce_dashboard_page.metrics.orders') }}</el-radio-button>
                                        <el-radio-button value="paid">{{ t('ecommerce_dashboard_page.metrics.paid') }}</el-radio-button>
                                    </el-radio-group>
                                </div>
                            </template>
                            <div ref="trendChartRef" style="height: 320px"></div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :lg="8" class="mb-4">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ t('ecommerce_dashboard_page.product_ranking') }}</span>
                            </template>
                            <div v-if="productRanking.length === 0" class="empty-state">{{ t('messages.no_data') }}</div>
                            <div v-for="(item, index) in productRanking" :key="item.sku_id" class="ranking-item">
                                <div class="ranking-index" :class="'rank-' + (index + 1)">{{ index + 1 }}</div>
                                <div class="ranking-info">
                                    <div class="ranking-name" :title="item.sku_name">{{ item.sku_name }}</div>
                                    <div class="ranking-code">{{ item.sku_code }}</div>
                                </div>
                                <div class="ranking-stats">
                                    <div class="ranking-qty">{{ t('ecommerce_dashboard_page.qty_n', { n: item.total_qty }) }}</div>
                                    <div class="ranking-revenue">¥{{ formatNum(item.total_revenue) }}</div>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :xs="24" :lg="12" class="mb-4">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ t('ecommerce_dashboard_page.payment_dist') }}</span>
                            </template>
                            <div ref="paymentChartRef" style="height: 260px"></div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :lg="12" class="mb-4">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ t('ecommerce_dashboard_page.refund_stats') }}</span>
                            </template>
                            <div ref="refundChartRef" style="height: 260px"></div>
                            <el-descriptions :column="2" border size="small" class="mt-4">
                                <el-descriptions-item :label="t('ecommerce_dashboard_page.paid_total')">¥{{ formatNum(refundRate.total_revenue) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('ecommerce_dashboard_page.refund_total')">¥{{ formatNum(refundRate.refunded_amount) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('ecommerce_dashboard_page.refund_orders')">{{ refundRate.refunded_orders }}</el-descriptions-item>
                                <el-descriptions-item :label="t('ecommerce_dashboard_page.refund_amount_rate')">{{ refundRate.refund_amount_rate }}%</el-descriptions-item>
                            </el-descriptions>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- Tab 2: 电商分析 (lazy loaded) -->
            <el-tab-pane label="电商分析" name="analytics">
                <template v-if="ea_tabVisited">
                    <div class="ea-analytics-wrap">
                        <div class="ea-header-actions mb-4">
                            <el-radio-group v-model="ea_period" @change="ea_loadAll" size="small">
                                <el-radio-button
                                    v-for="opt in ea_periodOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</el-radio-button>
                            </el-radio-group>
                            <el-button @click="ea_loadAll" :loading="ea_loading">
                                <el-icon><Refresh /></el-icon> {{ t('revenue_page.refresh') }}
                            </el-button>
                            <el-dropdown v-if="ea_subTab === 'sales'" @command="ea_handleExport">
                                <el-button>
                                    <el-icon><Download /></el-icon> {{ t('licenses_page.export_csv') }}
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="item in ea_exportOptions"
                                            :key="item.command"
                                            :command="item.command"
                                        >{{ item.label }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>

                        <el-tabs v-model="ea_subTab">
                            <el-tab-pane :label="t('ecommerce_analytics_page.tabs.overview')" name="overview">
                                <el-loading v-model:loading="ea_loadingOverview">
                                    <el-row :gutter="16" class="mb-4">
                                        <el-col :span="6">
                                            <el-card shadow="hover">
                                                <div class="ea-stat-label">{{ t('business_metrics_page.metrics.total_revenue') }}</div>
                                                <div class="ea-stat-value">¥{{ formatNum(ea_summary.total_revenue) }}</div>
                                                <div class="ea-stat-change" :class="ea_summary.revenue_growth >= 0 ? 'up' : 'down'">
                                                    {{ ea_momFmt(ea_summary.revenue_growth) }}
                                                </div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="hover" class="ea-stat-active">
                                                <div class="ea-stat-label">{{ t('ecommerce_analytics_page.stats.total_orders') }}</div>
                                                <div class="ea-stat-value">{{ ea_summary.total_orders }}</div>
                                                <div class="ea-stat-change" :class="ea_summary.order_growth >= 0 ? 'up' : 'down'">
                                                    {{ ea_momFmt(ea_summary.order_growth) }}
                                                </div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="hover" class="ea-stat-info">
                                                <div class="ea-stat-label">{{ t('ecommerce_analytics_page.stats.total_customers') }}</div>
                                                <div class="ea-stat-value">{{ ea_summary.total_customers }}</div>
                                                <div class="ea-stat-change">{{ ea_newCustomersFmt(ea_summary.new_customers) }}</div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="hover" class="ea-stat-warning">
                                                <div class="ea-stat-label">{{ t('ecommerce_analytics_page.stats.avg_order_value') }}</div>
                                                <div class="ea-stat-value">¥{{ formatNum(ea_summary.avg_order_value) }}</div>
                                            </el-card>
                                        </el-col>
                                    </el-row>

                                    <el-row :gutter="16" class="mb-4">
                                        <el-col :span="8">
                                            <el-card shadow="hover" size="small">
                                                <template #header>{{ t('ecommerce_analytics_page.comparison.current_period') }}</template>
                                                <div v-if="ea_comparison.current" class="ea-compact-stat">
                                                    <div>{{ t('revenue_page.cols.revenue') }}: <strong>¥{{ formatNum(ea_comparison.current.revenue) }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.orders') }}: <strong>{{ ea_comparison.current.orders }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.avg_order') }}: <strong>¥{{ formatNum(ea_comparison.current.avg_order) }}</strong></div>
                                                </div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-card shadow="hover" size="small">
                                                <template #header>{{ t('ecommerce_analytics_page.comparison.previous_period') }}</template>
                                                <div v-if="ea_comparison.previous_period" class="ea-compact-stat">
                                                    <div>{{ t('revenue_page.cols.revenue') }}: <strong>¥{{ formatNum(ea_comparison.previous_period.revenue) }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.orders') }}: <strong>{{ ea_comparison.previous_period.orders }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.avg_order') }}: <strong>¥{{ formatNum(ea_comparison.previous_period.avg_order) }}</strong></div>
                                                </div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-card shadow="hover" size="small">
                                                <template #header>{{ t('ecommerce_analytics_page.comparison.year_ago') }}</template>
                                                <div v-if="ea_comparison.year_ago" class="ea-compact-stat">
                                                    <div>{{ t('revenue_page.cols.revenue') }}: <strong>¥{{ formatNum(ea_comparison.year_ago.revenue) }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.orders') }}: <strong>{{ ea_comparison.year_ago.orders }}</strong></div>
                                                    <div>{{ t('ecommerce_analytics_page.comparison.chain_growth') }}: <strong :class="ea_comparison.chain_growth >= 0 ? 'ea-text-up' : 'ea-text-down'">
                                                        {{ ea_comparison.chain_growth >= 0 ? '+' : '' }}{{ ea_comparison.chain_growth }}%
                                                    </strong></div>
                                                </div>
                                            </el-card>
                                        </el-col>
                                    </el-row>

                                    <el-card class="mb-4" v-if="ea_forecastData">
                                        <template #header>
                                            <span>{{ t('ecommerce_analytics_page.forecast.title') }}
                                                <el-tag :type="ea_forecastData.confidence === 'high' ? 'success' : ea_forecastData.confidence === 'medium' ? 'warning' : 'info'"
                                                    size="small" class="ml-2">
                                                    {{ ea_confidenceLabel(ea_forecastData.confidence) }}
                                                </el-tag>
                                                <span v-if="ea_forecastData.trend_direction === 'up'" class="ea-text-up ml-2">{{ t('ecommerce_analytics_page.forecast.trend_up') }}</span>
                                                <span v-else class="ea-text-down ml-2">{{ t('ecommerce_analytics_page.forecast.trend_down') }}</span>
                                            </span>
                                        </template>
                                        <div class="ea-forecast-summary">
                                            <span>{{ t('ecommerce_analytics_page.forecast.total_predicted') }}: <strong>¥{{ formatNum(ea_forecastData.total_predicted_revenue) }}</strong></span>
                                            <span class="ml-4">{{ t('ecommerce_analytics_page.forecast.daily_trend') }}: <strong>{{ ea_forecastData.daily_trend_rate >= 0 ? '+' : '' }}{{ ea_forecastData.daily_trend_rate }}</strong></span>
                                        </div>
                                    </el-card>
                                </el-loading>
                            </el-tab-pane>

                            <el-tab-pane :label="t('ecommerce_analytics_page.tabs.sales')" name="sales">
                                <el-card>
                                    <template #header>
                                        <div class="ea-card-header-flex">
                                            <span>{{ t('ecommerce_analytics_page.sections.daily_sales_trend') }}</span>
                                            <el-button size="small" @click="ea_handleExport('sales_trend')">
                                                <el-icon><Download /></el-icon> {{ t('licenses_page.export_csv') }}
                                            </el-button>
                                        </div>
                                    </template>
                                    <div class="ea-chart-container">
                                        <div v-if="!ea_salesTrend?.length" class="ea-chart-empty">{{ t('ecommerce_analytics_page.empty.no_sales_data') }}</div>
                                        <div v-else class="ea-dual-chart">
                                            <div class="ea-bar-chart">
                                                <div v-for="(day, i) in ea_salesTrend" :key="i" class="ea-bar-group">
                                                    <div class="ea-bar ea-bar-revenue"
                                                        :style="{ height: ea_barHeight(day.revenue, ea_maxRevenue) + 'px' }"
                                                        :title="day.date + ': ¥' + day.revenue">
                                                        <span v-if="day.revenue > ea_maxRevenue * 0.7" class="ea-bar-value">¥{{ day.revenue }}</span>
                                                    </div>
                                                    <div class="ea-bar-label">{{ ea_formatShortDate(day.date) }}</div>
                                                </div>
                                            </div>
                                            <div class="ea-trend-legend mt-2">
                                                <span class="ea-legend-item"><span class="ea-dot ea-dot-revenue"></span> {{ t('ecommerce_analytics_page.legend.revenue') }}</span>
                                                <span class="ea-legend-item"><span class="ea-dot ea-dot-orders"></span> {{ t('ecommerce_analytics_page.legend.orders_total', { n: ea_totalOrders }) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </el-card>
                            </el-tab-pane>

                            <el-tab-pane :label="t('ecommerce_analytics_page.tabs.products')" name="products">
                                <el-card>
                                    <template #header>
                                        <div class="ea-card-header-flex">
                                            <span>{{ t('ecommerce_analytics_page.sections.product_ranking') }}</span>
                                            <el-button size="small" @click="ea_handleExport('product_ranking')">
                                                <el-icon><Download /></el-icon> {{ t('licenses_page.export_csv') }}
                                            </el-button>
                                        </div>
                                    </template>
                                    <el-table :data="ea_productRanking" stripe v-loading="ea_loadingProducts">
                                        <el-table-column type="index" label="#" width="50" />
                                        <el-table-column prop="description" :label="t('ecommerce_analytics_page.cols.product')" min-width="200" />
                                        <el-table-column prop="type" :label="t('ecommerce_analytics_page.cols.type')" width="100" />
                                        <el-table-column prop="total_quantity" :label="t('ecommerce_analytics_page.cols.quantity')" width="100" align="center" sortable />
                                        <el-table-column prop="total_revenue" :label="t('ecommerce_analytics_page.cols.revenue_yuan')" width="120" align="center" sortable>
                                            <template #default="{ row }">¥{{ formatNum(row.total_revenue) }}</template>
                                        </el-table-column>
                                        <el-table-column prop="order_count" :label="t('ecommerce_analytics_page.cols.order_count')" width="90" align="center" sortable />
                                    </el-table>
                                </el-card>
                            </el-tab-pane>

                            <el-tab-pane :label="t('ecommerce_analytics_page.tabs.repurchase')" name="repurchase">
                                <el-row :gutter="16" class="mb-4">
                                    <el-col :span="6">
                                        <el-card shadow="hover">
                                            <div class="ea-stat-label">{{ t('ecommerce_analytics_page.repurchase.total_buyers') }}</div>
                                            <div class="ea-stat-value">{{ ea_repurchaseData.total_buyers }}</div>
                                        </el-card>
                                    </el-col>
                                    <el-col :span="6">
                                        <el-card shadow="hover" class="ea-stat-active">
                                            <div class="ea-stat-label">{{ t('ecommerce_analytics_page.repurchase.repurchase_rate') }}</div>
                                            <div class="ea-stat-value">{{ ea_repurchaseData.repurchase_rate }}%</div>
                                        </el-card>
                                    </el-col>
                                    <el-col :span="6">
                                        <el-card shadow="hover" class="ea-stat-info">
                                            <div class="ea-stat-label">{{ t('ecommerce_analytics_page.repurchase.multi_purchase_rate') }}</div>
                                            <div class="ea-stat-value">{{ ea_repurchaseData.multi_purchase_rate }}%</div>
                                        </el-card>
                                    </el-col>
                                    <el-col :span="6">
                                        <el-card shadow="hover" class="ea-stat-warning">
                                            <div class="ea-stat-label">{{ t('ecommerce_analytics_page.repurchase.avg_spent') }}</div>
                                            <div class="ea-stat-value">¥{{ formatNum(ea_repurchaseData.avg_spent_per_buyer) }}</div>
                                        </el-card>
                                    </el-col>
                                </el-row>
                                <el-card>
                                    <template #header>{{ t('ecommerce_analytics_page.sections.order_distribution') }}</template>
                                    <div class="ea-distribution-grid">
                                        <div v-for="(count, label) in ea_repurchaseData.order_distribution" :key="label" class="ea-dist-item">
                                            <div class="ea-dist-value">{{ count }}</div>
                                            <div class="ea-dist-label">{{ label }}</div>
                                        </div>
                                    </div>
                                </el-card>
                            </el-tab-pane>

                            <el-tab-pane :label="t('ecommerce_analytics_page.tabs.payment')" name="payment">
                                <el-card>
                                    <template #header>
                                        <div class="ea-card-header-flex">
                                            <span>{{ t('ecommerce_analytics_page.sections.payment_channels') }}</span>
                                            <el-button size="small" @click="ea_handleExport('payment_channels')">
                                                <el-icon><Download /></el-icon> {{ t('licenses_page.export_csv') }}
                                            </el-button>
                                        </div>
                                    </template>
                                    <el-table :data="ea_paymentChannels" stripe>
                                        <el-table-column prop="channel" :label="t('revenue_page.cols.channel')" min-width="160" />
                                        <el-table-column prop="order_count" :label="t('ecommerce_analytics_page.cols.order_count')" width="100" align="center" />
                                        <el-table-column prop="total_amount" :label="t('ecommerce_analytics_page.cols.amount_yuan')" width="130" align="center">
                                            <template #default="{ row }">¥{{ formatNum(row.total_amount) }}</template>
                                        </el-table-column>
                                        <el-table-column :label="t('ecommerce_analytics_page.cols.share')" min-width="200">
                                            <template #default="{ row }">
                                                <el-progress :percentage="row.percentage" :stroke-width="12" />
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </el-card>
                            </el-tab-pane>
                        </el-tabs>
                    </div>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'
import { Refresh, Coin, ShoppingCart, CircleCheck, Download } from '@element-plus/icons-vue'
import ecommerceDashboardApi from '@/api/ecommerceDashboard'
import ecommerceAnalyticsApi from '@/api/ecommerceAnalytics'

const { t, locale } = useI18n()

// ---- 主 Tab 切换 ----
const ecMainTab = ref('dashboard')

// ============ 电商看板 ============
const loading = ref(false)
const today = ref({})
const productRanking = ref([])
const paymentRate = ref({})
const refundRate = ref({})
const trend = ref([])
const trendMetric = ref('revenue')

const trendChartRef = ref(null)
const paymentChartRef = ref(null)
const refundChartRef = ref(null)

let trendChart = null
let paymentChart = null
let refundChart = null

function formatNum(val) {
    if (val === null || val === undefined || val === 0) return '0.00'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return Number(val).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadAll() {
    loading.value = true
    try {
        const [dashboardRes, payRateRes, refundRateRes] = await Promise.all([
            ecommerceDashboardApi.getDashboard(),
            ecommerceDashboardApi.getPaymentSuccessRate(),
            ecommerceDashboardApi.getRefundRate(),
        ])
        const data = dashboardRes.data.data
        today.value = data.today
        productRanking.value = data.product_ranking
        trend.value = data.trend
        paymentRate.value = payRateRes.data.data
        refundRate.value = refundRateRes.data.data

        await nextTick()
        renderTrendChart()
        renderPaymentChart()
        renderRefundChart()
    } catch (e) {
        console.error('Failed to load ecommerce dashboard', e)
    } finally {
        loading.value = false
    }
}

function metricName(metric) {
    return t(`ecommerce_dashboard_page.metrics.${metric}`)
}

function renderTrendChart() {
    if (!trendChartRef.value) return
    if (!trendChart) {
        trendChart = echarts.init(trendChartRef.value)
    }
    const dates = trend.value.map(item => item.label)
    const metric = trendMetric.value
    const colorMap = { revenue: '#0f172a', orders: '#67C23A', paid: '#E6A23C' }

    trendChart.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: 60, right: 20, bottom: 30, top: 20 },
        xAxis: { type: 'category', data: dates, boundaryGap: false },
        yAxis: { type: 'value' },
        series: [{
            name: metricName(metric),
            type: 'line',
            smooth: true,
            data: trend.value.map(item => item[metric]),
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: colorMap[metric] + '40' },
                { offset: 1, color: colorMap[metric] + '05' },
            ])},
            lineStyle: { color: colorMap[metric], width: 3 },
            itemStyle: { color: colorMap[metric] },
        }],
    }, true)
}

function renderPaymentChart() {
    if (!paymentChartRef.value) return
    if (!paymentChart) {
        paymentChart = echarts.init(paymentChartRef.value)
    }
    const data = [
        { value: paymentRate.value.paid || 0, name: t('ecommerce_dashboard_page.statuses.paid'), itemStyle: { color: '#67C23A' } },
        { value: paymentRate.value.pending || 0, name: t('ecommerce_dashboard_page.statuses.pending'), itemStyle: { color: '#E6A23C' } },
        { value: paymentRate.value.cancelled || 0, name: t('ecommerce_dashboard_page.statuses.cancelled'), itemStyle: { color: '#909399' } },
        { value: paymentRate.value.refunded || 0, name: t('ecommerce_dashboard_page.statuses.refunded'), itemStyle: { color: '#F56C6C' } },
    ]
    paymentChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        series: [{
            type: 'pie',
            radius: ['40%', '70%'],
            center: ['50%', '50%'],
            data: data.filter(d => d.value > 0),
            label: { show: true, formatter: '{b}\n{d}%' },
            emphasis: { scale: true },
        }],
    }, true)
}

function renderRefundChart() {
    if (!refundChartRef.value) return
    if (!refundChart) {
        refundChart = echarts.init(refundChartRef.value)
    }
    const paidAmount = refundRate.value.total_revenue || 0
    const refundedAmount = refundRate.value.refunded_amount || 0
    const netAmount = paidAmount - refundedAmount
    refundChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: ¥{c}' },
        series: [{
            type: 'pie',
            radius: ['40%', '70%'],
            data: [
                { value: netAmount, name: t('ecommerce_dashboard_page.net_revenue'), itemStyle: { color: '#0f172a' } },
                { value: refundedAmount, name: t('ecommerce_dashboard_page.refund_amount'), itemStyle: { color: '#F56C6C' } },
            ],
            label: {
                show: true,
                formatter: ({ name, value, percent }) => {
                    if (value === 0) return ''
                    return `${name}\n¥${value.toLocaleString()}\n${percent.toFixed(1)}%`
                },
            },
            emphasis: { scale: true },
        }],
    }, true)
}

// ============ 电商分析 (Lazy) ============
const ea_tabVisited = ref(false)
const ea_loading = ref(false)
const ea_loadingOverview = ref(false)
const ea_loadingProducts = ref(false)
const ea_subTab = ref('overview')
const ea_period = ref('30d')

const ea_summary = reactive({
    total_revenue: 0, total_orders: 0, total_customers: 0,
    new_customers: 0, avg_order_value: 0,
    revenue_growth: 0, order_growth: 0,
})

const ea_comparison = reactive({})

const ea_salesTrend = ref([])

const ea_productRanking = ref([])

const ea_repurchaseData = reactive({
    total_buyers: 0, repurchase_rate: 0, multi_purchase_rate: 0,
    avg_orders_per_buyer: 0, avg_spent_per_buyer: 0, order_distribution: {},
})

const ea_paymentChannels = ref([])

const ea_forecastData = ref(null)

const ea_periodOptions = computed(() => [
    { value: '7d', label: t('ecommerce_analytics_page.period.d7') },
    { value: '30d', label: t('ecommerce_analytics_page.period.d30') },
    { value: '90d', label: t('ecommerce_analytics_page.period.d90') },
    { value: '1y', label: t('ecommerce_analytics_page.period.y1') },
])

const ea_exportOptions = computed(() => [
    { command: 'sales_trend', label: t('ecommerce_analytics_page.export.sales_trend') },
    { command: 'product_ranking', label: t('ecommerce_analytics_page.export.product_ranking') },
    { command: 'payment_channels', label: t('ecommerce_analytics_page.export.payment_channels') },
])

const ea_confidenceLabels = computed(() => ({
    high: t('ecommerce_analytics_page.forecast.confidence_high'),
    medium: t('ecommerce_analytics_page.forecast.confidence_medium'),
    low: t('ecommerce_analytics_page.forecast.confidence_low'),
}))

const ea_maxRevenue = computed(() => Math.max(...ea_salesTrend.value.map(d => d.revenue), 1))
const ea_totalOrders = computed(() => ea_salesTrend.value.reduce((s, d) => s + d.order_count, 0))

function ea_confidenceLabel(level) {
    return ea_confidenceLabels.value[level] || ea_confidenceLabels.value.low
}

function ea_momFmt(pct) {
    const sign = pct >= 0 ? '+' : ''
    return t('ecommerce_analytics_page.stats.mom_fmt', { sign, pct })
}

function ea_newCustomersFmt(n) {
    return t('ecommerce_analytics_page.stats.new_customers_fmt', { n })
}

function ea_barHeight(val, max) {
    return Math.max(2, (val / max) * 140)
}

function ea_formatShortDate(dateStr) {
    if (!dateStr) return ''
    const parts = dateStr.split('-')
    return parts.length >= 3 ? `${parts[1]}/${parts[2]}` : dateStr
}

async function ea_loadAll() {
    ea_loading.value = true
    try {
        const days = ea_period.value === '7d' ? 7 : ea_period.value === '30d' ? 30 : ea_period.value === '90d' ? 90 : 365
        const params = { period: ea_period.value }

        const [
            summaryRes, comparisonRes, trendRes,
            productRes, repurchaseRes, paymentRes,
            forecastRes,
        ] = await Promise.all([
            ecommerceAnalyticsApi.getSummary({ days }),
            ecommerceAnalyticsApi.getComparison({ days }),
            ecommerceAnalyticsApi.getSalesTrend({ days }),
            ecommerceAnalyticsApi.getProductRanking({ period: ea_period.value }),
            ecommerceAnalyticsApi.getRepurchaseRate({ days: Math.min(days * 3, 365) }),
            ecommerceAnalyticsApi.getPaymentChannels({ days }),
            ecommerceAnalyticsApi.getForecast({ days: Math.min(days, 90) }),
        ])

        Object.assign(ea_summary, summaryRes.data?.data || {})
        Object.assign(ea_comparison, comparisonRes.data?.data || {})
        ea_salesTrend.value = trendRes.data?.data || []
        ea_productRanking.value = productRes.data?.data || []
        Object.assign(ea_repurchaseData, repurchaseRes.data?.data || {})
        ea_paymentChannels.value = paymentRes.data?.data || []
        ea_forecastData.value = forecastRes.data?.data || null
    } catch (err) {
        console.error('Failed to load analytics', err)
    } finally {
        ea_loading.value = false
    }
}

function ea_handleExport(type) {
    const token = localStorage.getItem('token')
    const days = ea_period.value === '7d' ? 7 : ea_period.value === '30d' ? 30 : ea_period.value === '90d' ? 90 : 365
    const url = `${import.meta.env.VITE_API_URL || ''}/api/ecommerce-analytics/export-csv?type=${type}&days=${days}`
    const link = document.createElement('a')
    link.href = token ? `${url}&token=${token}` : url
    link.click()
}

// ---- 懒加载：切换到"电商分析"时首次加载数据 ----
watch(ecMainTab, (newVal) => {
    if (newVal === 'analytics' && !ea_tabVisited.value) {
        ea_tabVisited.value = true
        nextTick(() => ea_loadAll())
    }
})

// ---- 生命周期 ----
onMounted(() => {
    loadAll()
})
</script>

<style scoped>
.ecommerce-dashboard-page {
    padding: 16px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.mb-6 {
    margin-bottom: 24px;
}

.mb-4 {
    margin-bottom: 16px;
}

.mt-4 {
    margin-top: 16px;
}

/* --- 看板统计卡片 --- */
.stat-card {
    position: relative;
    border-left: 4px solid;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.revenue { border-left-color: #0f172a; }
.stat-card.orders { border-left-color: #67C23A; }
.stat-card.rate { border-left-color: #E6A23C; }
.stat-card.refund { border-left-color: #F56C6C; }

.stat-icon {
    position: absolute;
    top: 16px;
    right: 16px;
    opacity: 0.15;
}

.stat-body {
    padding: 4px 0;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #303133;
    margin-bottom: 6px;
}

.stat-change {
    font-size: 12px;
}

.stat-change.up {
    color: #67C23A;
}

.stat-change.down {
    color: #F56C6C;
}

.stat-sub {
    font-size: 12px;
    color: #909399;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.sub-stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}

.sub-stat-value {
    font-size: 18px;
    font-weight: 600;
    color: #303133;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ranking-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
    gap: 10px;
}

.ranking-item:last-child {
    border-bottom: none;
}

.ranking-index {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    background: #f0f0f0;
    color: #909399;
    flex-shrink: 0;
}

.ranking-index.rank-1 { background: #E6A23C; color: #fff; }
.ranking-index.rank-2 { background: #909399; color: #fff; }
.ranking-index.rank-3 { background: #A0522D; color: #fff; }

.ranking-info {
    flex: 1;
    min-width: 0;
}

.ranking-name {
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ranking-code {
    font-size: 11px;
    color: #909399;
    margin-top: 2px;
}

.ranking-stats {
    text-align: right;
    flex-shrink: 0;
}

.ranking-qty {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
}

.ranking-revenue {
    font-size: 11px;
    color: #909399;
    margin-top: 2px;
}

.empty-state {
    padding: 40px 0;
    text-align: center;
    color: #909399;
}

/* ============ 电商分析样式 ============ */
.ea-analytics-wrap .ml-2 { margin-left: 8px; }
.ea-analytics-wrap .ml-4 { margin-left: 16px; }
.ea-analytics-wrap .mt-2 { margin-top: 8px; }

.ea-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.ea-stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}

.ea-stat-value {
    font-size: 22px;
    font-weight: 700;
}

.ea-stat-change {
    font-size: 12px;
    margin-top: 4px;
}

.ea-stat-change.up { color: #67c23a; }
.ea-stat-change.down { color: #f56c6c; }
.ea-stat-active .ea-stat-value { color: #67c23a; }
.ea-stat-info .ea-stat-value { color: #0f172a; }
.ea-stat-warning .ea-stat-value { color: #e6a23c; }

.ea-text-up { color: #67c23a; }
.ea-text-down { color: #f56c6c; }

.ea-chart-container { min-height: 200px; }
.ea-chart-empty { text-align: center; color: #c0c4cc; padding: 60px 0; }

.ea-bar-chart { display: flex; align-items: flex-end; gap: 2px; min-height: 180px; overflow-x: auto; padding-top: 20px; }
.ea-bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 16px; }
.ea-bar { width: 100%; max-width: 24px; border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s; position: relative; }
.ea-bar-revenue { background: linear-gradient(to top, #0f172a, #94a3b8); }
.ea-bar-value { font-size: 9px; position: absolute; top: -16px; white-space: nowrap; color: #606266; }
.ea-bar-label { font-size: 8px; color: #909399; margin-top: 2px; white-space: nowrap; }

.ea-trend-legend { display: flex; gap: 16px; font-size: 12px; }
.ea-legend-item { display: flex; align-items: center; gap: 4px; }
.ea-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.ea-dot-revenue { background: #0f172a; }
.ea-dot-orders { background: #67c23a; }

.ea-compact-stat { font-size: 13px; line-height: 1.8; }
.ea-forecast-summary { font-size: 14px; padding: 8px 0; }

.ea-distribution-grid { display: flex; gap: 12px; flex-wrap: wrap; }
.ea-dist-item { flex: 1; min-width: 80px; text-align: center; padding: 16px; background: #f5f7fa; border-radius: 6px; }
.ea-dist-value { font-size: 24px; font-weight: 700; color: #0f172a; }
.ea-dist-label { font-size: 12px; color: #909399; margin-top: 4px; }

.ea-card-header-flex { display: flex; justify-content: space-between; align-items: center; }
</style>
