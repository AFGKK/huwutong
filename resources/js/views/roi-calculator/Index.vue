<template>
    <div class="roi-calculator-page">
        <el-card class="header-card">
            <div class="header-content">
                <h1>ROI 计算器</h1>
                <p class="subtitle">对比自建方案与 {{ platformName }} 平台方案的成本差异，了解您的投资回报率</p>
                <div class="currency-toggle">
                    <el-radio-group v-model="currency" @change="recalculate">
                        <el-radio-button value="CNY">¥ 人民币</el-radio-button>
                        <el-radio-button value="USD">$ 美元</el-radio-button>
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
                            <span>自建方案成本</span>
                            <el-tag type="danger">方案A</el-tag>
                        </div>
                    </template>

                    <el-form label-position="top" size="small">
                        <el-form-item label="开发人员年薪">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.developer_salary" :min="50000" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ currencySymbol }}/年</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="所需开发人数">
                            <el-slider v-model="params.developer_count" :min="1" :max="20" show-input />
                        </el-form-item>
                        <el-form-item label="开发周期">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.development_months" :min="1" :max="36" show-input />
                                <span class="suffix">个月</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="运维/DevOps 年成本">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.devops_cost" :min="0" :max="200000" :step="5000" show-input />
                                <span class="suffix">{{ currencySymbol }}/年</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="基础设施年费用（云服务器/数据库/带宽等）">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.infrastructure_cost" :min="0" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ currencySymbol }}/年</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="年维护成本">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.maintenance_yearly" :min="5" :max="50" show-input />
                                <span class="suffix">%</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="合规/安全年费用">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.compliance_cost" :min="0" :max="200000" :step="5000" show-input />
                                <span class="suffix">{{ currencySymbol }}/年</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="机会成本（开发延迟月损失）">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.opportunity_cost" :min="0" :max="500000" :step="10000" show-input />
                                <span class="suffix">{{ currencySymbol }}/月</span>
                            </div>
                        </el-form-item>
                    </el-form>

                </el-card>

                <el-card class="input-card" style="margin-top: 16px;">
                    <template #header>
                        <div class="card-header">
                            <span>{{ platformName }} 方案成本</span>
                            <el-tag type="success">方案B</el-tag>
                        </div>
                    </template>

                    <el-form label-position="top" size="small">
                        <el-form-item label="License 年费（每 Seat）">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.license_fee" :min="0" :max="5000" :step="50" show-input />
                                <span class="suffix">{{ currencySymbol }}/seat</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="Seat 数量">
                            <el-slider v-model="params.seat_count" :min="1" :max="10000" :step="10" show-input />
                        </el-form-item>
                        <el-form-item label="技术支持年费">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.support_fee" :min="0" :max="100000" :step="5000" show-input />
                                <span class="suffix">{{ currencySymbol }}/年</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="一次性部署费用">
                            <div class="input-with-suffix">
                                <el-slider v-model="params.setup_fee" :min="0" :max="50000" :step="1000" show-input />
                                <span class="suffix">{{ currencySymbol }}</span>
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
                            <div class="result-label">第一年节省</div>
                            <div class="result-value">{{ formatMoney(result.savings?.year1) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card highlight" :class="{ positive: savingsYearly > 0 }">
                            <div class="result-label">每年节省</div>
                            <div class="result-value">{{ formatMoney(result.savings?.yearly) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card highlight">
                            <div class="result-label">回本周期</div>
                            <div class="result-value break-even">{{ result.savings?.break_even_label }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" style="margin-top: 16px">
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">首年 ROI</div>
                            <div class="result-value">{{ result.roi?.year1 }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">每年 ROI</div>
                            <div class="result-value">{{ result.roi?.yearly }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="result-card">
                            <div class="result-label">5年 ROI</div>
                            <div class="result-value five-year">{{ result.roi?.five_year }}%</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 5年节省标语 -->
                <el-card class="savings-banner" shadow="hover">
                    <div class="banner-content">
                        <span class="banner-icon">💡</span>
                        <span class="banner-text">
                            使用 <strong>{{ platformName }}</strong>，5年可节省 <strong class="highlight-text">{{ formatMoney(result.savings?.five_year) }}</strong>，
                            相比自建方案节省 <strong class="highlight-text">{{ fiveYearPct }}%</strong>
                        </span>
                    </div>
                </el-card>

                <!-- 成本对比图 -->
                <el-card class="chart-card">
                    <template #header>
                        <span>5年成本对比</span>
                    </template>
                    <div class="chart-wrapper" ref="chartRef"></div>
                    <el-table :data="result.yearly_comparison || []" border size="small" style="margin-top: 16px">
                        <el-table-column prop="year" label="年份" width="60" />
                        <el-table-column label="自建成本" width="150">
                            <template #default="{ row }">{{ formatMoney(row.build_cost) }}</template>
                        </el-table-column>
                        <el-table-column label="本平台成本" width="150">
                            <template #default="{ row }">{{ formatMoney(row.platform_cost) }}</template>
                        </el-table-column>
                        <el-table-column label="节省" width="150">
                            <template #default="{ row }">
                                <span :class="row.savings >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(row.savings) }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="累计自建" width="150">
                            <template #default="{ row }">{{ formatMoney(row.cumulative_build) }}</template>
                        </el-table-column>
                        <el-table-column label="累计本平台" width="150">
                            <template #default="{ row }">{{ formatMoney(row.cumulative_platform) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 成本明细 -->
                <el-row :gutter="16" style="margin-top: 16px">
                    <el-col :span="12">
                        <el-card class="cost-breakdown">
                            <template #header>
                                <span class="text-danger">自建方案年成本明细</span>
                            </template>
                            <div v-for="(val, key) in result.build?.breakdown" :key="key" class="breakdown-row">
                                <span class="breakdown-label">{{ buildBreakdownLabel(key) }}</span>
                                <span class="breakdown-value">{{ formatMoney(val) }}</span>
                            </div>
                            <el-divider />
                            <div class="breakdown-row total">
                                <span class="breakdown-label">第一年总计</span>
                                <span class="breakdown-value">{{ formatMoney(result.build?.year1) }}</span>
                            </div>
                            <div class="breakdown-row total">
                                <span class="breakdown-label">之后每年</span>
                                <span class="breakdown-value">{{ formatMoney(result.build?.yearly) }}</span>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card class="cost-breakdown">
                            <template #header>
                                <span class="text-success">本平台方案年成本明细</span>
                            </template>
                            <div v-for="(val, key) in result.platform?.breakdown" :key="key" class="breakdown-row">
                                <span class="breakdown-label">{{ platformBreakdownLabel(key) }}</span>
                                <span class="breakdown-value">{{ formatMoney(val) }}</span>
                            </div>
                            <el-divider />
                            <div class="breakdown-row total">
                                <span class="breakdown-label">第一年总计</span>
                                <span class="breakdown-value">{{ formatMoney(result.platform?.year1) }}</span>
                            </div>
                            <div class="breakdown-row total">
                                <span class="breakdown-label">之后每年</span>
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
                <span>嵌入官网</span>
            </template>
            <p class="embed-desc">将此代码复制到您的网站，即可嵌入ROI计算器</p>
            <el-input v-model="embedCode" type="textarea" :rows="4" readonly />
            <el-button size="small" style="margin-top: 8px" @click="copyEmbedCode">复制代码</el-button>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import * as echarts from 'echarts'
import { calculateRoi, getRoiDefaults } from '@/api/roiCalculator'

const platformName = '互物通'
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

const currencySymbol = computed(() => currency.value === 'CNY' ? '¥' : '$')

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
    const num = typeof val === 'number' ? val.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : val
    return `${sym}${num}`
}

const breakdownLabels = {
    development: '开发成本',
    infrastructure: '基础设施',
    devops: '运维成本',
    compliance: '合规安全',
    maintenance: '维护成本',
    opportunity_cost: '机会成本',
    license_fee: 'License费用',
    support_fee: '技术支持',
    setup_fee: '部署费用',
}

function buildBreakdownLabel(key) { return breakdownLabels[key] || key }
function platformBreakdownLabel(key) { return breakdownLabels[key] || key }

async function calculate() {
    try {
        const res = await calculateRoi({ ...params.value, currency: currency.value })
        result.value = res.data || res
        await nextTick()
        renderChart()
    } catch { ElMessage.error('计算失败') }
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
    const years = comparison.map(r => `第${r.year}年`)

    chartInstance.setOption({
        tooltip: {
            trigger: 'axis',
            formatter: function(params) {
                let html = `<strong>${params[0].axisValue}</strong><br/>`
                params.forEach(p => {
                    html += `${p.marker} ${p.seriesName}: ${formatMoney(p.value)}<br/>`
                })
                return html
            }
        },
        legend: { data: ['自建方案', '本平台方案', '节省'], top: 0 },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: years },
        yAxis: {
            type: 'value',
            axisLabel: { formatter: v => currencySymbol.value + (v / 10000).toFixed(0) + '万' }
        },
        series: [
            {
                name: '自建方案',
                type: 'bar',
                data: comparison.map(r => r.build_cost),
                itemStyle: { color: '#f56c6c' },
                barGap: '10%',
            },
            {
                name: '本平台方案',
                type: 'bar',
                data: comparison.map(r => r.platform_cost),
                itemStyle: { color: '#67c23a' },
            },
            {
                name: '节省',
                type: 'line',
                data: comparison.map(r => r.savings),
                itemStyle: { color: '#409eff' },
                lineStyle: { type: 'dashed' },
                symbol: 'diamond',
            },
        ],
    })
}

function copyEmbedCode() {
    navigator.clipboard.writeText(embedCode.value)
    ElMessage.success('已复制嵌入代码')
}

// 监听参数变化自动重算
watch(() => params.value, () => calculate(), { deep: true })

onMounted(() => {
    loadDefaults()
})

// 窗口大小变化时重新渲染
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
    color: #409eff;
    font-size: 18px;
}
.result-value.five-year {
    color: #e6a23c;
}
.savings-banner {
    margin-top: 16px;
    background: linear-gradient(135deg, #ecf5ff 0%, #f0f9eb 100%);
}
.banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
}
.banner-icon {
    font-size: 28px;
}
.highlight-text {
    color: #409eff;
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
