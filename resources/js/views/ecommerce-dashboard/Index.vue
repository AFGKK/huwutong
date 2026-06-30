<template>
    <div class="ecommerce-dashboard-page">
        <div class="page-header">
            <h2>📊 电商数据看板</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 今日概要卡片 -->
        <el-row :gutter="16" class="mb-6">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="stat-card revenue">
                    <div class="stat-icon"><el-icon size="28"><Coin /></el-icon></div>
                    <div class="stat-body">
                        <div class="stat-label">今日销售额</div>
                        <div class="stat-value">¥{{ formatNum(today.today_revenue) }}</div>
                        <div class="stat-change" :class="today.revenue_growth >= 0 ? 'up' : 'down'">
                            {{ today.revenue_growth >= 0 ? '↑' : '↓' }} 环比昨日 {{ Math.abs(today.revenue_growth) }}%
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="stat-card orders">
                    <div class="stat-icon"><el-icon size="28"><ShoppingCart /></el-icon></div>
                    <div class="stat-body">
                        <div class="stat-label">今日订单量</div>
                        <div class="stat-value">{{ today.today_orders }}</div>
                        <div class="stat-sub">
                            <el-tag size="small" type="success">已支付 {{ today.today_paid }}</el-tag>
                            <el-tag size="small" type="warning">待支付 {{ today.today_pending }}</el-tag>
                            <el-tag size="small" type="info">已取消 {{ today.today_cancelled }}</el-tag>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="stat-card rate">
                    <div class="stat-icon"><el-icon size="28"><CircleCheck /></el-icon></div>
                    <div class="stat-body">
                        <div class="stat-label">支付成功率</div>
                        <div class="stat-value">{{ paymentRate.success_rate }}%</div>
                        <div class="stat-sub">
                            成功 {{ paymentRate.paid }} / 总计 {{ paymentRate.total }}
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="stat-card refund">
                    <div class="stat-icon"><el-icon size="28"><Refresh /></el-icon></div>
                    <div class="stat-body">
                        <div class="stat-label">退款率</div>
                        <div class="stat-value">{{ refundRate.refund_rate }}%</div>
                        <div class="stat-sub">
                            退款 {{ refundRate.refunded_orders }} 笔 / 已支付 {{ refundRate.total_paid_orders }} 笔
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行：月度累计 + 总累计 -->
        <el-row :gutter="16" class="mb-6">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" size="small">
                    <div class="sub-stat-label">本月销售额</div>
                    <div class="sub-stat-value">¥{{ formatNum(today.month_revenue) }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" size="small">
                    <div class="sub-stat-label">本月订单数</div>
                    <div class="sub-stat-value">{{ today.month_orders }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" size="small">
                    <div class="sub-stat-label">累计销售额</div>
                    <div class="sub-stat-value">¥{{ formatNum(today.total_revenue) }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" size="small">
                    <div class="sub-stat-label">累计订单总数</div>
                    <div class="sub-stat-value">{{ today.total_orders }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-6">
            <!-- 趋势图表 -->
            <el-col :xs="24" :lg="16" class="mb-4">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span>近7天趋势</span>
                            <el-radio-group v-model="trendMetric" size="small" @change="renderTrendChart">
                                <el-radio-button value="revenue">销售额</el-radio-button>
                                <el-radio-button value="orders">订单量</el-radio-button>
                                <el-radio-button value="paid">支付数</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div ref="trendChartRef" style="height: 320px"></div>
                </el-card>
            </el-col>

            <!-- 商品销量排行 -->
            <el-col :xs="24" :lg="8" class="mb-4">
                <el-card shadow="hover">
                    <template #header>
                        <span>🏆 商品销量排行</span>
                    </template>
                    <div v-if="productRanking.length === 0" class="empty-state">暂无数据</div>
                    <div v-for="(item, index) in productRanking" :key="item.sku_id" class="ranking-item">
                        <div class="ranking-index" :class="'rank-' + (index + 1)">{{ index + 1 }}</div>
                        <div class="ranking-info">
                            <div class="ranking-name" :title="item.sku_name">{{ item.sku_name }}</div>
                            <div class="ranking-code">{{ item.sku_code }}</div>
                        </div>
                        <div class="ranking-stats">
                            <div class="ranking-qty">{{ item.total_qty }} 件</div>
                            <div class="ranking-revenue">¥{{ formatNum(item.total_revenue) }}</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 支付/退款详情 -->
        <el-row :gutter="16">
            <el-col :xs="24" :lg="12" class="mb-4">
                <el-card shadow="hover">
                    <template #header>
                        <span>💳 支付状态分布</span>
                    </template>
                    <div ref="paymentChartRef" style="height: 260px"></div>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="12" class="mb-4">
                <el-card shadow="hover">
                    <template #header>
                        <span>🔄 退款统计</span>
                    </template>
                    <div ref="refundChartRef" style="height: 260px"></div>
                    <el-descriptions :column="2" border size="small" class="mt-4">
                        <el-descriptions-item label="已支付总额">¥{{ formatNum(refundRate.total_revenue) }}</el-descriptions-item>
                        <el-descriptions-item label="退款总额">¥{{ formatNum(refundRate.refunded_amount) }}</el-descriptions-item>
                        <el-descriptions-item label="退款单数">{{ refundRate.refunded_orders }}</el-descriptions-item>
                        <el-descriptions-item label="金额退款率">{{ refundRate.refund_amount_rate }}%</el-descriptions-item>
                    </el-descriptions>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import * as echarts from 'echarts';
import ecommerceDashboardApi from '@/api/ecommerceDashboard';

const loading = ref(false);

// 数据
const today = ref({});
const productRanking = ref([]);
const paymentRate = ref({});
const refundRate = ref({});
const trend = ref([]);
const trendMetric = ref('revenue');

// 图表引用
const trendChartRef = ref(null);
const paymentChartRef = ref(null);
const refundChartRef = ref(null);

// 图表实例
let trendChart = null;
let paymentChart = null;
let refundChart = null;

function formatNum(val) {
    if (val === null || val === undefined) return '0.00';
    return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadAll() {
    loading.value = true;
    try {
        const [dashboardRes, payRateRes, refundRateRes] = await Promise.all([
            ecommerceDashboardApi.getDashboard(),
            ecommerceDashboardApi.getPaymentSuccessRate(),
            ecommerceDashboardApi.getRefundRate(),
        ]);
        const data = dashboardRes.data.data;
        today.value = data.today;
        productRanking.value = data.product_ranking;
        trend.value = data.trend;
        paymentRate.value = payRateRes.data.data;
        refundRate.value = refundRateRes.data.data;

        await nextTick();
        renderTrendChart();
        renderPaymentChart();
        renderRefundChart();
    } catch (e) {
        console.error('加载看板数据失败', e);
    } finally {
        loading.value = false;
    }
}

function renderTrendChart() {
    if (!trendChartRef.value) return;
    if (!trendChart) {
        trendChart = echarts.init(trendChartRef.value);
    }
    const dates = trend.value.map(t => t.label);
    let series = [];
    const metric = trendMetric.value;
    const colorMap = { revenue: '#409EFF', orders: '#67C23A', paid: '#E6A23C' };

    series.push({
        name: metric === 'revenue' ? '销售额' : metric === 'orders' ? '订单量' : '支付数',
        type: 'line',
        smooth: true,
        data: trend.value.map(t => t[metric]),
        areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: colorMap[metric] + '40' },
            { offset: 1, color: colorMap[metric] + '05' },
        ])},
        lineStyle: { color: colorMap[metric], width: 3 },
        itemStyle: { color: colorMap[metric] },
    });

    trendChart.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: 60, right: 20, bottom: 30, top: 20 },
        xAxis: { type: 'category', data: dates, boundaryGap: false },
        yAxis: { type: 'value' },
        series,
    }, true);
}

