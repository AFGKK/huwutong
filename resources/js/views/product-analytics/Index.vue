<template>
    <div class="product-analytics-page">
        <div class="page-header">
            <h2>{{ t('product_analytics_page.title') }}</h2>
            <div class="header-actions">
                <el-radio-group v-model="periodDays" @change="loadAll" size="small">
                    <el-radio-button :value="7">{{ t('product_analytics_page.days_n', { n: 7 }) }}</el-radio-button>
                    <el-radio-button :value="30">{{ t('product_analytics_page.days_n', { n: 30 }) }}</el-radio-button>
                    <el-radio-button :value="90">{{ t('product_analytics_page.days_n', { n: 90 }) }}</el-radio-button>
                </el-radio-group>
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('actions.refresh') }}
                </el-button>
            </div>
        </div>

        <el-loading v-model:loading="loading">
            <el-row :gutter="16" class="mb-4">
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">{{ t('product_analytics_page.stats.products') }}</div>
                        <div class="stat-value">{{ summary.total_products }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-active">
                        <div class="stat-label">{{ t('product_analytics_page.stats.total_licenses') }}</div>
                        <div class="stat-value">{{ summary.total_licenses }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-active">
                        <div class="stat-label">{{ t('product_analytics_page.stats.active_licenses') }}</div>
                        <div class="stat-value">{{ summary.active_licenses }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">{{ t('product_analytics_page.stats.new_period', { n: summary.period_days }) }}</div>
                        <div class="stat-value">{{ summary.new_licenses_period }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-warning">
                        <div class="stat-label">{{ t('product_analytics_page.stats.activation_rate') }}</div>
                        <div class="stat-value">{{ summary.activation_rate }}%</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-info">
                        <div class="stat-label">{{ t('product_analytics_page.stats.devices') }}</div>
                        <div class="stat-value">{{ summary.total_devices }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-tabs v-model="activeTab">
                <el-tab-pane :label="t('product_analytics_page.tabs.ranking')" name="ranking">
                    <el-card>
                        <el-table :data="productRanking" stripe v-loading="loadingRanking">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="product_name" :label="t('product_analytics_page.cols.product')" min-width="160" />
                            <el-table-column prop="version" :label="t('product_analytics_page.cols.version')" width="80" />
                            <el-table-column prop="total_licenses" :label="t('product_analytics_page.cols.total_licenses')" width="100" align="center" sortable />
                            <el-table-column prop="active_licenses" :label="t('product_analytics_page.cols.active')" width="80" align="center" sortable />
                            <el-table-column prop="expired_licenses" :label="t('product_analytics_page.cols.expired')" width="80" align="center" sortable />
                            <el-table-column :label="t('product_analytics_page.cols.activation_rate')" width="100" align="center" sortable>
                                <template #default="{ row }">
                                    <el-progress :percentage="row.activation_rate" :stroke-width="8" :width="80"
                                        :color="row.activation_rate > 70 ? '#67c23a' : row.activation_rate > 40 ? '#e6a23c' : '#f56c6c'" />
                                </template>
                            </el-table-column>
                            <el-table-column prop="total_devices" :label="t('product_analytics_page.cols.devices')" width="80" align="center" />
                            <el-table-column prop="total_events" :label="t('product_analytics_page.cols.events')" width="100" align="center" sortable />
                        </el-table>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane :label="t('product_analytics_page.tabs.trends')" name="trends">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>{{ t('product_analytics_page.license_trend') }}</span></template>
                                <div class="chart-container">
                                    <div v-if="!licenseTrendData?.length" class="chart-empty">{{ t('messages.no_data') }}</div>
                                    <div v-else class="trend-chart">
                                        <div class="bar-chart">
                                            <div v-for="(day, i) in licenseTrendData" :key="i" class="bar-group">
                                                <div class="bar" :style="{ height: barHeight(day.new_licenses, maxLicense) + 'px' }"
                                                    :title="t('product_analytics_page.bar_new', { date: day.date, n: day.new_licenses })">
                                                    <span v-if="day.new_licenses > 0" class="bar-value">{{ day.new_licenses }}</span>
                                                </div>
                                                <div class="bar-label">{{ formatShortDate(day.date) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>{{ t('product_analytics_page.activation_trend') }}</span></template>
                                <div class="chart-container">
                                    <div v-if="!activationTrendData?.length" class="chart-empty">{{ t('messages.no_data') }}</div>
                                    <div v-else class="trend-chart">
                                        <div class="bar-chart">
                                            <div v-for="(day, i) in activationTrendData" :key="i" class="bar-group">
                                                <div class="bar bar-activation" :style="{ height: barHeight(day.activations, maxActivations) + 'px' }"
                                                    :title="t('product_analytics_page.bar_activation', { date: day.date, n: day.activations })">
                                                    <span v-if="day.activations > 0" class="bar-value">{{ day.activations }}</span>
                                                </div>
                                                <div class="bar-label">{{ formatShortDate(day.date) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="16" class="mt-4">
                        <el-col :span="24">
                            <el-card>
                                <template #header><span>{{ t('product_analytics_page.monthly_trend') }}</span></template>
                                <el-table :data="productMonthlyTrend" stripe v-loading="loadingMonthlyTrend">
                                    <el-table-column prop="month" :label="t('product_analytics_page.cols.month')" width="100" />
                                    <el-table-column prop="total_new_licenses" :label="t('product_analytics_page.cols.new_licenses')" width="110" align="center" />
                                    <el-table-column :label="t('product_analytics_page.cols.by_product')" min-width="300">
                                        <template #default="{ row }">
                                            <span v-for="(p, i) in row.products" :key="i" class="monthly-product-item">
                                                <el-tag size="small" class="mr-1">{{ p.product_name }}: +{{ p.new_licenses }}</el-tag>
                                            </span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <el-tab-pane :label="t('product_analytics_page.tabs.regional')" name="regional">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>{{ t('product_analytics_page.country_dist') }}</span></template>
                                <el-table :data="regionalCountries" stripe v-loading="loadingRegional">
                                    <el-table-column prop="country_code" :label="t('product_analytics_page.cols.code')" width="80" />
                                    <el-table-column prop="country_name" :label="t('product_analytics_page.cols.country')" min-width="150" />
                                    <el-table-column :label="t('product_analytics_page.cols.events')" width="100" align="center">
                                        <template #default="{ row }">{{ row.period_events }}</template>
                                    </el-table-column>
                                    <el-table-column prop="active_licenses" :label="t('product_analytics_page.cols.active_licenses')" width="110" align="center" />
                                    <el-table-column prop="city_count" :label="t('product_analytics_page.cols.cities')" width="70" align="center" />
                                    <el-table-column :label="t('product_analytics_page.cols.share')" width="100">
                                        <template #default="{ row }">
                                            <el-progress :percentage="row.share_percent" :stroke-width="6" />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>{{ t('product_analytics_page.regional_monthly') }}</span></template>
                                <el-table :data="regionalTrendMonthly" stripe v-loading="loadingRegionalTrend">
                                    <el-table-column prop="month" :label="t('product_analytics_page.cols.month')" width="90" />
                                    <el-table-column prop="total_events" :label="t('product_analytics_page.cols.events')" width="80" align="center" />
                                    <el-table-column :label="t('product_analytics_page.cols.top_countries')" min-width="280">
                                        <template #default="{ row }">
                                            <span v-for="(c, i) in row.countries?.slice(0, 5)" :key="i" class="region-item">
                                                <el-tag size="small" class="mr-1">{{ c.country_code }}: {{ c.event_count }}</el-tag>
                                            </span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <el-tab-pane :label="t('product_analytics_page.tabs.modules')" name="modules">
                    <el-card>
                        <div v-if="!moduleUsageData?.length" class="chart-empty">{{ t('product_analytics_page.no_modules') }}</div>
                        <div v-for="(product, pi) in moduleUsageData" :key="pi" class="mb-4">
                            <h4 class="module-product-title">{{ product.product_name }}</h4>
                            <el-table :data="product.modules" stripe>
                                <el-table-column type="index" label="#" width="50" />
                                <el-table-column prop="module_name" :label="t('product_analytics_page.cols.module')" min-width="160" />
                                <el-table-column prop="event_count" :label="t('product_analytics_page.cols.usage_events')" width="120" align="center" />
                                <el-table-column :label="t('product_analytics_page.cols.usage_rate')" min-width="200">
                                    <template #default="{ row }">
                                        <el-progress :percentage="row.usage_rate" :stroke-width="12"
                                            :color="row.usage_rate > 60 ? '#67c23a' : row.usage_rate > 30 ? '#e6a23c' : '#909399'" />
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-card>
                </el-tab-pane>
            </el-tabs>
        </el-loading>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import productAnalyticsApi from '@/api/productAnalytics'

const { t } = useI18n()

const loading = ref(false)
const activeTab = ref('ranking')
const periodDays = ref(30)

const summary = reactive({
    total_products: 0, total_licenses: 0, active_licenses: 0,
    new_licenses_period: 0, activation_rate: 0, total_devices: 0, period_days: 30,
})

const productRanking = ref([])
const loadingRanking = ref(false)

const licenseTrendData = ref([])
const activationTrendData = ref([])
const productMonthlyTrend = ref([])
const loadingMonthlyTrend = ref(false)

const regionalCountries = ref([])
const regionalTrendMonthly = ref([])
const loadingRegional = ref(false)
const loadingRegionalTrend = ref(false)

const moduleUsageData = ref([])

const maxLicense = computed(() => Math.max(...licenseTrendData.value.map(d => d.new_licenses), 1))
const maxActivations = computed(() => Math.max(...activationTrendData.value.map(d => d.activations), 1))

function barHeight(val, max) {
    return Math.max(2, (val / max) * 120)
}

function formatShortDate(dateStr) {
    if (!dateStr) return ''
    const parts = dateStr.split('-')
    return parts.length >= 3 ? `${parts[1]}/${parts[2]}` : dateStr
}

async function loadAll() {
    loading.value = true
    try {
        const [summaryRes, rankingRes, trendRes, activationRes, monthlyRes,
            regionalRes, regionalTrendRes, moduleRes] = await Promise.all([
            productAnalyticsApi.getSummary({ days: periodDays.value }),
            productAnalyticsApi.getProductRanking(),
            productAnalyticsApi.getLicenseTrend({ days: periodDays.value }),
            productAnalyticsApi.getActivationTrend({ days: periodDays.value }),
            productAnalyticsApi.getProductMonthlyTrend({ months: periodDays.value > 90 ? 12 : 6 }),
            productAnalyticsApi.getRegionalGrowth({ days: periodDays.value }),
            productAnalyticsApi.getRegionalTrend({ months: periodDays.value > 90 ? 12 : 6 }),
            productAnalyticsApi.getModuleUsage(),
        ])

        Object.assign(summary, summaryRes.data?.data || {})
        productRanking.value = rankingRes.data?.data || []
        licenseTrendData.value = trendRes.data?.data || []
        activationTrendData.value = activationRes.data?.data || []
        productMonthlyTrend.value = monthlyRes.data?.data || []
        regionalCountries.value = regionalRes.data?.data?.countries || []
        regionalTrendMonthly.value = regionalTrendRes.data?.data?.monthly_trend || []
        moduleUsageData.value = moduleRes.data?.data || []
    } catch (err) {
        console.error('Failed to load analytics', err)
    } finally {
        loading.value = false
    }
}

onMounted(loadAll)
</script>

<style scoped>
.product-analytics-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-info .stat-value { color: #0f172a; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mr-1 { margin-right: 4px; }

.chart-container { min-height: 200px; }
.chart-empty { text-align: center; color: #c0c4cc; padding: 60px 0; }

.trend-chart { overflow-x: auto; padding-top: 10px; }
.bar-chart { display: flex; align-items: flex-end; gap: 3px; min-height: 160px; }
.bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 20px; }
.bar { width: 100%; max-width: 30px; background: linear-gradient(to top, #0f172a, #94a3b8); border-radius: 3px 3px 0 0; min-height: 2px; transition: height 0.3s; position: relative; }
.bar-activation { background: linear-gradient(to top, #67c23a, #95d475); }
.bar-value { position: absolute; top: -18px; font-size: 10px; color: #606266; white-space: nowrap; }
.bar-label { font-size: 9px; color: #909399; transform: rotate(-45deg); margin-top: 4px; white-space: nowrap; }

.module-product-title { margin: 0 0 8px; font-size: 15px; color: #303133; }
.monthly-product-item, .region-item { display: inline-block; margin-bottom: 4px; }
</style>
