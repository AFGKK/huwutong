<template>
    <div class="mrr-waterfall-page">
        <div class="page-header">
            <div>
                <h2>MRR 瀑布图</h2>
                <p class="text-muted">月经常性收入变动分析：新增 / 扩展 / 收缩 / 流失</p>
            </div>
            <div class="header-actions">
                <el-date-picker v-model="selectedMonth" type="month" placeholder="选择月份" value-format="YYYY-MM" style="width:150px;margin-right:8px" @change="onMonthChange" />
                <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4" v-if="summary">
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">当前 MRR</div><div class="stat-value">¥{{ fmt(summary.mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">ARR</div><div class="stat-value primary">¥{{ fmt(summary.arr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">净增 MRR</div><div class="stat-value" :class="summary.net_mrr_change >= 0 ? 'success' : 'danger'">{{ summary.net_mrr_change >= 0 ? '+' : '' }}¥{{ fmt(summary.net_mrr_change) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">新增 MRR</div><div class="stat-value success">+¥{{ fmt(summary.new_mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">流失 MRR</div><div class="stat-value danger">-¥{{ fmt(summary.churned_mrr) }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="4">
                <el-card shadow="hover"><div class="stat-label">活跃订阅</div><div class="stat-value">{{ summary.total_subscriptions || 0 }}</div></el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="mb-4">
            <template #header><span>MRR 瀑布图趋势</span></template>
            <MrrWaterfallChart :chartData="waterfallData" :loading="chartLoading" @refresh="loadWaterfall" />
        </el-card>

        <el-row :gutter="16" class="mb-4" v-if="summary">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span>{{ selectedMonth }} MRR 构成</span></template>
                    <div class="mrr-composition">
                        <div class="mrr-comp-item" v-if="summary.new_mrr"><span class="dot green"></span><span>新增</span><span class="val success">+¥{{ fmt(summary.new_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.expansion_mrr"><span class="dot blue"></span><span>扩展</span><span class="val success">+¥{{ fmt(summary.expansion_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.contraction_mrr"><span class="dot orange"></span><span>收缩</span><span class="val warning">-¥{{ fmt(summary.contraction_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.churned_mrr"><span class="dot red"></span><span>流失</span><span class="val danger">-¥{{ fmt(summary.churned_mrr) }}</span></div>
                        <div class="mrr-comp-item" v-if="summary.reactivation_mrr"><span class="dot gray"></span><span>重新激活</span><span class="val success">+¥{{ fmt(summary.reactivation_mrr) }}</span></div>
                    </div>
                    <div class="filter-tags mt-3">
                        <el-tag v-for="t in changeTypeFilters" :key="t.value" :type="filterType === t.value ? '' : 'info'" class="clickable" @click="setFilterType(t.value)">{{ t.label }}</el-tag>
                        <el-tag v-if="filterType" type="info" class="clickable" @click="setFilterType('')">清除筛选</el-tag>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover" v-loading="breakdownLoading">
                    <template #header>
                        <el-tabs v-model="breakdownTab" class="breakdown-tabs">
                            <el-tab-pane label="按产品/方案" name="product" />
                            <el-tab-pane label="按区域" name="region" />
                        </el-tabs>
                    </template>
                    <el-table :data="breakdownTab === 'product' ? productBreakdown : regionBreakdown" stripe size="small" max-height="260">
                        <el-table-column :label="breakdownTab === 'product' ? '方案' : '区域'" prop="label" min-width="120" />
                        <el-table-column label="变更笔数" prop="change_count" width="90" align="center" />
                        <el-table-column label="MRR 影响" width="120" align="right">
                            <template #default="{ row }">
                                <span :class="row.total_impact >= 0 ? 'success' : 'danger'">{{ row.total_impact >= 0 ? '+' : '' }}¥{{ fmt(row.total_impact) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!(breakdownTab === 'product' ? productBreakdown : regionBreakdown).length" description="暂无数据" :image-size="60" />
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover">
            <MrrDrilldownPanel :yearMonth="selectedMonth" :changeType="filterType" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { Refresh } from '@element-plus/icons-vue';
import MrrWaterfallChart from '@/components/MrrWaterfallChart.vue';
import MrrDrilldownPanel from '@/components/MrrDrilldownPanel.vue';
import { getMrrWaterfall, getMrrSummary, getMrrBreakdownByProduct, getMrrBreakdownByRegion } from '@/api/mrr.js';

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

const changeTypeFilters = [
    { value: 'new_subscription', label: '新增订阅' },
    { value: 'upgrade', label: '升级' },
    { value: 'downgrade', label: '降级' },
    { value: 'cancellation', label: '取消' },
    { value: 'reactivation', label: '重新激活' },
];

function fmt(v) {
    return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
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
.stat-value.primary { color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.mrr-composition { display: flex; flex-direction: column; gap: 10px; }
.mrr-comp-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.mrr-comp-item .val { margin-left: auto; font-weight: 600; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot.green { background: #67c23a; }
.dot.blue { background: #409eff; }
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
