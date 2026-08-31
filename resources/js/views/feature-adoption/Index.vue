<template>
    <div class="feature-adoption-container">
        <el-page-header :content="t('nav.feature_adoption')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t(`${P}.alert`)"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 日期筛选 -->
        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t(`${P}.filters.start_date`)">
                    <el-date-picker
                        v-model="filters.start_date"
                        type="date"
                        :placeholder="t(`${P}.filters.start_date_ph`)"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.end_date`)">
                    <el-date-picker
                        v-model="filters.end_date"
                        type="date"
                        :placeholder="t(`${P}.filters.end_date_ph`)"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('actions.search') }}</el-button>
                    <el-button @click="handleGenerateSnapshot">{{ t(`${P}.actions.generate_snapshot`) }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total_events }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.total_events`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dashboard.stats.active_users }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.active_users`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.stats.avg_adoption_rate >= 30 ? 'text-success' : 'text-warning'">
                        {{ dashboard.stats.avg_adoption_rate }}%
                    </div>
                    <div class="stat-label">{{ t(`${P}.stats.avg_adoption_rate`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.feature_count }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.feature_count`) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab">
            <!-- 功能概览 -->
            <el-tab-pane :label="t(`${P}.tabs.overview`)" name="overview">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t(`${P}.sections.adoption_detail`) }}</span>
                            <el-select v-model="categoryFilter" :placeholder="t(`${P}.filters.select_category`)" clearable size="small" style="width: 160px">
                                <el-option :label="t(`${P}.filters.all`)" value="" />
                                <el-option v-for="(name, key) in dashboard.categories" :key="key" :label="name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="filteredFeatures" stripe style="width: 100%">
                        <el-table-column prop="feature_name" :label="t(`${P}.columns.feature_name`)" min-width="160" />
                        <el-table-column prop="feature_key" :label="t(`${P}.columns.feature_key`)" width="160">
                            <template #default="{ row }">
                                <code>{{ row.feature_key }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" :label="t(`${P}.columns.category`)" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ dashboard.categories[row.category] || row.category }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="pv" :label="t(`${P}.columns.pv`)" width="80" sortable />
                        <el-table-column prop="uv" :label="t(`${P}.columns.uv`)" width="80" sortable />
                        <el-table-column :label="t(`${P}.columns.actions`)" width="120">
                            <template #default="{ row }">
                                <el-button size="small" link @click="showFeatureDetail(row)">{{ t(`${P}.detail`) }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 分类统计 -->
            <el-tab-pane :label="t(`${P}.tabs.category`)" name="category">
                <el-card>
                    <template #header><span>{{ t(`${P}.sections.by_category`) }}</span></template>
                    <el-table :data="dashboard.by_category" stripe>
                        <el-table-column :label="t(`${P}.columns.category`)" min-width="160">
                            <template #default="{ row }">
                                <el-tag size="large">{{ dashboard.categories[row.category] || row.category }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="pv" :label="t(`${P}.columns.pv`)" sortable width="120" />
                        <el-table-column prop="uv" :label="t(`${P}.columns.uv`)" sortable width="120" />
                        <el-table-column :label="t(`${P}.columns.actions`)" width="120">
                            <template #default="{ row }">
                                <el-button size="small" link @click="showCategoryDetail(row.category)">{{ t(`${P}.detail`) }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 漏斗分析 -->
            <el-tab-pane :label="t(`${P}.tabs.funnel`)" name="funnel">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t(`${P}.sections.funnel`) }}</span>
                            <el-select v-model="selectedFunnel" :placeholder="t(`${P}.filters.select_funnel`)" size="small" style="width: 200px">
                                <el-option v-for="(f, key) in featureDefs.funnels" :key="key" :label="f.name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>

                    <div v-if="funnelData.steps && funnelData.steps.length > 0">
                        <div class="funnel-container">
                            <div v-for="(step, i) in funnelData.steps" :key="i" class="funnel-step">
                                <div class="funnel-step-header">
                                    <span class="funnel-step-num">{{ step.step }}</span>
                                    <strong>{{ step.feature_name }}</strong>
                                    <el-tag size="small" type="info">{{ step.feature_key }}</el-tag>
                                </div>
                                <div class="funnel-bar-wrapper">
                                    <div class="funnel-bar" :style="{ width: step.conversion_rate + '%' }">
                                        <span v-if="step.conversion_rate > 15">{{ t(`${P}.funnel.users_unit`, { count: step.user_count }) }}</span>
                                    </div>
                                    <span class="funnel-bar-label">{{ step.conversion_rate }}%</span>
                                </div>
                                <div class="funnel-meta">
                                    <span>{{ t(`${P}.funnel.adoption_rate`, { rate: step.adoption_rate }) }}</span>
                                    <span v-if="step.drop_count > 0" class="text-danger">{{ t(`${P}.funnel.dropoff`, { count: step.drop_count, rate: step.drop_rate }) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="funnel-overall">
                            {{ t(`${P}.funnel.overall_conversion`, { rate: funnelData.overall_conversion }) }}
                        </div>
                    </div>
                    <div v-else-if="funnelData.error" class="empty-hint">{{ funnelData.error }}</div>
                    <div v-else class="empty-hint">{{ t(`${P}.empty.select_funnel`) }}</div>
                </el-card>
            </el-tab-pane>

            <!-- 趋势 -->
            <el-tab-pane :label="t(`${P}.tabs.trend`)" name="trend">
                <el-card>
                    <template #header><span>{{ t(`${P}.sections.daily_trend`) }}</span></template>
                    <div v-if="trendData.length === 0" class="empty-hint">{{ t(`${P}.empty.no_trend`) }}</div>
                    <div ref="trendChartRef" style="height: 350px" v-else></div>
                </el-card>
            </el-tab-pane>

            <!-- 事件记录 -->
            <el-tab-pane :label="t(`${P}.tabs.events`)" name="events">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t(`${P}.sections.raw_events`) }}</span>
                            <el-select v-model="eventFeatureFilter" :placeholder="t(`${P}.filters.filter_feature`)" clearable size="small" style="width: 180px">
                                <el-option :label="t(`${P}.filters.all`)" value="" />
                                <el-option v-for="(def, key) in featureDefs.features" :key="key" :label="def.name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="eventsList" stripe v-loading="eventsLoading">
                        <el-table-column prop="created_at" :label="t(`${P}.columns.time`)" width="160" />
                        <el-table-column prop="feature_name" :label="t(`${P}.columns.feature`)" width="140" />
                        <el-table-column prop="feature_key" :label="t(`${P}.columns.feature_key`)" width="120">
                            <template #default="{ row }"><code>{{ row.feature_key }}</code></template>
                        </el-table-column>
                        <el-table-column prop="action" :label="t(`${P}.columns.action`)" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.action }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" :label="t(`${P}.columns.category`)" width="100" />
                        <el-table-column prop="user_id" :label="t(`${P}.columns.user_id`)" width="80" />
                        <el-table-column prop="page_url" :label="t(`${P}.columns.page_url`)" min-width="200" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 功能详情对话框 -->
        <el-dialog v-model="detailVisible" :title="detailTitle" width="600px">
            <template v-if="featureDetail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t(`${P}.dialog.feature_key`)">{{ featureDetail.feature_key }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.dialog.feature_name`)">{{ featureDetail.feature_name }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.dialog.category`)">{{ featureDetail.category }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.dialog.total_events`)">{{ featureDetail.total_events }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.dialog.unique_users`)">{{ featureDetail.unique_users }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.dialog.adoption_rate`)">{{ featureDetail.adoption_rate }}%</el-descriptions-item>
                </el-descriptions>

                <h4 style="margin: 16px 0 8px">{{ t(`${P}.sections.by_action`) }}</h4>
                <el-table :data="featureDetail.by_action" size="small">
                    <el-table-column prop="action" :label="t(`${P}.columns.action`)" />
                    <el-table-column prop="count" :label="t(`${P}.columns.count`)" />
                </el-table>

                <h4 style="margin: 16px 0 8px">{{ t(`${P}.sections.trend`) }}</h4>
                <div v-if="featureDetail.trend && featureDetail.trend.length > 0" class="trend-mini">
                    <div v-for="point in featureDetail.trend.slice(-14)" :key="point.date" class="trend-mini-item">
                        <span class="trend-date">{{ point.date.slice(5) }}</span>
                        <div class="trend-mini-bar" :style="{ height: Math.max(point.pv / 2, 4) + 'px', background: '#0f172a' }"></div>
                        <span class="trend-pv">{{ point.pv }}</span>
                    </div>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import featureAdoption from '@/api/featureAdoption';

const P = 'feature_adoption_page';
const { t } = useI18n();

const activeTab = ref('overview');
const categoryFilter = ref('');
const eventFeatureFilter = ref('');
const selectedFunnel = ref('');
const detailVisible = ref(false);
const detailTitle = ref('');
const eventsLoading = ref(false);
const trendChartRef = ref(null);

const filters = reactive({
    start_date: '',
    end_date: '',
});

const dashboard = reactive({
    stats: { total_events: 0, active_users: 0, avg_adoption_rate: 0, feature_count: 0 },
    by_feature: [],
    by_category: [],
    top_features: [],
    categories: {},
    features_def: {},
});

const featureDefs = reactive({
    features: {},
    categories: {},
    funnels: {},
});

const featureDetail = ref(null);
const trendData = ref([]);
const eventsList = ref([]);
const funnelData = reactive({});

const filteredFeatures = computed(() => {
    const list = dashboard.by_feature || [];
    if (!categoryFilter.value) return list;
    return list.filter(f => f.category === categoryFilter.value);
});

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

async function loadData() {
    await Promise.all([
        loadDashboard(),
        loadFeatureDefs(),
        loadTrend(),
    ]);
}

async function loadDashboard() {
    try {
        const res = await featureAdoption.dashboard({
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        Object.assign(dashboard, res.data.data);
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    }
}

async function loadFeatureDefs() {
    try {
        const res = await featureAdoption.featureDefs();
        Object.assign(featureDefs, res.data.data);
    } catch (e) {
        console.error('Failed to load feature defs:', e);
    }
}

async function loadTrend() {
    try {
        const res = await featureAdoption.trend({
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        trendData.value = res.data.data || [];
        await nextTick();
        renderTrendChart();
    } catch (e) {
        console.error('Failed to load trend:', e);
    }
}

async function loadEvents() {
    eventsLoading.value = true;
    try {
        const params = {
            start_date: filters.start_date,
            end_date: filters.end_date,
            per_page: 50,
        };
        if (eventFeatureFilter.value) params.feature_key = eventFeatureFilter.value;
        const res = await featureAdoption.events(params);
        eventsList.value = res.data.data.items || [];
    } catch (e) {
        console.error('Failed to load events:', e);
    } finally {
        eventsLoading.value = false;
    }
}

async function showFeatureDetail(row) {
    detailTitle.value = t(`${P}.dialog.feature_detail_title`, { name: row.feature_name });
    try {
        const res = await featureAdoption.featureDetail(row.feature_key, {
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        featureDetail.value = res.data.data;
        detailVisible.value = true;
    } catch (e) {
        console.error('Failed to load feature detail:', e);
    }
}

function showCategoryDetail(category) {
    categoryFilter.value = category;
    activeTab.value = 'overview';
}

async function loadFunnel() {
    if (!selectedFunnel.value) return;
    try {
        const res = await featureAdoption.funnel(selectedFunnel.value, {
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        Object.assign(funnelData, res.data.data);
    } catch (e) {
        console.error('Failed to load funnel:', e);
    }
}

async function handleGenerateSnapshot() {
    try {
        await featureAdoption.generateSnapshot();
        ElMessage.success(t(`${P}.messages.snapshot_generated`));
        loadTrend();
    } catch (e) {
        console.error('Failed to generate snapshot:', e);
    }
}

function renderTrendChart() {
    if (!trendChartRef.value || trendData.value.length === 0) return;
    const container = trendChartRef.value;
    const maxPv = Math.max(...trendData.value.map(item => item.total_pv || 0), 1);
    const pvLabel = t(`${P}.columns.pv`);
    const uvLabel = t(`${P}.columns.uv`);
    container.innerHTML = '<div style="padding: 16px; max-height: 350px; overflow-y: auto;">' +
        trendData.value.map(item =>
            `<div style="display: flex; align-items: center; margin-bottom: 6px; gap: 8px;">
                <span style="min-width: 80px; font-size: 12px; color: #909399;">${item.snapshot_date}</span>
                <div style="flex: 1; height: 20px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                    <div style="width: ${(item.total_pv || 0) / maxPv * 100}%; background: #0f172a; height: 100%; border-radius: 4px;"></div>
                </div>
                <span style="min-width: 60px; font-size: 12px; text-align: right;">
                    ${pvLabel}: ${item.total_pv || 0} | ${uvLabel}: ${item.total_uv || 0}
                </span>
            </div>`
        ).join('') +
    '</div>';
}

watch(activeTab, (tab) => {
    if (tab === 'events') loadEvents();
    else if (tab === 'trend') nextTick(renderTrendChart);
});

watch(selectedFunnel, () => {
    if (selectedFunnel.value) loadFunnel();
});

watch(eventFeatureFilter, () => {
    if (activeTab.value === 'events') loadEvents();
});

watch(categoryFilter, () => {
    // Just recompute filteredFeatures
});

onMounted(() => {
    setDefaultDates();
    loadData();
});
</script>

<style scoped>
.feature-adoption-container {
    padding: 20px;
}

.alert-info {
    margin: 16px 0;
}

.filter-card {
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-cards .el-card {
    text-align: center;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #303133;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.text-success { color: #67c23a; }
.text-primary { color: #0f172a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }

.empty-hint {
    text-align: center;
    padding: 40px;
    color: #909399;
}

.funnel-container {
    padding: 16px 0;
}

.funnel-step {
    margin-bottom: 20px;
}

.funnel-step-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.funnel-step-num {
    width: 24px;
    height: 24px;
    background: #0f172a;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.funnel-bar-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.funnel-bar {
    height: 32px;
    background: linear-gradient(90deg, #0f172a, #94a3b8);
    border-radius: 6px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    color: white;
    font-size: 13px;
    font-weight: bold;
    min-width: 60px;
    transition: width 0.5s ease;
}

.funnel-bar-label {
    font-size: 13px;
    color: #909399;
    min-width: 50px;
}

.funnel-meta {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.funnel-overall {
    text-align: center;
    padding: 16px;
    font-size: 16px;
    color: #303133;
}

.trend-mini {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 120px;
    padding: 10px 0;
}

.trend-mini-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
}

.trend-mini-bar {
    width: 100%;
    max-width: 20px;
    border-radius: 3px 3px 0 0;
    min-height: 4px;
}

.trend-date {
    font-size: 10px;
    color: #909399;
    margin-top: 4px;
}

.trend-pv {
    font-size: 10px;
    color: #909399;
    margin-bottom: 2px;
}
</style>