function renderPaymentChart() {
    if (!paymentChartRef.value) return;
    if (!paymentChart) {
        paymentChart = echarts.init(paymentChartRef.value);
    }
    const data = [
        { value: paymentRate.value.paid || 0, name: '已支付', itemStyle: { color: '#67C23A' } },
        { value: paymentRate.value.pending || 0, name: '待支付', itemStyle: { color: '#E6A23C' } },
        { value: paymentRate.value.cancelled || 0, name: '已取消', itemStyle: { color: '#909399' } },
        { value: paymentRate.value.refunded || 0, name: '已退款', itemStyle: { color: '#F56C6C' } },
    ];
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
    }, true);
}

function renderRefundChart() {
    if (!refundChartRef.value) return;
    if (!refundChart) {
        refundChart = echarts.init(refundChartRef.value);
    }
    const paidAmount = refundRate.value.total_revenue || 0;
    const refundedAmount = refundRate.value.refunded_amount || 0;
    const netAmount = paidAmount - refundedAmount;
    refundChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: ¥{c}' },
        series: [{
            type: 'pie',
            radius: ['40%', '70%'],
            data: [
                { value: netAmount, name: '有效收入', itemStyle: { color: '#409EFF' } },
                { value: refundedAmount, name: '退款金额', itemStyle: { color: '#F56C6C' } },
            ],
            label: {
                show: true,
                formatter: ({ name, value, percent }) => {
                    if (value === 0) return '';
                    return `${name}\n¥${value.toLocaleString()}\n${percent.toFixed(1)}%`;
                },
            },
            emphasis: { scale: true },
        }],
    }, true);
}

onMounted(() => {
    loadAll();
});
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

/* 统计卡片 */
.stat-card {
    position: relative;
    border-left: 4px solid;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.revenue { border-left-color: #409EFF; }
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

/* 卡片标题 */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* 排行榜 */
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
.ranking-index.rank-3 { background: #CD7F32; color: #fff; background-color: #A0522D; }

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
</style>
