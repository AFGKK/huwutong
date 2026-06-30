<template>
    <div class="nps-survey-container">
        <el-page-header :content="'NPS 客户满意度调查'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="定期收集客户满意度评分（0-10分），自动分类为推荐者(9-10)、被动者(7-8)、贬损者(0-6)，贬损者自动创建跟进工单。"
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
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="npsScoreClass">{{ dashboard.stats.nps_score }}</div>
                    <div class="stat-label">NPS 分数</div>
                    <div class="stat-sub">目标: {{ dashboard.stats.target_score }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.stats.promoters }}</div>
                    <div class="stat-label">推荐者 (9-10)</div>
                    <div class="stat-sub">占比 {{ categoryPct('promoters') }}%</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.stats.passives }}</div>
                    <div class="stat-label">被动者 (7-8)</div>
                    <div class="stat-sub">占比 {{ categoryPct('passives') }}%</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dashboard.stats.detractors }}</div>
                    <div class="stat-label">贬损者 (0-6)</div>
                    <div class="stat-sub">占比 {{ categoryPct('detractors') }}%</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行：调查统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.surveys_sent }}</div>
                    <div class="stat-label">已发送调查</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.surveys_completed }}</div>
                    <div class="stat-label">已完成调查</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.stats.response_rate >= 30 ? 'text-success' : 'text-warning'">
                        {{ dashboard.stats.response_rate }}%
                    </div>
                    <div class="stat-label">响应率</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total_responses }}</div>
                    <div class="stat-label">总反馈数</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab">
            <!-- 趋势 -->
            <el-tab-pane label="趋势图表" name="trend">
                <el-card>
                    <template #header>
                        <span>NPS 分数趋势</span>
                    </template>
                    <div v-if="trendData.length === 0" class="empty-hint">暂无趋势数据，请先生成每日快照</div>
                    <div ref="trendChartRef" style="height: 350px" v-else></div>
                    <div class="action-bar">
                        <el-button size="small" @click="handleGenerateSnapshot">生成今日快照</el-button>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 评分分布 -->
            <el-tab-pane label="评分分布" name="distribution">
                <el-card>
                    <template #header>
                        <span>评分分布 ({{ report.total_responses }} 条)</span>
                    </template>
                    <div ref="distChartRef" style="height: 350px"></div>
                    <el-table :data="scoreDistList" stripe style="margin-top: 16px">
                        <el-table-column prop="score" label="评分" width="80" />
                        <el-table-column label="人数" width="120">
                            <template #default="{ row }">
                                <span>{{ row.count }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="占比">
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
            <el-tab-pane label="客户反馈" name="feedback">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>客户反馈</span>
                            <el-select v-model="feedbackFilter" placeholder="筛选类别" clearable size="small" style="width: 140px">
                                <el-option label="全部" value="" />
                                <el-option label="推荐者" value="promoter" />
                                <el-option label="被动者" value="passive" />
                                <el-option label="贬损者" value="detractor" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="filteredFeedback" stripe style="width: 100%">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column label="评分" width="80">
                            <template #default="{ row }">
                                <el-tag :type="scoreTagType(row.score)" size="large">{{ row.score }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="category" label="类别" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.category === 'promoter' ? 'success' : row.category === 'detractor' ? 'danger' : 'warning'" size="small">
                                    {{ row.category === 'promoter' ? '推荐者' : row.category === 'detractor' ? '贬损者' : '被动者' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="feedback" label="反馈" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="best_feature" label="最喜欢的功能" min-width="180" show-overflow-tooltip />
                        <el-table-column prop="improvement" label="改进建议" min-width="180" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 调查管理 -->
            <el-tab-pane label="调查管理" name="manage">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>调查发送管理</span>
                            <el-button size="small" type="primary" @click="showSendDialog = true">发送调查</el-button>
                        </el-space>
                    </template>

                    <el-table :data="surveysList" stripe v-loading="surveysLoading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="用户" min-width="150">
                            <template #default="{ row }">
                                <div>{{ row.user?.name || '未知' }}</div>
                                <div class="text-muted">{{ row.user?.email }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'sent' ? 'primary' : row.status === 'expired' ? 'info' : 'warning'" size="small">
                                    {{ row.status === 'completed' ? '已完成' : row.status === 'sent' ? '已发送' : row.status === 'expired' ? '已过期' : '待处理' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="渠道" width="80">
                            <template #default="{ row }">{{ row.channel }}</template>
                        </el-table-column>
                        <el-table-column label="评分" width="80">
                            <template #default="{ row }">
                                <span v-if="row.response">{{ row.response.score }}</span>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="sent_at" label="发送时间" width="160" />
                        <el-table-column prop="completed_at" label="完成时间" width="160" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 发送调查对话框 -->
        <el-dialog v-model="showSendDialog" title="发送 NPS 调查" width="500px">
            <el-form label-position="top">
                <el-form-item label="选择用户">
                    <el-select
                        v-model="sendForm.user_id"
                        filterable
                        remote
                        :remote-method="searchUsers"
                        placeholder="搜索用户"
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
                <el-form-item label="发送渠道">
                    <el-radio-group v-model="sendForm.channel">
                        <el-radio value="email">邮件</el-radio>
                        <el-radio value="in-app">站内信</el-radio>
                        <el-radio value="popup">弹窗</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSendDialog = false">取消</el-button>
                <el-button type="primary" :loading="sending" @click="handleSendSurvey">发送</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import npsSurvey from '@/api/npsSurvey';

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
        ElMessage.warning('请选择用户');
        return;
    }
    sending.value = true;
    try {
        await npsSurvey.sendSurvey({
            user_id: sendForm.user_id,
            channel: sendForm.channel,
        });
        ElMessage.success('调查已发送');
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
        ElMessage.success('快照已生成');
        loadTrend();
    } catch (e) {
        console.error('Failed to generate snapshot:', e);
    }
}

function renderTrendChart() {
    if (!trendChartRef.value || trendData.value.length === 0) return;
    // Use simple inline visualization
    const chartContainer = trendChartRef.value;
    chartContainer.innerHTML = '<div style="padding: 16px; max-height: 350px; overflow-y: auto;">' +
        trendData.value.map(item =>
            `<div style="display: flex; align-items: center; margin-bottom: 8px; gap: 8px;">
                <span style="min-width: 80px; font-size: 12px; color: #909399;">${item.snapshot_date || item.date}</span>
                <div style="flex: 1; height: 24px; background: #f5f5f5; border-radius: 4px; overflow: hidden; display: flex;">
                    <div style="width: ${item.promoters / (item.total_responses || 1) * 100}%; background: #67c23a; height: 100%;" title="推荐者 ${item.promoters}"></div>
                    <div style="width: ${item.passives / (item.total_responses || 1) * 100}%; background: #e6a23c; height: 100%;" title="被动者 ${item.passives}"></div>
                    <div style="width: ${item.detractors / (item.total_responses || 1) * 100}%; background: #f56c6c; height: 100%;" title="贬损者 ${item.detractors}"></div>
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
    // Load eligible users for send dialog
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
