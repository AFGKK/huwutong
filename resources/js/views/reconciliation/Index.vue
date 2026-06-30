<template>
    <div class="recon-page">
        <h2>电商对账系统</h2>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="s in statCards" :key="s.label">
                <el-card shadow="hover"><div class="stat-box"><div class="stat-num" :style="{color:s.color}">{{ s.value }}</div><div class="stat-lbl">{{ s.label }}</div></div></el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: 对账记录 -->
            <el-tab-pane label="对账记录" name="reconciliations">
                <div class="toolbar">
                    <el-select v-model="filters.status" placeholder="状态" clearable style="width:130px" @change="loadRecons">
                        <el-option label="待对账" value="pending" />
                        <el-option label="已匹配" value="matched" />
                        <el-option label="不匹配" value="unmatched" />
                        <el-option label="已解决" value="resolved" />
                    </el-select>
                    <el-input v-model="filters.search" placeholder="搜索支付参考号" clearable style="width:240px" @clear="loadRecons" @keyup.enter="loadRecons" />
                    <el-button @click="loadRecons">刷新</el-button>
                </div>
                <el-table :data="recons" v-loading="loading.recons" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="payment_ref" label="支付参考" width="150" />
                    <el-table-column label="发票金额" width="100"><template #default="{row}">¥{{ row.invoice_amount }}</template></el-table-column>
                    <el-table-column label="实际金额" width="100"><template #default="{row}">¥{{ row.actual_amount }}</template></el-table-column>
                    <el-table-column label="差异" width="100">
                        <template #default="{row}">
                            <span :class="row.difference > 0 ? 'diff-pos' : row.difference < 0 ? 'diff-neg' : ''">
                                ¥{{ row.difference }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{row}">
                            <el-tag :type="reconStatusType(row.status)" size="small">{{ row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="notes" label="备注" min-width="180" show-overflow-tooltip />
                    <el-table-column label="创建时间" width="150"><template #default="{row}">{{ formatTime(row.created_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{row}">
                            <el-button v-if="row.status === 'unmatched' || row.status === 'pending'" link type="primary" size="small" @click="showResolveDialog(row)">解决</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="reconPage" :page-size="reconPerPage" :total="reconTotal" layout="total,prev,pager,next" @current-change="loadRecons" /></div>
            </el-tab-pane>

            <!-- Tab 2: 渠道行 -->
            <el-tab-pane label="渠道明细" name="channelRows">
                <div class="toolbar">
                    <el-select v-model="cfilters.channel" placeholder="渠道" clearable style="width:130px" @change="loadChannelRows">
                        <el-option label="微信支付" value="wechat" />
                        <el-option label="支付宝" value="alipay" />
                        <el-option label="Stripe" value="stripe" />
                        <el-option label="PayPal" value="paypal" />
                    </el-select>
                    <el-select v-model="cfilters.match_status" placeholder="匹配状态" clearable style="width:130px" @change="loadChannelRows">
                        <el-option label="待匹配" value="pending" />
                        <el-option label="已匹配" value="matched" />
                        <el-option label="不匹配" value="unmatched" />
                    </el-select>
                    <el-input v-model="cfilters.search" placeholder="搜索交易号/订单号" clearable style="width:240px" @clear="loadChannelRows" @keyup.enter="loadChannelRows" />
                    <el-button @click="loadChannelRows">刷新</el-button>
                </div>
                <el-table :data="channelRows" v-loading="loading.channelRows" stripe>
                    <el-table-column prop="channel" label="渠道" width="80"><template #default="{row}"><el-tag size="small">{{ row.channel }}</el-tag></template></el-table-column>
                    <el-table-column prop="transaction_id" label="交易号" width="160" />
                    <el-table-column prop="order_id" label="订单号" width="130" />
                    <el-table-column label="金额" width="100"><template #default="{row}">{{ row.currency }} {{ row.amount }}</template></el-table-column>
                    <el-table-column label="匹配状态" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.match_status === 'matched' ? 'success' : row.match_status === 'unmatched' ? 'danger' : 'info'" size="small">{{ row.match_status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="matched_order_no" label="匹配订单" width="130" />
                    <el-table-column label="差异" width="90"><template #default="{row}">¥{{ row.difference }}</template></el-table-column>
                    <el-table-column prop="transaction_time" label="交易时间" width="150"><template #default="{row}">{{ formatTime(row.transaction_time) }}</template></el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{row}">
                            <el-button v-if="row.match_status === 'unmatched'" link type="primary" size="small" @click="showManualMatch(row)">手动匹配</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="cpage" :page-size="cperPage" :total="ctotal" layout="total,prev,pager,next" @current-change="loadChannelRows" /></div>
            </el-tab-pane>

            <!-- Tab 3: CSV 导入 -->
            <el-tab-pane label="CSV 导入" name="imports">
                <el-card shadow="never" class="mb-4">
                    <template #header>上传支付渠道账单</template>
                    <el-form :model="importForm" layout="inline">
                        <el-form-item label="渠道">
                            <el-select v-model="importForm.channel" style="width:160px">
                                <el-option label="微信支付" value="wechat" />
                                <el-option label="支付宝" value="alipay" />
                                <el-option label="Stripe" value="stripe" />
                                <el-option label="PayPal" value="paypal" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="CSV 文件">
                            <el-upload ref="uploadRef" :auto-upload="false" :show-file-list="true" accept=".csv,.txt" :limit="1" :on-change="handleFileChange">
                                <el-button type="primary">选择文件</el-button>
                                <template #tip><span style="font-size:12px;color:#909399">支持微信/支付宝/Stripe/PayPal 导出的 CSV</span></template>
                            </el-upload>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="success" @click="handleImport" :loading="importing">开始导入</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <h4 class="mb-4">导入历史</h4>
                <el-table :data="imports" v-loading="loading.imports" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column label="渠道" width="80"><template #default="{row}"><el-tag size="small">{{ row.channel }}</el-tag></template></el-table-column>
                    <el-table-column prop="filename" label="文件名" min-width="200" />
                    <el-table-column label="总计" width="60" prop="total_rows" />
                    <el-table-column label="已匹配" width="65" prop="matched_rows">
                        <template #default="{row}"><span class="success">{{ row.matched_rows }}</span></template>
                    </el-table-column>
                    <el-table-column label="不匹配" width="65" prop="unmatched_rows">
                        <template #default="{row}"><span class="danger">{{ row.unmatched_rows }}</span></template>
                    </el-table-column>
                    <el-table-column label="错误" width="55" prop="error_rows" />
                    <el-table-column label="状态" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="导入时间" width="150"><template #default="{row}">{{ formatTime(row.created_at) }}</template></el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="ipage" :page-size="iperPage" :total="itotal" layout="total,prev,pager,next" @current-change="loadImports" /></div>
            </el-tab-pane>

            <!-- Tab 4: 对账日历 -->
            <el-tab-pane label="对账日历" name="calendars">
                <div class="toolbar">
                    <el-select v-model="calFilter.type" placeholder="周期" style="width:120px" @change="loadCalendars">
                        <el-option label="每日" value="daily" />
                        <el-option label="每周" value="weekly" />
                        <el-option label="每月" value="monthly" />
                    </el-select>
                    <el-select v-model="calFilter.status" placeholder="状态" clearable style="width:120px" @change="loadCalendars">
                        <el-option label="待对账" value="pending" />
                        <el-option label="进行中" value="in_progress" />
                        <el-option label="已完成" value="completed" />
                    </el-select>
                    <el-button @click="handleGenerateCalendars">生成未来周期</el-button>
                    <el-button @click="loadCalendars">刷新</el-button>
                </div>
                <el-table :data="calendars" v-loading="loading.calendars" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column label="类型" width="70"><template #default="{row}"><el-tag size="small">{{ row.period_type }}</el-tag></template></el-table-column>
                    <el-table-column prop="period_start" label="开始日期" width="120" />
                    <el-table-column prop="period_end" label="结束日期" width="120" />
                    <el-table-column label="总交易" width="80" prop="total_transactions" />
                    <el-table-column label="已匹配" width="70" prop="matched_count" />
                    <el-table-column label="不匹配" width="70" prop="unmatched_count" />
                    <el-table-column label="差异金额" width="100"><template #default="{row}">¥{{ row.difference_amount }}</template></el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'in_progress' ? 'warning' : 'info'" size="small">{{ row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="对账时间" width="150"><template #default="{row}">{{ formatTime(row.reconciled_at) }}</template></el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="calPage" :page-size="calPerPage" :total="calTotal" layout="total,prev,pager,next" @current-change="loadCalendars" /></div>
            </el-tab-pane>

            <!-- Tab 5: 报告 -->
            <el-tab-pane label="对账报告" name="report">
                <el-card shadow="never" class="mb-4">
                    <template #header>生成报告</template>
                    <el-form :model="reportForm" inline>
                        <el-form-item label="开始日期"><el-date-picker v-model="reportForm.start_date" type="date" placeholder="选择日期" /></el-form-item>
                        <el-form-item label="结束日期"><el-date-picker v-model="reportForm.end_date" type="date" placeholder="选择日期" /></el-form-item>
                        <el-form-item><el-button type="primary" @click="generateReport">生成报告</el-button></el-form-item>
                    </el-form>
                </el-card>

                <div v-if="reportData">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6"><el-statistic title="总对账数" :value="reportData.summary?.total_reconciliation || 0" /></el-col>
                        <el-col :span="6"><el-statistic title="已匹配" :value="reportData.summary?.total_matched || 0" /></el-col>
                        <el-col :span="6"><el-statistic title="不匹配" :value="reportData.summary?.total_unmatched || 0" /></el-col>
                        <el-col :span="6"><el-statistic title="总差异" :value="reportData.summary?.total_difference || 0" prefix="¥" /></el-col>
                    </el-row>

                    <h4 class="mb-4">按渠道统计</h4>
                    <el-table :data="channelReportData" stripe size="small">
                        <el-table-column prop="channel" label="渠道" />
                        <el-table-column prop="total" label="总数" />
                        <el-table-column prop="matched" label="已匹配" />
                        <el-table-column prop="unmatched" label="不匹配" />
                        <el-table-column label="总金额"><template #default="{row}">¥{{ row.total_amount }}</template></el-table-column>
                        <el-table-column label="手续费"><template #default="{row}">¥{{ row.total_fee }}</template></el-table-column>
                    </el-table>

                    <h4 class="mb-4" v-if="reportData.unmatched_list?.length">未匹配明细 ({{ reportData.unmatched_list.length }})</h4>
                    <el-table v-if="reportData.unmatched_list?.length" :data="reportData.unmatched_list" stripe size="small">
                        <el-table-column prop="channel" label="渠道" width="80" />
                        <el-table-column prop="transaction_id" label="交易号" width="200" />
                        <el-table-column prop="order_id" label="订单号" width="150" />
                        <el-table-column label="金额" width="100"><template #default="{row}">¥{{ row.amount }}</template></el-table-column>
                        <el-table-column prop="transaction_time" label="交易时间" width="160" />
                    </el-table>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 解决差异对话框 -->
        <el-dialog v-model="resolveVisible" title="标记为已解决" width="450px">
            <el-form :model="resolveForm" label-width="80px">
                <el-form-item label="差异金额"><el-tag type="danger">¥{{ resolveTarget?.difference }}</el-tag></el-form-item>
                <el-form-item label="处理方式">
                    <el-select v-model="resolveForm.resolution" style="width:100%">
                        <el-option label="金额调整" value="金额调整" />
                        <el-option label="手工对平" value="手工对平" />
                        <el-option label="确认为合理差异" value="确认为合理差异" />
                        <el-option label="退款处理" value="退款处理" />
                        <el-option label="其他" value="其他" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model="resolveForm.notes" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="resolveVisible = false">取消</el-button><el-button type="primary" @click="handleResolve">确认解决</el-button></template>
        </el-dialog>

        <!-- 手动匹配对话框 -->
        <el-dialog v-model="matchVisible" title="手动匹配订单" width="500px">
            <el-form :model="matchForm" label-width="100px">
                <el-form-item label="渠道交易"><el-tag>{{ matchTarget?.channel }}: {{ matchTarget?.transaction_id }}</el-tag></el-form-item>
                <el-form-item label="金额">¥{{ matchTarget?.amount }}</el-form-item>
                <el-form-item label="订单ID"><el-input v-model="matchForm.order_id" placeholder="输入订单ID" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="matchVisible = false">取消</el-button><el-button type="primary" @click="handleManualMatch">确认匹配</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    getReconDashboard, getReconciliations, resolveReconciliation,
    getImports, importCsv, getChannelRows, manualMatch,
    getCalendars, generateCalendars, getReport,
} from '@/api/reconciliation';

const activeTab = ref('reconciliations');
const loading = reactive({ recons: false, channelRows: false, imports: false, calendars: false });

// ── 统计卡片 ──
const stats = ref({});
const statCards = reactive([
    { label: '总对账', key: 'totalRecon', color: '#409eff', value: '0' },
    { label: '待对账', key: 'pending', color: '#e6a23c', value: '0' },
    { label: '已匹配', key: 'matched', color: '#67c23a', value: '0' },
    { label: '不匹配', key: 'unmatched', color: '#f56c6c', value: '0' },
    { label: '总差异', key: 'totalDiff', color: '#f56c6c', value: '¥0' },
    { label: '渠道明细', key: 'channelRows', color: '#909399', value: '0' },
]);

// ── 对账记录 ──
const recons = ref([]);
const reconPage = ref(1);
const reconPerPage = ref(20);
const reconTotal = ref(0);
const filters = reactive({ status: '', search: '' });

// ── 渠道行 ──
const channelRows = ref([]);
const cpage = ref(1);
const cperPage = ref(20);
const ctotal = ref(0);
const cfilters = reactive({ channel: '', match_status: '', search: '' });

// ── 导入 ──
const imports = ref([]);
const ipage = ref(1);
const iperPage = ref(20);
const itotal = ref(0);
const importForm = reactive({ channel: 'wechat' });
const importFile = ref(null);
const importing = ref(false);

// ── 日历 ──
const calendars = ref([]);
const calPage = ref(1);
const calPerPage = ref(20);
const calTotal = ref(0);
const calFilter = reactive({ type: 'monthly', status: '' });

// ── 报告 ──
const reportForm = reactive({ start_date: '', end_date: '' });
const reportData = ref(null);
const channelReportData = computed(() => {
    if (!reportData.value?.by_channel) return [];
    return Object.entries(reportData.value.by_channel).map(([channel, data]) => ({ channel, ...data }));
});

// ── 解决 ──
const resolveVisible = ref(false);
const resolveTarget = ref(null);
const resolveForm = reactive({ resolution: '手工对平', notes: '' });

// ── 匹配 ──
const matchVisible = ref(false);
const matchTarget = ref(null);
const matchForm = reactive({ order_id: '' });

function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : ''; }
function reconStatusType(s) { return { pending: 'info', matched: 'success', unmatched: 'danger', resolved: 'primary' }[s] || 'info'; }

async function loadDashboard() {
    try {
        const res = await getReconDashboard();
        const d = res.data?.data || {};
        stats.value = d;
        statCards[0].value = String(d.totalRecon || 0);
        statCards[1].value = String(d.pending || 0);
        statCards[2].value = String(d.matched || 0);
        statCards[3].value = String(d.unmatched || 0);
        statCards[4].value = '¥' + (d.totalDiff || 0);
        statCards[5].value = String(d.channelRows || 0);
    } catch { /* ignore */ }
}

async function loadRecons() {
    loading.recons = true;
    try {
        const params = { page: reconPage.value, per_page: reconPerPage.value };
        if (filters.status) params.status = filters.status;
        if (filters.search) params.search = filters.search;
        const res = await getReconciliations(params);
        recons.value = res.data?.data?.data || [];
        reconTotal.value = res.data?.data?.total || 0;
    } catch { /* ignore */ }
    finally { loading.recons = false; }
}

async function loadChannelRows() {
    loading.channelRows = true;
    try {
        const params = { page: cpage.value, per_page: cperPage.value };
        if (cfilters.channel) params.channel = cfilters.channel;
        if (cfilters.match_status) params.match_status = cfilters.match_status;
        if (cfilters.search) params.search = cfilters.search;
        const res = await getChannelRows(params);
        channelRows.value = res.data?.data?.data || [];
        ctotal.value = res.data?.data?.total || 0;
    } catch { /* ignore */ }
    finally { loading.channelRows = false; }
}

async function loadImports() {
    loading.imports = true;
    try {
        const res = await getImports({ page: ipage.value, per_page: iperPage.value });
        imports.value = res.data?.data?.data || [];
        itotal.value = res.data?.data?.total || 0;
    } catch { /* ignore */ }
    finally { loading.imports = false; }
}

async function loadCalendars() {
    loading.calendars = true;
    try {
        const params = { page: calPage.value, per_page: calPerPage.value };
        if (calFilter.type) params.period_type = calFilter.type;
        if (calFilter.status) params.status = calFilter.status;
        const res = await getCalendars(params);
        calendars.value = res.data?.data?.data || [];
        calTotal.value = res.data?.data?.total || 0;
    } catch { /* ignore */ }
    finally { loading.calendars = false; }
}

function handleFileChange(file) { importFile.value = file.raw; }

async function handleImport() {
    if (!importFile.value) { ElMessage.warning('请选择CSV文件'); return; }
    importing.value = true;
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);
        formData.append('channel', importForm.channel);
        const res = await importCsv(formData);
        ElMessage.success('导入完成');
        importFile.value = null;
        loadImports();
        loadDashboard();
    } catch (e) { ElMessage.error('导入失败'); }
    finally { importing.value = false; }
}

