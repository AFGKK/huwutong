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
                        <div class="stat-label">本周 vs 上周</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ bounceAnalysis.total ?? '-' }}</div>
                        <div class="stat-label">总退信数</div>
                        <div class="stat-rate" v-if="bounceAnalysis.rate !== undefined">{{ bounceAnalysis.rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value info">{{ funnel.queued ?? '-' }}</div>
                        <div class="stat-label">排队中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value warning">{{ funnel.failed ?? '-' }}</div>
                        <div class="stat-label">发送失败</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <!-- 按日发送趋势 -->
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header><span>近30天发送趋势</span></template>
                    <div class="chart-placeholder" ref="dailyChartRef">
                        <div class="chart-empty" v-if="!dailyData.length">暂无数据</div>
                        <div class="bar-chart" v-else>
                            <div class="bar-item" v-for="day in dailyData" :key="day.date" :title="`${day.date}: ${day.total} 封`">
                                <div class="bar-stacked">
                                    <div class="bar-segment bar-bounced" :style="{ height: barHeight(day.bounced, day.total) + '%' }" :title="`退信: ${day.bounced}`"></div>
                                    <div class="bar-segment bar-opened" :style="{ height: barHeight(day.opened, day.total) + '%' }" :title="`打开: ${day.opened}`"></div>
                                    <div class="bar-segment bar-delivered" :style="{ height: barHeight(day.delivered, day.total) + '%' }" :title="`投递: ${day.delivered}`"></div>
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
                    <template #header><span>近7天时段分布</span></template>
                    <div class="chart-placeholder">
                        <div class="chart-empty" v-if="!hourlyData.length">暂无数据</div>
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
                <el-tab-pane label="邮件明细" name="logs">
                    <div class="toolbar">
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-input
                                    v-model="logFilters.search"
                                    placeholder="搜索收件人 / 主题 / 模板"
                                    clearable
                                    @clear="fetchLogs"
                                    @keyup.enter="fetchLogs"
                                    style="width: 240px"
                                >
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </el-form-item>
                            <el-form-item>
                                <el-select v-model="logFilters['filter.status']" placeholder="状态" clearable @change="fetchLogs" style="width: 120px">
                                    <el-option label="排队中" value="queued" />
                                    <el-option label="已发送" value="sent" />
                                    <el-option label="已投递" value="delivered" />
                                    <el-option label="已打开" value="opened" />
                                    <el-option label="退信" value="bounced" />
                                    <el-option label="失败" value="failed" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-select
                                    v-model="logFilters['filter.template_code']"
                                    placeholder="模板"
                                    clearable
                                    @change="fetchLogs"
                                    style="width: 160px"
                                >
                                    <el-option v-for="t in templateOptions" :key="t.code" :label="t.code" :value="t.code" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchLogs"><el-icon><Search /></el-icon> 查询</el-button>
                            </el-form-item>
                        </el-form>
                    </div>

                    <el-table :data="logs" v-loading="logsLoading" stripe>
                        <el-table-column prop="to_email" label="收件人" width="200" />
                        <el-table-column prop="subject" label="主题" min-width="200">
                            <template #default="{ row }">
                                <span class="subject-text">{{ row.subject }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="template_code" label="模板" width="140" />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="logStatusType(row.status)" size="small">{{ logStatusLabel(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="投递/打开" width="160">
                            <template #default="{ row }">
                                <div class="timeline-col">
                                    <span v-if="row.delivered_at" class="text-success">✓ {{ row.delivered_at }}</span>
                                    <span v-else-if="row.sent_at" class="text-info">→ {{ row.sent_at }}</span>
                                    <span v-else class="text-muted">-</span>
                                    <span v-if="row.opened_at" class="text-primary ml-1">👁 {{ row.opened_at }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="创建时间" width="160" />
                        <el-table-column label="操作" width="80" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openLogDetail(row)">详情</el-button>
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
                <el-tab-pane label="模板统计" name="templates">
                    <el-table :data="byTemplate" v-loading="loading" stripe>
                        <el-table-column prop="template_code" label="模板代码" min-width="160" />
                        <el-table-column prop="total_sent" label="发送量" width="80" align="center" />
                        <el-table-column label="投递" width="80" align="center">
                            <template #default="{ row }">{{ row.delivered }}</template>
                        </el-table-column>
                        <el-table-column label="打开" width="80" align="center">
                            <template #default="{ row }">{{ row.opened }}</template>
                        </el-table-column>
                        <el-table-column label="点击" width="80" align="center">
                            <template #default="{ row }">{{ row.clicked }}</template>
                        </el-table-column>
                        <el-table-column label="退信" width="80" align="center">
                            <template #default="{ row }">{{ row.bounced }}</template>
                        </el-table-column>
                        <el-table-column label="打开率" width="80" align="center">
                            <template #default="{ row }">{{ row.delivered > 0 ? ((row.opened / row.delivered) * 100).toFixed(1) : 0 }}%</template>
                        </el-table-column>
                        <el-table-column label="操作" width="80">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openTemplateDetail(row)">下钻</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 退信分析 -->
                <el-tab-pane label="退信分析" name="bounces">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <h4 class="section-title">退信原因</h4>
                            <el-table :data="bounceAnalysis.by_reason || []" v-loading="loading" size="small" stripe>
                                <el-table-column prop="bounce_reason" label="原因" min-width="200" />
                                <el-table-column prop="count" label="次数" width="80" align="center" />
                                <el-table-column prop="last_bounced_at" label="最近退信" width="160" />
                            </el-table>
                        </el-col>
                        <el-col :span="12">
                            <h4 class="section-title">退信域名</h4>
                            <el-table :data="bounceAnalysis.by_domain || []" v-loading="loading" size="small" stripe>
                                <el-table-column prop="domain" label="邮箱域名" min-width="200" />
                                <el-table-column prop="count" label="退信数" width="80" align="center" />
                            </el-table>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 邮件详情对话框 -->
        <el-dialog v-model="showLogDetail" title="邮件详情" width="600px">
            <template v-if="logDetailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="收件人">{{ logDetailData.to_email }}</el-descriptions-item>
                    <el-descriptions-item label="模板">{{ logDetailData.template_code }}</el-descriptions-item>
                    <el-descriptions-item label="主题" :span="2">{{ logDetailData.subject }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ logStatusLabel(logDetailData.status) }}</el-descriptions-item>
                    <el-descriptions-item label="追踪 ID">{{ logDetailData.tracking_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="发送时间">{{ logDetailData.sent_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="投递时间">{{ logDetailData.delivered_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="打开时间">{{ logDetailData.opened_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="点击时间">{{ logDetailData.clicked_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="退信原因" :span="2">{{ logDetailData.bounce_reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="错误信息" :span="2">{{ logDetailData.error_message || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
            <template #footer>
                <el-button @click="showLogDetail = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 模板下钻对话框 -->
        <el-dialog v-model="showTemplateDetail" title="模板详情" width="700px">
            <template v-if="templateDetailData">
                <p class="mb-4"><strong>模板代码：</strong>{{ templateDetailData.template_code }}</p>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="s in templateFunnelCards" :key="s.label">
                        <el-statistic :title="s.label" :value="s.value" />
                    </el-col>
                </el-row>
                <el-table :data="templateDetailData.daily || []" size="small" stripe>
                    <el-table-column prop="date" label="日期" width="120" />
                    <el-table-column prop="total" label="发送量" width="80" align="center" />
                    <el-table-column prop="opened" label="打开数" width="80" align="center" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import emailDashboardApi from '@/api/emailDashboard';

// ─── 漏斗统计 ───
const funnel = reactive({
    total_sent: 0, queued: 0, sent: 0,
    delivered: 0, opened: 0, clicked: 0,
    bounced: 0, failed: 0,
    delivery_rate: 0, open_rate: 0, click_rate: 0, bounce_rate: 0,
});

const funnelCards = computed(() => [
    { label: '总发送', value: funnel.total_sent, color: '' },
    { label: '已投递', value: funnel.delivered, color: 'success', rate: funnel.delivery_rate, clickable: true },
    { label: '已打开', value: funnel.opened, color: 'primary', rate: funnel.open_rate, clickable: true },
    { label: '已点击', value: funnel.clicked, color: 'info', rate: funnel.click_rate, clickable: true },
    { label: '退信', value: funnel.bounced, color: 'danger', rate: funnel.bounce_rate, clickable: true },
]);

const trend = reactive({ this_week: 0, last_week: 0, change_percent: 0, direction: 'up' });
const bounceAnalysis = reactive({ total: 0, rate: 0, by_reason: [], by_domain: [] });
const dailyData = ref([]);
const hourlyData = ref([]);

function barHeight(val, max) {
    if (!max || max === 0) return 0;
    return Math.max(2, (val / max) * 100);
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
        ElMessage.error('获取邮件日志失败');
    } finally {
        logsLoading.value = false;
    }
}

// ─── 模板统计 ───
const byTemplate = ref([]);
const templateOptions = computed(() => byTemplate.value.map(t => ({ code: t.template_code })));

// ─── 邮件详情 ───
const showLogDetail = ref(false);
const logDetailData = ref(null);

async function openLogDetail(row) {
    try {
        const res = await emailDashboardApi.logDetail(row.id);
        logDetailData.value = res.data?.data;
        showLogDetail.value = true;
    } catch {
        ElMessage.error('获取邮件详情失败');
    }
}

// ─── 模板下钻 ───
const showTemplateDetail = ref(false);
const templateDetailData = ref(null);

const templateFunnelCards = computed(() => {
    if (!templateDetailData.value?.funnel) return [];
    const f = templateDetailData.value.funnel;
    return [
        { label: '总发送', value: f.total_sent },
        { label: '已投递', value: f.delivered },
        { label: '已打开', value: f.opened },
        { label: '退信', value: f.bounced },
    ];
});

async function openTemplateDetail(row) {
    try {
        const res = await emailDashboardApi.templateDetail(row.template_code);
        templateDetailData.value = res.data?.data;
        showTemplateDetail.value = true;
    } catch {
        ElMessage.error('获取模板详情失败');
    }
}

// ─── 辅助 ───
const activeTab = ref('logs');

function logStatusType(status) {
    const map = { queued: 'info', sent: '', delivered: 'success', opened: 'primary', clicked: 'success', bounced: 'danger', failed: 'warning' };
    return map[status] || 'info';
}

function logStatusLabel(status) {
    const map = { queued: '排队中', sent: '已发送', delivered: '已投递', opened: '已打开', clicked: '已点击', bounced: '退信', failed: '失败' };
    return map[status] || status;
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
        ElMessage.error('获取邮件概览失败');
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
