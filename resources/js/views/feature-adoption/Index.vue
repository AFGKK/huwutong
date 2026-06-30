<template>
    <div class="feature-adoption-container">
        <el-page-header :content="'功能使用率追踪'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="追踪各功能模块的 PV/UV、使用频率趋势、功能漏斗和采用率，帮助运营团队了解产品功能使用情况。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 日期筛选 -->
        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item label="开始日期">
                    <el-date-picker
                        v-model="filters.start_date"
                        type="date"
                        placeholder="选择开始日期"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item label="结束日期">
                    <el-date-picker
                        v-model="filters.end_date"
                        type="date"
                        placeholder="选择结束日期"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">查询</el-button>
                    <el-button @click="handleGenerateSnapshot">生成快照</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total_events }}</div>
                    <div class="stat-label">总事件数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dashboard.stats.active_users }}</div>
                    <div class="stat-label">活跃用户数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.stats.avg_adoption_rate >= 30 ? 'text-success' : 'text-warning'">
                        {{ dashboard.stats.avg_adoption_rate }}%
                    </div>
                    <div class="stat-label">平均采用率</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.feature_count }}</div>
                    <div class="stat-label">功能数</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab">
            <!-- 功能概览 -->
            <el-tab-pane label="功能概览" name="overview">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>功能采用率详情</span>
                            <el-select v-model="categoryFilter" placeholder="选择分类" clearable size="small" style="width: 160px">
                                <el-option label="全部" value="" />
                                <el-option v-for="(name, key) in dashboard.categories" :key="key" :label="name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="filteredFeatures" stripe style="width: 100%">
                        <el-table-column prop="feature_name" label="功能名称" min-width="160" />
                        <el-table-column prop="feature_key" label="标识符" width="160">
                            <template #default="{ row }">
                                <code>{{ row.feature_key }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" label="分类" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ dashboard.categories[row.category] || row.category }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="pv" label="PV" width="80" sortable />
                        <el-table-column prop="uv" label="UV" width="80" sortable />
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button size="small" link @click="showFeatureDetail(row)">详情</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 分类统计 -->
            <el-tab-pane label="分类统计" name="category">
                <el-card>
                    <template #header><span>按分类统计</span></template>
                    <el-table :data="dashboard.by_category" stripe>
                        <el-table-column label="分类" min-width="160">
                            <template #default="{ row }">
                                <el-tag size="large">{{ dashboard.categories[row.category] || row.category }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="pv" label="PV" sortable width="120" />
                        <el-table-column prop="uv" label="UV" sortable width="120" />
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button size="small" link @click="showCategoryDetail(row.category)">详情</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 漏斗分析 -->
            <el-tab-pane label="漏斗分析" name="funnel">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>漏斗分析</span>
                            <el-select v-model="selectedFunnel" placeholder="选择漏斗" size="small" style="width: 200px">
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
                                        <span v-if="step.conversion_rate > 15">{{ step.user_count }} 人</span>
                                    </div>
                                    <span class="funnel-bar-label">{{ step.conversion_rate }}%</span>
                                </div>
                                <div class="funnel-meta">
                                    <span>采用率: {{ step.adoption_rate }}%</span>
                                    <span v-if="step.drop_count > 0" class="text-danger">流失: {{ step.drop_count }} 人 ({{ step.drop_rate }}%)</span>
                                </div>
                            </div>
                        </div>
                        <div class="funnel-overall">
                            整体转化率: <strong>{{ funnelData.overall_conversion }}%</strong>
                        </div>
                    </div>
                    <div v-else-if="funnelData.error" class="empty-hint">{{ funnelData.error }}</div>
                    <div v-else class="empty-hint">请选择要分析的漏斗</div>
                </el-card>
            </el-tab-pane>

            <!-- 趋势 -->
            <el-tab-pane label="趋势" name="trend">
                <el-card>
                    <template #header><span>每日趋势</span></template>
                    <div v-if="trendData.length === 0" class="empty-hint">暂无趋势数据，请先生成每日快照</div>
                    <div ref="trendChartRef" style="height: 350px" v-else></div>
                </el-card>
            </el-tab-pane>

            <!-- 事件记录 -->
            <el-tab-pane label="事件记录" name="events">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>原始事件记录</span>
                            <el-select v-model="eventFeatureFilter" placeholder="筛选功能" clearable size="small" style="width: 180px">
                                <el-option label="全部" value="" />
                                <el-option v-for="(def, key) in featureDefs.features" :key="key" :label="def.name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="eventsList" stripe v-loading="eventsLoading">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column prop="feature_name" label="功能" width="140" />
                        <el-table-column prop="feature_key" label="标识符" width="120">
                            <template #default="{ row }"><code>{{ row.feature_key }}</code></template>
                        </el-table-column>
                        <el-table-column prop="action" label="操作" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.action }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" label="分类" width="100" />
                        <el-table-column prop="user_id" label="用户ID" width="80" />
                        <el-table-column prop="page_url" label="页面URL" min-width="200" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 功能详情对话框 -->
        <el-dialog v-model="detailVisible" :title="detailTitle" width="600px">
            <template v-if="featureDetail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="功能标识">{{ featureDetail.feature_key }}</el-descriptions-item>
                    <el-descriptions-item label="功能名称">{{ featureDetail.feature_name }}</el-descriptions-item>
                    <el-descriptions-item label="分类">{{ featureDetail.category }}</el-descriptions-item>
                    <el-descriptions-item label="总事件">{{ featureDetail.total_events }}</el-descriptions-item>
                    <el-descriptions-item label="独立用户">{{ featureDetail.unique_users }}</el-descriptions-item>
                    <el-descriptions-item label="采用率">{{ featureDetail.adoption_rate }}%</el-descriptions-item>
                </el-descriptions>

                <h4 style="margin: 16px 0 8px">按操作类型</h4>
                <el-table :data="featureDetail.by_action" size="small">
                    <el-table-column prop="action" label="操作" />
                    <el-table-column prop="count" label="次数" />
                </el-table>

                <h4 style="margin: 16px 0 8px">趋势</h4>
                <div v-if="featureDetail.trend && featureDetail.trend.length > 0" class="trend-mini">
                    <div v-for="t in featureDetail.trend.slice(-14)" :key="t.date" class="trend-mini-item">
                        <span class="trend-date">{{ t.date.slice(5) }}</span>
                        <div class="trend-mini-bar" :style="{ height: Math.max(t.pv / 2, 4) + 'px', background: '#409eff' }"></div>
                        <span class="trend-pv">{{ t.pv }}</span>
                    </div>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import featureAdoption from '@/api/featureAdoption';

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
    detailTitle.value = `功能详情: ${row.feature_name}`;
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
        ElMessage.success('每日快照已生成');
        loadTrend();
    } catch (e) {
        console.error('Failed to generate snapshot:', e);
    }
}

function renderTrendChart() {
    if (!trendChartRef.value || trendData.value.length === 0) return;
    const container = trendChartRef.value;
    const maxPv = Math.max(...trendData.value.map(t => t.total_pv || 0), 1);
    container.innerHTML = '<div style="padding: 16px; max-height: 350px; overflow-y: auto;">' +
        trendData.value.map(item =>
            `<div style="display: flex; align-items: center; margin-bottom: 6px; gap: 8px;">
                <span style="min-width: 80px; font-size: 12px; color: #909399;">${item.snapshot_date}</span>
                <div style="flex: 1; height: 20px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                    <div style="width: ${(item.total_pv || 0) / maxPv * 100}%; background: #409eff; height: 100%; border-radius: 4px;"></div>
                </div>
                <span style="min-width: 60px; font-size: 12px; text-align: right;">
                    PV: ${item.total_pv || 0} | UV: ${item.total_uv || 0}
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
.text-primary { color: #409eff; }
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
    background: #409eff;
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
    background: linear-gradient(90deg, #409eff, #79bbff);
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
