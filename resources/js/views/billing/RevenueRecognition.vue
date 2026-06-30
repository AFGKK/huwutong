<template>
    <div class="revenue-recognition-page">
        <div class="page-header">
            <h2>收入确认报告 <small>ASC 606 / IFRS 15</small></h2>
            <p class="text-muted">订阅收入分期确认、递延收入计算与月度财务快照</p>
        </div>

        <!-- 汇总概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(summary.total_amount) }}</div>
                    <div class="stat-label">排程总额</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(summary.recognized_amount) }}</div>
                    <div class="stat-label">已确认收入</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card warning">
                    <div class="stat-value">{{ formatMoney(summary.deferred_amount) }}</div>
                    <div class="stat-label">递延收入余额</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ summary.completion_rate }}%</div>
                    <div class="stat-label">确认完成率</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ summary.this_month_to_recognize ? formatMoney(summary.this_month_to_recognize) : '¥0' }}</div>
                    <div class="stat-label">本月待确认</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card info">
                    <div class="stat-value">{{ summary.active_schedules }}</div>
                    <div class="stat-label">活跃排程</div>
                    <div class="stat-sub">{{ summary.completed_schedules }} 已完成</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作按钮 -->
        <el-card class="toolbar-card">
            <el-button type="primary" @click="handleProcessRecognition" :icon="Pointer" :loading="processing">
                执行当期收入确认
            </el-button>
            <el-button @click="handleCreateSchedules" :icon="Plus">
                创建未排程发票排程
            </el-button>
            <el-button @click="handleGenerateSnapshot" :icon="DataBoard" :loading="snapshotLoading">
                生成月度快照
            </el-button>
            <el-button @click="showAsc606Dialog" :icon="Document" style="float: right">
                ASC 606 报告
            </el-button>
        </el-card>

        <!-- 递延收入趋势（从 summary.deferred_trend 来） -->
        <el-card class="chart-card" v-if="summary.deferred_trend && summary.deferred_trend.length">
            <template #header>
                <span><el-icon><DataLine /></el-icon> 递延收入趋势（近12个月）</span>
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
                    <span><el-icon><List /></el-icon> 收入确认排程</span>
                    <div>
                        <el-select v-model="filterStatus" clearable placeholder="排程状态" size="small" style="width: 140px; margin-right: 8px;" @change="loadSchedules">
                            <el-option label="全部" value="" />
                            <el-option label="进行中" value="active" />
                            <el-option label="已完成" value="completed" />
                            <el-option label="待处理" value="pending" />
                            <el-option label="已取消" value="cancelled" />
                        </el-select>
                        <el-button size="small" @click="loadSchedules" :icon="Refresh">刷新</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="schedules" v-loading="loading" stripe empty-text="暂无排程" style="width: 100%">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="revenue_type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.revenue_type === 'subscription' ? 'primary' : 'warning'" size="small">
                            {{ row.revenue_type === 'subscription' ? '订阅' : '升级' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_period" label="周期" width="100">
                    <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                </el-table-column>
                <el-table-column prop="total_amount" label="总额" width="120">
                    <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                </el-table-column>
                <el-table-column prop="recognized_amount" label="已确认" width="120">
                    <template #default="{ row }">{{ formatMoney(row.recognized_amount) }}</template>
                </el-table-column>
                <el-table-column prop="deferred_amount" label="递延" width="120">
                    <template #default="{ row }">
                        <span class="text-warning">{{ formatMoney(row.deferred_amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="进度" width="140">
                    <template #default="{ row }">
                        <el-progress :percentage="row.progress || 0" :status="row.progress >= 100 ? 'success' : undefined" />
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="start_date" label="期间" min-width="160">
                    <template #default="{ row }">{{ row.start_date }} ~ {{ row.end_date }}</template>
                </el-table-column>
                <el-table-column prop="invoice.invoice_no" label="发票" width="130" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="showDetail(row)">详情</el-button>
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
        <el-dialog v-model="detailVisible" title="排程详情" width="750px">
            <template v-if="selectedSchedule">
                <div class="flex justify-between mb-3">
                    <span class="font-bold">排程 #{{ selectedSchedule.id }}</span>
                    <div>
                        <el-button v-if="selectedSchedule.status === 'active'" size="small" type="warning" @click="handleCancelSchedule(selectedSchedule)">取消排程</el-button>
                        <el-button v-if="selectedSchedule.status === 'active'" size="small" type="primary" @click="handleRecomputeSchedule(selectedSchedule)">重算排程</el-button>
                    </div>
                </div>
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="类型">{{ selectedSchedule.revenue_type }}</el-descriptions-item>
                    <el-descriptions-item label="计费周期">{{ periodLabel(selectedSchedule.billing_period) }}</el-descriptions-item>
                    <el-descriptions-item label="总额">{{ formatMoney(selectedSchedule.total_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="已确认">{{ formatMoney(selectedSchedule.recognized_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="递延">{{ formatMoney(selectedSchedule.deferred_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="进度">
                        <el-progress :percentage="selectedSchedule.progress" :status="selectedSchedule.progress >= 100 ? 'success' : undefined" style="width: 150px" />
                    </el-descriptions-item>
                    <el-descriptions-item label="期间" :span="2">{{ selectedSchedule.start_date }} ~ {{ selectedSchedule.end_date }}</el-descriptions-item>
                    <el-descriptions-item label="期数">{{ selectedSchedule.recognized_periods }} / {{ selectedSchedule.total_periods }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTag(selectedSchedule.status)">{{ statusLabel(selectedSchedule.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="发票">{{ selectedSchedule.invoice?.invoice_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="订阅">{{ selectedSchedule.subscription?.plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="确认方式">{{ selectedSchedule.recognition_method === 'straight_line' ? '直线法' : selectedSchedule.recognition_method }}</el-descriptions-item>
                    <el-descriptions-item label="最近确认">{{ selectedSchedule.last_recognized_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item v-if="selectedSchedule.cancel_reason" label="取消原因">{{ selectedSchedule.cancel_reason }}</el-descriptions-item>
                    <el-descriptions-item v-if="selectedSchedule.cancelled_at" label="取消时间">{{ selectedSchedule.cancelled_at }}</el-descriptions-item>
                </el-descriptions>

                <h4 style="margin: 20px 0 10px">确认明细</h4>
                <el-table :data="selectedSchedule.lines || []" size="small" stripe>
                    <el-table-column prop="period_number" label="期数" width="60" />
                    <el-table-column prop="recognition_date" label="确认日期" width="130" />
                    <el-table-column prop="amount" label="金额" width="120">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'recognized' ? 'success' : (row.status === 'skipped' ? 'danger' : 'info')" size="small">
                                {{ row.status === 'recognized' ? '已确认' : (row.status === 'skipped' ? '已跳过' : '待确认') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="recognized_at" label="确认时间" width="160" />
                    <el-table-column prop="reason" label="原因" width="100" />
                    <el-table-column prop="description" label="说明" min-width="140" />
                </el-table>
            </template>
        </el-dialog>

        <!-- ASC 606 报告弹窗 -->
        <el-dialog v-model="asc606Visible" title="ASC 606 收入确认报告" width="800px">
            <el-form :inline="true">
                <el-form-item label="年份">
                    <el-select v-model="asc606Year" style="width: 120px">
                        <el-option v-for="y in years" :key="y" :label="y" :value="y" />
                    </el-select>
                </el-form-item>
                <el-form-item label="月份">
                    <el-select v-model="asc606Month" style="width: 100px">
                        <el-option v-for="m in 12" :key="m" :label="String(m).padStart(2, '0')" :value="String(m).padStart(2, '0')" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadAsc606Report" :loading="reportLoading">生成报告</el-button>
                    <el-button v-if="asc606Report" @click="exportAsc606Report" :icon="Download">导出CSV</el-button>
                </el-form-item>
            </el-form>

            <template v-if="asc606Report">
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item label="报告期间">{{ asc606Report.report_period }}</el-descriptions-item>
                    <el-descriptions-item label="确认方法">{{ asc606Report.recognition_method === 'straight_line' ? '直线法' : asc606Report.recognition_method }}</el-descriptions-item>
                    <el-descriptions-item label="期初递延">{{ formatMoney(asc606Report.opening_deferred_revenue) }}</el-descriptions-item>
                    <el-descriptions-item label="期末递延">{{ formatMoney(asc606Report.closing_deferred_revenue) }}</el-descriptions-item>
                    <el-descriptions-item label="已开发票">{{ formatMoney(asc606Report.total_invoiced) }}</el-descriptions-item>
                    <el-descriptions-item label="已确认收入">{{ formatMoney(asc606Report.recognized_revenue) }}</el-descriptions-item>
                    <el-descriptions-item label="递延变动">{{ formatMoney(asc606Report.change_in_deferred) }}</el-descriptions-item>
                    <el-descriptions-item label="新增排程数">{{ asc606Report.new_schedules_count }}</el-descriptions-item>
                    <el-descriptions-item label="新增排程金额">{{ formatMoney(asc606Report.new_schedules_value) }}</el-descriptions-item>
                </el-descriptions>

                <!-- 按产品拆分 -->
                <template v-if="asc606Report.product_breakdown && asc606Report.product_breakdown.length">
                    <h4 style="margin: 16px 0 8px">按产品拆分</h4>
                    <el-table :data="asc606Report.product_breakdown" size="small" stripe>
                        <el-table-column prop="product" label="产品" min-width="120" />
                        <el-table-column prop="recognized_amount" label="已确认金额" width="140">
                            <template #default="{ row }">{{ formatMoney(row.recognized_amount) }}</template>
                        </el-table-column>
                        <el-table-column prop="transaction_count" label="交易笔数" width="100" />
                    </el-table>
                </template>

                <h4 style="margin: 16px 0 8px">已确认交易（{{ (asc606Report.recognized_transactions || []).length }} 笔）</h4>
                <el-table :data="asc606Report.recognized_transactions || []" size="small" max-height="300" stripe>
                    <el-table-column prop="schedule_id" label="排程ID" width="80" />
                    <el-table-column prop="period" label="期数" width="60" />
                    <el-table-column prop="amount" label="金额" width="120">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="recognition_date" label="确认日期" width="130" />
                    <el-table-column prop="invoice_no" label="发票号" width="140" />
                    <el-table-column prop="product" label="产品" min-width="100" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Pointer, Plus, DataBoard, DataLine, Document, List, Refresh, Download } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import revenueApi from '../../api/revenueRecognition';

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

function nowYear() { return String(new Date().getFullYear()); }
function nowMonth() { return String(new Date().getMonth() + 1).padStart(2, '0'); }

const years = computed(() => {
    const y = [];
    for (let i = 2024; i <= 2028; i++) y.push(i);
    return y;
});

function formatMoney(val) {
    const num = parseFloat(val || 0);
    return '¥' + num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatShort(val) {
    const num = parseFloat(val || 0);
    if (num >= 10000) return '¥' + (num / 10000).toFixed(1) + 'w';
    return '¥' + num.toFixed(0);
}

function periodLabel(p) {
    const map = { monthly: '月付', quarterly: '季付', semi_annually: '半年付', yearly: '年付' };
    return map[p] || p || '-';
}

function statusTag(s) {
    const map = { active: 'warning', completed: 'success', pending: 'info', cancelled: 'danger' };
    return map[s] || 'info';
}

function statusLabel(s) {
    const map = { active: '进行中', completed: '已完成', pending: '待处理', cancelled: '已取消' };
    return map[s] || s;
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
        ElMessage.error('加载排程失败');
    } finally {
        loading.value = false;
    }
}

async function handleProcessRecognition() {
    processing.value = true;
    try {
        const res = await revenueApi.processRecognition();
        ElMessage.success(`已确认 ${res.data.result?.recognized_count || 0} 条，金额: ${formatMoney(res.data.result?.total_amount)}`);
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error('确认失败');
    } finally {
        processing.value = false;
    }
}

async function handleCreateSchedules() {
    try {
        const res = await revenueApi.createSchedules();
        ElMessage.success(`已创建 ${res.data.created || 0} 个排程`);
        loadSchedules();
    } catch (e) {
        ElMessage.error('创建排程失败');
    }
}

async function handleGenerateSnapshot() {
    snapshotLoading.value = true;
    try {
        await revenueApi.generateSnapshot();
        ElMessage.success('月度快照已生成');
    } catch (e) {
        ElMessage.error('生成失败');
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
        ElMessage.error('生成报告失败');
    } finally {
        reportLoading.value = false;
    }
}

async function exportAsc606Report() {
    if (!asc606Report.value) {
        ElMessage.warning('请先生成报告');
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
        ElMessage.success('报告已导出');
    } catch (e) {
        ElMessage.error('导出失败');
    }
}

async function handleCancelSchedule(schedule) {
    try {
        await revenueApi.cancelSchedule(schedule.id, { reason: 'manual_cancel' });
        ElMessage.success('排程已取消');
        detailVisible.value = false;
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error('取消失败');
    }
}

async function handleRecomputeSchedule(schedule) {
    try {
        await revenueApi.recomputeSchedule(schedule.id);
        ElMessage.success('排程已重算');
        // 刷新详情
        const res = await revenueApi.getSchedule(schedule.id);
        selectedSchedule.value = res.data;
        loadSchedules();
        loadSummary();
    } catch (e) {
        ElMessage.error('重算失败');
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
    color: #409eff;
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
    color: #409eff;
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
    background: linear-gradient(to top, #409eff, #79bbff);
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
