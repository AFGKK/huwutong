<template>
    <div class="ecommerce-analytics-page">
        <div class="page-header">
            <h2>电商数据分析报表</h2>
            <div class="header-actions">
                <el-radio-group v-model="period" @change="loadAll" size="small">
                    <el-radio-button value="7d">7天</el-radio-button>
                    <el-radio-button value="30d">30天</el-radio-button>
                    <el-radio-button value="90d">90天</el-radio-button>
                    <el-radio-button value="1y">1年</el-radio-button>
                </el-radio-group>
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
                <el-dropdown v-if="activeTab === 'sales'" @command="handleExport">
                    <el-button>
                        <el-icon><Download /></el-icon> 导出CSV
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="sales_trend">销售趋势</el-dropdown-item>
                            <el-dropdown-item command="product_ranking">热销商品</el-dropdown-item>
                            <el-dropdown-item command="payment_channels">支付渠道</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <!-- Tab 1: 总览 -->
            <el-tab-pane label="总览" name="overview">
                <el-loading v-model:loading="loadingOverview">
                    <!-- 概要卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-label">总收入</div>
                                <div class="stat-value">¥{{ formatNum(summary.total_revenue) }}</div>
                                <div class="stat-change" :class="summary.revenue_growth >= 0 ? 'up' : 'down'">
                                    环比 {{ summary.revenue_growth >= 0 ? '+' : '' }}{{ summary.revenue_growth }}%
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover" class="stat-active">
                                <div class="stat-label">订单数</div>
                                <div class="stat-value">{{ summary.total_orders }}</div>
                                <div class="stat-change" :class="summary.order_growth >= 0 ? 'up' : 'down'">
                                    环比 {{ summary.order_growth >= 0 ? '+' : '' }}{{ summary.order_growth }}%
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover" class="stat-info">
                                <div class="stat-label">客户数</div>
                                <div class="stat-value">{{ summary.total_customers }}</div>
                                <div class="stat-change">新增 {{ summary.new_customers }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover" class="stat-warning">
                                <div class="stat-label">平均客单价</div>
                                <div class="stat-value">¥{{ formatNum(summary.avg_order_value) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 同比/环比 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="8">
                            <el-card shadow="hover" size="small">
                                <template #header>当前周期</template>
                                <div v-if="comparison.current" class="compact-stat">
                                    <div>收入: <strong>¥{{ formatNum(comparison.current.revenue) }}</strong></div>
                                    <div>订单: <strong>{{ comparison.current.orders }}</strong></div>
                                    <div>客单价: <strong>¥{{ formatNum(comparison.current.avg_order) }}</strong></div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="hover" size="small">
                                <template #header>上一周期</template>
                                <div v-if="comparison.previous_period" class="compact-stat">
                                    <div>收入: <strong>¥{{ formatNum(comparison.previous_period.revenue) }}</strong></div>
                                    <div>订单: <strong>{{ comparison.previous_period.orders }}</strong></div>
                                    <div>客单价: <strong>¥{{ formatNum(comparison.previous_period.avg_order) }}</strong></div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="hover" size="small">
                                <template #header>同比去年同期</template>
                                <div v-if="comparison.year_ago" class="compact-stat">
                                    <div>收入: <strong>¥{{ formatNum(comparison.year_ago.revenue) }}</strong></div>
                                    <div>订单: <strong>{{ comparison.year_ago.orders }}</strong></div>
                                    <div>环比: <strong :class="comparison.chain_growth >= 0 ? 'text-up' : 'text-down'">
                                        {{ comparison.chain_growth >= 0 ? '+' : '' }}{{ comparison.chain_growth }}%
                                    </strong></div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 销售预测 -->
                    <el-card class="mb-4" v-if="forecastData">
                        <template #header>
                            <span>销售预测 (未来30天)
                                <el-tag :type="forecastData.confidence === 'high' ? 'success' : forecastData.confidence === 'medium' ? 'warning' : 'info'"
                                    size="small" class="ml-2">
                                    {{ forecastData.confidence === 'high' ? '高可信度' : forecastData.confidence === 'medium' ? '中可信度' : '低可信度' }}
                                </el-tag>
                                <span v-if="forecastData.trend_direction === 'up'" class="text-up ml-2">↑ 上升趋势</span>
                                <span v-else class="text-down ml-2">↓ 下降趋势</span>
                            </span>
                        </template>
                        <div class="forecast-summary">
                            <span>预测期内总收入: <strong>¥{{ formatNum(forecastData.total_predicted_revenue) }}</strong></span>
                            <span class="ml-4">日均趋势: <strong>{{ forecastData.daily_trend_rate >= 0 ? '+' : '' }}{{ forecastData.daily_trend_rate }}</strong></span>
                        </div>
                    </el-card>
                </el-loading>
            </el-tab-pane>

            <!-- Tab 2: 销售趋势 -->
            <el-tab-pane label="销售趋势" name="sales">
                <el-card>
                    <template #header>
                        <div class="card-header-flex">
                            <span>日销售趋势</span>
                            <el-button size="small" @click="handleExport('sales_trend')">
                                <el-icon><Download /></el-icon> 导出CSV
                            </el-button>
                        </div>
                    </template>
                    <div class="chart-container">
                        <div v-if="!salesTrend?.length" class="chart-empty">暂无销售数据</div>
                        <div v-else class="dual-chart">
                            <!-- 收入柱状图 -->
                            <div class="bar-chart">
                                <div v-for="(day, i) in salesTrend" :key="i" class="bar-group">
                                    <div class="bar bar-revenue"
                                        :style="{ height: barHeight(day.revenue, maxRevenue) + 'px' }"
                                        :title="day.date + ': ¥' + day.revenue">
                                        <span v-if="day.revenue > maxRevenue * 0.7" class="bar-value">¥{{ day.revenue }}</span>
                                    </div>
                                    <div class="bar-label">{{ formatShortDate(day.date) }}</div>
                                </div>
                            </div>
                            <!-- 订单数折线叠加 -->
                            <div class="trend-legend mt-2">
                                <span class="legend-item"><span class="dot dot-revenue"></span> 收入</span>
                                <span class="legend-item"><span class="dot dot-orders"></span> 订单数: {{ totalOrders }}</span>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 热销商品 -->
            <el-tab-pane label="热销商品" name="products">
                <el-card>
                    <template #header>
                        <div class="card-header-flex">
                            <span>热销商品排行</span>
                            <el-button size="small" @click="handleExport('product_ranking')">
                                <el-icon><Download /></el-icon> 导出CSV
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="productRanking" stripe v-loading="loadingProducts">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="description" label="商品/项目" min-width="200" />
                        <el-table-column prop="type" label="类型" width="100" />
                        <el-table-column prop="total_quantity" label="销量(件)" width="100" align="center" sortable />
                        <el-table-column prop="total_revenue" label="收入(元)" width="120" align="center" sortable>
                            <template #default="{ row }">¥{{ formatNum(row.total_revenue) }}</template>
                        </el-table-column>
                        <el-table-column prop="order_count" label="订单数" width="90" align="center" sortable />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 4: 客户复购 -->
            <el-tab-pane label="客户复购" name="repurchase">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-label">总购买客户</div>
                            <div class="stat-value">{{ repurchaseData.total_buyers }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-active">
                            <div class="stat-label">复购率</div>
                            <div class="stat-value">{{ repurchaseData.repurchase_rate }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-info">
                            <div class="stat-label">多单率(3+)</div>
                            <div class="stat-value">{{ repurchaseData.multi_purchase_rate }}%</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-warning">
                            <div class="stat-label">人均消费</div>
                            <div class="stat-value">¥{{ formatNum(repurchaseData.avg_spent_per_buyer) }}</div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-card>
                    <template #header>购买次数分布</template>
                    <div class="distribution-grid">
                        <div v-for="(count, label) in repurchaseData.order_distribution" :key="label" class="dist-item">
                            <div class="dist-value">{{ count }}</div>
                            <div class="dist-label">{{ label }}</div>
                        </div>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 5: 支付渠道 -->
            <el-tab-pane label="支付渠道" name="payment">
                <el-card>
                    <template #header>
                        <div class="card-header-flex">
                            <span>支付渠道分布</span>
                            <el-button size="small" @click="handleExport('payment_channels')">
                                <el-icon><Download /></el-icon> 导出CSV
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="paymentChannels" stripe>
                        <el-table-column prop="channel" label="渠道" min-width="160" />
                        <el-table-column prop="order_count" label="订单数" width="100" align="center" />
                        <el-table-column prop="total_amount" label="金额(元)" width="130" align="center">
                            <template #default="{ row }">¥{{ formatNum(row.total_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="占比" min-width="200">
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

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import ecommerceAnalyticsApi from '@/api/ecommerceAnalytics';

const loading = ref(false);
const loadingOverview = ref(false);
const loadingProducts = ref(false);
const activeTab = ref('overview');
const period = ref('30d');

// 概要
const summary = reactive({
    total_revenue: 0, total_orders: 0, total_customers: 0,
    new_customers: 0, avg_order_value: 0,
    revenue_growth: 0, order_growth: 0,
});

// 对比
const comparison = reactive({});

// 销售趋势
const salesTrend = ref([]);

// 热销商品
const productRanking = ref([]);

// 复购
const repurchaseData = reactive({
    total_buyers: 0, repurchase_rate: 0, multi_purchase_rate: 0,
    avg_orders_per_buyer: 0, avg_spent_per_buyer: 0, order_distribution: {},
});

// 支付
const paymentChannels = ref([]);

// 预测
const forecastData = ref(null);

// 计算
const maxRevenue = computed(() => Math.max(...salesTrend.value.map(d => d.revenue), 1));
const totalOrders = computed(() => salesTrend.value.reduce((s, d) => s + d.order_count, 0));

function barHeight(val, max) {
    return Math.max(2, (val / max) * 140);
}

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    return parts.length >= 3 ? `${parts[1]}/${parts[2]}` : dateStr;
}

function formatNum(n) {
    if (n === 0 || n === null || n === undefined) return '0.00';
    return Number(n).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadAll() {
    loading.value = true;
    try {
        const days = period.value === '7d' ? 7 : period.value === '30d' ? 30 : period.value === '90d' ? 90 : 365;
        const params = { period: period.value };

        const [
            summaryRes, comparisonRes, trendRes,
            productRes, repurchaseRes, paymentRes,
            forecastRes,
        ] = await Promise.all([
            ecommerceAnalyticsApi.getSummary({ days }),
            ecommerceAnalyticsApi.getComparison({ days }),
            ecommerceAnalyticsApi.getSalesTrend({ days }),
            ecommerceAnalyticsApi.getProductRanking({ period: period.value }),
            ecommerceAnalyticsApi.getRepurchaseRate({ days: Math.min(days * 3, 365) }),
            ecommerceAnalyticsApi.getPaymentChannels({ days }),
            ecommerceAnalyticsApi.getForecast({ days: Math.min(days, 90) }),
        ]);

        Object.assign(summary, summaryRes.data?.data || {});
        Object.assign(comparison, comparisonRes.data?.data || {});
        salesTrend.value = trendRes.data?.data || [];
        productRanking.value = productRes.data?.data || [];
        Object.assign(repurchaseData, repurchaseRes.data?.data || {});
        paymentChannels.value = paymentRes.data?.data || [];
        forecastData.value = forecastRes.data?.data || null;
    } catch (err) {
        console.error('Failed to load analytics', err);
    } finally {
        loading.value = false;
    }
}

function handleExport(type) {
    const token = localStorage.getItem('token');
    const days = period.value === '7d' ? 7 : period.value === '30d' ? 30 : period.value === '90d' ? 90 : 365;
    const url = `${import.meta.env.VITE_API_URL || ''}/api/ecommerce-analytics/export-csv?type=${type}&days=${days}`;
    // 在新窗口打开或通过链接下载
    const link = document.createElement('a');
    link.href = token ? `${url}&token=${token}` : url;
    link.click();
}

onMounted(loadAll);
</script>

<style scoped>
.ecommerce-analytics-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.ml-4 { margin-left: 16px; }
.mt-2 { margin-top: 8px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-change { font-size: 12px; margin-top: 4px; }
.stat-change.up { color: #67c23a; }
.stat-change.down { color: #f56c6c; }
.stat-active .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #409eff; }
.stat-warning .stat-value { color: #e6a23c; }

.text-up { color: #67c23a; }
.text-down { color: #f56c6c; }

.chart-container { min-height: 200px; }
.chart-empty { text-align: center; color: #c0c4cc; padding: 60px 0; }

.bar-chart { display: flex; align-items: flex-end; gap: 2px; min-height: 180px; overflow-x: auto; padding-top: 20px; }
.bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 16px; }
.bar { width: 100%; max-width: 24px; border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s; position: relative; }
.bar-revenue { background: linear-gradient(to top, #409eff, #79bbff); }
.bar-value { font-size: 9px; position: absolute; top: -16px; white-space: nowrap; color: #606266; }
.bar-label { font-size: 8px; color: #909399; margin-top: 2px; white-space: nowrap; }

.trend-legend { display: flex; gap: 16px; font-size: 12px; }
.legend-item { display: flex; align-items: center; gap: 4px; }
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-revenue { background: #409eff; }
.dot-orders { background: #67c23a; }

.compact-stat { font-size: 13px; line-height: 1.8; }

.forecast-summary { font-size: 14px; padding: 8px 0; }

.distribution-grid { display: flex; gap: 12px; flex-wrap: wrap; }
.dist-item { flex: 1; min-width: 80px; text-align: center; padding: 16px; background: #f5f7fa; border-radius: 6px; }
.dist-value { font-size: 24px; font-weight: 700; color: #409eff; }
.dist-label { font-size: 12px; color: #909399; margin-top: 4px; }

.card-header-flex { display: flex; justify-content: space-between; align-items: center; }
</style>
