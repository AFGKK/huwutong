<template>
    <div class="report-page">
        <div class="page-header">
            <h2>高级报表</h2>
            <el-button @click="refreshAll" :icon="Refresh" :loading="loading" circle size="small" />
        </div>

        <!-- 第一行：关键财务指标 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" class="metric-card">
                    <div class="metric-label">MRR (月经常性收入)</div>
                    <div class="metric-value primary">¥{{ formatNum(dashboard.mrr) }}</div>
                    <div class="metric-sub">
                        ARR: ¥{{ formatNum(dashboard.arr) }}
                        <el-tag size="small" type="success" class="ml-2">年化</el-tag>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="metric-card">
                    <div class="metric-label">本月收入</div>
                    <div class="metric-value success">¥{{ formatNum(dashboard.month_revenue) }}</div>
                    <div class="metric-sub">累计: ¥{{ formatNum(dashboard.total_revenue) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="metric-card">
                    <div class="metric-label">活跃订阅</div>
                    <div class="metric-value primary">{{ dashboard.subscriptions?.active || 0 }}</div>
                    <div class="metric-sub">
                        总计: {{ dashboard.subscriptions?.total || 0 }}
                        <el-tag v-if="dashboard.subscriptions?.grace > 0" size="small" type="warning" class="ml-2">
                            {{ dashboard.subscriptions?.grace }} 宽限期
                        </el-tag>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="metric-card">
                    <div class="metric-label">活跃客户</div>
                    <div class="metric-value success">{{ dashboard.customers?.active || 0 }}</div>
                    <div class="metric-sub">总计: {{ dashboard.customers?.total || 0 }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行：MRR 构成 + 收入趋势 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header><span>MRR 构成</span></template>
                    <div v-loading="loading">
                        <div v-for="(value, key) in dashboard.mrr_breakdown" :key="key" class="mrr-row">
                            <span class="mrr-label">{{ periodLabel(key) }}</span>
                            <div class="mrr-bar-bg">
                                <div class="mrr-bar" :style="{ width: mrrBarWidth(value) + '%', background: mrrColor(key) }"></div>
                            </div>
                            <span class="mrr-value">¥{{ formatNum(value) }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="section-header">
                            <span>收入趋势</span>
                            <el-radio-group v-model="trendPeriod" size="small" @change="fetchRevenueTrend">
                                <el-radio-button value="monthly">按月</el-radio-button>
                                <el-radio-button value="daily">按日</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div v-loading="loadingTrend">
                        <div v-if="revenueTrend.length > 0" class="trend-chart">
                            <div v-for="(item, idx) in revenueTrend" :key="idx" class="trend-bar-group">
                                <div class="trend-bar-wrapper">
                                    <div class="trend-bar" :style="{ height: trendBarHeight(item.revenue) + 'px' }"
                                        :title="item.period + ': ¥' + formatNum(item.revenue)"></div>
                                </div>
                                <div class="trend-label">{{ formatPeriod(item.period) }}</div>
                                <div class="trend-value">¥{{ formatNum(item.revenue) }}</div>
                                <div v-if="item.growth_rate" class="trend-growth" :class="item.growth_rate >= 0 ? 'up' : 'down'">
                                    {{ item.growth_rate > 0 ? '+' : '' }}{{ item.growth_rate }}%
                                </div>
                            </div>
                        </div>
                        <el-empty v-else description="暂无收入数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第三行：订阅分析 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>订阅分析</span></template>
                    <div v-loading="loading">
                        <el-descriptions :column="2" border size="small">
                            <el-descriptions-item label="总订阅数">{{ analytics.subscriptions?.total || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="活跃订阅数">{{ analytics.subscriptions?.active || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="宽限期">{{ analytics.subscriptions?.grace || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="已过期">{{ analytics.subscriptions?.expired || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="已取消">{{ analytics.subscriptions?.canceled || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="30天内到期">{{ subAnalytics.expiring_soon_30d || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="平均订阅时长">
                                {{ subAnalytics.avg_subscription_days ? subAnalytics.avg_subscription_days + ' 天' : '-' }}
                            </el-descriptions-item>
                            <el-descriptions-item label="总定价方案">{{ dashboard.total_plans || '-' }}</el-descriptions-item>
                        </el-descriptions>

                        <el-divider>按计费周期分布</el-divider>
                        <el-table :data="subAnalytics.by_period || []" size="small" stripe>
                            <el-table-column prop="billing_period" label="周期">
                                <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                            </el-table-column>
                            <el-table-column prop="count" label="数量" align="right" />
                            <el-table-column prop="total_value" label="总额" align="right">
                                <template #default="{ row }">¥{{ formatNum(row.total_value) }}</template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>定价方案分布</span></template>
                    <div v-loading="loading">
                        <el-table :data="planDistribution" size="small" stripe v-if="planDistribution.length > 0">
                            <el-table-column prop="name" label="方案" min-width="100" />
                            <el-table-column prop="subscriber_count" label="订阅数" align="right" width="90" />
                            <el-table-column label="月收入" align="right" width="120">
                                <template #default="{ row }">¥{{ formatNum(row.revenue_monthly) }}</template>
                            </el-table-column>
                            <el-table-column label="ARPU" align="right" width="100">
                                <template #default="{ row }">
                                    ¥{{ row.subscriber_count > 0 ? formatNum(row.revenue_monthly / row.subscriber_count) : '-' }}
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-else description="暂无方案数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第四行：客户 LTV + 流失分析 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>客户生命周期价值 (LTV)</span></template>
                    <div v-loading="loading">
                        <el-row :gutter="12" class="mb-3">
                            <el-col :span="8">
                                <el-statistic title="平均 LTV" :value="ltv.average_ltv" :precision="2" prefix="¥" />
                            </el-col>
                            <el-col :span="8">
                                <el-statistic title="中位 LTV" :value="ltv.median_ltv" :precision="2" prefix="¥" />
                            </el-col>
                            <el-col :span="8">
                                <el-statistic title="最大 LTV" :value="ltv.max_ltv" :precision="2" prefix="¥" />
                            </el-col>
                        </el-row>
                        <el-row :gutter="12" class="mb-3">
                            <el-col :span="8">
                                <el-card shadow="never" class="tier-card">
                                    <div class="tier-value">¥10,000+</div>
                                    <div class="tier-count">{{ ltv.tiers?.high || 0 }} 人</div>
                                </el-card>
                            </el-col>
                            <el-col :span="8">
                                <el-card shadow="never" class="tier-card">
                                    <div class="tier-value">¥1,000-9,999</div>
                                    <div class="tier-count">{{ ltv.tiers?.medium || 0 }} 人</div>
                                </el-card>
                            </el-col>
                            <el-col :span="8">
                                <el-card shadow="never" class="tier-card">
                                    <div class="tier-value">¥1-999</div>
                                    <div class="tier-count">{{ ltv.tiers?.low || 0 }} 人</div>
                                </el-card>
                            </el-col>
                        </el-row>
                        <el-divider>TOP 10 客户</el-divider>
                        <el-table :data="ltv.top_customers || []" size="small" stripe max-height="250">
                            <el-table-column prop="name" label="客户名" min-width="100" />
                            <el-table-column prop="email" label="邮箱" min-width="150" />
                            <el-table-column prop="total_paid" label="累计消费" align="right">
                                <template #default="{ row }">¥{{ formatNum(row.total_paid) }}</template>
                            </el-table-column>
                            <el-table-column prop="invoice_count" label="发票数" align="right" width="80" />
                        </el-table>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>月度流失分析</span></template>
                    <div v-loading="loading">
                        <el-table :data="churnData" size="small" stripe v-if="churnData.length > 0" max-height="400">
                            <el-table-column prop="label" label="月份" width="100" />
                            <el-table-column prop="start_active" label="月初活跃" align="right" width="90" />
                            <el-table-column prop="new_subscriptions" label="新增" align="right" width="70" />
                            <el-table-column prop="churned" label="流失" align="right" width="70">
                                <template #default="{ row }">
                                    <span :class="row.churned > 0 ? 'text-danger' : ''">{{ row.churned }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="流失率" align="right" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="churnTagType(row.churn_rate)" size="small">
                                        {{ row.churn_rate }}%
                                    </el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-else description="暂无流失数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- MRR 趋势表格 -->
        <el-card shadow="never">
            <template #header><span>MRR / ARR 历史趋势</span></template>
            <div v-loading="loadingMrt">
                <el-table :data="mrrTrendData" size="small" stripe max-height="350" v-if="mrrTrendData.length > 0">
                    <el-table-column prop="label" label="月份" width="100" />
                    <el-table-column prop="mrr" label="MRR" align="right">
                        <template #default="{ row }">¥{{ formatNum(row.mrr) }}</template>
                    </el-table-column>
                    <el-table-column prop="arr" label="ARR" align="right">
                        <template #default="{ row }">¥{{ formatNum(row.arr) }}</template>
                    </el-table-column>
                    <el-table-column prop="active_subscriptions" label="活跃订阅数" align="right" />
                </el-table>
                <el-empty v-else description="暂无趋势数据" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import reportApi from '@/api/report'

const loading = ref(false)
const loadingTrend = ref(false)
const loadingMrt = ref(false)
const trendPeriod = ref('monthly')

// Dashboard data
const dashboard = reactive({
    total_revenue: 0, month_revenue: 0, year_revenue: 0,
    mrr: 0, arr: 0, pending_amount: 0, total_plans: 0,
    mrr_breakdown: {},
    subscriptions: { total: 0, active: 0, grace: 0, expired: 0, canceled: 0 },
    customers: { total: 0, active: 0 },
})

const revenueTrend = ref([])
const mrrTrendData = ref([])

const analytics = reactive({
    subscriptions: { total: 0, active: 0, grace: 0, expired: 0, canceled: 0 },
})

const subAnalytics = reactive({
    by_period: [],
    by_status: {},
    cancel_trend: [],
    expiring_soon_30d: 0,
    avg_subscription_days: 0,
})

const planDistribution = ref([])
const ltv = reactive({
    total_customers: 0, total_revenue: 0, average_ltv: 0,
    max_ltv: 0, median_ltv: 0, tiers: {},
    top_customers: [],
})
const churnData = ref([])

// ── Helpers ──
function formatNum(n) {
    if (n === null || n === undefined) return '0'
    if (n >= 100000000) return (n / 100000000).toFixed(2) + '亿'
    if (n >= 10000) return (n / 10000).toFixed(2) + '万'
    return Number(n).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function periodLabel(p) {
    const map = { monthly: '月付', quarterly: '季付', semi_annually: '半年付', yearly: '年付' }
    return map[p] || p
}

function mrrBarWidth(val) {
    const max = Math.max(
        dashboard.mrr_breakdown?.monthly || 0,
        dashboard.mrr_breakdown?.quarterly || 0,
        dashboard.mrr_breakdown?.semi_annually || 0,
        dashboard.mrr_breakdown?.yearly || 0,
        1
    )
    return (val / max) * 100
}

function mrrColor(key) {
    const map = { monthly: '#409eff', quarterly: '#67c23a', semi_annually: '#e6a23c', yearly: '#f56c6c' }
    return map[key] || '#909399'
}

function trendBarHeight(val) {
    const max = Math.max(...revenueTrend.value.map(i => i.revenue || 0), 1)
    return Math.max(4, (val / max) * 180)
}

function formatPeriod(p) {
    if (!p) return ''
    if (p.length === 7) {
        const [y, m] = p.split('-')
        return y.slice(2) + '/' + m
    }
    return p.slice(5)
}

function churnTagType(rate) {
    if (rate > 10) return 'danger'
    if (rate > 5) return 'warning'
    return 'success'
}

// ── Data Fetching ──
async function fetchDashboard() {
    try {
        const { data: res } = await reportApi.dashboard()
        if (res.success) {
            Object.assign(dashboard, res.data)
            Object.assign(analytics, { subscriptions: res.data.subscriptions })
        }
    } catch { /* handled by interceptor */ }
}

async function fetchRevenueTrend() {
    loadingTrend.value = true
    try {
        const { data: res } = await reportApi.revenueTrend({
            period: trendPeriod.value,
            months: trendPeriod.value === 'monthly' ? 12 : 30,
        })
        if (res.success) {
            revenueTrend.value = res.data.trend || []
        }
    } catch { /* empty */ }
    finally { loadingTrend.value = false }
}

async function fetchMrrTrend() {
    loadingMrt.value = true
    try {
        const { data: res } = await reportApi.mrrTrend({ months: 12 })
        if (res.success) {
            mrrTrendData.value = res.data || []
        }
    } catch { /* empty */ }
    finally { loadingMrt.value = false }
}

async function fetchSubAnalytics() {
    try {
        const { data: res } = await reportApi.subscriptionAnalytics()
        if (res.success) {
            Object.assign(subAnalytics, res.data)
        }
    } catch { /* empty */ }
}

async function fetchPlanDistribution() {
    try {
        const { data: res } = await reportApi.planDistribution()
        if (res.success) {
            planDistribution.value = res.data || []
        }
    } catch { /* empty */ }
}

async function fetchLtv() {
    try {
        const { data: res } = await reportApi.customerLtv()
        if (res.success) {
            Object.assign(ltv, res.data)
        }
    } catch { /* empty */ }
}

async function fetchChurn() {
    try {
        const { data: res } = await reportApi.churnAnalysis()
        if (res.success) {
            churnData.value = res.data || []
        }
    } catch { /* empty */ }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([
        fetchDashboard(),
        fetchRevenueTrend(),
        fetchMrrTrend(),
        fetchSubAnalytics(),
        fetchPlanDistribution(),
        fetchLtv(),
        fetchChurn(),
    ])
    loading.value = false
}

onMounted(refreshAll)
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.ml-2 { margin-left: 8px; }

.metric-card {
    transition: transform .2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.metric-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}
.metric-value {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.3;
}
.metric-value.primary { color: #409eff; }
.metric-value.success { color: #67c23a; }
.metric-value.warning { color: #e6a23c; }
.metric-sub {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
    display: flex;
    align-items: center;
}

/* MRR Breakdown */
.mrr-row {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    gap: 8px;
}
.mrr-label {
    width: 60px;
    font-size: 13px;
    color: #606266;
    flex-shrink: 0;
}
.mrr-bar-bg {
    flex: 1;
    height: 20px;
    background: #f0f2f5;
    border-radius: 4px;
    overflow: hidden;
}
.mrr-bar {
    height: 100%;
    border-radius: 4px;
    transition: width .5s ease;
    min-width: 4px;
}
.mrr-value {
    width: 80px;
    text-align: right;
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    flex-shrink: 0;
}

/* Trend Chart */
.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    min-height: 240px;
    padding: 8px 0;
}
.trend-bar-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 0;
}
.trend-bar-wrapper {
    flex: 1;
    display: flex;
    align-items: flex-end;
    width: 100%;
}
.trend-bar {
    width: 100%;
    max-width: 36px;
    margin: 0 auto;
    background: linear-gradient(to top, #409eff, #79bbff);
    border-radius: 3px 3px 0 0;
    transition: height .4s ease;
    cursor: pointer;
    min-height: 4px;
}
.trend-bar:hover {
    opacity: .8;
}
.trend-label {
    font-size: 10px;
    color: #909399;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}
.trend-value {
    font-size: 10px;
    font-weight: 600;
    color: #303133;
    text-align: center;
}
.trend-growth {
    font-size: 9px;
}
.trend-growth.up { color: #67c23a; }
.trend-growth.down { color: #f56c6c; }

/* Section header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* LTV */
.tier-card {
    text-align: center;
}
.tier-value {
    font-size: 16px;
    font-weight: 600;
    color: #409eff;
}
.tier-count {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.text-danger { color: #f56c6c; }
</style>
