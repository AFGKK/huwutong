<template>
    <div class="product-analytics-page">
        <div class="page-header">
            <h2>产品使用分析看板</h2>
            <div class="header-actions">
                <el-radio-group v-model="periodDays" @change="loadAll" size="small">
                    <el-radio-button :value="7">7天</el-radio-button>
                    <el-radio-button :value="30">30天</el-radio-button>
                    <el-radio-button :value="90">90天</el-radio-button>
                </el-radio-group>
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-loading v-model:loading="loading">
            <!-- 概要卡片 -->
            <el-row :gutter="16" class="mb-4">
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">产品数</div>
                        <div class="stat-value">{{ summary.total_products }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-active">
                        <div class="stat-label">总 License</div>
                        <div class="stat-value">{{ summary.total_licenses }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-active">
                        <div class="stat-label">活跃 License</div>
                        <div class="stat-value">{{ summary.active_licenses }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">新增({{ summary.period_days }}天)</div>
                        <div class="stat-value">{{ summary.new_licenses_period }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-warning">
                        <div class="stat-label">激活率</div>
                        <div class="stat-value">{{ summary.activation_rate }}%</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover" class="stat-info">
                        <div class="stat-label">设备数</div>
                        <div class="stat-value">{{ summary.total_devices }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-tabs v-model="activeTab">
                <!-- Tab 1: 畅销产品 -->
                <el-tab-pane label="畅销产品" name="ranking">
                    <el-card>
                        <el-table :data="productRanking" stripe v-loading="loadingRanking">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="product_name" label="产品名称" min-width="160" />
                            <el-table-column prop="version" label="版本" width="80" />
                            <el-table-column prop="total_licenses" label="总License" width="100" align="center" sortable />
                            <el-table-column prop="active_licenses" label="活跃" width="80" align="center" sortable />
                            <el-table-column prop="expired_licenses" label="已过期" width="80" align="center" sortable />
                            <el-table-column label="激活率" width="100" align="center" sortable>
                                <template #default="{ row }">
                                    <el-progress :percentage="row.activation_rate" :stroke-width="8" :width="80"
                                        :color="row.activation_rate > 70 ? '#67c23a' : row.activation_rate > 40 ? '#e6a23c' : '#f56c6c'" />
                                </template>
                            </el-table-column>
                            <el-table-column prop="total_devices" label="设备数" width="80" align="center" />
                            <el-table-column prop="total_events" label="事件数" width="100" align="center" sortable />
                        </el-table>
                    </el-card>
                </el-tab-pane>

                <!-- Tab 2: 增长趋势 -->
                <el-tab-pane label="增长趋势" name="trends">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>License 增长趋势</span></template>
                                <div class="chart-container">
                                    <div v-if="!licenseTrendData?.length" class="chart-empty">暂无数据</div>
                                    <div v-else class="trend-chart">
                                        <div class="bar-chart">
                                            <div v-for="(day, i) in licenseTrendData" :key="i" class="bar-group">
                                                <div class="bar" :style="{ height: barHeight(day.new_licenses, maxLicense) + 'px' }"
                                                    :title="day.date + ': ' + day.new_licenses + ' 新增'">
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
                                <template #header><span>激活趋势</span></template>
                                <div class="chart-container">
                                    <div v-if="!activationTrendData?.length" class="chart-empty">暂无数据</div>
                                    <div v-else class="trend-chart">
                                        <div class="bar-chart">
                                            <div v-for="(day, i) in activationTrendData" :key="i" class="bar-group">
                                                <div class="bar bar-activation" :style="{ height: barHeight(day.activations, maxActivations) + 'px' }"
                                                    :title="day.date + ': ' + day.activations + ' 激活'">
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
                                <template #header><span>产品月度趋势</span></template>
                                <el-table :data="productMonthlyTrend" stripe v-loading="loadingMonthlyTrend">
                                    <el-table-column prop="month" label="月份" width="100" />
                                    <el-table-column prop="total_new_licenses" label="新增License" width="110" align="center" />
                                    <el-table-column label="各产品新增" min-width="300">
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

                <!-- Tab 3: 区域增长 -->
                <el-tab-pane label="区域增长" name="regional">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>国家/地区分布</span></template>
                                <el-table :data="regionalCountries" stripe v-loading="loadingRegional">
                                    <el-table-column prop="country_code" label="代码" width="80" />
                                    <el-table-column prop="country_name" label="国家/地区" min-width="150" />
                                    <el-table-column label="事件数" width="100" align="center">
                                        <template #default="{ row }">{{ row.period_events }}</template>
                                    </el-table-column>
                                    <el-table-column prop="active_licenses" label="活跃License" width="110" align="center" />
                                    <el-table-column prop="city_count" label="城市数" width="70" align="center" />
                                    <el-table-column label="占比" width="100">
                                        <template #default="{ row }">
                                            <el-progress :percentage="row.share_percent" :stroke-width="6" />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>区域月度趋势</span></template>
                                <el-table :data="regionalTrendMonthly" stripe v-loading="loadingRegionalTrend">
                                    <el-table-column prop="month" label="月份" width="90" />
                                    <el-table-column prop="total_events" label="事件数" width="80" align="center" />
                                    <el-table-column label="Top国家" min-width="280">
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

                <!-- Tab 4: 功能模块使用率 -->
                <el-tab-pane label="功能模块" name="modules">
                    <el-card>
                        <div v-if="!moduleUsageData?.length" class="chart-empty">暂无模块数据（产品未配置模块）</div>
                        <div v-for="(product, pi) in moduleUsageData" :key="pi" class="mb-4">
                            <h4 class="module-product-title">{{ product.product_name }}</h4>
                            <el-table :data="product.modules" stripe>
                                <el-table-column type="index" label="#" width="50" />
                                <el-table-column prop="module_name" label="模块名称" min-width="160" />
                                <el-table-column prop="event_count" label="使用事件数" width="120" align="center" />
                                <el-table-column label="使用率" min-width="200">
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
import { ref, reactive, computed, onMounted } from 'vue';
import productAnalyticsApi from '@/api/productAnalytics';

const loading = ref(false);
const activeTab = ref('ranking');
const periodDays = ref(30);

// 概要
const summary = reactive({
    total_products: 0, total_licenses: 0, active_licenses: 0,
    new_licenses_period: 0, activation_rate: 0, total_devices: 0, period_days: 30,
});

// 畅销产品
const productRanking = ref([]);
const loadingRanking = ref(false);

// 增长趋势
const licenseTrendData = ref([]);
const activationTrendData = ref([]);
const productMonthlyTrend = ref([]);
const loadingMonthlyTrend = ref(false);

// 区域增长
const regionalCountries = ref([]);
const regionalTrendMonthly = ref([]);
const loadingRegional = ref(false);
const loadingRegionalTrend = ref(false);

// 模块使用率
const moduleUsageData = ref([]);

// 柱状图高度计算
const maxLicense = computed(() => Math.max(...licenseTrendData.value.map(d => d.new_licenses), 1));
const maxActivations = computed(() => Math.max(...activationTrendData.value.map(d => d.activations), 1));

function barHeight(val, max) {
    return Math.max(2, (val / max) * 120);
}

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    return parts.length >= 3 ? `${parts[1]}/${parts[2]}` : dateStr;
}

async function loadAll() {
    loading.value = true;
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
        ]);

        Object.assign(summary, summaryRes.data?.data || {});
        productRanking.value = rankingRes.data?.data || [];
        licenseTrendData.value = trendRes.data?.data || [];
        activationTrendData.value = activationRes.data?.data || [];
        productMonthlyTrend.value = monthlyRes.data?.data || [];
        regionalCountries.value = regionalRes.data?.data?.countries || [];
        regionalTrendMonthly.value = regionalTrendRes.data?.data?.monthly_trend || [];
        moduleUsageData.value = moduleRes.data?.data || [];
    } catch (err) {
        console.error('Failed to load analytics', err);
    } finally {
        loading.value = false;
    }
}

onMounted(loadAll);
</script>

<style scoped>
.product-analytics-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-info .stat-value { color: #409eff; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mr-1 { margin-right: 4px; }

.chart-container { min-height: 200px; }
.chart-empty { text-align: center; color: #c0c4cc; padding: 60px 0; }

.trend-chart { overflow-x: auto; padding-top: 10px; }
.bar-chart { display: flex; align-items: flex-end; gap: 3px; min-height: 160px; }
.bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 20px; }
.bar { width: 100%; max-width: 30px; background: linear-gradient(to top, #409eff, #79bbff); border-radius: 3px 3px 0 0; min-height: 2px; transition: height 0.3s; position: relative; }
.bar-activation { background: linear-gradient(to top, #67c23a, #95d475); }
.bar-value { position: absolute; top: -18px; font-size: 10px; color: #606266; white-space: nowrap; }
.bar-label { font-size: 9px; color: #909399; transform: rotate(-45deg); margin-top: 4px; white-space: nowrap; }

.module-product-title { margin: 0 0 8px; font-size: 15px; color: #303133; }
.monthly-product-item, .region-item { display: inline-block; margin-bottom: 4px; }
</style>
