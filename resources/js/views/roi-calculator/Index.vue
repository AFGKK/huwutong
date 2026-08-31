<template>
    <div class="roi-calculator-page">
        <el-card class="header-card">
            <div class="header-content">
                <h1>{{ t('roi_calculator_page.title') }}</h1>
                <p class="subtitle">{{ t('roi_calculator_page.subtitle', { platform: platformName }) }}</p>
                <div class="currency-toggle">
                    <el-radio-group v-model="currency" @change="recalculate">
                        <el-radio-button value="CNY">{{ t('roi_calculator_page.currency.cny') }}</el-radio-button>
                        <el-radio-button value="USD">{{ t('roi_calculator_page.currency.usd') }}</el-radio-button>
                    </el-radio-group>
                </div>
            </div>
        </el-card>

        <el-row :gutter="24">
            <!-- 输入区域 -->
            <el-col :span="24" :lg="10">
                <el-card class="input-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('roi_calculator_page.build.title') }}</span>
                            <el-tag type="danger">{{ t('roi_calculator_page.plan_a') }}</el-tag>
                        </div>
                    </template>

                    <el-form label-position="top" size="small">
                        <el-form-item :label="t('roi_calculator_page.build.developer_salary')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.developer_salary" :min="50000" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_year', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.developer_count')">
                            <el-slider v-model="params.developer_count" :min="1" :max="20" show-input />
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.development_months')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.development_months" :min="1" :max="36" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.months') }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.devops_cost')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.devops_cost" :min="0" :max="200000" :step="5000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_year', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.infrastructure_cost')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.infrastructure_cost" :min="0" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_year', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.maintenance_yearly')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.maintenance_yearly" :min="5" :max="50" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.percent') }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.compliance_cost')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.compliance_cost" :min="0" :max="200000" :step="5000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_year', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.build.opportunity_cost')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.opportunity_cost" :min="0" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_month', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                    </el-form>

                </el-card>

                <el-card class="input-card" style="margin-top: 16px;">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('roi_calculator_page.platform.title', { platform: platformName }) }}</span>
                            <el-tag type="success">{{ t('roi_calculator_page.plan_b') }}</el-tag>
                        </div>
                    </template>

                    <el-form label-position="top" size="small">
                        <el-form-item :label="t('roi_calculator_page.platform.license_fee')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.license_fee" :min="0" :max="5000" :step="50" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_seat', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.platform.seat_count')">
                            <el-slider v-model="params.seat_count" :min="1" :max="10000" :step="10" show-input />
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.platform.support_fee')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.support_fee" :min="0" :max="100000" :step="5000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.per_year', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('roi_calculator_page.platform.setup_fee')">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.setup_fee" :min="0" :max="50000" :step="1000" show-input />
                                <span class="suffix">{{ t('roi_calculator_page.units.plain', { symbol: currencySymbol }) }}</span>
                            </div>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <!-- 结果区域 -->
            <el-col :span="24" :lg="14">
                <!-- 核心指标 -->
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card highlight" :class="{ positive: savingsYear1 > 0 }">
                            <div class="result-label">{{ t('roi_calculator_page.results.savings_year1') }}</div>
                            <div class="result-value">{{ formatMoney(result.savings?.year1) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card highlight" :class="{ positive: savingsYearly > 0 }">
                            <div class="result-label">{{ t('roi_calculator_page.results.savings_yearly') }}</div>
                            <div class="result-value">{{ formatMoney(result.savings?.yearly) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card highlight">
                            <div class="result-label">{{ t('roi_calculator_page.results.break_even') }}</div>
                            <div class="result-value break-even">{{ result.savings?.break_even_label }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" style="margin-top: 16px">
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">{{ t('roi_calculator_page.results.roi_year1') }}</div>
                            <div class="result-value">{{ result.roi?.year1 }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">{{ t('roi_calculator_page.results.roi_yearly') }}</div>
                            <div class="result-value">{{ result.roi?.yearly }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">{{ t('roi_calculator_page.results.roi_five_year') }}</div>
                            <div class="result-value five-year">{{ result.roi?.five_year }}%</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 5年节省摘要 -->
                <el-card class="savings-banner" shadow="hover">
                    <div class="banner-content">
                        <span class="banner-text">
                            {{ t('roi_calculator_page.banner', {
                                platform: platformName,
                                amount: formatMoney(result.savings?.five_year),
                                pct: fiveYearPct,
                            }) }}
                        </span>
                    </div>
                </el-card>

                <!-- 成本对比图 -->
                <el-card class="chart-card">
                    <template #header>
                        <span>{{ t('roi_calculator_page.chart.title') }}</span>
                    </template>
                    <div class="chart-wrapper" ref="chartRef"></div>
                    <el-table :data="result.yearly_comparison || []" border size="small" style="margin-top: 16px">
                        <el-table-column prop="year" :label="t('roi_calculator_page.table.year')" width="60" />
                        <el-table-column :label="t('roi_calculator_page.table.build_cost')" width="150">
                            <template #default="{ row }">{{ formatMoney(row.build_cost) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('roi_calculator_page.table.platform_cost')" width="150">
                            <template #default="{ row }">{{ formatMoney(row.platform_cost) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('roi_calculator_page.table.savings')" width="150">
                            <template #default="{ row }">
                                <span :class="row.savings >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(row.savings) }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('roi_calculator_page.table.cumulative_build')" width="150">
                            <template #default="{ row }">{{ formatMoney(row.cumulative_build) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('roi_calculator_page.table.cumulative_platform')" width="150">
                            <template #default="{ row }">{{ formatMoney(row.cumulative_platform) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 成本明细 -->
                <el-row :gutter="16" style="margin-top: 16px">
                    <el-col :span="12">
                        <el-card class="cost-breakdown">
                            <template #header>
                                <span class="text-danger">{{ t('roi_calculator_page.build.breakdown_title') }}</span>
                            </template>
                            <div v-for="(val, key) in result.build?.breakdown" :key="key" class="breakdown-row">
                                <span class="breakdown-label">{{ breakdownLabel(key) }}</span>
                                <span class="breakdown-value">{{ formatMoney(val) }}</span>
                            </div>
                            <el-divider />
                            <div class="breakdown-row total">
                                <span class="breakdown-label">{{ t('roi_calculator_page.breakdown.year1_total') }}</span>
                                <span class="breakdown-value">{{ formatMoney(result.build?.year1) }}</span>
                            </div>
                            <div class="breakdown-row total">
                                <span class="breakdown-label">{{ t('roi_calculator_page.breakdown.yearly_after') }}</span>
                                <span class="breakdown-value">{{ formatMoney(result.build?.yearly) }}</span>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card class="cost-breakdown">
                            <template #header>
                                <span class="text-success">{{ t('roi_calculator_page.platform.breakdown_title') }}</span>
                            </template>
                            <div v-for="(val, key) in result.platform?.breakdown" :key="key" class="breakdown-row">
                                <span class="breakdown-label">{{ breakdownLabel(key) }}</span>
                                <span class="breakdown-value">{{ formatMoney(val) }}</span>
                            </div>
                            <el-divider />
                            <div class="breakdown-row total">
                                <span class="breakdown-label">{{ t('roi_calculator_page.breakdown.year1_total') }}</span>
                                <span class="breakdown-value">{{ formatMoney(result.platform?.year1) }}</span>
                            </div>
                            <div class="breakdown-row total">
                                <span class="breakdown-label">{{ t('roi_calculator_page.breakdown.yearly_after') }}</span>
                                <span class="breakdown-value">{{ formatMoney(result.platform?.yearly) }}</span>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-col>
        </el-row>

        <!-- 嵌入代码 -->
        <el-card class="embed-card" style="margin-top: 24px">
            <template #header>
                <span>{{ t('roi_calculator_page.embed.title') }}</span>
            </template>
            <p class="embed-desc">{{ t('roi_calculator_page.embed.desc') }}</p>
            <el-input v-model="embedCode" type="textarea" :rows="4" readonly />
            <el-button size="small" style="margin-top: 8px" @click="copyEmbedCode">{{ t('actions.copy') }}</el-button>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import * as echarts from 'echarts'
import { calculateRoi, getRoiDefaults } from '@/api/roiCalculator'

const { t, locale } = useI18n()

const currency = ref('CNY')
const chartRef = ref(null)
let chartInstance = null

const params = ref({
    developer_salary: 300000,
    developer_count: 2,
    development_months: 6,
    devops_cost: 50000,
    infrastructure_cost: 80000,
    maintenance_yearly: 20,
    compliance_cost: 30000,
    opportunity_cost: 60000,
    license_fee: 1200,
    seat_count: 50,
    support_fee: 20000,
    setup_fee: 10000,
})

const result = ref({})

const platformName = computed(() => t('app_name'))
const currencySymbol = computed(() => currency.value === 'CNY' ? '¥' : '$')
const numLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'))

const breakdownKeys = [
    'development', 'infrastructure', 'devops', 'compliance',
    'maintenance', 'opportunity_cost', 'license_fee', 'support_fee', 'setup_fee',
]

const breakdownLabels = computed(() => {
    const map = {}
    breakdownKeys.forEach((key) => {
        map[key] = t(`roi_calculator_page.breakdown.${key}`)
    })
    return map
})

const savingsYear1 = computed(() => result.value.savings?.year1 || 0)
const savingsYearly = computed(() => result.value.savings?.yearly || 0)
const fiveYearPct = computed(() => {
    const fiveYear = result.value.savings?.five_year || 0
    const fiveYearBuild = result.value.yearly_comparison?.[4]?.cumulative_build || 0
    return fiveYearBuild > 0 ? ((fiveYear / fiveYearBuild) * 100).toFixed(1) : 0
})

const embedCode = computed(() => {
    return `<iframe src="${window.location.origin}/embed/roi-calculator" width="100%" height="800" frameborder="0"></iframe>`
})

function formatMoney(val) {
    if (val === undefined || val === null) return '-'
    const sym = currencySymbol.value
    const num = typeof val === 'number'
        ? val.toLocaleString(numLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : val
    return `${sym}${num}`
}

function breakdownLabel(key) {
    return breakdownLabels.value[key] || key
}

async function calculate() {
    try {
        const res = await calculateRoi({ ...params.value, currency: currency.value })
        result.value = res.data || res
        await nextTick()
        renderChart()
    } catch {
        ElMessage.error(t('roi_calculator_page.messages.calculate_failed'))
    }
}

function recalculate() {
    calculate()
}

async function loadDefaults() {
    try {
        const res = await getRoiDefaults(currency.value)
        const data = res.data || res
        const d = data.defaults
        if (d) {
            Object.assign(params.value, {
                developer_salary: d.developer_salary,
                developer_count: d.developer_count,
                development_months: d.development_months,
                devops_cost: d.devops_cost,
                infrastructure_cost: d.infrastructure_cost,
                maintenance_yearly: d.maintenance_yearly,
                compliance_cost: d.compliance_cost,
                opportunity_cost: d.opportunity_cost,
                license_fee: d.license_fee,
                seat_count: d.seat_count,
                support_fee: d.support_fee,
                setup_fee: d.setup_fee,
            })
        }
    } catch { /* use defaults */ }
    await calculate()
}

function renderChart() {
    if (!chartRef.value) return
    if (!chartInstance) {
        chartInstance = echarts.init(chartRef.value)
    }

    const comparison = result.value.yearly_comparison || []
    const years = comparison.map((r) => t('roi_calculator_page.units.year_n', { n: r.year }))
    const chartBuild = t('roi_calculator_page.chart.build')
    const chartPlatform = t('roi_calculator_page.chart.platform')
    const chartSavings = t('roi_calculator_page.chart.savings')

    chartInstance.setOption({
        tooltip: {
            trigger: 'axis',
            formatter(params) {
                let html = `<strong>${params[0].axisValue}</strong><br/>`
                params.forEach((p) => {
                    html += `${p.marker} ${p.seriesName}: ${formatMoney(p.value)}<br/>`
                })
                return html
            },
        },
        legend: { data: [chartBuild, chartPlatform, chartSavings], top: 0 },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: years },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: (v) => t('roi_calculator_page.units.chart_axis', {
                    symbol: currencySymbol.value,
                    n: (v / 10000).toFixed(0),
                }),
            },
        },
        series: [
            {
                name: chartBuild,
                type: 'bar',
                data: comparison.map((r) => r.build_cost),
                itemStyle: { color: '#f56c6c' },
                barGap: '10%',
            },
            {
                name: chartPlatform,
                type: 'bar',
                data: comparison.map((r) => r.platform_cost),
                itemStyle: { color: '#67c23a' },
            },
            {
                name: chartSavings,
                type: 'line',
                data: comparison.map((r) => r.savings),
                itemStyle: { color: '#0f172a' },
                lineStyle: { type: 'dashed' },
                symbol: 'diamond',
            },
        ],
    })
}

function copyEmbedCode() {
    navigator.clipboard.writeText(embedCode.value)
    ElMessage.success(t('roi_calculator_page.messages.embed_copied'))
}

watch(() => params.value, () => calculate(), { deep: true })
watch(locale, () => nextTick(() => renderChart()))

onMounted(() => {
    loadDefaults()
})

window.addEventListener('resize', () => {
    chartInstance?.resize()
})
</script>

<style scoped>
.roi-calculator-page {
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
}
.header-card {
    margin-bottom: 24px;
    text-align: center;
}
.header-content h1 {
    font-size: 28px;
    margin: 0 0 8px;
    color: #303133;
}
.subtitle {
    color: #909399;
    font-size: 14px;
    margin: 0 0 16px;
}
.currency-toggle {
    display: flex;
    justify-content: center;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
}
.input-card {
    margin-bottom: 24px;
}
.input-with-suffix {
    display: flex;
    align-items: center;
    gap: 12px;
}
.input-with-suffix .el-slider {
    flex: 1;
}
.suffix {
    white-space: nowrap;
    color: #909399;
    font-size: 12px;
    min-width: 50px;
}
.result-card {
    text-align: center;
    margin-bottom: 0;
}
.result-card .result-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 8px;
}
.result-card .result-value {
    font-size: 22px;
    font-weight: bold;
    color: #303133;
}
.result-card.highlight.positive .result-value {
    color: #67c23a;
}
.result-value.break-even {
    color: #0f172a;
    font-size: 18px;
}
.result-value.five-year {
    color: #e6a23c;
}
.savings-banner {
    margin-top: 16px;
    background: linear-gradient(135deg, #f1f5f9 0%, #f0f9eb 100%);
}
.banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
}
.highlight-text {
    color: #0f172a;
    font-size: 18px;
}
.chart-card {
    margin-top: 16px;
}
.chart-wrapper {
    height: 350px;
}
.cost-breakdown {
    height: 100%;
}
.breakdown-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
}
.breakdown-row.total {
    font-weight: bold;
    font-size: 14px;
}
.breakdown-value {
    font-family: monospace;
}
.text-success { color: #67c23a; font-weight: bold; }
.text-danger { color: #f56c6c; }
.embed-card {
    margin-top: 24px;
}
.embed-desc {
    color: #909399;
    font-size: 13px;
    margin: 0 0 8px;
}
</style>
