<template>
    <div class="business-metrics-page">
        <div class="page-header">
            <div>
                <h2>{{ t('business_metrics_page.title') }}</h2>
                <p class="text-muted">{{ t('business_metrics_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('revenue_page.refresh') }}</el-button>
                <el-button type="primary" @click="exportReport" :icon="Download">{{ t('report_builder_page.dialogs.export_report') }}</el-button>
            </div>
        </div>

        <!-- 业务健康评分 -->
        <el-alert
            v-if="healthScore"
            :title="t('business_metrics_page.health_score', { score: healthScore.score, label: healthScore.label })"
            :type="healthScore.level === 'healthy' ? 'success' : (healthScore.level === 'warning' ? 'warning' : 'error')"
            show-icon
            :closable="false"
            class="mb-4"
        />

        <!-- ── 第一行：核心指标卡片 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">MRR</div>
                            <div class="metric-value">¥{{ fmt(overview.mrr) }}</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.mrr_sub') }}</div>
                        </div>
                        <el-icon :size="32" color="#0f172a"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">ARR</div>
                            <div class="metric-value">¥{{ fmt(overview.arr) }}</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.arr_sub') }}</div>
                        </div>
                        <el-icon :size="32" color="#b37feb"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ cols.churn_rate }}</div>
                            <div class="metric-value" :class="overview.churn_rate > 5 ? 'danger' : 'success'">{{ overview.churn_rate }}%</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.churn_sub') }}</div>
                        </div>
                        <el-icon :size="32" :color="overview.churn_rate > 5 ? '#f56c6c' : '#67c23a'"><Warning /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ cols.ltv }}</div>
                            <div class="metric-value">¥{{ fmt(overview.ltv) }}</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.ltv_sub') }}</div>
                        </div>
                        <el-icon :size="32" color="#e6a23c"><Money /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.cac') }}</div>
                            <div class="metric-value">¥{{ fmt(overview.cac) }}</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.cac_sub') }}</div>
                        </div>
                        <el-icon :size="32" color="#909399"><User /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6" :lg="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.ltv_cac') }}</div>
                            <div class="metric-value" :class="overview.ltv_cac_ratio >= 3 ? 'success' : (overview.ltv_cac_ratio >= 1 ? 'warning' : 'danger')">{{ overview.ltv_cac_ratio }}x</div>
                            <div class="metric-sub">{{ ltvCacRating }}</div>
                        </div>
                        <el-icon :size="32" :color="overview.ltv_cac_ratio >= 3 ? '#67c23a' : (overview.ltv_cac_ratio >= 1 ? '#e6a23c' : '#f56c6c')"><DataBoard /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第二行：续费/激活/转化 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ cols.renewal_rate }}</div>
                            <div class="metric-value" :class="overview.renewal_rate >= 80 ? 'success' : (overview.renewal_rate >= 60 ? 'warning' : 'danger')">{{ overview.renewal_rate }}%</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.renewal_sub') }}</div>
                        </div>
                    </div>
                    <el-progress :percentage="Math.min(overview.renewal_rate, 100)" :status="overview.renewal_rate >= 80 ? 'success' : (overview.renewal_rate >= 60 ? 'warning' : 'exception')" :stroke-width="8" />
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.activation_rate') }}</div>
                            <div class="metric-value" :class="overview.activation_rate >= 70 ? 'success' : (overview.activation_rate >= 50 ? 'warning' : 'danger')">{{ overview.activation_rate }}%</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.activation_sub') }}</div>
                        </div>
                    </div>
                    <el-progress :percentage="Math.min(overview.activation_rate, 100)" :status="overview.activation_rate >= 70 ? 'success' : (overview.activation_rate >= 50 ? 'warning' : 'exception')" :stroke-width="8" />
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.trial_conversion') }}</div>
                            <div class="metric-value" :class="overview.trial_conversion_rate >= 30 ? 'success' : (overview.trial_conversion_rate >= 20 ? 'warning' : 'danger')">{{ overview.trial_conversion_rate }}%</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.trial_conversion_sub') }}</div>
                        </div>
                    </div>
                    <el-progress :percentage="Math.min(overview.trial_conversion_rate, 100)" :status="overview.trial_conversion_rate >= 30 ? 'success' : (overview.trial_conversion_rate >= 20 ? 'warning' : 'exception')" :stroke-width="8" />
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第三行：活跃概况 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.active_customers') }}</div>
                            <div class="metric-value">{{ overview.active_customers }}</div>
                        </div>
                        <el-icon :size="32" color="#0f172a"><UserFilled /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.active_subscriptions') }}</div>
                            <div class="metric-value">{{ overview.total_subscriptions }}</div>
                        </div>
                        <el-icon :size="32" color="#67c23a"><Key /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('business_metrics_page.metrics.total_revenue') }}</div>
                            <div class="metric-value">¥{{ fmt(overview.total_revenue) }}</div>
                        </div>
                        <el-icon :size="32" color="#e6a23c"><Money /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ cols.arpu }}</div>
                            <div class="metric-value">¥{{ overview.active_customers ? fmt(overview.mrr / overview.active_customers) : '-' }}</div>
                            <div class="metric-sub">{{ t('business_metrics_page.metrics.arpu_sub') }}</div>
                        </div>
                        <el-icon :size="32" color="#b37feb"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第四行：MRR 趋势图 ── -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><TrendCharts /></el-icon> {{ t('business_metrics_page.sections.mrr_trend') }} <small class="text-muted">{{ t('business_metrics_page.sections.mrr_trend_hint') }}</small></span>
                    <el-radio-group v-model="trendMonths" size="small" @change="loadDashboard">
                        <el-radio-button :value="6">{{ t('business_metrics_page.months.m6') }}</el-radio-button>
                        <el-radio-button :value="12">{{ t('business_metrics_page.months.m12') }}</el-radio-button>
                        <el-radio-button :value="24">{{ t('business_metrics_page.months.m24') }}</el-radio-button>
                    </el-radio-group>
                </div>
            </template>
            <div v-loading="loading" class="trend-section">
                <el-table :data="mrrTrend" stripe size="small" max-height="400">
                    <el-table-column prop="label" :label="cols.period" width="110" />
                    <el-table-column label="MRR" width="150">
                        <template #default="{ row }">¥{{ fmt(row.mrr) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.mom" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.mom_change >= 0 ? 'success' : 'danger'" size="small">
                                {{ row.mom_change >= 0 ? '+' : '' }}{{ row.mom_change }}%
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="cols.yoy" width="120">
                        <template #default="{ row }">
                            <span v-if="row.yoy_change !== null">
                                <el-tag :type="row.yoy_change >= 0 ? 'success' : 'danger'" size="small">
                                    {{ row.yoy_change >= 0 ? '+' : '' }}{{ row.yoy_change }}%
                                </el-tag>
                            </span>
                            <span v-else class="text-muted">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="cols.trend" min-width="200">
                        <template #default="{ row }">
                            <span v-if="row.mom_change > 0" class="trend-up">{{ mrrTrendText(row.mom_change) }}</span>
                            <span v-else-if="row.mom_change < 0" class="trend-down">{{ mrrTrendText(row.mom_change) }}</span>
                            <span v-else class="trend-flat">{{ mrrTrendText(row.mom_change) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-card>

        <!-- ── 第五行：流失率趋势 + 新增客户 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span><el-icon><Warning /></el-icon> {{ t('business_metrics_page.sections.churn_trend') }}</span>
                    </template>
                    <el-table :data="churnTrend" stripe size="small" max-height="350" v-loading="loading">
                        <el-table-column prop="label" :label="cols.period" width="100" />
                        <el-table-column :label="cols.churn_rate" width="110">
                            <template #default="{ row }">
                                <el-tag :type="row.churn_rate > 10 ? 'danger' : (row.churn_rate > 5 ? 'warning' : 'success')" size="small">
                                    {{ row.churn_rate }}%
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="cols.churned_count" width="80" prop="churned_count" />
                        <el-table-column :label="cols.active_begin" width="100" prop="active_begin" />
                        <el-table-column :label="cols.trend" min-width="120">
                            <template #default="{ row }">
                                <span v-if="row.churn_rate > 10" class="trend-down">{{ churnTrendStatus(row.churn_rate) }}</span>
                                <span v-else-if="row.churn_rate > 5" class="trend-flat" style="color:#e6a23c">{{ churnTrendStatus(row.churn_rate) }}</span>
                                <span v-else class="trend-up" style="color:#67c23a">{{ churnTrendStatus(row.churn_rate) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span><el-icon><UserFilled /></el-icon> {{ t('business_metrics_page.sections.new_customer_trend') }}</span>
                    </template>
                    <el-table :data="newCustomerTrend" stripe size="small" max-height="350" v-loading="loading">
                        <el-table-column prop="label" :label="cols.period" width="100" />
                        <el-table-column :label="cols.new_customers" width="130">
                            <template #default="{ row }">
                                <span class="text-success">+{{ row.count }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="cols.trend" min-width="120">
                            <template #default="{ row, $index }">
                                <span v-if="$index > 0 && row.count > (newCustomerTrend[$index - 1]?.count || 0)" class="trend-up">{{ t('business_metrics_page.trend.up') }}</span>
                                <span v-else-if="$index > 0 && row.count < (newCustomerTrend[$index - 1]?.count || 0)" class="trend-down">{{ t('business_metrics_page.trend.down') }}</span>
                                <span v-else class="trend-flat">{{ t('business_metrics_page.trend.flat') }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第六行：同期群分析 ── -->
        <el-card shadow="hover">
            <template #header>
                <span><el-icon><DataBoard /></el-icon> {{ t('business_metrics_page.sections.cohort') }}</span>
            </template>
            <div v-loading="loading" class="cohort-table-wrap">
                <el-table :data="cohortTable" stripe size="small" max-height="500">
                    <el-table-column prop="cohort" :label="cols.cohort" width="100" fixed />
                    <el-table-column prop="total" :label="cols.customers" width="80" />
                    <el-table-column v-for="m in cohortMonths" :key="m" :label="t('business_metrics_page.cols.month_n', { n: m })" width="90" align="center">
                        <template #default="{ row }">
                            <span v-if="row.retention[m - 1]" :style="{ color: retentionColor(row.retention[m - 1].retention_rate) }">
                                {{ row.retention[m - 1].retention_rate }}%
                            </span>
                            <span v-else class="text-muted">—</span>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!cohortTable.length" :description="t('business_metrics_page.empty.no_cohort')" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Download, TrendCharts, Coin, Warning, Money, User, UserFilled, Key, DataBoard } from '@element-plus/icons-vue';
import businessMetricsApi from '@/api/businessMetrics';

const { t, locale } = useI18n();

const loading = ref(false);
const overview = reactive({
    mrr: 0, arr: 0, churn_rate: 0, ltv: 0, cac: 0, ltv_cac_ratio: 0,
    renewal_rate: 0, activation_rate: 0, trial_conversion_rate: 0,
    active_customers: 0, total_subscriptions: 0, total_revenue: 0,
});
const healthScore = ref(null);
const mrrTrend = ref([]);
const churnTrend = ref([]);
const newCustomerTrend = ref([]);
const cohortTable = ref([]);
const trendMonths = ref(12);

const cols = computed(() => ({
    period: t('revenue_page.cols.period'),
    ltv: t('revenue_page.cols.ltv'),
    churn_rate: t('revenue_page.cols.churn_rate'),
    renewal_rate: t('revenue_page.cols.renewal_rate'),
    arpu: t('revenue_page.cols.arpu'),
    customers: t('revenue_page.cols.customers'),
    mom: t('business_metrics_page.cols.mom'),
    yoy: t('business_metrics_page.cols.yoy'),
    trend: t('business_metrics_page.cols.trend'),
    churned_count: t('business_metrics_page.cols.churned_count'),
    active_begin: t('business_metrics_page.cols.active_begin'),
    new_customers: t('business_metrics_page.cols.new_customers'),
    cohort: t('business_metrics_page.cols.cohort'),
}));

const ltvCacRating = computed(() => {
    const ratio = overview.ltv_cac_ratio;
    if (ratio >= 3) return t('business_metrics_page.rating.strong');
    if (ratio >= 1) return t('business_metrics_page.rating.moderate');
    return t('business_metrics_page.rating.needs_improvement');
});

const cohortMonths = computed(() => {
    const max = Math.max(...cohortTable.value.map(c => c.retention?.length || 0));
    return max > 0 ? Array.from({ length: max }, (_, i) => i + 1) : [];
});

onMounted(loadDashboard);

async function loadDashboard() {
    loading.value = true;
    try {
        const res = await businessMetricsApi.dashboard();
        const data = res.data?.data || {};
        Object.assign(overview, data.overview || {});
        healthScore.value = data.health_score || null;
        mrrTrend.value = data.trends?.mrr || [];
        churnTrend.value = data.trends?.churn_rate || [];
        newCustomerTrend.value = data.trends?.new_customers || [];
        cohortTable.value = (data.cohorts || []).map(c => ({
            cohort: c.label,
            total: c.total_customers,
            retention: c.retention || [],
        }));
    } catch (e) {
        ElMessage.error(t('business_metrics_page.messages.load_failed'));
    } finally { loading.value = false; }
}

async function refreshAll() { await loadDashboard(); }

async function exportReport() {
    try {
        const res = await businessMetricsApi.exportData({ format: 'csv', months: trendMonths.value });
        const data = res.data?.data || [];
        if (!data.length) {
            ElMessage.warning(t('business_metrics_page.messages.no_export_data'));
            return;
        }
        const csv = data.map(r => r.map(c => typeof c === 'string' && c.includes(',') ? `"${c}"` : c).join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = t('business_metrics_page.export_filename');
        a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('business_metrics_page.messages.export_success'));
    } catch (e) {
        ElMessage.error(t('business_metrics_page.messages.export_failed'));
    }
}

function fmt(v) {
    if (v === null || v === undefined) return '0';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return Number(v).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mrrTrendText(momChange) {
    if (momChange > 0) return t('business_metrics_page.trend.growth_pct', { pct: momChange });
    if (momChange < 0) return t('business_metrics_page.trend.decline_pct', { pct: Math.abs(momChange) });
    return t('business_metrics_page.trend.flat');
}

function churnTrendStatus(rate) {
    if (rate > 10) return t('business_metrics_page.churn_status.attention');
    if (rate > 5) return t('business_metrics_page.churn_status.normal');
    return t('business_metrics_page.churn_status.good');
}

function retentionColor(rate) {
    if (rate >= 80) return '#67c23a';
    if (rate >= 60) return '#e6a23c';
    return '#f56c6c';
}
</script>

<style scoped>
.business-metrics-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card .metric-content { display: flex; justify-content: space-between; align-items: center; }
.metric-info { flex: 1; }
.metric-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.metric-value { font-size: 22px; font-weight: 700; line-height: 1.3; }
.metric-sub { font-size: 12px; color: #c0c4cc; margin-top: 2px; }
.success { color: #67c23a; }
.danger { color: #f56c6c; }
.warning { color: #e6a23c; }
.text-muted { color: #c0c4cc; }
.text-success { color: #67c23a; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.trend-up { color: #67c23a; }
.trend-down { color: #f56c6c; }
.trend-flat { color: #909399; }
.cohort-table-wrap { overflow-x: auto; }
</style>
