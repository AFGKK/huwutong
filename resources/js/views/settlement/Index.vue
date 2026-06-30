<template>
    <div class="settlement-page">
        <div class="page-header">
            <div class="header-left">
                <h2>财务结算系统</h2>
                <span class="header-subtitle">佣金结算、批次管理、平台费用统一管理</span>
            </div>
            <div class="header-right">
                <el-button @click="scanReleasable" :loading="scanLoading">
                    <el-icon><Search /></el-icon> 扫描可结算佣金
                </el-button>
                <el-button type="primary" @click="openCreateBatchDialog">
                    <el-icon><Plus /></el-icon> 新建结算批次
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.pending_settlements ?? '-' }}</div>
                    <div class="stat-label">待结算笔数</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.releasable_count ?? '-' }}</div>
                    <div class="stat-label">可释放笔数</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.pending_payouts) }}</div>
                    <div class="stat-label">待打款总额</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ dashData.active_agents ?? '-' }}</div>
                    <div class="stat-label">活跃代理/推客</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.monthly_settled) }}</div>
                    <div class="stat-label">本月已结算</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ formatMoney(dashData.monthly_fees) }}</div>
                    <div class="stat-label">本月平台费用</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- ═══ 结算周期 ═══ -->
                <el-tab-pane label="结算周期" name="cycles">
                    <template #label>
                        <span><el-icon><Calendar /></el-icon> 结算周期</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="handleGenerateCycle" :loading="cycleGenLoading">
                            <el-icon><Plus /></el-icon> 生成月度周期
                        </el-button>
                        <el-select v-model="cycleFilter.status" clearable placeholder="状态" style="width: 130px">
                            <el-option label="全部" value="" />
                            <el-option label="待处理" value="pending" />
                            <el-option label="处理中" value="processing" />
                            <el-option label="已结算" value="settled" />
                            <el-option label="已打款" value="paid" />
                        </el-select>
                    </div>
                    <el-table :data="cycles" v-loading="cyclesLoading" stripe>
                        <el-table-column label="周期名称" prop="name" min-width="180" />
                        <el-table-column label="类型" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ periodTypeLabel(row.period_type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="结算区间" min-width="200">
                            <template #default="{ row }">
                                {{ row.period_start }} ~ {{ row.period_end }}
                            </template>
                        </el-table-column>
                        <el-table-column label="佣金总额" width="120">
                            <template #default="{ row }">
                                {{ formatMoney(row.total_commission) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="打款日期" width="110" prop="payout_date">
                            <template #default="{ row }">{{ row.payout_date || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="cycleStatusTag(row.status)" size="small">
                                    {{ cycleStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="viewCycleDetail(row)">
                                    详情
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
                <el-tab-pane label="结算批次" name="batches">
                    <template #label>
                        <span><el-icon><List /></el-icon> 结算批次</span>
                    </template>
                    <div class="toolbar">
                        <el-select v-model="batchFilter.status" clearable placeholder="状态" style="width: 130px">
                            <el-option label="全部" value="" />
                            <el-option label="草稿" value="draft" />
                            <el-option label="待审核" value="pending_approval" />
                            <el-option label="已通过" value="approved" />
                            <el-option label="处理中" value="processing" />
                            <el-option label="已完成" value="completed" />
                            <el-option label="已取消" value="cancelled" />
                        </el-select>
                        <el-input v-model="batchFilter.search" placeholder="搜索批次号" clearable style="width: 200px" @keyup.enter="loadBatches" />
                        <el-button type="primary" @click="loadBatches"><el-icon><Search /></el-icon> 查询</el-button>
                    </div>
                    <el-table :data="batches" v-loading="batchesLoading" stripe>
                        <el-table-column label="批次号" prop="batch_no" width="180" />
                        <el-table-column label="周期" min-width="150">
                            <template #default="{ row }">{{ row.settlement_cycle?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="渠道" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ channelLabel(row.channel) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="总金额" width="120">
                            <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="手续费" width="100">
                            <template #default="{ row }">{{ formatMoney(row.total_fee) }}</template>
                        </el-table-column>
                        <el-table-column label="净额" width="120">
                            <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="笔数" width="60" prop="item_count" />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="batchStatusTag(row.status)" size="small">
                                    {{ batchStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="240" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="viewBatchDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'draft'" text size="small" type="warning" @click="handleSubmitBatch(row)">提交</el-button>
                                <el-button v-if="row.status === 'pending_approval'" text size="small" type="success" @click="handleApproveBatch(row)">通过</el-button>
                                <el-button v-if="row.status === 'approved'" text size="small" type="primary" @click="handleCompleteBatch(row)">完成</el-button>
                                <el-button v-if="['draft', 'pending_approval'].includes(row.status)" text size="small" type="danger" @click="handleCancelBatch(row)">取消</el-button>
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
                <el-tab-pane label="平台费用" name="fees">
                    <template #label>
                        <span><el-icon><Money /></el-icon> 平台费用</span>
                    </template>
                    <div class="toolbar">
                        <el-date-picker
                            v-model="feeYearMonth"
                            type="month"
                            placeholder="选择月份"
                            value-format="YYYY-MM"
                            style="width: 160px"
                            @change="loadFeeStats"
                        />
                    </div>
                    <el-row :gutter="16" v-if="feeStats">
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ formatMoney(feeStats.total_fees) }}</div>
                                <div class="stat-label">费用总额</div>
                            </el-card>
                        </el-col>
                        <el-col v-for="(total, type) in feeStats.by_type" :key="type" :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ formatMoney(total) }}</div>
                                <div class="stat-label">{{ feeTypeLabel(type) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <div v-else class="text-muted" style="padding: 40px; text-align: center;">暂无费用数据</div>
                </el-tab-pane>

                <!-- ═══ 可结算扫描 ═══ -->
                <el-tab-pane label="可结算佣金" name="releasable">
                    <template #label>
                        <span><el-icon><Search /></el-icon> 可结算佣金</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="scanReleasable" :loading="scanLoading">
                            <el-icon><Refresh /></el-icon> 重新扫描
                        </el-button>
                        <span class="text-muted" v-if="releasableData">
                            共 {{ releasableData.total_count }} 笔，可结算金额 {{ formatMoney(releasableData.total_amount) }}
                        </span>
                    </div>
                    <el-table :data="releasableData?.items || []" v-loading="scanLoading" stripe>
                        <el-table-column label="代理" min-width="180">
                            <template #default="{ row }">{{ row.agent?.user?.name || 'ID:' + row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column label="佣金金额" width="120">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="释放日期" width="110" prop="released_at">
                            <template #default="{ row }">{{ row.released_at ? new Date(row.released_at).toLocaleDateString('zh-CN') : '-' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" type="success">可结算</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 创建批次 Dialog -->
        <el-dialog v-model="createBatchVisible" title="新建结算批次" width="600px">
            <el-form ref="batchFormRef" :model="batchForm" :rules="batchFormRules" label-width="120px">
                <el-form-item label="关联周期">
                    <el-select v-model="batchForm.settlement_cycle_id" clearable placeholder="选择结算周期（可选）" style="width: 100%">
                        <el-option v-for="c in cycles" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="打款渠道" prop="channel">
                    <el-select v-model="batchForm.channel" style="width: 100%">
                        <el-option label="余额" value="balance" />
                        <el-option label="银行转账" value="bank" />
                        <el-option label="支付宝" value="alipay" />
                        <el-option label="微信支付" value="wechat" />
                        <el-option label="PayPal" value="paypal" />
                    </el-select>
                </el-form-item>
                <el-form-item label="选择结算记录" prop="settlement_ids">
                    <el-table :data="releasableData?.items || []" max-height="300" @selection-change="onSelectionChange">
                        <el-table-column type="selection" width="40" />
                        <el-table-column label="代理" min-width="140">
                            <template #default="{ row }">{{ row.agent?.user?.name || 'ID:' + row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column label="金额" width="100">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="释放日" width="90" prop="released_at" />
                    </el-table>
                    <div class="text-muted" v-if="!releasableData?.items?.length">请先点击"扫描可结算佣金"获取数据</div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="batchForm.notes" type="textarea" :rows="2" placeholder="可选备注" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createBatchVisible = false">取消</el-button>
                <el-button type="primary" :loading="batchCreating" @click="submitBatch">创建批次</el-button>
            </template>
        </el-dialog>

        <!-- 批次详情 Dialog -->
        <el-dialog v-model="batchDetailVisible" title="批次详情" width="700px">
            <div v-if="batchDetail">
                <el-descriptions :column="2" size="small" border>
                    <el-descriptions-item label="批次号">{{ batchDetail.batch_no }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="batchStatusTag(batchDetail.status)" size="small">{{ batchStatusLabel(batchDetail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="渠道">{{ channelLabel(batchDetail.channel) }}</el-descriptions-item>
                    <el-descriptions-item label="总金额">{{ formatMoney(batchDetail.total_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="手续费">{{ formatMoney(batchDetail.total_fee) }}</el-descriptions-item>
                    <el-descriptions-item label="净额">{{ formatMoney(batchDetail.net_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="笔数">{{ batchDetail.item_count }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatDate(batchDetail.created_at) }}</el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 style="margin: 0 0 12px;">明细列表</h4>
                <el-table :data="batchDetail.items || []" size="small" stripe>
                    <el-table-column label="类型" width="120">
                        <template #default="{ row }">{{ row.settleable_type?.includes('CommissionSettlement') ? '佣金结算' : '提现' }}</template>
                    </el-table-column>
                    <el-table-column label="金额" width="100" prop="amount">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column label="手续费" width="80" prop="fee">
                        <template #default="{ row }">{{ formatMoney(row.fee) }}</template>
                    </el-table-column>
                    <el-table-column label="净额" width="100" prop="net_amount">
                        <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag size="small" type="success">{{ row.status === 'paid' ? '已支付' : '待处理' }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <template #footer>
                <el-button @click="batchDetailVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 周期详情 Dialog -->
        <el-dialog v-model="cycleDetailVisible" title="结算周期详情" width="700px">
            <div v-if="cycleDetail">
                <el-descriptions :column="2" size="small" border>
                    <el-descriptions-item label="周期名称">{{ cycleDetail.name }}</el-descriptions-item>
                    <el-descriptions-item label="类型">{{ periodTypeLabel(cycleDetail.period_type) }}</el-descriptions-item>
                    <el-descriptions-item label="结算区间">
                        {{ cycleDetail.period_start }} ~ {{ cycleDetail.period_end }}
                    </el-descriptions-item>
                    <el-descriptions-item label="结算日">{{ cycleDetail.settlement_date }}</el-descriptions-item>
                    <el-descriptions-item label="打款日">{{ cycleDetail.payout_date || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="cycleStatusTag(cycleDetail.status)" size="small">{{ cycleStatusLabel(cycleDetail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="佣金总额">{{ formatMoney(cycleDetail.total_commission) }}</el-descriptions-item>
                    <el-descriptions-item label="代理数">{{ cycleDetail.agent_count }}</el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 style="margin: 0 0 12px;">关联批次</h4>
                <el-table :data="cycleDetail.batches || []" size="small" stripe>
                    <el-table-column label="批次号" prop="batch_no" />
                    <el-table-column label="金额" width="120">
                        <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="batchStatusTag(row.status)" size="small">{{ batchStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <template #footer>
                <el-button @click="cycleDetailVisible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, Calendar, List, Money, Refresh } from '@element-plus/icons-vue';
import settlementApi from '@/api/settlement';

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
const batchFormRules = {
    channel: [{ required: true, message: '请选择打款渠道', trigger: 'change' }],
};
const selectedItems = ref([]);

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
                ElMessage.success(`发现 ${res.data.total_count} 笔可结算佣金，共 ${formatMoney(res.data.total_amount)}`);
            } else {
                ElMessage.info('暂无可结算佣金');
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
            ElMessage.success('结算周期已生成');
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
        ElMessage.warning('请至少选择一条结算记录');
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
            ElMessage.success('结算批次创建成功');
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
        await ElMessageBox.confirm(`确认提交批次 "${row.batch_no}" 进行审核？`, '确认提交');
        await settlementApi.batchSubmit(row.id);
        ElMessage.success('已提交审核');
        loadBatches();
    } catch {}
}

async function handleApproveBatch(row) {
    try {
        await ElMessageBox.confirm(`确认通过批次 "${row.batch_no}"？`, '确认通过');
        await settlementApi.batchApprove(row.id);
        ElMessage.success('批次已通过');
        loadBatches();
    } catch {}
}

async function handleCompleteBatch(row) {
    try {
        await ElMessageBox.confirm(
            `确认完成批次 "${row.batch_no}"？\n这将释放佣金到代理收益账户余额。`,
            '确认完成',
            { confirmButtonText: '确认完成', cancelButtonText: '取消', type: 'warning' }
        );
        await settlementApi.batchComplete(row.id);
        ElMessage.success('批次已完成，佣金已释放到收益账户');
        loadBatches();
        loadDashboard();
    } catch {}
}

async function handleCancelBatch(row) {
    try {
        await ElMessageBox.confirm(`确认取消批次 "${row.batch_no}"？`, '确认取消', { type: 'warning' });
        await settlementApi.batchCancel(row.id);
        ElMessage.success('批次已取消');
        loadBatches();
    } catch {}
}

// ══════════════════════════════════════════
//  工具函数
// ══════════════════════════════════════════

function formatMoney(val) {
    if (val === null || val === undefined) return '¥0.00';
    return '¥' + Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

function periodTypeLabel(type) {
    const map = { weekly: '周结', 'bi-weekly': '双周结', monthly: '月结' };
    return map[type] || type;
}

function cycleStatusTag(status) {
    const map = { pending: 'info', processing: 'warning', settled: 'primary', paid: 'success', cancelled: 'danger' };
    return map[status] || '';
}

function cycleStatusLabel(status) {
    const map = { pending: '待处理', processing: '处理中', settled: '已结算', paid: '已打款', cancelled: '已取消' };
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
        draft: '草稿', pending_approval: '待审核', approved: '已通过',
        processing: '处理中', completed: '已完成', failed: '失败', cancelled: '已取消',
    };
    return map[status] || status;
}

function channelLabel(ch) {
    const map = { bank: '银行转账', alipay: '支付宝', wechat: '微信支付', paypal: 'PayPal', balance: '余额' };
    return map[ch] || ch;
}

function feeTypeLabel(type) {
    const map = { gateway: '网关费', platform: '平台费', commission: '佣金费', withdrawal: '提现费', refund: '退费' };
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
