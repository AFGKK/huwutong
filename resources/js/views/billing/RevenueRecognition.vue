<template>
    <div class="revenue-recognition-page">
        <div class="page-header">
            <h2>{{ t('revenue_recognition_page.title') }} <small>{{ t('revenue_recognition_page.std_label') }}</small></h2>
            <p class="text-muted">{{ t('revenue_recognition_page.subtitle') }}</p>
        </div>

        <!-- 汇总概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(summary.total_amount) }}</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.total_scheduled') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(summary.recognized_amount) }}</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.recognized') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card warning">
                    <div class="stat-value">{{ formatMoney(summary.deferred_amount) }}</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.deferred_balance') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ summary.completion_rate }}%</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.completion_rate') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ summary.this_month_to_recognize ? formatMoney(summary.this_month_to_recognize) : formatMoney(0) }}</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.this_month_pending') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card info">
                    <div class="stat-value">{{ summary.active_schedules }}</div>
                    <div class="stat-label">{{ t('revenue_recognition_page.stats.active_schedules') }}</div>
                    <div class="stat-sub">{{ t('revenue_recognition_page.stats.completed_sub', { count: summary.completed_schedules }) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作按钮 -->
        <el-card class="toolbar-card">
            <el-button type="primary" @click="handleProcessRecognition" :icon="Pointer" :loading="processing">
                {{ t('revenue_recognition_page.actions.process_recognition') }}
            </el-button>
            <el-button @click="handleCreateSchedules" :icon="Plus">
                {{ t('revenue_recognition_page.actions.create_schedules') }}
            </el-button>
            <el-button @click="handleGenerateSnapshot" :icon="DataBoard" :loading="snapshotLoading">
                {{ t('revenue_recognition_page.actions.generate_snapshot') }}
            </el-button>
            <el-button @click="showAsc606Dialog" :icon="Document" style="float: right">
                {{ t('revenue_recognition_page.actions.asc606_report') }}
            </el-button>
        </el-card>

        <!-- 递延收入趋势（从 summary.deferred_trend 来） -->
        <el-card class="chart-card" v-if="summary.deferred_trend && summary.deferred_trend.length">
            <template #header>
                <span><el-icon><DataLine /></el-icon> {{ t('revenue_recognition_page.chart.deferred_trend') }}</span>
            </template>
            <div class="trend-chart">
                <div class="trend-bar" v-for="item in summary.deferred_trend" :key="item.month">
                    <div class="bar-wrapper">
                        <div
                            class="bar"
                            :style="{ height: barHeight(item.deferred_revenue) + '%' }"
                        ></div>
                    </div>
                    <div class="bar-label">{{ item.month.slice(5) }}</div>
                    <div class="bar-value">{{ formatShort(item.deferred_revenue) }}</div>
                </div>
            </div>
        </el-card>

        <!-- 排程列表 -->
        <el-card class="table-card">
            <template #header>
                <div class="card-header">
                    <span><el-icon><List /></el-icon> {{ t('revenue_recognition_page.table.schedules_title') }}</span>
                    <div>
                        <el-select v-model="filterStatus" clearable :placeholder="t('revenue_recognition_page.filters.status_ph')" size="small" style="width: 140px; margin-right: 8px;" @change="loadSchedules">
                            <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-button size="small" @click="loadSchedules" :icon="Refresh">{{ t('revenue_recognition_page.actions.refresh') }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="schedules" v-loading="loading" stripe :empty-text="t('revenue_recognition_page.table.empty')" style="width: 100%">
                <el-table-column prop="id" :label="t('revenue_recognition_page.cols.id')" width="60" />
                <el-table-column prop="revenue_type" :label="t('billing_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.revenue_type === 'subscription' ? 'primary' : 'warning'" size="small">
                            {{ revenueTypeLabel(row.revenue_type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_period" :label="t('revenue_recognition_page.cols.period')" width="100">
                    <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                </el-table-column>
                <el-table-column prop="total_amount" :label="t('revenue_recognition_page.cols.total')" width="120">
                    <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                </el-table-column>
                <el-table-column prop="recognized_amount" :label="t('revenue_recognition_page.cols.recognized')" width="120">
                    <template #default="{ row }">{{ formatMoney(row.recognized_amount) }}</template>
                </el-table-column>
                <el-table-column prop="deferred_amount" :label="t('revenue_recognition_page.cols.deferred')" width="120">
                    <template #default="{ row }">
                        <span class="text-warning">{{ formatMoney(row.deferred_amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('revenue_recognition_page.cols.progress')" width="140">
                    <template #default="{ row }">
                        <el-progress :percentage="row.progress || 0" :status="row.progress >= 100 ? 'success' : undefined" />
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('billing_page.col_status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="start_date" :label="t('revenue_recognition_page.cols.period_range')" min-width="160">
                    <template #default="{ row }">{{ row.start_date }} ~ {{ row.end_date }}</template>
                </el-table-column>
                <el-table-column prop="invoice.invoice_no" :label="t('revenue_recognition_page.cols.invoice')" width="130" />
                <el-table-column :label="t('billing_page.col_actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="showDetail(row)">{{ t('billing_page.detail') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="totalPages > 1">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="totalRecords"
                    layout="prev, pager, next, total"
                    @current-change="loadSchedules"
                />
            </div>
        </el-card>

        <!-- 排程详情弹窗 -->
        <el-dialog v-model="detailVisible" :title="t('revenue_recognition_page.detail.title')" width="750px">
            <template v-if="selectedSchedule">
                <div class="flex justify-between mb-3">
                    <span class="font-bold">{{ t('revenue_recognition_page.detail.schedule_label', { id: selectedSchedule.id }) }}</span>
                    <div>
                        <el-button v-if="selectedSchedule.status === 'active'" size="small" type="warning" @click="handleCancelSchedule(selectedSchedule)">{{ t('revenue_recognition_page.actions.cancel_schedule') }}</el-button>
                        <el-button v-if="selectedSchedule.status === 'active'" size="small" type="primary" @click="handleRecomputeSchedule(selectedSchedule)">{{ t('revenue_recognition_page.actions.recompute_schedule') }}</el-button>
                    </div>
                </div>
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('billing_page.col_type')">{{ revenueTypeLabel(selectedSchedule.revenue_type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.billing_period')">{{ periodLabel(selectedSchedule.billing_period) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.total')">{{ formatMoney(selectedSchedule.total_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.recognized')">{{ formatMoney(selectedSchedule.recognized_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.deferred')">{{ formatMoney(selectedSchedule.deferred_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.progress')">
                        <el-progress :percentage="selectedSchedule.progress" :status="selectedSchedule.progress >= 100 ? 'success' : undefined" style="width: 150px" />
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.period_range')" :span="2">{{ selectedSchedule.start_date }} ~ {{ selectedSchedule.end_date }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.periods')">{{ selectedSchedule.recognized_periods }} / {{ selectedSchedule.total_periods }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_status')">
                        <el-tag :type="statusTag(selectedSchedule.status)">{{ statusLabel(selectedSchedule.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.invoice')">{{ selectedSchedule.invoice?.invoice_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.subscription')">{{ selectedSchedule.subscription?.plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.recognition_method')">{{ recognitionMethodLabel(selectedSchedule.recognition_method) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.last_recognized')">{{ selectedSchedule.last_recognized_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item v-if="selectedSchedule.cancel_reason" :label="t('revenue_recognition_page.cols.cancel_reason')">{{ selectedSchedule.cancel_reason }}</el-descriptions-item>
                    <el-descriptions-item v-if="selectedSchedule.cancelled_at" :label="t('revenue_recognition_page.cols.cancelled_at')">{{ selectedSchedule.cancelled_at }}</el-descriptions-item>
                </el-descriptions>

                <h4 style="margin: 20px 0 10px">{{ t('revenue_recognition_page.detail.lines_title') }}</h4>
                <el-table :data="selectedSchedule.lines || []" size="small" stripe>
                    <el-table-column prop="period_number" :label="t('revenue_recognition_page.cols.period_number')" width="60" />
                    <el-table-column prop="recognition_date" :label="t('revenue_recognition_page.cols.recognition_date')" width="130" />
                    <el-table-column prop="amount" :label="t('billing_page.col_amount')" width="120">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="status" :label="t('billing_page.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="lineStatusTag(row.status)" size="small">
                                {{ lineStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="recognized_at" :label="t('revenue_recognition_page.cols.recognized_at')" width="160" />
                    <el-table-column prop="reason" :label="t('billing_page.col_reason')" width="100" />
                    <el-table-column prop="description" :label="t('revenue_recognition_page.cols.description')" min-width="140" />
                </el-table>
            </template>
        </el-dialog>

        <!-- ASC 606 报告弹窗 -->
        <el-dialog v-model="asc606Visible" :title="t('revenue_recognition_page.asc606.title')" width="800px">
            <el-form :inline="true">
                <el-form-item :label="t('revenue_recognition_page.asc606.year')">
                    <el-select v-model="asc606Year" style="width: 120px">
                        <el-option v-for="y in years" :key="y" :label="y" :value="y" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('revenue_recognition_page.asc606.month')">
                    <el-select v-model="asc606Month" style="width: 100px">
                        <el-option v-for="m in 12" :key="m" :label="String(m).padStart(2, '0')" :value="String(m).padStart(2, '0')" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadAsc606Report" :loading="reportLoading">{{ t('revenue_recognition_page.actions.generate_report') }}</el-button>
                    <el-button v-if="asc606Report" @click="exportAsc606Report" :icon="Download">{{ t('revenue_recognition_page.actions.export_csv') }}</el-button>
                </el-form-item>
            </el-form>

            <template v-if="asc606Report">
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.report_period')">{{ asc606Report.report_period }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.cols.recognition_method')">{{ recognitionMethodLabel(asc606Report.recognition_method) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.opening_deferred')">{{ formatMoney(asc606Report.opening_deferred_revenue) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.closing_deferred')">{{ formatMoney(asc606Report.closing_deferred_revenue) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.total_invoiced')">{{ formatMoney(asc606Report.total_invoiced) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.recognized_revenue')">{{ formatMoney(asc606Report.recognized_revenue) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.change_in_deferred')">{{ formatMoney(asc606Report.change_in_deferred) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.new_schedules_count')">{{ asc606Report.new_schedules_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('revenue_recognition_page.asc606.new_schedules_value')">{{ formatMoney(asc606Report.new_schedules_value) }}</el-descriptions-item>
                </el-descriptions>

                <!-- 按产品拆分 -->
                <template v-if="asc606Report.product_breakdown && asc606Report.product_breakdown.length">
                    <h4 style="margin: 16px 0 8px">{{ t('revenue_recognition_page.asc606.product_breakdown') }}</h4>
                    <el-table :data="asc606Report.product_breakdown" size="small" stripe>
                        <el-table-column prop="product" :label="t('billing_page.col_product')" min-width="120" />
                        <el-table-column prop="recognized_amount" :label="t('revenue_recognition_page.cols.recognized_amount')" width="140">
                            <template #default="{ row }">{{ formatMoney(row.recognized_amount) }}</template>
                        </el-table-column>
                        <el-table-column prop="transaction_count" :label="t('revenue_recognition_page.cols.transaction_count')" width="100" />
                    </el-table>
                </template>

                <h4 style="margin: 16px 0 8px">{{ t('revenue_recognition_page.asc606.recognized_transactions', { count: (asc606Report.recognized_transactions || []).length }) }}</h4>
                <el-table :data="asc606Report.recognized_transactions || []" size="small" max-height="300" stripe>
                    <el-table-column prop="schedule_id" :label="t('revenue_recognition_page.cols.schedule_id')" width="80" />
                    <el-table-column prop="period" :label="t('revenue_recognition_page.cols.period_number')" width="60" />
                    <el-table-column prop="amount" :label="t('billing_page.col_amount')" width="120">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="recognition_date" :label="t('revenue_recognition_page.cols.recognition_date')" width="130" />
                    <el-table-column prop="invoice_no" :label="t('revenue_recognition_page.cols.invoice_no')" width="140" />
                    <el-table-column prop="product" :label="t('billing_page.col_product')" min-width="100" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Pointer, Plus, DataBoard, DataLine, Document, List, Refresh, Download } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import revenueApi from '../../api/revenueRecognition';

const { t, locale } = useI18n();

const loading = ref(false);
const processing = ref(false);
const snapshotLoading = ref(false);
const schedules = ref([]);
const currentPage = ref(1);
const perPage = ref(20);
const totalRecords = ref(0);
const totalPages = computed(() => Math.ceil(totalRecords.value / perPage.value));
const filterStatus = ref('');

const summary = reactive({
    total_amount: 0,
    recognized_amount: 0,
    deferred_amount: 0,
    completion_rate: 0,
    active_schedules: 0,
    completed_schedules: 0,
    pending_schedules: 0,
    this_month_to_recognize: 0,
    deferred_trend: [],
});

// 详情
const detailVisible = ref(false);
const selectedSchedule = ref(null);

// ASC 606
const asc606Visible = ref(false);
const asc606Year = ref(nowYear());
const asc606Month = ref(nowMonth());
const asc606Report = ref(null);
const reportLoading = ref(false);

const periodLabels = computed(() => ({
    monthly: t('billing_page.period_monthly'),
    quarterly: t('billing_page.period_quarterly'),
    semi_annually: t('billing_page.period_semi_annually'),
    yearly: t('billing_page.period_yearly'),
}));

const revenueTypeLabels = computed(() => ({
    subscription: t('revenue_recognition_page.revenue_types.subscription'),
    upgrade: t('revenue_recognition_page.revenue_types.upgrade'),
}));

const scheduleStatusLabels = computed(() => ({
    active: t('revenue_recognition_page.status.active'),
    completed: t('revenue_recognition_page.status.completed'),
    pending: t('revenue_recognition_page.status.pending'),
    cancelled: t('revenue_recognition_page.status.cancelled'),
}));

const lineStatusLabels = computed(() => ({
    recognized: t('revenue_recognition_page.line_status.recognized'),
    skipped: t('revenue_recognition_page.line_status.skipped'),
    pending: t('revenue_recognition_page.line_status.pending'),
}));

const recognitionMethodLabels = computed(() => ({
    straight_line: t('revenue_recognition_page.recognition_methods.straight_line'),
}));

const statusFilterOptions = computed(() => [
    { label: t('revenue_recognition_page.filters.all'), value: '' },
    { label: t('revenue_recognition_page.status.active'), value: 'active' },
    { label: t('revenue_recognition_page.status.completed'), value: 'completed' },
    { label: t('revenue_recognition_page.status.pending'), value: 'pending' },
    { label: t('revenue_recognition_page.status.cancelled'), value: 'cancelled' },
]);

function nowYear() { return String(new Date().getFullYear()); }
function nowMonth() { return String(new Date().getMonth() + 1).padStart(2, '0'); }

const years = computed(() => {
    const y = [];
    for (let i = 2024; i <= 2028; i++) y.push(i);
    return y;
});

function formatMoney(val) {
    const num = parseFloat(val || 0);
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return '¥' + num.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatShort(val) {
    const num = parseFloat(val || 0);
    if (locale.value === 'en') {
        if (num >= 1000000) return '¥' + (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return '¥' + (num / 1000).toFixed(1) + 'K';
        return '¥' + num.toFixed(0);
    }
    if (num >= 10000) return '¥' + (num / 10000).toFixed(1) + 'w';
    return '¥' + num.toFixed(0);
}

function periodLabel(p) {
    return periodLabels.value[p] || p || '-';
}

function revenueTypeLabel(type) {
    return revenueTypeLabels.value[type] || type || '-';
}

function statusTag(s) {
    const map = { active: 'warning', completed: 'success', pending: 'info', cancelled: 'danger' };
    return map[s] || 'info';
}

function statusLabel(s) {
    return scheduleStatusLabels.value[s] || s;
}

function lineStatusTag(s) {
    const map = { recognized: 'success', skipped: 'danger', pending: 'info' };
    return map[s] || 'info';
}

function lineStatusLabel(s) {
    return lineStatusLabels.value[s] || s;
}

function recognitionMethodLabel(method) {
    return recognitionMethodLabels.value[method] || method || '-';
}

function barHeight(val) {
    const max = Math.max(...(summary.deferred_trend || []).map(i => i.deferred_revenue), 1);
    return Math.max((val / max) * 100, 5);
}

async function loadSummary() {
    try {
        const res = await revenueApi.getSummary();
        Object.assign(summary, res.data);
    } catch (e) {
        console.error('Failed to load summary:', e);
    }
}

async function loadSchedules() {
    loading.value = true;
    try {
        const params = {
            page: currentPage.value,
            per_page: perPage.value,
        };
        if (filterStatus.value) params.status = filterStatus.value;

        const res = await revenueApi.getSchedules(params);
        const data = res.data;
        schedules.value = data.schedules?.data || [];
        if (data.schedules?.meta) {
            totalRecords.value = data.schedules.meta.total;
            currentPage.value = data.schedules.meta.current_page;
        }
        if (data.summary) {
            Object.assign(summary, data.summary);
        }
    } catch (e) {
        console.error('Failed to load schedules:', e);
        ElMessage.error(t('revenue_recognition_page.messages.load_schedules_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleProcessRecognition() {
    processing.value = true;
    try {
        const res = await revenueApi.processRecognition();
        ElMessage.success(t('revenue_recognition_page.messages.recognition_success', {
            count: res.data.result?.recognized_count || 0,
            amount: formatMoney(res.data.result?.total_amount),
        }));
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.recognition_failed'));
    } finally {
        processing.value = false;
    }
}

async function handleCreateSchedules() {
    try {
        const res = await revenueApi.createSchedules();
        ElMessage.success(t('revenue_recognition_page.messages.create_schedules_success', {
            count: res.data.created || 0,
        }));
        loadSchedules();
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.create_schedules_failed'));
    }
}

async function handleGenerateSnapshot() {
    snapshotLoading.value = true;
    try {
        await revenueApi.generateSnapshot();
        ElMessage.success(t('revenue_recognition_page.messages.snapshot_success'));
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.snapshot_failed'));
    } finally {
        snapshotLoading.value = false;
    }
}

function showDetail(row) {
    selectedSchedule.value = row;
    detailVisible.value = true;
    // Load full detail with lines
    revenueApi.getSchedule(row.id).then(res => {
        selectedSchedule.value = res.data;
    }).catch(() => {});
}

function showAsc606Dialog() {
    asc606Visible.value = true;
    asc606Report.value = null;
}

async function loadAsc606Report() {
    reportLoading.value = true;
    try {
        const res = await revenueApi.getAsc606Report({
            year: asc606Year.value,
            month: asc606Month.value,
        });
        asc606Report.value = res.data;
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.report_failed'));
    } finally {
        reportLoading.value = false;
    }
}

async function exportAsc606Report() {
    if (!asc606Report.value) {
        ElMessage.warning(t('revenue_recognition_page.messages.generate_report_first'));
        return;
    }
    try {
        const res = await revenueApi.exportAsc606Report({
            year: asc606Year.value,
            month: asc606Month.value,
        });
        // 创建下载链接
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `asc606-report-${asc606Year.value}-${asc606Month.value}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
        ElMessage.success(t('revenue_recognition_page.messages.export_success'));
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.export_failed'));
    }
}

async function handleCancelSchedule(schedule) {
    try {
        await revenueApi.cancelSchedule(schedule.id, { reason: 'manual_cancel' });
        ElMessage.success(t('revenue_recognition_page.messages.cancel_success'));
        detailVisible.value = false;
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.cancel_failed'));
    }
}

async function handleRecomputeSchedule(schedule) {
    try {
        await revenueApi.recomputeSchedule(schedule.id);
        ElMessage.success(t('revenue_recognition_page.messages.recompute_success'));
        // 刷新详情
        const res = await revenueApi.getSchedule(schedule.id);
        selectedSchedule.value = res.data;
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error(t('revenue_recognition_page.messages.recompute_failed'));
    }
}

onMounted(() => {
    loadSchedules();
});
</script>

<style scoped>
.revenue-recognition-page {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.page-header small {
    font-size: 13px;
    color: #909399;
    font-weight: normal;
}

.text-muted {
    color: #909399;
    font-size: 13px;
}

.stats-row {
    margin-bottom: 16px;
}

.stat-card {
    text-align: center;
    margin-bottom: 12px;
}

.stat-card .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.stat-card .stat-sub {
    font-size: 11px;
    color: #c0c4cc;
    margin-top: 2px;
}

.stat-card.warning .stat-value {
    color: #e6a23c;
}

.stat-card.credit .stat-value {
    color: #67c23a;
}

.stat-card.info .stat-value {
    color: #0f172a;
}

.toolbar-card {
    margin-bottom: 16px;
}

.chart-card {
    margin-bottom: 16px;
}

.trend-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    height: 180px;
    padding-top: 10px;
}

.trend-bar {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    max-width: 60px;
}

.bar-wrapper {
    width: 36px;
    height: 140px;
    background: #f0f2f5;
    border-radius: 4px;
    position: relative;
    display: flex;
    align-items: flex-end;
}

.bar {
    width: 100%;
    background: linear-gradient(to top, #0f172a, #94a3b8);
    border-radius: 4px;
    transition: height 0.3s;
    min-height: 4px;
}

.bar-label {
    font-size: 11px;
    color: #909399;
    margin-top: 4px;
}

.bar-value {
    font-size: 10px;
    color: #606266;
    margin-top: 2px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: center;
}

.text-warning {
    color: #e6a23c;
    font-weight: 600;
}
</style>
