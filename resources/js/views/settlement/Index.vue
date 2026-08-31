<template>
    <div class="settlement-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('settlement_page.title') }}</h2>
                <span class="header-subtitle">{{ t('settlement_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="scanReleasable" :loading="scanLoading">
                    <el-icon><Search /></el-icon> {{ t('settlement_page.scan_releasable') }}
                </el-button>
                <el-button type="primary" @click="openCreateBatchDialog">
                    <el-icon><Plus /></el-icon> {{ t('settlement_page.new_batch') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.pending_settlements ?? '-' }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_pending_settlements') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.releasable_count ?? '-' }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_releasable_count') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.pending_payouts) }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_pending_payouts') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.active_agents ?? '-' }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_active_agents') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.monthly_settled) }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_monthly_settled') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.monthly_fees) }}</div>
                    <div class="stat-label">{{ t('settlement_page.stat_monthly_fees') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- ═══ 结算周期 ═══ -->
                <el-tab-pane :label="t('settlement_page.tab_cycles')" name="cycles">
                    <template #label>
                        <span><el-icon><Calendar /></el-icon> {{ t('settlement_page.tab_cycles') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="handleGenerateCycle" :loading="cycleGenLoading">
                            <el-icon><Plus /></el-icon> {{ t('settlement_page.generate_monthly_cycle') }}
                        </el-button>
                        <el-select v-model="cycleFilter.status" clearable :placeholder="t('settlement_page.status')" style="width: 130px">
                            <el-option v-for="opt in cycleStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </div>
                    <el-table :data="cycles" v-loading="cyclesLoading" stripe>
                        <el-table-column :label="t('settlement_page.col_cycle_name')" prop="name" min-width="180" />
                        <el-table-column :label="t('settlement_page.col_type')" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ periodTypeLabel(row.period_type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_period_range')" min-width="200">
                            <template #default="{ row }">
                                {{ row.period_start }} ~ {{ row.period_end }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_total_commission')" width="120">
                            <template #default="{ row }">
                                {{ formatMoney(row.total_commission) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_payout_date')" width="110" prop="payout_date">
                            <template #default="{ row }">{{ row.payout_date || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="cycleStatusTag(row.status)" size="small">
                                    {{ cycleStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="viewCycleDetail(row)">
                                    {{ t('settlement_page.detail') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper" v-if="cycleTotal > 0">
                        <el-pagination
                            v-model:current-page="cyclePage"
                            v-model:page-size="cyclePerPage"
                            :total="cycleTotal"
                            size="default"
                            layout="total, prev, pager, next"
                            @current-change="loadCycles"
                        />
                    </div>
                </el-tab-pane>

                <!-- ═══ 结算批次 ═══ -->
                <el-tab-pane :label="t('settlement_page.tab_batches')" name="batches">
                    <template #label>
                        <span><el-icon><List /></el-icon> {{ t('settlement_page.tab_batches') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-select v-model="batchFilter.status" clearable :placeholder="t('settlement_page.status')" style="width: 130px">
                            <el-option v-for="opt in batchStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-input v-model="batchFilter.search" :placeholder="t('settlement_page.search_batch_no')" clearable style="width: 200px" @keyup.enter="loadBatches" />
                        <el-button type="primary" @click="loadBatches"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                    </div>
                    <el-table :data="batches" v-loading="batchesLoading" stripe>
                        <el-table-column :label="t('settlement_page.col_batch_no')" prop="batch_no" width="180" />
                        <el-table-column :label="t('settlement_page.col_cycle')" min-width="150">
                            <template #default="{ row }">{{ row.settlement_cycle?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_channel')" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ channelLabel(row.channel) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_total_amount')" width="120">
                            <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_fee')" width="100">
                            <template #default="{ row }">{{ formatMoney(row.total_fee) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_net_amount')" width="120">
                            <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_item_count')" width="60" prop="item_count" />
                        <el-table-column :label="t('settlement_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="batchStatusTag(row.status)" size="small">
                                    {{ batchStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_actions')" width="240" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="viewBatchDetail(row)">{{ t('settlement_page.detail') }}</el-button>
                                <el-button v-if="row.status === 'draft'" text size="small" type="warning" @click="handleSubmitBatch(row)">{{ t('actions.submit') }}</el-button>
                                <el-button v-if="row.status === 'pending_approval'" text size="small" type="success" @click="handleApproveBatch(row)">{{ t('actions.approve') }}</el-button>
                                <el-button v-if="row.status === 'approved'" text size="small" type="primary" @click="handleCompleteBatch(row)">{{ t('settlement_page.complete') }}</el-button>
                                <el-button v-if="['draft', 'pending_approval'].includes(row.status)" text size="small" type="danger" @click="handleCancelBatch(row)">{{ t('actions.cancel') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper" v-if="batchTotal > 0">
                        <el-pagination
                            v-model:current-page="batchPage"
                            v-model:page-size="batchPerPage"
                            :total="batchTotal"
                            size="default"
                            layout="total, prev, pager, next"
                            @current-change="loadBatches"
                        />
                    </div>
                </el-tab-pane>

                <!-- ═══ 平台费用 ═══ -->
                <el-tab-pane :label="t('settlement_page.tab_fees')" name="fees">
                    <template #label>
                        <span><el-icon><Money /></el-icon> {{ t('settlement_page.tab_fees') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-date-picker
                            v-model="feeYearMonth"
                            type="month"
                            :placeholder="t('settlement_page.select_month')"
                            value-format="YYYY-MM"
                            style="width: 160px"
                            @change="loadFeeStats"
                        />
                    </div>
                    <el-row :gutter="16" v-if="feeStats">
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ formatMoney(feeStats.total_fees) }}</div>
                                <div class="stat-label">{{ t('settlement_page.fee_total') }}</div>
                            </el-card>
                        </el-col>
                        <el-col v-for="(total, type) in feeStats.by_type" :key="type" :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ formatMoney(total) }}</div>
                                <div class="stat-label">{{ feeTypeLabel(type) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <div v-else class="text-muted" style="padding: 40px; text-align: center;">{{ t('settlement_page.no_fee_data') }}</div>
                </el-tab-pane>

                <!-- ═══ 可结算扫描 ═══ -->
                <el-tab-pane :label="t('settlement_page.tab_releasable')" name="releasable">
                    <template #label>
                        <span><el-icon><Search /></el-icon> {{ t('settlement_page.tab_releasable') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="scanReleasable" :loading="scanLoading">
                            <el-icon><Refresh /></el-icon> {{ t('settlement_page.rescan') }}
                        </el-button>
                        <span class="text-muted" v-if="releasableData">
                            {{ t('settlement_page.releasable_summary', { count: releasableData.total_count, amount: formatMoney(releasableData.total_amount) }) }}
                        </span>
                    </div>
                    <el-table :data="releasableData?.items || []" v-loading="scanLoading" stripe>
                        <el-table-column :label="t('settlement_page.col_agent')" min-width="180">
                            <template #default="{ row }">{{ row.agent?.user?.name || 'ID:' + row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_commission_amount')" width="120">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_release_date')" width="110" prop="released_at">
                            <template #default="{ row }">{{ row.released_at ? formatDateOnly(row.released_at) : '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" type="success">{{ t('settlement_page.releasable_status') }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 创建批次 Dialog -->
        <el-dialog v-model="createBatchVisible" :title="t('settlement_page.dialog_create_batch')" width="600px">
            <el-form ref="batchFormRef" :model="batchForm" :rules="batchFormRules" label-width="120px">
                <el-form-item :label="t('settlement_page.label_linked_cycle')">
                    <el-select v-model="batchForm.settlement_cycle_id" clearable :placeholder="t('settlement_page.ph_select_cycle')" style="width: 100%">
                        <el-option v-for="c in cycles" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('settlement_page.label_payout_channel')" prop="channel">
                    <el-select v-model="batchForm.channel" style="width: 100%">
                        <el-option v-for="opt in channelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('settlement_page.label_select_settlements')" prop="settlement_ids">
                    <el-table :data="releasableData?.items || []" max-height="300" @selection-change="onSelectionChange">
                        <el-table-column type="selection" width="40" />
                        <el-table-column :label="t('settlement_page.col_agent')" min-width="140">
                            <template #default="{ row }">{{ row.agent?.user?.name || 'ID:' + row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_amount')" width="100">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('settlement_page.col_release_day')" width="90" prop="released_at" />
                    </el-table>
                    <div class="text-muted" v-if="!releasableData?.items?.length">{{ t('settlement_page.hint_scan_first') }}</div>
                </el-form-item>
                <el-form-item :label="t('settlement_page.label_notes')">
                    <el-input v-model="batchForm.notes" type="textarea" :rows="2" :placeholder="t('settlement_page.ph_notes_optional')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createBatchVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="batchCreating" @click="submitBatch">{{ t('settlement_page.create_batch') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批次详情 Dialog -->
        <el-dialog v-model="batchDetailVisible" :title="t('settlement_page.batch_detail_title')" width="700px">
            <div v-if="batchDetail">
                <el-descriptions :column="2" size="small" border>
                    <el-descriptions-item :label="t('settlement_page.col_batch_no')">{{ batchDetail.batch_no }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_status')">
                        <el-tag :type="batchStatusTag(batchDetail.status)" size="small">{{ batchStatusLabel(batchDetail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_channel')">{{ channelLabel(batchDetail.channel) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_total_amount')">{{ formatMoney(batchDetail.total_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_fee')">{{ formatMoney(batchDetail.total_fee) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_net_amount')">{{ formatMoney(batchDetail.net_amount) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_item_count')">{{ batchDetail.item_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_created_at')">{{ formatDate(batchDetail.created_at) }}</el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 style="margin: 0 0 12px;">{{ t('settlement_page.item_list') }}</h4>
                <el-table :data="batchDetail.items || []" size="small" stripe>
                    <el-table-column :label="t('settlement_page.col_type')" width="120">
                        <template #default="{ row }">{{ row.settleable_type?.includes('CommissionSettlement') ? t('settlement_page.type_commission_settlement') : t('settlement_page.type_withdrawal') }}</template>
                    </el-table-column>
                    <el-table-column :label="t('settlement_page.col_amount')" width="100" prop="amount">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('settlement_page.col_fee')" width="80" prop="fee">
                        <template #default="{ row }">{{ formatMoney(row.fee) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('settlement_page.col_net_amount')" width="100" prop="net_amount">
                        <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('settlement_page.col_status')" width="80">
                        <template #default="{ row }">
                            <el-tag size="small" type="success">{{ row.status === 'paid' ? t('settlement_page.item_st_paid') : t('settlement_page.item_st_pending') }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <template #footer>
                <el-button @click="batchDetailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 周期详情 Dialog -->
        <el-dialog v-model="cycleDetailVisible" :title="t('settlement_page.cycle_detail_title')" width="700px">
            <div v-if="cycleDetail">
                <el-descriptions :column="2" size="small" border>
                    <el-descriptions-item :label="t('settlement_page.col_cycle_name')">{{ cycleDetail.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_type')">{{ periodTypeLabel(cycleDetail.period_type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_period_range')">
                        {{ cycleDetail.period_start }} ~ {{ cycleDetail.period_end }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_settlement_date')">{{ cycleDetail.settlement_date }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_payout_day')">{{ cycleDetail.payout_date || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_status')">
                        <el-tag :type="cycleStatusTag(cycleDetail.status)" size="small">{{ cycleStatusLabel(cycleDetail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_total_commission')">{{ formatMoney(cycleDetail.total_commission) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('settlement_page.col_agent_count')">{{ cycleDetail.agent_count }}</el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 style="margin: 0 0 12px;">{{ t('settlement_page.linked_batches') }}</h4>
                <el-table :data="cycleDetail.batches || []" size="small" stripe>
                    <el-table-column :label="t('settlement_page.col_batch_no')" prop="batch_no" />
                    <el-table-column :label="t('settlement_page.col_amount')" width="120">
                        <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('settlement_page.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="batchStatusTag(row.status)" size="small">{{ batchStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <template #footer>
                <el-button @click="cycleDetailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, Calendar, List, Money, Refresh } from '@element-plus/icons-vue';
import settlementApi from '@/api/settlement';

const { t, locale } = useI18n();

const activeTab = ref('cycles');

// ─── Dashboard ───
const dashData = ref({});
const scanLoading = ref(false);

// ─── Cycles ───
const cycles = ref([]);
const cyclesLoading = ref(false);
const cycleTotal = ref(0);
const cyclePage = ref(1);
const cyclePerPage = ref(20);
const cycleFilter = reactive({ status: '' });
const cycleGenLoading = ref(false);
const cycleDetailVisible = ref(false);
const cycleDetail = ref(null);

// ─── Batches ───
const batches = ref([]);
const batchesLoading = ref(false);
const batchTotal = ref(0);
const batchPage = ref(1);
const batchPerPage = ref(20);
const batchFilter = reactive({ status: '', search: '' });
const batchDetailVisible = ref(false);
const batchDetail = ref(null);

// ─── Fees ───
const feeStats = ref(null);
const feeYearMonth = ref(new Date().toISOString().slice(0, 7));

// ─── Releasable ───
const releasableData = ref(null);

// ─── Create Batch Dialog ───
const createBatchVisible = ref(false);
const batchCreating = ref(false);
const batchFormRef = ref(null);
const batchForm = reactive({
    settlement_cycle_id: null,
    channel: 'balance',
    settlement_ids: [],
    notes: '',
});

const cycleStatusOptions = computed(() => [
    { label: t('settlement_page.all'), value: '' },
    { label: t('settlement_page.cycle_st_pending'), value: 'pending' },
    { label: t('settlement_page.cycle_st_processing'), value: 'processing' },
    { label: t('settlement_page.cycle_st_settled'), value: 'settled' },
    { label: t('settlement_page.cycle_st_paid'), value: 'paid' },
]);

const batchStatusOptions = computed(() => [
    { label: t('settlement_page.all'), value: '' },
    { label: t('settlement_page.batch_st_draft'), value: 'draft' },
    { label: t('settlement_page.batch_st_pending_approval'), value: 'pending_approval' },
    { label: t('settlement_page.batch_st_approved'), value: 'approved' },
    { label: t('settlement_page.batch_st_processing'), value: 'processing' },
    { label: t('settlement_page.batch_st_completed'), value: 'completed' },
    { label: t('settlement_page.batch_st_cancelled'), value: 'cancelled' },
]);

const channelOptions = computed(() => [
    { label: t('settlement_page.channel_balance'), value: 'balance' },
    { label: t('settlement_page.channel_bank'), value: 'bank' },
    { label: t('settlement_page.channel_alipay'), value: 'alipay' },
    { label: t('settlement_page.channel_wechat'), value: 'wechat' },
    { label: t('settlement_page.channel_paypal'), value: 'paypal' },
]);

const batchFormRules = computed(() => ({
    channel: [{ required: true, message: t('settlement_page.rule_channel_required'), trigger: 'change' }],
}));

function onSelectionChange(rows) {
    batchForm.settlement_ids = rows.map(r => r.id);
}

// ══════════════════════════════════════════
//  数据加载
// ══════════════════════════════════════════

async function loadDashboard() {
    try {
        const { data: res } = await settlementApi.dashboard();
        if (res.success) dashData.value = res.data;
    } catch {}
}

async function loadCycles() {
    cyclesLoading.value = true;
    try {
        const params = { page: cyclePage.value, per_page: cyclePerPage.value };
        if (cycleFilter.status) params.status = cycleFilter.status;
        const { data: res } = await settlementApi.cycles(params);
        cycles.value = res.data || [];
        cycleTotal.value = res.meta?.total || 0;
    } catch { cycles.value = []; }
    finally { cyclesLoading.value = false; }
}

async function loadBatches() {
    batchesLoading.value = true;
    try {
        const params = { page: batchPage.value, per_page: batchPerPage.value };
        if (batchFilter.status) params.status = batchFilter.status;
        if (batchFilter.search) params.search = batchFilter.search;
        const { data: res } = await settlementApi.batches(params);
        batches.value = res.data || [];
        batchTotal.value = res.meta?.total || 0;
    } catch { batches.value = []; }
    finally { batchesLoading.value = false; }
}

async function loadFeeStats() {
    try {
        const params = {};
        if (feeYearMonth.value) params.year_month = feeYearMonth.value;
        const { data: res } = await settlementApi.feeStats(params);
        if (res.success) feeStats.value = res.data;
    } catch { feeStats.value = null; }
}

async function scanReleasable() {
    scanLoading.value = true;
    try {
        const { data: res } = await settlementApi.scanReleasable();
        if (res.success) {
            releasableData.value = res.data;
            if (res.data.total_count > 0) {
                ElMessage.success(t('settlement_page.scan_found', {
                    count: res.data.total_count,
                    amount: formatMoney(res.data.total_amount),
                }));
            } else {
                ElMessage.info(t('settlement_page.scan_none'));
            }
        }
    } catch {} finally { scanLoading.value = false; }
}

function handleTabChange(tab) {
    if (tab === 'batches') loadBatches();
    if (tab === 'fees') loadFeeStats();
    if (tab === 'releasable') scanReleasable();
}

// ══════════════════════════════════════════
//  结算周期
// ══════════════════════════════════════════

async function handleGenerateCycle() {
    cycleGenLoading.value = true;
    try {
        const { data: res } = await settlementApi.cycleGenerate();
        if (res.success) {
            ElMessage.success(t('settlement_page.cycle_generated'));
            loadCycles();
        }
    } catch {} finally { cycleGenLoading.value = false; }
}

async function viewCycleDetail(row) {
    try {
        const { data: res } = await settlementApi.cycleShow(row.id);
        if (res.success) {
            cycleDetail.value = res.data;
            cycleDetailVisible.value = true;
        }
    } catch {}
}

// ══════════════════════════════════════════
//  结算批次
// ══════════════════════════════════════════

async function openCreateBatchDialog() {
    if (!releasableData.value?.items?.length) {
        await scanReleasable();
    }
    batchForm.settlement_cycle_id = null;
    batchForm.channel = 'balance';
    batchForm.settlement_ids = [];
    batchForm.notes = '';
    createBatchVisible.value = true;
}

async function submitBatch() {
    const valid = await batchFormRef.value.validate().catch(() => false);
    if (!valid) return;
    if (!batchForm.settlement_ids.length) {
        ElMessage.warning(t('settlement_page.select_at_least_one'));
        return;
    }
    batchCreating.value = true;
    try {
        const { data: res } = await settlementApi.batchCreate({
            settlement_cycle_id: batchForm.settlement_cycle_id,
            channel: batchForm.channel,
            settlement_ids: batchForm.settlement_ids,
            notes: batchForm.notes,
        });
        if (res.success) {
            ElMessage.success(t('settlement_page.batch_created'));
            createBatchVisible.value = false;
            loadBatches();
            loadDashboard();
        }
    } catch {} finally { batchCreating.value = false; }
}

async function viewBatchDetail(row) {
    try {
        const { data: res } = await settlementApi.batchShow(row.id);
        if (res.success) {
            batchDetail.value = res.data;
            batchDetailVisible.value = true;
        }
    } catch {}
}

async function handleSubmitBatch(row) {
    try {
        await ElMessageBox.confirm(
            t('settlement_page.confirm_submit_msg', { batch_no: row.batch_no }),
            t('settlement_page.confirm_submit_title'),
        );
        await settlementApi.batchSubmit(row.id);
        ElMessage.success(t('settlement_page.submitted'));
        loadBatches();
    } catch {}
}

async function handleApproveBatch(row) {
    try {
        await ElMessageBox.confirm(
            t('settlement_page.confirm_approve_msg', { batch_no: row.batch_no }),
            t('settlement_page.confirm_approve_title'),
        );
        await settlementApi.batchApprove(row.id);
        ElMessage.success(t('settlement_page.batch_approved'));
        loadBatches();
    } catch {}
}

async function handleCompleteBatch(row) {
    try {
        await ElMessageBox.confirm(
            t('settlement_page.confirm_complete_msg', { batch_no: row.batch_no }),
            t('settlement_page.confirm_complete_title'),
            { confirmButtonText: t('settlement_page.confirm_complete_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await settlementApi.batchComplete(row.id);
        ElMessage.success(t('settlement_page.batch_completed'));
        loadBatches();
        loadDashboard();
    } catch {}
}

async function handleCancelBatch(row) {
    try {
        await ElMessageBox.confirm(
            t('settlement_page.confirm_cancel_msg', { batch_no: row.batch_no }),
            t('settlement_page.confirm_cancel_title'),
            { type: 'warning' },
        );
        await settlementApi.batchCancel(row.id);
        ElMessage.success(t('settlement_page.batch_cancelled'));
        loadBatches();
    } catch {}
}

// ══════════════════════════════════════════
//  工具函数
// ══════════════════════════════════════════

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

function formatMoney(val) {
    if (val === null || val === undefined) return '¥0.00';
    return '¥' + Number(val).toLocaleString(dateLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(dateLocale.value, {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

function formatDateOnly(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString(dateLocale.value);
}

function periodTypeLabel(type) {
    const map = {
        weekly: t('settlement_page.period_weekly'),
        'bi-weekly': t('settlement_page.period_biweekly'),
        monthly: t('settlement_page.period_monthly'),
    };
    return map[type] || type;
}

function cycleStatusTag(status) {
    const map = { pending: 'info', processing: 'warning', settled: 'primary', paid: 'success', cancelled: 'danger' };
    return map[status] || '';
}

function cycleStatusLabel(status) {
    const map = {
        pending: t('settlement_page.cycle_st_pending'),
        processing: t('settlement_page.cycle_st_processing'),
        settled: t('settlement_page.cycle_st_settled'),
        paid: t('settlement_page.cycle_st_paid'),
        cancelled: t('settlement_page.cycle_st_cancelled'),
    };
    return map[status] || status;
}

function batchStatusTag(status) {
    const map = {
        draft: 'info', pending_approval: 'warning', approved: 'primary',
        processing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info',
    };
    return map[status] || '';
}

function batchStatusLabel(status) {
    const map = {
        draft: t('settlement_page.batch_st_draft'),
        pending_approval: t('settlement_page.batch_st_pending_approval'),
        approved: t('settlement_page.batch_st_approved'),
        processing: t('settlement_page.batch_st_processing'),
        completed: t('settlement_page.batch_st_completed'),
        failed: t('settlement_page.batch_st_failed'),
        cancelled: t('settlement_page.batch_st_cancelled'),
    };
    return map[status] || status;
}

function channelLabel(ch) {
    const map = {
        bank: t('settlement_page.channel_bank'),
        alipay: t('settlement_page.channel_alipay'),
        wechat: t('settlement_page.channel_wechat'),
        paypal: t('settlement_page.channel_paypal'),
        balance: t('settlement_page.channel_balance'),
    };
    return map[ch] || ch;
}

function feeTypeLabel(type) {
    const map = {
        gateway: t('settlement_page.fee_gateway'),
        platform: t('settlement_page.fee_platform'),
        commission: t('settlement_page.fee_commission'),
        withdrawal: t('settlement_page.fee_withdrawal'),
        refund: t('settlement_page.fee_refund'),
    };
    return map[type] || type;
}

onMounted(() => {
    loadDashboard();
    loadCycles();
});
</script>

<style scoped>
.settlement-page { padding: 20px; }
.page-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 24px; font-weight: 600; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.text-muted { color: var(--el-text-color-placeholder); }
.pagination-wrapper { display: flex; justify-content: flex-end; margin-top: 12px; }
:deep(.el-card__body) { padding: 16px; }
</style>
