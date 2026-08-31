<template>
    <div class="email-dashboard-page">
        <!-- 漏斗统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="card in funnelCards" :key="card.label">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card" :class="{ clickable: card.clickable }" @click="card.clickable ? activeTab = 'logs' : ''">
                        <div class="stat-value" :class="card.color || ''">{{ card.value }}</div>
                        <div class="stat-label">{{ card.label }}</div>
                        <div class="stat-rate" v-if="card.rate !== undefined">{{ card.rate }}%</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 趋势卡片（第二行） -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value" :class="trend.direction === 'up' ? 'success' : 'danger'">
                            {{ trend.direction === 'up' ? '↑' : '↓' }} {{ Math.abs(trend.change_percent) }}%
                        </div>
                        <div class="stat-label">{{ t('email_dashboard_page.trend_week_vs_last') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ bounceAnalysis.total ?? '-' }}</div>
                        <div class="stat-label">{{ t('email_dashboard_page.total_bounces') }}</div>
                        <div class="stat-rate" v-if="bounceAnalysis.rate !== undefined">{{ bounceAnalysis.rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value info">{{ funnel.queued ?? '-' }}</div>
                        <div class="stat-label">{{ t('email_dashboard_page.queued') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value warning">{{ funnel.failed ?? '-' }}</div>
                        <div class="stat-label">{{ t('email_dashboard_page.send_failed') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <!-- 按日发送趋势 -->
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header><span>{{ t('email_dashboard_page.chart_daily_title') }}</span></template>
                    <div class="chart-placeholder" ref="dailyChartRef">
                        <div class="chart-empty" v-if="!dailyData.length">{{ t('messages.no_data') }}</div>
                        <div class="bar-chart" v-else>
                            <div
                                class="bar-item"
                                v-for="day in dailyData"
                                :key="day.date"
                                :title="dayTooltip(day)"
                            >
                                <div class="bar-stacked">
                                    <div
                                        class="bar-segment bar-bounced"
                                        :style="{ height: barHeight(day.bounced, day.total) + '%' }"
                                        :title="chartSegmentTooltip('bounced', day.bounced)"
                                    ></div>
                                    <div
                                        class="bar-segment bar-opened"
                                        :style="{ height: barHeight(day.opened, day.total) + '%' }"
                                        :title="chartSegmentTooltip('opened', day.opened)"
                                    ></div>
                                    <div
                                        class="bar-segment bar-delivered"
                                        :style="{ height: barHeight(day.delivered, day.total) + '%' }"
                                        :title="chartSegmentTooltip('delivered', day.delivered)"
                                    ></div>
                                </div>
                                <div class="bar-label">{{ day.date.slice(5) }}</div>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 按时段分布 -->
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header><span>{{ t('email_dashboard_page.chart_hourly_title') }}</span></template>
                    <div class="chart-placeholder">
                        <div class="chart-empty" v-if="!hourlyData.length">{{ t('messages.no_data') }}</div>
                        <div class="hourly-chart" v-else>
                            <div class="hour-item" v-for="h in hourlyData" :key="h.hour">
                                <div class="hour-bar" :style="{ height: barHeight(h.total, maxHourly) + '%' }"></div>
                                <div class="hour-label">{{ ('0' + h.hour).slice(-2) }}:00</div>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs: 邮件明细 | 模板统计 | 退信分析 -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- 邮件明细 -->
                <el-tab-pane :label="t('email_dashboard_page.tabs.logs')" name="logs">
                    <div class="toolbar">
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-input
                                    v-model="logFilters.search"
                                    :placeholder="t('email_dashboard_page.search_ph')"
                                    clearable
                                    @clear="fetchLogs"
                                    @keyup.enter="fetchLogs"
                                    style="width: 240px"
                                >
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </el-form-item>
                            <el-form-item>
                                <el-select
                                    v-model="logFilters['filter.status']"
                                    :placeholder="t('email_dashboard_page.status_ph')"
                                    clearable
                                    @change="fetchLogs"
                                    style="width: 120px"
                                >
                                    <el-option
                                        v-for="opt in logStatusOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-select
                                    v-model="logFilters['filter.template_code']"
                                    :placeholder="t('email_dashboard_page.template_ph')"
                                    clearable
                                    @change="fetchLogs"
                                    style="width: 160px"
                                >
                                    <el-option v-for="tpl in templateOptions" :key="tpl.code" :label="tpl.code" :value="tpl.code" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchLogs">
                                    <el-icon><Search /></el-icon> {{ t('actions.search') }}
                                </el-button>
                            </el-form-item>
                        </el-form>
                    </div>

                    <el-table :data="logs" v-loading="logsLoading" stripe>
                        <el-table-column prop="to_email" :label="t('email_dashboard_page.cols.recipient')" width="200" />
                        <el-table-column prop="subject" :label="t('email_dashboard_page.cols.subject')" min-width="200">
                            <template #default="{ row }">
                                <span class="subject-text">{{ row.subject }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="template_code" :label="t('email_dashboard_page.cols.template')" width="140" />
                        <el-table-column :label="t('email_dashboard_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="logStatusType(row.status)" size="small">{{ logStatusLabel(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.delivery_open')" width="160">
                            <template #default="{ row }">
                                <div class="timeline-col">
                                    <span v-if="row.delivered_at" class="text-success">✓ {{ row.delivered_at }}</span>
                                    <span v-else-if="row.sent_at" class="text-info">→ {{ row.sent_at }}</span>
                                    <span v-else class="text-muted">-</span>
                                    <span v-if="row.opened_at" class="text-primary ml-1">{{ row.opened_at }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="t('email_dashboard_page.cols.created_at')" width="160" />
                        <el-table-column :label="t('email_dashboard_page.cols.actions')" width="80" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openLogDetail(row)">
                                    {{ t('email_dashboard_page.detail') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="logPage"
                            v-model:page-size="logPerPage"
                            :total="logTotal"
                            :page-sizes="[10, 20, 50]"
                            layout="total, sizes, prev, pager, next"
                            @change="fetchLogs"
                        />
                    </div>
                </el-tab-pane>

                <!-- 模板统计 -->
                <el-tab-pane :label="t('email_dashboard_page.tabs.templates')" name="templates">
                    <el-table :data="byTemplate" v-loading="loading" stripe>
                        <el-table-column prop="template_code" :label="t('email_dashboard_page.cols.template_code')" min-width="160" />
                        <el-table-column prop="total_sent" :label="t('email_dashboard_page.cols.sent_count')" width="80" align="center" />
                        <el-table-column :label="t('email_dashboard_page.cols.delivered')" width="80" align="center">
                            <template #default="{ row }">{{ row.delivered }}</template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.opened')" width="80" align="center">
                            <template #default="{ row }">{{ row.opened }}</template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.clicked')" width="80" align="center">
                            <template #default="{ row }">{{ row.clicked }}</template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.bounced')" width="80" align="center">
                            <template #default="{ row }">{{ row.bounced }}</template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.open_rate')" width="80" align="center">
                            <template #default="{ row }">{{ row.delivered > 0 ? ((row.opened / row.delivered) * 100).toFixed(1) : 0 }}%</template>
                        </el-table-column>
                        <el-table-column :label="t('email_dashboard_page.cols.actions')" width="80">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openTemplateDetail(row)">
                                    {{ t('email_dashboard_page.drill_down') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 退信分析 -->
                <el-tab-pane :label="t('email_dashboard_page.tabs.bounces')" name="bounces">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <h4 class="section-title">{{ t('email_dashboard_page.bounce.reasons_title') }}</h4>
                            <el-table :data="bounceAnalysis.by_reason || []" v-loading="loading" size="small" stripe>
                                <el-table-column prop="bounce_reason" :label="t('email_dashboard_page.bounce.reason')" min-width="200" />
                                <el-table-column prop="count" :label="t('email_dashboard_page.bounce.count')" width="80" align="center" />
                                <el-table-column prop="last_bounced_at" :label="t('email_dashboard_page.bounce.last_bounced')" width="160" />
                            </el-table>
                        </el-col>
                        <el-col :span="12">
                            <h4 class="section-title">{{ t('email_dashboard_page.bounce.domains_title') }}</h4>
                            <el-table :data="bounceAnalysis.by_domain || []" v-loading="loading" size="small" stripe>
                                <el-table-column prop="domain" :label="t('email_dashboard_page.bounce.domain')" min-width="200" />
                                <el-table-column prop="count" :label="t('email_dashboard_page.bounce.bounce_count')" width="80" align="center" />
                            </el-table>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 邮件详情对话框 -->
        <el-dialog v-model="showLogDetail" :title="t('email_dashboard_page.log_detail_title')" width="600px">
            <template v-if="logDetailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.recipient')">{{ logDetailData.to_email }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.template')">{{ logDetailData.template_code }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.subject')" :span="2">{{ logDetailData.subject }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.status')">{{ logStatusLabel(logDetailData.status) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.tracking_id')">{{ logDetailData.tracking_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.sent_at')">{{ logDetailData.sent_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.delivered_at')">{{ logDetailData.delivered_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.opened_at')">{{ logDetailData.opened_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.clicked_at')">{{ logDetailData.clicked_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.bounce_reason')" :span="2">{{ logDetailData.bounce_reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('email_dashboard_page.fields.error_message')" :span="2">{{ logDetailData.error_message || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
            <template #footer>
                <el-button @click="showLogDetail = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 模板下钻对话框 -->
        <el-dialog v-model="showTemplateDetail" :title="t('email_dashboard_page.template_detail_title')" width="700px">
            <template v-if="templateDetailData">
                <p class="mb-4">
                    <strong>{{ t('email_dashboard_page.template_code_label') }}</strong>{{ templateDetailData.template_code }}
                </p>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="s in templateFunnelCards" :key="s.label">
                        <el-statistic :title="s.label" :value="s.value" />
                    </el-col>
                </el-row>
                <el-table :data="templateDetailData.daily || []" size="small" stripe>
                    <el-table-column prop="date" :label="t('email_dashboard_page.cols.date')" width="120" />
                    <el-table-column prop="total" :label="t('email_dashboard_page.cols.sent_count')" width="80" align="center" />
                    <el-table-column prop="opened" :label="t('email_dashboard_page.cols.open_count')" width="80" align="center" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import emailDashboardApi from '@/api/emailDashboard';

const { t } = useI18n();

// ─── 漏斗统计 ───
const funnel = reactive({
    total_sent: 0, queued: 0, sent: 0,
    delivered: 0, opened: 0, clicked: 0,
    bounced: 0, failed: 0,
    delivery_rate: 0, open_rate: 0, click_rate: 0, bounce_rate: 0,
});

const funnelCards = computed(() => [
    { label: t('email_dashboard_page.funnel.total_sent'), value: funnel.total_sent, color: '' },
    { label: t('email_dashboard_page.funnel.delivered'), value: funnel.delivered, color: 'success', rate: funnel.delivery_rate, clickable: true },
    { label: t('email_dashboard_page.funnel.opened'), value: funnel.opened, color: 'primary', rate: funnel.open_rate, clickable: true },
    { label: t('email_dashboard_page.funnel.clicked'), value: funnel.clicked, color: 'info', rate: funnel.click_rate, clickable: true },
    { label: t('email_dashboard_page.funnel.bounced'), value: funnel.bounced, color: 'danger', rate: funnel.bounce_rate, clickable: true },
]);

const trend = reactive({ this_week: 0, last_week: 0, change_percent: 0, direction: 'up' });
const bounceAnalysis = reactive({ total: 0, rate: 0, by_reason: [], by_domain: [] });
const dailyData = ref([]);
const hourlyData = ref([]);

function barHeight(val, max) {
    if (!max || max === 0) return 0;
    return Math.max(2, (val / max) * 100);
}

function dayTooltip(day) {
    return t('email_dashboard_page.chart.day_tooltip', { date: day.date, count: day.total });
}

function chartSegmentTooltip(segment, count) {
    return t(`email_dashboard_page.chart.${segment}`, { count });
}

const maxHourly = computed(() => {
    if (!hourlyData.value.length) return 1;
    return Math.max(...hourlyData.value.map(h => h.total));
});

// ─── 日志列表 ───
const logs = ref([]);
const logsLoading = ref(false);
const logPage = ref(1);
const logPerPage = ref(20);
const logTotal = ref(0);
const logFilters = reactive({
    search: '',
    'filter.status': '',
    'filter.template_code': '',
});

const logStatusLabels = computed(() => ({
    queued: t('email_dashboard_page.status.queued'),
    sent: t('email_dashboard_page.status.sent'),
    delivered: t('email_dashboard_page.status.delivered'),
    opened: t('email_dashboard_page.status.opened'),
    clicked: t('email_dashboard_page.status.clicked'),
    bounced: t('email_dashboard_page.status.bounced'),
    failed: t('email_dashboard_page.status.failed'),
}));

const logStatusOptions = computed(() => [
    { value: 'queued', label: logStatusLabels.value.queued },
    { value: 'sent', label: logStatusLabels.value.sent },
    { value: 'delivered', label: logStatusLabels.value.delivered },
    { value: 'opened', label: logStatusLabels.value.opened },
    { value: 'bounced', label: logStatusLabels.value.bounced },
    { value: 'failed', label: logStatusLabels.value.failed },
]);

async function fetchLogs() {
    logsLoading.value = true;
    try {
        const params = { page: logPage.value, per_page: logPerPage.value };
        if (logFilters.search) params.search = logFilters.search;
        if (logFilters['filter.status']) params['filter.status'] = logFilters['filter.status'];
        if (logFilters['filter.template_code']) params['filter.template_code'] = logFilters['filter.template_code'];

        const res = await emailDashboardApi.logs(params);
        logs.value = res.data?.data || [];
        logTotal.value = res.data?.meta?.total || 0;
    } catch {
        ElMessage.error(t('email_dashboard_page.messages.fetch_logs_failed'));
    } finally {
        logsLoading.value = false;
    }
}

// ─── 模板统计 ───
const byTemplate = ref([]);
const templateOptions = computed(() => byTemplate.value.map(tpl => ({ code: tpl.template_code })));

// ─── 邮件详情 ───
const showLogDetail = ref(false);
const logDetailData = ref(null);

async function openLogDetail(row) {
    try {
        const res = await emailDashboardApi.logDetail(row.id);
        logDetailData.value = res.data?.data;
        showLogDetail.value = true;
    } catch {
        ElMessage.error(t('email_dashboard_page.messages.fetch_detail_failed'));
    }
}

// ─── 模板下钻 ───
const showTemplateDetail = ref(false);
const templateDetailData = ref(null);

const templateFunnelCards = computed(() => {
    if (!templateDetailData.value?.funnel) return [];
    const f = templateDetailData.value.funnel;
    return [
        { label: t('email_dashboard_page.funnel.total_sent'), value: f.total_sent },
        { label: t('email_dashboard_page.funnel.delivered'), value: f.delivered },
        { label: t('email_dashboard_page.funnel.opened'), value: f.opened },
        { label: t('email_dashboard_page.funnel.bounced'), value: f.bounced },
    ];
});

async function openTemplateDetail(row) {
    try {
        const res = await emailDashboardApi.templateDetail(row.template_code);
        templateDetailData.value = res.data?.data;
        showTemplateDetail.value = true;
    } catch {
        ElMessage.error(t('email_dashboard_page.messages.fetch_template_failed'));
    }
}

// ─── 辅助 ───
const activeTab = ref('logs');
const loading = ref(false);

function logStatusType(status) {
    const map = { queued: 'info', sent: '', delivered: 'success', opened: 'primary', clicked: 'success', bounced: 'danger', failed: 'warning' };
    return map[status] || 'info';
}

function logStatusLabel(status) {
    return logStatusLabels.value[status] || status;
}

async function fetchOverview() {
    try {
        const res = await emailDashboardApi.overview();
        const data = res.data?.data || {};
        Object.assign(funnel, data.funnel || {});
        dailyData.value = data.daily || [];
        byTemplate.value = data.by_template || [];
        Object.assign(bounceAnalysis, data.bounce_analysis || {});
        Object.assign(trend, data.trend || {});
        hourlyData.value = data.hourly || [];
    } catch {
        ElMessage.error(t('email_dashboard_page.messages.fetch_overview_failed'));
    }
}

onMounted(async () => {
    await fetchOverview();
    await fetchLogs();
});
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.ml-1 { margin-left: 4px; }
.mt-2 { margin-top: 8px; }

.stat-card { text-align: center; }
.stat-card.clickable { cursor: pointer; transition: transform 0.1s; }
.stat-card.clickable:hover { transform: translateY(-2px); }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.success { color: var(--el-color-success); }
.stat-value.danger { color: var(--el-color-danger); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.primary { color: var(--el-color-primary); }
.stat-value.info { color: var(--el-color-info); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 2px; }
.stat-rate { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 2px; }

.chart-placeholder {
    height: 200px; overflow-y: auto;
}
.chart-empty {
    display: flex; align-items: center; justify-content: center;
    height: 100%; color: var(--el-text-color-secondary);
}

.bar-chart {
    display: flex; align-items: flex-end; gap: 3px; height: 180px;
}
.bar-item {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px;
}
.bar-stacked {
    width: 100%; max-width: 24px; min-height: 4px;
    display: flex; flex-direction: column-reverse;
    border-radius: 2px 2px 0 0; overflow: hidden;
}
.bar-segment { width: 100%; min-height: 2px; }
.bar-bounced { background: var(--el-color-danger-light-5); }
.bar-opened { background: var(--el-color-primary-light-5); }
.bar-delivered { background: var(--el-color-success-light-5); }
.bar-label { font-size: 10px; color: var(--el-text-color-secondary); white-space: nowrap; }

.hourly-chart {
    display: flex; align-items: flex-end; gap: 2px; height: 160px;
}
.hour-item {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px;
}
.hour-bar {
    width: 100%; max-width: 12px;
    background: var(--el-color-primary-light-5);
    border-radius: 2px 2px 0 0; min-height: 2px;
}
.hour-label { font-size: 9px; color: var(--el-text-color-secondary); }

.toolbar { margin-bottom: 16px; }

.subject-text {
    max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;
}

.timeline-col {
    font-size: 12px; line-height: 1.6;
}
.text-success { color: var(--el-color-success); }
.text-info { color: var(--el-color-info); }
.text-primary { color: var(--el-color-primary); }
.text-muted { color: var(--el-text-color-placeholder); }

.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }

.section-title {
    margin: 0 0 12px 0;
}
</style>