async function handleGenerateCalendars() {
    try {
        const res = await generateCalendars({ type: calFilter.type, months: 3 });
        const count = res.data?.data?.length || 0;
        ElMessage.success(`已生成 ${count} 个对账周期`);
        loadCalendars();
    } catch { ElMessage.error('生成失败'); }
}

function showResolveDialog(row) {
    resolveTarget.value = row;
    resolveForm.resolution = '手工对平';
    resolveForm.notes = '';
    resolveVisible.value = true;
}

async function handleResolve() {
    try {
        await resolveReconciliation(resolveTarget.value.id, resolveForm);
        ElMessage.success('已解决');
        resolveVisible.value = false;
        loadRecons();
        loadDashboard();
    } catch { ElMessage.error('操作失败'); }
}

function showManualMatch(row) {
    matchTarget.value = row;
    matchForm.order_id = '';
    matchVisible.value = true;
}

async function handleManualMatch() {
    if (!matchForm.order_id) { ElMessage.warning('请输入订单ID'); return; }
    try {
        await manualMatch({ channel_row_id: matchTarget.value.id, order_id: matchForm.order_id });
        ElMessage.success('已匹配');
        matchVisible.value = false;
        loadChannelRows();
    } catch { ElMessage.error('匹配失败'); }
}

async function generateReport() {
    try {
        const params = {};
        if (reportForm.start_date) params.start_date = reportForm.start_date;
        if (reportForm.end_date) params.end_date = reportForm.end_date;
        const res = await getReport(params);
        reportData.value = res.data?.data || null;
        ElMessage.success('报告已生成');
    } catch { ElMessage.error('生成失败'); }
}

onMounted(() => {
    loadDashboard();
    loadRecons();
    loadChannelRows();
    loadImports();
    loadCalendars();
});
</script>

<style scoped>
.recon-page { padding: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-box { text-align: center; padding: 4px; }
.stat-num { font-size: 24px; font-weight: 700; }
.stat-lbl { font-size: 13px; color: #909399; margin-top: 2px; }
.toolbar { display: flex; align-items: center; margin-bottom: 16px; gap: 8px; flex-wrap: wrap; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
.diff-pos { color: #f56c6c; font-weight: 600; }
.diff-neg { color: #67c23a; font-weight: 600; }
.success { color: #67c23a; }
.danger { color: #f56c6c; }
</style>
