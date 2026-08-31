<template>
    <div class="nps-survey-container">
        <el-page-header :content="t('nps_survey_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('nps_survey_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 日期筛选 -->
        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t('nps_survey_page.filters.start_date')">
                    <el-date-picker
                        v-model="filters.start_date"
                        type="date"
                        :placeholder="t('nps_survey_page.filters.start_date_ph')"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item :label="t('nps_survey_page.filters.end_date')">
                    <el-date-picker
                        v-model="filters.end_date"
                        type="date"
                        :placeholder="t('nps_survey_page.filters.end_date_ph')"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="npsScoreClass">{{ dashboard.stats.nps_score }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.nps_score') }}</div>
                    <div class="stat-sub">{{ t('nps_survey_page.stats.target', { score: dashboard.stats.target_score }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.stats.promoters }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.promoters') }}</div>
                    <div class="stat-sub">{{ t('nps_survey_page.stats.pct', { pct: categoryPct('promoters') }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.stats.passives }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.passives') }}</div>
                    <div class="stat-sub">{{ t('nps_survey_page.stats.pct', { pct: categoryPct('passives') }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dashboard.stats.detractors }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.detractors') }}</div>
                    <div class="stat-sub">{{ t('nps_survey_page.stats.pct', { pct: categoryPct('detractors') }) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行：调查统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.surveys_sent }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.surveys_sent') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.surveys_completed }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.surveys_completed') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.stats.response_rate >= 30 ? 'text-success' : 'text-warning'">
                        {{ dashboard.stats.response_rate }}%
                    </div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.response_rate') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total_responses }}</div>
                    <div class="stat-label">{{ t('nps_survey_page.stats.total_responses') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab">
            <!-- 趋势 -->
            <el-tab-pane :label="t('nps_survey_page.tabs.trend')" name="trend">
                <el-card>
                    <template #header>
                        <span>{{ t('nps_survey_page.sections.trend_title') }}</span>
                    </template>
                    <div v-if="trendData.length === 0" class="empty-hint">{{ t('nps_survey_page.empty_trend') }}</div>
                    <div ref="trendChartRef" style="height: 350px" v-else></div>
                    <div class="action-bar">
                        <el-button size="small" @click="handleGenerateSnapshot">{{ t('nps_survey_page.generate_snapshot') }}</el-button>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 评分分布 -->
            <el-tab-pane :label="t('nps_survey_page.tabs.distribution')" name="distribution">
                <el-card>
                    <template #header>
                        <span>{{ t('nps_survey_page.sections.distribution_title', { count: report.total_responses }) }}</span>
                    </template>
                    <div ref="distChartRef" style="height: 350px"></div>
                    <el-table :data="scoreDistList" stripe style="margin-top: 16px">
                        <el-table-column prop="score" :label="t('nps_survey_page.cols.score')" width="80" />
                        <el-table-column :label="t('nps_survey_page.cols.count')" width="120">
                            <template #default="{ row }">
                                <span>{{ row.count }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('nps_survey_page.cols.pct')">
                            <template #default="{ row }">
                                <el-progress
                                    :percentage="report.total_responses > 0 ? Math.round(row.count / report.total_responses * 100) : 0"
                                    :color="scoreBarColor(row.score)"
                                    :stroke-width="20"
                                />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 反馈列表 -->
            <el-tab-pane :label="t('nps_survey_page.tabs.feedback')" name="feedback">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('nps_survey_page.tabs.feedback') }}</span>
                            <el-select v-model="feedbackFilter" :placeholder="t('nps_survey_page.filter_category_ph')" clearable size="small" style="width: 140px">
                                <el-option
                                    v-for="opt in feedbackCategoryOptions"
                                    :key="opt.value || 'all'"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="filteredFeedback" stripe style="width: 100%">
                        <el-table-column prop="created_at" :label="t('nps_survey_page.cols.time')" width="160" />
                        <el-table-column :label="t('nps_survey_page.cols.score')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="scoreTagType(row.score)" size="large">{{ row.score }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" :label="t('nps_survey_page.cols.category')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.category === 'promoter' ? 'success' : row.category === 'detractor' ? 'danger' : 'warning'" size="small">
                                    {{ feedbackCategoryLabel(row.category) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="feedback" :label="t('nps_survey_page.cols.feedback')" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="best_feature" :label="t('nps_survey_page.cols.best_feature')" min-width="180" show-overflow-tooltip />
                        <el-table-column prop="improvement" :label="t('nps_survey_page.cols.improvement')" min-width="180" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 调查管理 -->
            <el-tab-pane :label="t('nps_survey_page.tabs.manage')" name="manage">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('nps_survey_page.sections.manage_title') }}</span>
                            <el-button size="small" type="primary" @click="showSendDialog = true">{{ t('nps_survey_page.send_survey') }}</el-button>
                        </el-space>
                    </template>

                    <el-table :data="surveysList" stripe v-loading="surveysLoading">
                        <el-table-column prop="id" :label="t('nps_survey_page.cols.id')" width="60" />
                        <el-table-column :label="t('nps_survey_page.cols.user')" min-width="150">
                            <template #default="{ row }">
                                <div>{{ row.user?.name || t('nps_survey_page.unknown') }}</div>
                                <div class="text-muted">{{ row.user?.email }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('nps_survey_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'sent' ? 'primary' : row.status === 'expired' ? 'info' : 'warning'" size="small">
                                    {{ surveyStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('nps_survey_page.cols.channel')" width="80">
                            <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('nps_survey_page.cols.score')" width="80">
                            <template #default="{ row }">
                                <span v-if="row.response">{{ row.response.score }}</span>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="sent_at" :label="t('nps_survey_page.cols.sent_at')" width="160" />
                        <el-table-column prop="completed_at" :label="t('nps_survey_page.cols.completed_at')" width="160" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 发送调查对话框 -->
        <el-dialog v-model="showSendDialog" :title="t('nps_survey_page.send_dialog_title')" width="500px">
            <el-form label-position="top">
                <el-form-item :label="t('nps_survey_page.form.select_user')">
                    <el-select
                        v-model="sendForm.user_id"
                        filterable
                        remote
                        :remote-method="searchUsers"
                        :placeholder="t('nps_survey_page.form.search_user_ph')"
                        style="width: 100%"
                        :loading="userSearchLoading"
                    >
                        <el-option
                            v-for="u in eligibleUsersList"
                            :key="u.id"
                            :label="`${u.name} (${u.email})`"
                            :value="u.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('nps_survey_page.form.channel')">
                    <el-radio-group v-model="sendForm.channel">
                        <el-radio v-for="opt in channelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSendDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="sending" @click="handleSendSurvey">{{ t('nps_survey_page.send_survey') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import npsSurvey from '@/api/npsSurvey';

const { t } = useI18n();

const activeTab = ref('trend');
const showSendDialog = ref(false);
const sending = ref(false);
const surveysLoading = ref(false);
const userSearchLoading = ref(false);
const trendChartRef = ref(null);
const distChartRef = ref(null);
const feedbackFilter = ref('');

const filters = reactive({
    start_date: '',
    end_date: '',
});

const sendForm = reactive({
    user_id: null,
    channel: 'email',
});

const dashboard = reactive({
    stats: {
        nps_score: 0,
        target_score: 50,
        promoters: 0,
        passives: 0,
        detractors: 0,
        surveys_sent: 0,
        surveys_completed: 0,
        response_rate: 0,
        total_responses: 0,
    },
    trend: [],
    recent_feedback: [],
});

const trendData = ref([]);
const report = reactive({
    total_responses: 0,
    score_distribution: {},
    by_category: {},
    nps_score: 0,
});

const surveysList = ref([]);
const eligibleUsersList = ref([]);

const feedbackCategoryKeys = ['promoter', 'passive', 'detractor'];
const surveyStatusKeys = ['completed', 'sent', 'expired', 'pending'];
const channelKeys = [
    { value: 'email', key: 'email' },
    { value: 'in-app', key: 'in_app' },
    { value: 'popup', key: 'popup' },
];

const feedbackCategoryOptions = computed(() => [
    { label: t('nps_survey_page.all'), value: '' },
    ...feedbackCategoryKeys.map((key) => ({
        label: t(`nps_survey_page.category.${key}`),
        value: key,
    })),
]);

const feedbackCategoryLabels = computed(() => Object.fromEntries(
    feedbackCategoryKeys.map((key) => [key, t(`nps_survey_page.category.${key}`)]),
));

const surveyStatusLabels = computed(() => Object.fromEntries(
    surveyStatusKeys.map((key) => [key, t(`nps_survey_page.status.${key}`)]),
));

const channelLabels = computed(() => Object.fromEntries(
    channelKeys.map(({ value, key }) => [value, t(`nps_survey_page.channel.${key}`)]),
));

const channelOptions = computed(() => channelKeys.map(({ value, key }) => ({
    value,
    label: t(`nps_survey_page.channel.${key}`),
})));

function feedbackCategoryLabel(category) {
    return feedbackCategoryLabels.value[category] || category;
}

function surveyStatusLabel(status) {
    return surveyStatusLabels.value[status] || status;
}

function channelLabel(channel) {
    return channelLabels.value[channel] || channel;
}

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

function scoreTagType(score) {
    if (score >= 9) return 'success';
    if (score >= 7) return 'warning';
    return 'danger';
}

function scoreBarColor(score) {
    if (score >= 9) return '#67c23a';
    if (score >= 7) return '#e6a23c';
    return '#f56c6c';
}

const npsScoreClass = computed(() => {
    const s = dashboard.stats.nps_score;
    if (s >= dashboard.stats.target_score) return 'text-success';
    if (s >= 0) return 'text-warning';
    return 'text-danger';
});

function categoryPct(cat) {
    const total = dashboard.stats.total_responses;
    if (!total) return 0;
    return ((dashboard.stats[cat] || 0) / total * 100).toFixed(1);
}

const filteredFeedback = computed(() => {
    if (!feedbackFilter.value) return dashboard.recent_feedback;
    return dashboard.recent_feedback.filter(f => f.category === feedbackFilter.value);
});

const scoreDistList = computed(() => {
    const dist = report.score_distribution || {};
    return Object.entries(dist).map(([score, count]) => ({
        score: parseInt(score),
        count,
    })).sort((a, b) => a.score - b.score);
});

async function loadData() {
    await Promise.all([
        loadDashboard(),
        loadReport(),
        loadTrend(),
        loadSurveys(),
    ]);
}

async function loadDashboard() {
    try {
        const res = await npsSurvey.dashboard({
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        Object.assign(dashboard, res.data.data);
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    }
}

async function loadReport() {
    try {
        const res = await npsSurvey.report({
            start_date: filters.start_date,
            end_date: filters.end_date,
        });
        Object.assign(report, res.data.data);
    } catch (e) {
        console.error('Failed to load report:', e);
    }
}

async function loadTrend() {
    try {
        const res = await npsSurvey.trend({ days: 90 });
        trendData.value = res.data.data || [];
        await nextTick();
        renderTrendChart();
    } catch (e) {
        console.error('Failed to load trend:', e);
    }
}

async function loadSurveys() {
    surveysLoading.value = true;
    try {
        const res = await npsSurvey.surveys({ per_page: 50 });
        surveysList.value = res.data.data.items || [];
    } catch (e) {
        console.error('Failed to load surveys:', e);
    } finally {
        surveysLoading.value = false;
    }
}

async function searchUsers(query) {
    if (!query) return;
    userSearchLoading.value = true;
    try {
        const res = await npsSurvey.eligibleUsers({ limit: 20 });
        eligibleUsersList.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to search users:', e);
    } finally {
        userSearchLoading.value = false;
    }
}

async function handleSendSurvey() {
    if (!sendForm.user_id) {
        ElMessage.warning(t('nps_survey_page.messages.select_user'));
        return;
    }
    sending.value = true;
    try {
        await npsSurvey.sendSurvey({
            user_id: sendForm.user_id,
            channel: sendForm.channel,
        });
        ElMessage.success(t('nps_survey_page.messages.survey_sent'));
        showSendDialog.value = false;
        loadSurveys();
    } catch (e) {
        console.error('Failed to send survey:', e);
    } finally {
        sending.value = false;
    }
}

async function handleGenerateSnapshot() {
    try {
        await npsSurvey.generateSnapshot();
        ElMessage.success(t('nps_survey_page.messages.snapshot_generated'));
        loadTrend();
    } catch (e) {
        console.error('Failed to generate snapshot:', e);
    }
}

function renderTrendChart() {
    if (!trendChartRef.value || trendData.value.length === 0) return;
    const chartContainer = trendChartRef.value;
    chartContainer.innerHTML = '<div style="padding: 16px; max-height: 350px; overflow-y: auto;">' +
        trendData.value.map(item =>
            `<div style="display: flex; align-items: center; margin-bottom: 8px; gap: 8px;">
                <span style="min-width: 80px; font-size: 12px; color: #909399;">${item.snapshot_date || item.date}</span>
                <div style="flex: 1; height: 24px; background: #f5f5f5; border-radius: 4px; overflow: hidden; display: flex;">
                    <div style="width: ${item.promoters / (item.total_responses || 1) * 100}%; background: #67c23a; height: 100%;" title="${t('nps_survey_page.chart.promoter_tooltip', { count: item.promoters })}"></div>
                    <div style="width: ${item.passives / (item.total_responses || 1) * 100}%; background: #e6a23c; height: 100%;" title="${t('nps_survey_page.chart.passive_tooltip', { count: item.passives })}"></div>
                    <div style="width: ${item.detractors / (item.total_responses || 1) * 100}%; background: #f56c6c; height: 100%;" title="${t('nps_survey_page.chart.detractor_tooltip', { count: item.detractors })}"></div>
                </div>
                <span style="min-width: 60px; font-weight: bold; font-size: 14px;" class="${item.nps_score >= (dashboard.stats.target_score || 50) ? 'text-success' : item.nps_score >= 0 ? 'text-warning' : 'text-danger'}">
                    ${item.nps_score}
                </span>
            </div>`
        ).join('') +
    '</div>';
}

function renderDistChart() {
    if (!distChartRef.value) return;
    const dist = report.score_distribution || {};
    const entries = Object.entries(dist).sort((a, b) => parseInt(a[0]) - parseInt(b[0]));
    const maxCount = Math.max(...entries.map(([, c]) => c), 1);
    const chartContainer = distChartRef.value;
    chartContainer.innerHTML = '<div style="display: flex; align-items: flex-end; height: 300px; gap: 4px; padding: 20px 10px;">' +
        entries.map(([score, count]) =>
            `<div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;">
                <span style="font-size: 11px; margin-bottom: 2px;">${count}</span>
                <div style="width: 100%; max-width: 40px; height: ${count / maxCount * 250}px; background: ${scoreBarColor(parseInt(score))}; border-radius: 4px 4px 0 0; transition: height 0.3s;"></div>
                <span style="font-size: 12px; margin-top: 4px; color: #909399;">${score}</span>
            </div>`
        ).join('') +
    '</div>';
}

watch(activeTab, (tab) => {
    if (tab === 'distribution') {
        nextTick(renderDistChart);
    } else if (tab === 'trend') {
        nextTick(renderTrendChart);
    }
});

watch(feedbackFilter, () => {
    // Just recompute filteredFeedback
});

onMounted(() => {
    setDefaultDates();
    searchUsers('');
    loadData();
});
</script>

<style scoped>
.nps-survey-container {
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

.stat-sub {
    font-size: 11px;
    color: #c0c4cc;
    margin-top: 2px;
}

.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-muted { color: #909399; }

.action-bar {
    margin-top: 12px;
    text-align: right;
}

.empty-hint {
    text-align: center;
    padding: 40px;
    color: #909399;
}
</style>
