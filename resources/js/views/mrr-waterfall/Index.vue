<template>
    <div class="mrr-waterfall-page">
        <div class="page-header">
            <div>
                <h2>{{ t('mrr_waterfall_page.title') }}</h2>
                <p class="text-muted">{{ t('mrr_waterfall_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-date-picker v-model="selectedMonth" type="month" :placeholder="t('mrr_waterfall_page.select_month')" value-format="YYYY-MM" style="width:150px;margin-right:8px" @change="onMonthChange" />
                <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('mrr_waterfall_page.refresh') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4" v-if="summary">
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.current_mrr') }}</div><div class="stat-value">¥{{ fmt(summary.mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.arr') }}</div><div class="stat-value primary">¥{{ fmt(summary.arr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.net_mrr_change') }}</div><div class="stat-value" :class="summary.net_mrr_change >= 0 ? 'success' : 'danger'">{{ summary.net_mrr_change >= 0 ? '+' : '' }}¥{{ fmt(summary.net_mrr_change) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.new_mrr') }}</div><div class="stat-value success">+¥{{ fmt(summary.new_mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.churned_mrr') }}</div><div class="stat-value danger">-¥{{ fmt(summary.churned_mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">{{ t('mrr_waterfall_page.stats.active_subscriptions') }}</div><div class="stat-value">{{ summary.total_subscriptions || 0 }}</div></el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="mb-4">
            <template #header><span>{{ t('mrr_waterfall_page.sections.trend') }}</span></template>
            <MrrWaterfallChart :chartData="waterfallData" :loading="chartLoading" @refresh="loadWaterfall" />
        </el-card>

        <el-row :gutter="16" class="mb-4" v-if="summary">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ t('mrr_waterfall_page.sections.composition', { month: selectedMonth }) }}</span></template>
                    <div class="mrr-composition">
                        <div class="mrr-comp-item" v-if="summary.new_mrr"><span class="dot green"></span><span>{{ t('mrr.new') }}</span><span class="val success">+¥{{ fmt(summary.new_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.expansion_mrr"><span class="dot blue"></span><span>{{ t('mrr.expansion') }}</span><span class="val success">+¥{{ fmt(summary.expansion_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.contraction_mrr"><span class="dot orange"></span><span>{{ t('mrr.contraction') }}</span><span class="val warning">-¥{{ fmt(summary.contraction_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.churned_mrr"><span class="dot red"></span><span>{{ t('mrr.churned') }}</span><span class="val danger">-¥{{ fmt(summary.churned_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.reactivation_mrr"><span class="dot gray"></span><span>{{ t('mrr.type_reactivate') }}</span><span class="val success">+¥{{ fmt(summary.reactivation_mrr) }}</span></div>
                    </div>
                    <div class="filter-tags mt-3">
                        <el-tag v-for="item in changeTypeFilters" :key="item.value" :type="filterType === item.value ? '' : 'info'" class="clickable" @click="setFilterType(item.value)">{{ item.label }}</el-tag>
                        <el-tag v-if="filterType" type="info" class="clickable" @click="setFilterType('')">{{ t('mrr_waterfall_page.clear_filter') }}</el-tag>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover" v-loading="breakdownLoading">
                    <template #header>
                        <el-tabs v-model="breakdownTab" class="breakdown-tabs">
                            <el-tab-pane :label="t('mrr_waterfall_page.breakdown.tab_product')" name="product" />
                            <el-tab-pane :label="t('mrr_waterfall_page.breakdown.tab_region')" name="region" />
                        </el-tabs>
                    </template>
                    <el-table :data="breakdownTab === 'product' ? productBreakdown : regionBreakdown" stripe size="small" max-height="260">
                        <el-table-column :label="breakdownTab === 'product' ? t('mrr_waterfall_page.breakdown.col_plan') : t('mrr_waterfall_page.breakdown.col_region')" prop="label" min-width="120" />
                        <el-table-column :label="t('mrr_waterfall_page.breakdown.col_change_count')" prop="change_count" width="90" align="center" />
                        <el-table-column :label="t('mrr_waterfall_page.breakdown.col_mrr_impact')" width="120" align="right">
                            <template #default="{ row }">
                                <span :class="row.total_impact >= 0 ? 'success' : 'danger'">{{ row.total_impact >= 0 ? '+' : '' }}¥{{ fmt(row.total_impact) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!(breakdownTab === 'product' ? productBreakdown : regionBreakdown).length" :description="t('messages.no_data')" :image-size="60" />
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover">
            <MrrDrilldownPanel :yearMonth="selectedMonth" :changeType="filterType" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Refresh } from '@element-plus/icons-vue';
import MrrWaterfallChart from '@/components/MrrWaterfallChart.vue';
import MrrDrilldownPanel from '@/components/MrrDrilldownPanel.vue';
import { getMrrWaterfall, getMrrSummary, getMrrBreakdownByProduct, getMrrBreakdownByRegion } from '@/api/mrr.js';

const { t, locale } = useI18n();

const loading = ref(false);
const chartLoading = ref(false);
const breakdownLoading = ref(false);
const waterfallData = ref([]);
const summary = ref(null);
const productBreakdown = ref([]);
const regionBreakdown = ref([]);
const selectedMonth = ref(new Date().toISOString().slice(0, 7));
const filterType = ref('');
const breakdownTab = ref('product');
const chartMonths = ref(6);

const changeTypeFilterKeys = [
    { value: 'new_subscription', key: 'mrr.type_new' },
    { value: 'upgrade', key: 'mrr.type_upgrade' },
    { value: 'downgrade', key: 'mrr.type_downgrade' },
    { value: 'cancellation', key: 'mrr.type_cancel' },
    { value: 'reactivation', key: 'mrr.type_reactivate' },
];

const changeTypeFilters = computed(() => changeTypeFilterKeys.map((item) => ({
    value: item.value,
    label: t(item.key),
})));

function numberLocale() {
    return locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US';
}

function fmt(v) {
    return Number(v || 0).toLocaleString(numberLocale(), { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function setFilterType(type) {
    filterType.value = filterType.value === type ? '' : type;
}

async function loadWaterfall(months) {
    chartLoading.value = true;
    chartMonths.value = months || chartMonths.value;
    try {
        const res = await getMrrWaterfall({ months: chartMonths.value });
        waterfallData.value = res.data?.data || [];
        if (waterfallData.value.length && !selectedMonth.value) {
            selectedMonth.value = waterfallData.value[waterfallData.value.length - 1].month;
        }
    } catch {
        waterfallData.value = [];
    } finally {
        chartLoading.value = false;
    }
}

async function loadSummary() {
    try {
        const res = await getMrrSummary({ year_month: selectedMonth.value });
        summary.value = res.data?.data || null;
    } catch {
        summary.value = null;
    }
}

async function loadBreakdowns() {
    breakdownLoading.value = true;
    try {
        const params = { year_month: selectedMonth.value };
        const [productRes, regionRes] = await Promise.all([
            getMrrBreakdownByProduct(params),
            getMrrBreakdownByRegion(params),
        ]);
        productBreakdown.value = productRes.data?.data || [];
        regionBreakdown.value = regionRes.data?.data || [];
    } catch {
        productBreakdown.value = [];
        regionBreakdown.value = [];
    } finally {
        breakdownLoading.value = false;
    }
}

function onMonthChange() {
    loadSummary();
    loadBreakdowns();
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadWaterfall(chartMonths.value), loadSummary(), loadBreakdowns()]);
    loading.value = false;
}

watch(selectedMonth, () => {
    loadSummary();
    loadBreakdowns();
});

onMounted(refreshAll);
</script>

<style scoped>
.mrr-waterfall-page { padding: 0 4px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.text-muted { color: #909399; font-size: 13px; margin: 0; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; }
.stat-value.primary { color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.mrr-composition { display: flex; flex-direction: column; gap: 10px; }
.mrr-comp-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.mrr-comp-item .val { margin-left: auto; font-weight: 600; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot.green { background: #67c23a; }
.dot.blue { background: #0f172a; }
.dot.orange { background: #e6a23c; }
.dot.red { background: #f56c6c; }
.dot.gray { background: #909399; }
.success { color: #67c23a; }
.warning { color: #e6a23c; }
.danger { color: #f56c6c; }
.filter-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.clickable { cursor: pointer; }
.breakdown-tabs :deep(.el-tabs__header) { margin-bottom: 0; }
</style>
