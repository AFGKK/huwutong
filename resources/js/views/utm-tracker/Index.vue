<template>
    <div class="utm-tracker-container">
        <el-page-header :content="'UTM/渠道归因追踪'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="追踪用户来源渠道（UTM参数），支持首次接触/最后接触/线性/时间衰减多种归因模型，自动生成渠道ROI分析报告。"
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
                <el-form-item label="归因模型">
                    <el-select v-model="attributionModel" placeholder="选择归因模型">
                        <el-option
                            v-for="(label, key) in options.attribution_models"
                            :key="key"
                            :label="label"
                            :value="key"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadDashboard">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.total_visits }}</div>
                    <div class="stat-label">总访问次数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.total_conversions }}</div>
                    <div class="stat-label">转化数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.overall_rate }}%</div>
                    <div class="stat-label">整体转化率</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ attributionReport.total_conversions }}</div>
                    <div class="stat-label">归因转化数</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab">
            <!-- 渠道概览 -->
            <el-tab-pane label="渠道概览" name="channels">
                <el-card>
                    <template #header>
                        <span>渠道转化概览</span>
                    </template>
                    <el-table :data="dashboard.by_channel" stripe style="width: 100%">
                        <el-table-column prop="channel" label="渠道分组" />
                        <el-table-column prop="visits" label="访问次数" sortable />
                        <el-table-column prop="conversions" label="转化数" sortable />
                        <el-table-column prop="conversion_rate" label="转化率" sortable>
                            <template #default="{ row }">
                                <el-tag :type="row.conversion_rate > 5 ? 'success' : row.conversion_rate > 1 ? 'warning' : 'info'">
                                    {{ row.conversion_rate }}%
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 归因报告 -->
            <el-tab-pane label="归因报告" name="attribution">
                <el-card>
                    <template #header>
                        <span>
                            归因报告（模型：{{ attributionReport.model_label }}）
                        </span>
                    </template>
                    <el-table :data="attributionReport.channels" stripe style="width: 100%">
                        <el-table-column prop="channel" label="渠道" />
                        <el-table-column prop="conversions" label="转化数" sortable />
                        <el-table-column prop="percentage" label="占比" sortable>
                            <template #default="{ row }">
                                <el-progress :percentage="row.percentage" :stroke-width="16" />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 来源详情 -->
            <el-tab-pane label="来源详情" name="sources">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>来源/媒介详细统计</span>
                            <el-select
                                v-model="sourceChannelFilter"
                                placeholder="渠道分组筛选"
                                clearable
                                size="small"
                                style="width: 160px"
                            >
                                <el-option
                                    v-for="ch in dashboard.channel_groups"
                                    :key="ch"
                                    :label="ch"
                                    :value="ch"
                                />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="sourceDetail.sources" stripe style="width: 100%">
                        <el-table-column prop="source" label="来源" />
                        <el-table-column prop="visits" label="访问" sortable />
                        <el-table-column prop="conversions" label="转化" sortable />
                        <el-table-column prop="rate" label="转化率" sortable>
                            <template #default="{ row }">
                                {{ row.rate }}%
                            </template>
                        </el-table-column>
                        <el-table-column label="媒介细分">
                            <template #default="{ row }">
                                <el-popover placement="bottom" :width="300" trigger="click">
                                    <template #reference>
                                        <el-button size="small" link>查看媒介</el-button>
                                    </template>
                                    <el-table :data="mediumList(row.mediums)" size="small">
                                        <el-table-column prop="medium" label="媒介" />
                                        <el-table-column prop="visits" label="访问" width="80" />
                                        <el-table-column prop="conversions" label="转化" width="80" />
                                    </el-table>
                                </el-popover>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 记录列表 -->
            <el-tab-pane label="记录列表" name="records">
                <el-card>
                    <el-table :data="records.data" stripe style="width: 100%" v-loading="recordsLoading">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column prop="utm_source" label="来源" width="120" />
                        <el-table-column prop="utm_medium" label="媒介" width="100" />
                        <el-table-column prop="utm_campaign" label="活动" width="120" />
                        <el-table-column prop="channel_group" label="渠道分组" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.channel_group }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="attribution_type" label="类型" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.attribution_type === 'conversion' ? 'success' : 'info'" size="small">
                                    {{ row.attribution_type === 'conversion' ? '转化' : '访问' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="landing_page" label="落地页" min-width="200" show-overflow-tooltip />
                    </el-table>
                    <el-pagination
                        v-if="records.total > 0"
                        v-model:current-page="records.current_page"
                        :page-size="records.per_page"
                        :total="records.total"
                        layout="prev, pager, next"
                        @current-change="loadRecords"
                        class="pagination"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import utmTracker from '@/api/utmTracker';

const activeTab = ref('channels');
const attributionModel = ref('first_touch');
const sourceChannelFilter = ref('');
const recordsLoading = ref(false);

const filters = reactive({
    start_date: '',
    end_date: '',
});

const options = reactive({
    channel_groups: [],
    attribution_models: {},
    utm_params: [],
});

const dashboard = reactive({
    total_visits: 0,
    total_conversions: 0,
    overall_rate: 0,
    by_channel: [],
    by_source: [],
    channel_groups: [],
    attribution_models: {},
});

const attributionReport = reactive({
    total_conversions: 0,
    model_label: '',
    channels: [],
});

const sourceDetail = reactive({
    sources: [],
});

const records = reactive({
    data: [],
    total: 0,
    current_page: 1,
    per_page: 20,
});

// 工具：将 mediums 对象转为数组
function mediumList(mediums) {
    if (!mediums) return [];
    return Object.entries(mediums).map(([medium, data]) => ({
        medium: medium || '(无)',
        visits: data.visits || 0,
        conversions: data.conversions || 0,
    }));
}

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

async function loadOptions() {
    try {
        const res = await utmTracker.options();
        Object.assign(options, res.data.data);
    } catch (e) {
        console.error('Failed to load options:', e);
    }
}

async function loadDashboard() {
    try {
        const res = await utmTracker.dashboard({
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        Object.assign(dashboard, res.data.data);
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    }
}

async function loadAttributionReport() {
    try {
        const res = await utmTracker.attributionReport({
            start_date: filters.start_date,
            end_date: filters.end_date,
            model: attributionModel.value,
        });
        Object.assign(attributionReport, res.data.data);
    } catch (e) {
        console.error('Failed to load attribution report:', e);
    }
}

async function loadSourceDetail() {
    try {
        const params = {
            start_date: filters.start_date,
            end_date: filters.end_date,
        };
        if (sourceChannelFilter.value) {
            params.channel_group = sourceChannelFilter.value;
        }
        const res = await utmTracker.sourceDetail(params);
        Object.assign(sourceDetail, res.data.data);
    } catch (e) {
        console.error('Failed to load source detail:', e);
    }
}

async function loadRecords(page) {
    recordsLoading.value = true;
    try {
        const params = {
            start_date: filters.start_date,
            end_date: filters.end_date,
            page: page || records.current_page,
            per_page: records.per_page,
        };
        const res = await utmTracker.records(params);
        records.data = res.data.data;
        records.total = res.data.total;
        records.current_page = res.data.current_page;
        records.per_page = res.data.per_page;
    } catch (e) {
        console.error('Failed to load records:', e);
    } finally {
        recordsLoading.value = false;
    }
}

watch(activeTab, (tab) => {
    if (tab === 'attribution') loadAttributionReport();
    else if (tab === 'sources') loadSourceDetail();
    else if (tab === 'records') loadRecords(1);
});

watch(sourceChannelFilter, () => {
    if (activeTab.value === 'sources') loadSourceDetail();
});

onMounted(() => {
    setDefaultDates();
    loadOptions();
    loadDashboard();
});
</script>

<style scoped>
.utm-tracker-container {
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

.text-success {
    color: #67c23a;
}

.text-warning {
    color: #e6a23c;
}

.text-primary {
    color: #409eff;
}

.pagination {
    margin-top: 16px;
    text-align: center;
}
</style>
