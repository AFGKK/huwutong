<template>
    <div class="recon-page">
        <h2>{{ t('reconciliation_page.title') }}</h2>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="s in statCards" :key="s.key">
                <el-card shadow="hover"><div class="stat-box"><div class="stat-num" :style="{color:s.color}">{{ s.value }}</div><div class="stat-lbl">{{ s.label }}</div></div></el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: 对账记录 -->
            <el-tab-pane :label="t('reconciliation_page.tab_reconciliations')" name="reconciliations">
                <div class="toolbar">
                    <el-select v-model="filters.status" :placeholder="t('reconciliation_page.status')" clearable style="width:130px" @change="loadRecons">
                        <el-option v-for="opt in reconStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-input v-model="filters.search" :placeholder="t('reconciliation_page.search_payment_ref')" clearable style="width:240px" @clear="loadRecons" @keyup.enter="loadRecons" />
                    <el-button @click="loadRecons">{{ t('reconciliation_page.refresh') }}</el-button>
                </div>
                <el-table :data="recons" v-loading="loading.recons" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="payment_ref" :label="t('reconciliation_page.col_payment_ref')" width="150" />
                    <el-table-column :label="t('reconciliation_page.col_invoice_amount')" width="100"><template #default="{row}">¥{{ row.invoice_amount }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_actual_amount')" width="100"><template #default="{row}">¥{{ row.actual_amount }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_difference')" width="100">
                        <template #default="{row}">
                            <span :class="row.difference > 0 ? 'diff-pos' : row.difference < 0 ? 'diff-neg' : ''">
                                ¥{{ row.difference }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_status')" width="90">
                        <template #default="{row}">
                            <el-tag :type="reconStatusType(row.status)" size="small">{{ reconStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="notes" :label="t('reconciliation_page.col_notes')" min-width="180" show-overflow-tooltip />
                    <el-table-column :label="t('reconciliation_page.col_created_at')" width="150"><template #default="{row}">{{ formatTime(row.created_at) }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_actions')" width="100" fixed="right">
                        <template #default="{row}">
                            <el-button v-if="row.status === 'unmatched' || row.status === 'pending'" link type="primary" size="small" @click="showResolveDialog(row)">{{ t('reconciliation_page.resolve') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="reconPage" :page-size="reconPerPage" :total="reconTotal" layout="total,prev,pager,next" @current-change="loadRecons" /></div>
            </el-tab-pane>

            <!-- Tab 2: 渠道行 -->
            <el-tab-pane :label="t('reconciliation_page.tab_channel_rows')" name="channelRows">
                <div class="toolbar">
                    <el-select v-model="cfilters.channel" :placeholder="t('reconciliation_page.channel')" clearable style="width:130px" @change="loadChannelRows">
                        <el-option v-for="opt in channelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-select v-model="cfilters.match_status" :placeholder="t('reconciliation_page.match_status')" clearable style="width:130px" @change="loadChannelRows">
                        <el-option v-for="opt in matchStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-input v-model="cfilters.search" :placeholder="t('reconciliation_page.search_tx_order')" clearable style="width:240px" @clear="loadChannelRows" @keyup.enter="loadChannelRows" />
                    <el-button @click="loadChannelRows">{{ t('reconciliation_page.refresh') }}</el-button>
                </div>
                <el-table :data="channelRows" v-loading="loading.channelRows" stripe>
                    <el-table-column prop="channel" :label="t('reconciliation_page.channel')" width="80">
                        <template #default="{row}"><el-tag size="small">{{ channelLabel(row.channel) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="transaction_id" :label="t('reconciliation_page.col_transaction_id')" width="160" />
                    <el-table-column prop="order_id" :label="t('reconciliation_page.col_order_id')" width="130" />
                    <el-table-column :label="t('reconciliation_page.col_amount')" width="100"><template #default="{row}">{{ row.currency }} {{ row.amount }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.match_status')" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.match_status === 'matched' ? 'success' : row.match_status === 'unmatched' ? 'danger' : 'info'" size="small">{{ matchStatusLabel(row.match_status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="matched_order_no" :label="t('reconciliation_page.col_matched_order')" width="130" />
                    <el-table-column :label="t('reconciliation_page.col_difference')" width="90"><template #default="{row}">¥{{ row.difference }}</template></el-table-column>
                    <el-table-column prop="transaction_time" :label="t('reconciliation_page.col_transaction_time')" width="150"><template #default="{row}">{{ formatTime(row.transaction_time) }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_actions')" width="100" fixed="right">
                        <template #default="{row}">
                            <el-button v-if="row.match_status === 'unmatched'" link type="primary" size="small" @click="showManualMatch(row)">{{ t('reconciliation_page.manual_match') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="cpage" :page-size="cperPage" :total="ctotal" layout="total,prev,pager,next" @current-change="loadChannelRows" /></div>
            </el-tab-pane>

            <!-- Tab 3: CSV 导入 -->
            <el-tab-pane :label="t('reconciliation_page.tab_imports')" name="imports">
                <el-card shadow="never" class="mb-4">
                    <template #header>{{ t('reconciliation_page.upload_header') }}</template>
                    <el-form :model="importForm" layout="inline">
                        <el-form-item :label="t('reconciliation_page.channel')">
                            <el-select v-model="importForm.channel" style="width:160px">
                                <el-option v-for="opt in channelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('reconciliation_page.csv_file')">
                            <el-upload ref="uploadRef" :auto-upload="false" :show-file-list="true" accept=".csv,.txt" :limit="1" :on-change="handleFileChange">
                                <el-button type="primary">{{ t('reconciliation_page.select_file') }}</el-button>
                                <template #tip><span style="font-size:12px;color:#909399">{{ t('reconciliation_page.csv_tip') }}</span></template>
                            </el-upload>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="success" @click="handleImport" :loading="importing">{{ t('reconciliation_page.start_import') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <h4 class="mb-4">{{ t('reconciliation_page.import_history') }}</h4>
                <el-table :data="imports" v-loading="loading.imports" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column :label="t('reconciliation_page.channel')" width="80">
                        <template #default="{row}"><el-tag size="small">{{ channelLabel(row.channel) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="filename" :label="t('reconciliation_page.col_filename')" min-width="200" />
                    <el-table-column :label="t('reconciliation_page.col_total')" width="60" prop="total_rows" />
                    <el-table-column :label="t('reconciliation_page.recon_st_matched')" width="65" prop="matched_rows">
                        <template #default="{row}"><span class="success">{{ row.matched_rows }}</span></template>
                    </el-table-column>
                    <el-table-column :label="t('reconciliation_page.recon_st_unmatched')" width="65" prop="unmatched_rows">
                        <template #default="{row}"><span class="danger">{{ row.unmatched_rows }}</span></template>
                    </el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_errors')" width="55" prop="error_rows" />
                    <el-table-column :label="t('reconciliation_page.col_status')" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ importStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_import_time')" width="150"><template #default="{row}">{{ formatTime(row.created_at) }}</template></el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="ipage" :page-size="iperPage" :total="itotal" layout="total,prev,pager,next" @current-change="loadImports" /></div>
            </el-tab-pane>

            <!-- Tab 4: 对账日历 -->
            <el-tab-pane :label="t('reconciliation_page.tab_calendars')" name="calendars">
                <div class="toolbar">
                    <el-select v-model="calFilter.type" :placeholder="t('reconciliation_page.period')" style="width:120px" @change="loadCalendars">
                        <el-option v-for="opt in periodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-select v-model="calFilter.status" :placeholder="t('reconciliation_page.status')" clearable style="width:120px" @change="loadCalendars">
                        <el-option v-for="opt in calStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-button @click="handleGenerateCalendars">{{ t('reconciliation_page.generate_periods') }}</el-button>
                    <el-button @click="loadCalendars">{{ t('reconciliation_page.refresh') }}</el-button>
                </div>
                <el-table :data="calendars" v-loading="loading.calendars" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column :label="t('reconciliation_page.col_period_type')" width="70">
                        <template #default="{row}"><el-tag size="small">{{ periodTypeLabel(row.period_type) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="period_start" :label="t('reconciliation_page.col_period_start')" width="120" />
                    <el-table-column prop="period_end" :label="t('reconciliation_page.col_period_end')" width="120" />
                    <el-table-column :label="t('reconciliation_page.col_total_transactions')" width="80" prop="total_transactions" />
                    <el-table-column :label="t('reconciliation_page.recon_st_matched')" width="70" prop="matched_count" />
                    <el-table-column :label="t('reconciliation_page.recon_st_unmatched')" width="70" prop="unmatched_count" />
                    <el-table-column :label="t('reconciliation_page.col_difference_amount')" width="100"><template #default="{row}">¥{{ row.difference_amount }}</template></el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_status')" width="90">
                        <template #default="{row}">
                            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'in_progress' ? 'warning' : 'info'" size="small">{{ calStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('reconciliation_page.col_reconciled_at')" width="150"><template #default="{row}">{{ formatTime(row.reconciled_at) }}</template></el-table-column>
                </el-table>
                <div class="pagination-wrap"><el-pagination v-model:current-page="calPage" :page-size="calPerPage" :total="calTotal" layout="total,prev,pager,next" @current-change="loadCalendars" /></div>
            </el-tab-pane>

            <!-- Tab 5: 报告 -->
            <el-tab-pane :label="t('reconciliation_page.tab_report')" name="report">
                <el-card shadow="never" class="mb-4">
                    <template #header>{{ t('reconciliation_page.generate_report') }}</template>
                    <el-form :model="reportForm" inline>
                        <el-form-item :label="t('reconciliation_page.start_date')"><el-date-picker v-model="reportForm.start_date" type="date" :placeholder="t('reconciliation_page.select_date')" /></el-form-item>
                        <el-form-item :label="t('reconciliation_page.end_date')"><el-date-picker v-model="reportForm.end_date" type="date" :placeholder="t('reconciliation_page.select_date')" /></el-form-item>
                        <el-form-item><el-button type="primary" @click="generateReport">{{ t('reconciliation_page.generate_report') }}</el-button></el-form-item>
                    </el-form>
                </el-card>

                <div v-if="reportData">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6"><el-statistic :title="t('reconciliation_page.report_total_recon')" :value="reportData.summary?.total_reconciliation || 0" /></el-col>
                        <el-col :span="6"><el-statistic :title="t('reconciliation_page.recon_st_matched')" :value="reportData.summary?.total_matched || 0" /></el-col>
                        <el-col :span="6"><el-statistic :title="t('reconciliation_page.recon_st_unmatched')" :value="reportData.summary?.total_unmatched || 0" /></el-col>
                        <el-col :span="6"><el-statistic :title="t('reconciliation_page.stat_total_diff')" :value="reportData.summary?.total_difference || 0" prefix="¥" /></el-col>
                    </el-row>

                    <h4 class="mb-4">{{ t('reconciliation_page.by_channel') }}</h4>
                    <el-table :data="channelReportData" stripe size="small">
                        <el-table-column prop="channel" :label="t('reconciliation_page.channel')">
                            <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
                        </el-table-column>
                        <el-table-column prop="total" :label="t('reconciliation_page.col_count')" />
                        <el-table-column prop="matched" :label="t('reconciliation_page.recon_st_matched')" />
                        <el-table-column prop="unmatched" :label="t('reconciliation_page.recon_st_unmatched')" />
                        <el-table-column :label="t('reconciliation_page.col_total_amount')"><template #default="{row}">¥{{ row.total_amount }}</template></el-table-column>
                        <el-table-column :label="t('reconciliation_page.col_fee')"><template #default="{row}">¥{{ row.total_fee }}</template></el-table-column>
                    </el-table>

                    <h4 class="mb-4" v-if="reportData.unmatched_list?.length">{{ t('reconciliation_page.unmatched_detail', { n: reportData.unmatched_list.length }) }}</h4>
                    <el-table v-if="reportData.unmatched_list?.length" :data="reportData.unmatched_list" stripe size="small">
                        <el-table-column prop="channel" :label="t('reconciliation_page.channel')" width="80">
                            <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
                        </el-table-column>
                        <el-table-column prop="transaction_id" :label="t('reconciliation_page.col_transaction_id')" width="200" />
                        <el-table-column prop="order_id" :label="t('reconciliation_page.col_order_id')" width="150" />
                        <el-table-column :label="t('reconciliation_page.col_amount')" width="100"><template #default="{row}">¥{{ row.amount }}</template></el-table-column>
                        <el-table-column prop="transaction_time" :label="t('reconciliation_page.col_transaction_time')" width="160" />
                    </el-table>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 解决差异对话框 -->
        <el-dialog v-model="resolveVisible" :title="t('reconciliation_page.resolve_title')" width="450px">
            <el-form :model="resolveForm" label-width="80px">
                <el-form-item :label="t('reconciliation_page.resolve_diff_amount')"><el-tag type="danger">¥{{ resolveTarget?.difference }}</el-tag></el-form-item>
                <el-form-item :label="t('reconciliation_page.resolve_method')">
                    <el-select v-model="resolveForm.resolution" style="width:100%">
                        <el-option v-for="opt in resolutionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('reconciliation_page.label_notes')"><el-input v-model="resolveForm.notes" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="resolveVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleResolve">{{ t('reconciliation_page.confirm_resolve') }}</el-button>
            </template>
        </el-dialog>

        <!-- 手动匹配对话框 -->
        <el-dialog v-model="matchVisible" :title="t('reconciliation_page.match_title')" width="500px">
            <el-form :model="matchForm" label-width="100px">
                <el-form-item :label="t('reconciliation_page.match_channel_tx')"><el-tag>{{ channelLabel(matchTarget?.channel) }}: {{ matchTarget?.transaction_id }}</el-tag></el-form-item>
                <el-form-item :label="t('reconciliation_page.col_amount')">¥{{ matchTarget?.amount }}</el-form-item>
                <el-form-item :label="t('reconciliation_page.match_order_id')"><el-input v-model="matchForm.order_id" :placeholder="t('reconciliation_page.match_order_id_ph')" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="matchVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleManualMatch">{{ t('reconciliation_page.confirm_match') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import i18n from '@/i18n';
import { ElMessage } from 'element-plus';
import {
    getReconDashboard, getReconciliations, resolveReconciliation,
    getImports, importCsv, getChannelRows, manualMatch,
    getCalendars, generateCalendars, getReport,
} from '@/api/reconciliation';

const { t, locale } = useI18n();
const apiZh = (key) => i18n.global.t(key, {}, { locale: 'zh_CN' });
const defaultResolution = () => apiZh('reconciliation_page.resolution_manual');

const activeTab = ref('reconciliations');
const loading = reactive({ recons: false, channelRows: false, imports: false, calendars: false });

// ── 统计卡片 ──
const stats = ref({});
const statValues = reactive({
    totalRecon: '0',
    pending: '0',
    matched: '0',
    unmatched: '0',
    totalDiff: '¥0',
    channelRows: '0',
});

const statCards = computed(() => [
    { label: t('reconciliation_page.stat_total'), key: 'totalRecon', color: '#0f172a', value: statValues.totalRecon },
    { label: t('reconciliation_page.stat_pending'), key: 'pending', color: '#e6a23c', value: statValues.pending },
    { label: t('reconciliation_page.stat_matched'), key: 'matched', color: '#67c23a', value: statValues.matched },
    { label: t('reconciliation_page.stat_unmatched'), key: 'unmatched', color: '#f56c6c', value: statValues.unmatched },
    { label: t('reconciliation_page.stat_total_diff'), key: 'totalDiff', color: '#f56c6c', value: statValues.totalDiff },
    { label: t('reconciliation_page.stat_channel_rows'), key: 'channelRows', color: '#909399', value: statValues.channelRows },
]);

const reconStatusOptions = computed(() => [
    { label: t('reconciliation_page.recon_st_pending'), value: 'pending' },
    { label: t('reconciliation_page.recon_st_matched'), value: 'matched' },
    { label: t('reconciliation_page.recon_st_unmatched'), value: 'unmatched' },
    { label: t('reconciliation_page.recon_st_resolved'), value: 'resolved' },
]);

const matchStatusOptions = computed(() => [
    { label: t('reconciliation_page.match_st_pending'), value: 'pending' },
    { label: t('reconciliation_page.match_st_matched'), value: 'matched' },
    { label: t('reconciliation_page.match_st_unmatched'), value: 'unmatched' },
]);

const channelOptions = computed(() => [
    { label: t('reconciliation_page.channel_wechat'), value: 'wechat' },
    { label: t('reconciliation_page.channel_alipay'), value: 'alipay' },
    { label: t('reconciliation_page.channel_stripe'), value: 'stripe' },
    { label: t('reconciliation_page.channel_paypal'), value: 'paypal' },
]);

const periodOptions = computed(() => [
    { label: t('reconciliation_page.period_daily'), value: 'daily' },
    { label: t('reconciliation_page.period_weekly'), value: 'weekly' },
    { label: t('reconciliation_page.period_monthly'), value: 'monthly' },
]);

const calStatusOptions = computed(() => [
    { label: t('reconciliation_page.cal_st_pending'), value: 'pending' },
    { label: t('reconciliation_page.cal_st_in_progress'), value: 'in_progress' },
    { label: t('reconciliation_page.cal_st_completed'), value: 'completed' },
]);

const resolutionOptions = computed(() => [
    { label: t('reconciliation_page.resolution_amount_adjust'), value: apiZh('reconciliation_page.resolution_amount_adjust') },
    { label: t('reconciliation_page.resolution_manual'), value: apiZh('reconciliation_page.resolution_manual') },
    { label: t('reconciliation_page.resolution_acceptable'), value: apiZh('reconciliation_page.resolution_acceptable') },
    { label: t('reconciliation_page.resolution_refund'), value: apiZh('reconciliation_page.resolution_refund') },
    { label: t('reconciliation_page.resolution_other'), value: apiZh('reconciliation_page.resolution_other') },
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
const resolveForm = reactive({ resolution: defaultResolution(), notes: '' });

// ── 匹配 ──
const matchVisible = ref(false);
const matchTarget = ref(null);
const matchForm = reactive({ order_id: '' });

function formatTime(ts) {
    if (!ts) return '';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(ts).toLocaleString(loc);
}

function reconStatusType(s) { return { pending: 'info', matched: 'success', unmatched: 'danger', resolved: 'primary' }[s] || 'info'; }

function reconStatusLabel(s) {
    const map = {
        pending: t('reconciliation_page.recon_st_pending'),
        matched: t('reconciliation_page.recon_st_matched'),
        unmatched: t('reconciliation_page.recon_st_unmatched'),
        resolved: t('reconciliation_page.recon_st_resolved'),
    };
    return map[s] || s;
}

function matchStatusLabel(s) {
    const map = {
        pending: t('reconciliation_page.match_st_pending'),
        matched: t('reconciliation_page.match_st_matched'),
        unmatched: t('reconciliation_page.match_st_unmatched'),
    };
    return map[s] || s;
}

function channelLabel(c) {
    const map = {
        wechat: t('reconciliation_page.channel_wechat'),
        alipay: t('reconciliation_page.channel_alipay'),
        stripe: t('reconciliation_page.channel_stripe'),
        paypal: t('reconciliation_page.channel_paypal'),
    };
    return map[c] || c;
}

function importStatusLabel(s) {
    const map = {
        completed: t('reconciliation_page.import_st_completed'),
        failed: t('reconciliation_page.import_st_failed'),
        processing: t('reconciliation_page.import_st_processing'),
    };
    return map[s] || s;
}

function calStatusLabel(s) {
    const map = {
        pending: t('reconciliation_page.cal_st_pending'),
        in_progress: t('reconciliation_page.cal_st_in_progress'),
        completed: t('reconciliation_page.cal_st_completed'),
    };
    return map[s] || s;
}

function periodTypeLabel(p) {
    const map = {
        daily: t('reconciliation_page.period_daily'),
        weekly: t('reconciliation_page.period_weekly'),
        monthly: t('reconciliation_page.period_monthly'),
    };
    return map[p] || p;
}

async function loadDashboard() {
    try {
        const res = await getReconDashboard();
        const d = res.data?.data || {};
        stats.value = d;
        statValues.totalRecon = String(d.totalRecon || 0);
        statValues.pending = String(d.pending || 0);
        statValues.matched = String(d.matched || 0);
        statValues.unmatched = String(d.unmatched || 0);
        statValues.totalDiff = '¥' + (d.totalDiff || 0);
        statValues.channelRows = String(d.channelRows || 0);
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
    if (!importFile.value) { ElMessage.warning(t('reconciliation_page.msg_select_csv')); return; }
    importing.value = true;
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);
        formData.append('channel', importForm.channel);
        await importCsv(formData);
        ElMessage.success(t('reconciliation_page.msg_import_done'));
        importFile.value = null;
        loadImports();
        loadDashboard();
    } catch { ElMessage.error(t('reconciliation_page.msg_import_failed')); }
    finally { importing.value = false; }
}

async function handleGenerateCalendars() {
    try {
        const res = await generateCalendars({ type: calFilter.type, months: 3 });
        const count = res.data?.data?.length || 0;
        ElMessage.success(t('reconciliation_page.msg_periods_generated', { n: count }));
        loadCalendars();
    } catch { ElMessage.error(t('reconciliation_page.msg_generate_failed')); }
}

function showResolveDialog(row) {
    resolveTarget.value = row;
    resolveForm.resolution = defaultResolution();
    resolveForm.notes = '';
    resolveVisible.value = true;
}

async function handleResolve() {
    try {
        await resolveReconciliation(resolveTarget.value.id, resolveForm);
        ElMessage.success(t('reconciliation_page.msg_resolved'));
        resolveVisible.value = false;
        loadRecons();
        loadDashboard();
    } catch { ElMessage.error(t('messages.failed')); }
}

function showManualMatch(row) {
    matchTarget.value = row;
    matchForm.order_id = '';
    matchVisible.value = true;
}

async function handleManualMatch() {
    if (!matchForm.order_id) { ElMessage.warning(t('reconciliation_page.msg_order_id_required')); return; }
    try {
        await manualMatch({ channel_row_id: matchTarget.value.id, order_id: matchForm.order_id });
        ElMessage.success(t('reconciliation_page.msg_matched'));
        matchVisible.value = false;
        loadChannelRows();
    } catch { ElMessage.error(t('reconciliation_page.msg_match_failed')); }
}

async function generateReport() {
    try {
        const params = {};
        if (reportForm.start_date) params.start_date = reportForm.start_date;
        if (reportForm.end_date) params.end_date = reportForm.end_date;
        const res = await getReport(params);
        reportData.value = res.data?.data || null;
        ElMessage.success(t('reconciliation_page.msg_report_generated'));
    } catch { ElMessage.error(t('reconciliation_page.msg_generate_failed')); }
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
