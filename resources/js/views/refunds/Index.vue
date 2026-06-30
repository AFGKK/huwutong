<template>
    <div class="refunds-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total_refunds }}</div>
                        <div class="stat-label">总退款笔数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ formatAmount(stats.total_amount) }}</div>
                        <div class="stat-label">累计退款金额</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ formatAmount(stats.month_amount) }}</div>
                        <div class="stat-label">本月退款</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ formatAmount(stats.today_amount) }}</div>
                        <div class="stat-label">今日退款</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ riskStats.pending_review ?? 0 }}</div>
                        <div class="stat-label warn">待审核退款</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ riskStats.total_assessments ?? 0 }}</div>
                        <div class="stat-label">风控评估次数</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 选项卡 -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- 退款列表 -->
            <el-tab-pane label="退款记录" name="refunds">
                <div class="toolbar">
                    <el-form :inline="true" :model="filters" size="small">
                        <el-form-item>
                            <el-input v-model="filters.search" placeholder="搜索退款单号/License/客户" clearable style="width:260px"
                                @clear="fetchData" @keyup.enter="fetchData">
                                <template #prefix><el-icon><Search /></el-icon></template>
                            </el-input>
                        </el-form-item>
                        <el-form-item>
                            <el-select v-model="filters['filter.status']" placeholder="状态" clearable @change="fetchData" style="width:120px">
                                <el-option label="已完成" value="completed" />
                                <el-option label="处理中" value="pending" />
                                <el-option label="失败" value="failed" />
                                <el-option label="已取消" value="cancelled" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchData"><el-icon><Search /></el-icon> 查询</el-button>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="success" @click="showCreateDialog = true"><el-icon><Plus /></el-icon> 发起退款</el-button>
                        </el-form-item>
                    </el-form>
                </div>

                <el-table :data="refunds" v-loading="loading" stripe>
                    <el-table-column prop="refund_no" label="退款单号" width="170">
                        <template #default="{ row }"><code class="refund-no">{{ row.refund_no }}</code></template>
                    </el-table-column>
                    <el-table-column label="License" min-width="140">
                        <template #default="{ row }">{{ row.license?.license_key || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="客户" min-width="130">
                        <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="金额" width="110">
                        <template #default="{ row }">
                            <span class="amount-text">{{ row.currency }} {{ formatAmount(row.amount) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="类型" width="70">
                        <template #default="{ row }">
                            <el-tag v-if="row.refund_type === 'partial'" type="warning" size="small">部分</el-tag>
                            <el-tag v-else type="danger" size="small">全额</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="原因" min-width="160" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.reason || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="风控决策" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.auto_decision" :type="decisionType(row.auto_decision)" size="small">
                                {{ decisionLabel(row.auto_decision) }}
                            </el-tag>
                            <span v-else class="no-data">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="时间" width="160" />
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openDetail(row)">详情</el-button>
                            <el-button v-if="row.status === 'pending'" text size="small" type="warning" @click="handleReview(row)">审核</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap">
                    <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total"
                        :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" @change="fetchData" />
                </div>
            </el-tab-pane>

            <!-- 风控看板 -->
            <el-tab-pane label="风控看板" name="risk-dashboard">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header><span>评估按风险等级</span></template>
                            <div v-if="riskLevelChart.length" class="risk-chart">
                                <div v-for="item in riskLevelChart" :key="item.name" class="risk-bar-item">
                                    <span class="risk-bar-label">{{ item.label }}</span>
                                    <el-progress :percentage="item.percent" :color="item.color" :stroke-width="18" />
                                </div>
                            </div>
                            <div v-else class="empty-chart">暂无数据</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header><span>评估按决策类型</span></template>
                            <div v-if="riskDecisionChart.length" class="risk-chart">
                                <div v-for="item in riskDecisionChart" :key="item.name" class="risk-bar-item">
                                    <span class="risk-bar-label">{{ item.label }}</span>
                                    <el-progress :percentage="item.percent" :stroke-width="18" />
                                </div>
                            </div>
                            <div v-else class="empty-chart">暂无数据</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header><span>风控规则</span></template>
                            <div v-loading="loadingRules">
                                <div v-for="rule in riskRules" :key="rule.id" class="rule-item">
                                    <div class="rule-header">
                                        <el-switch :model-value="rule.is_active" size="small" @change="v => handleToggleRule(rule, v)" />
                                        <span class="rule-name">{{ rule.name }}</span>
                                        <el-tag :type="ruleTypeTag(rule.rule_type)" size="small">{{ rule.rule_type }}</el-tag>
                                    </div>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetail" title="退款详情" width="600px">
            <el-descriptions v-if="detail" :column="2" border>
                <el-descriptions-item label="退款单号">{{ detail.refund_no }}</el-descriptions-item>
                <el-descriptions-item label="状态"><el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                <el-descriptions-item label="License Key">{{ detail.license?.license_key || '-' }}</el-descriptions-item>
                <el-descriptions-item label="退款类型">{{ detail.refund_type === 'partial' ? '部分退款' : '全额退款' }}</el-descriptions-item>
                <el-descriptions-item label="客户">{{ detail.customer?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="邮箱">{{ detail.customer?.email || '-' }}</el-descriptions-item>
                <el-descriptions-item label="退款金额">{{ detail.currency }} {{ formatAmount(detail.amount) }}</el-descriptions-item>
                <el-descriptions-item label="退款方式">{{ paymentMethodLabel(detail.payment_method) }}</el-descriptions-item>
                <el-descriptions-item label="风控决策" :span="2">
                    <el-tag v-if="detail.auto_decision" :type="decisionType(detail.auto_decision)" size="small">{{ decisionLabel(detail.auto_decision) }}</el-tag>
                    <span v-else class="no-data">未评估</span>
                </el-descriptions-item>
                <el-descriptions-item label="处理人">{{ detail.processor?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="处理时间">{{ detail.completed_at || '-' }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
                <el-descriptions-item label="支付网关 ID">{{ detail.payment_refund_id || '-' }}</el-descriptions-item>
                <el-descriptions-item label="失败原因" v-if="detail.failure_reason" :span="2">{{ detail.failure_reason }}</el-descriptions-item>
                <el-descriptions-item label="退款原因" :span="2">{{ detail.reason || '-' }}</el-descriptions-item>
            </el-descriptions>
            <template #footer>
                <el-button @click="showDetail = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 创建退款对话框 -->
        <el-dialog v-model="showCreateDialog" title="发起退款（含风控评估）" width="500px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px">
                <el-form-item label="License" prop="license_id">
                    <el-select v-model="createForm.license_id" filterable remote :remote-method="searchLicenses"
                        :loading="searchingLicense" style="width:100%">
                        <el-option v-for="l in licenseOptions" :key="l.id" :label="`#${l.id} ${l.license_key}`" :value="l.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="金额" prop="amount">
                    <el-input-number v-model="createForm.amount" :min="0.01" :step="10" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item label="退款类型" prop="refund_type">
                    <el-select v-model="createForm.refund_type" style="width:200px">
                        <el-option label="全额退款" value="full" />
                        <el-option label="部分退款" value="partial" />
                    </el-select>
                </el-form-item>
                <el-form-item label="退款方式" prop="payment_method">
                    <el-select v-model="createForm.payment_method" style="width:200px">
                        <el-option label="原路退回" value="original" />
                        <el-option label="余额退回" value="balance" />
                        <el-option label="其他" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="原因" prop="reason">
                    <el-input v-model="createForm.reason" type="textarea" :rows="3" maxlength="500" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" :loading="creating" @click="handleCreateWithRisk">提交风控评估</el-button>
            </template>
        </el-dialog>

        <!-- 审核对话框 -->
        <el-dialog v-model="showReviewDialog" title="审核退款" width="450px">
            <el-alert v-if="reviewRefund" :title="`退款单: ${reviewRefund.refund_no}，金额: ${reviewRefund.currency} ${formatAmount(reviewRefund.amount)}`"
                type="info" show-icon style="margin-bottom:16px" />
            <el-radio-group v-model="reviewAction" style="margin-bottom:16px">
                <el-radio-button value="approve">批准</el-radio-button>
                <el-radio-button value="reject">拒绝</el-radio-button>
            </el-radio-group>
            <el-input v-model="reviewNote" type="textarea" :rows="3" placeholder="审核备注（可选）" />
            <template #footer>
                <el-button @click="showReviewDialog = false">取消</el-button>
                <el-button :type="reviewAction === 'approve' ? 'primary' : 'danger'" :loading="reviewing" @click="handleDoReview">
                    {{ reviewAction === 'approve' ? '批准退款' : '拒绝退款' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus } from '@element-plus/icons-vue';
import refundApi from '@/api/refund';

// ─── 选项卡 ───
const activeTab = ref('refunds');

// ─── 统计 ───
const stats = reactive({
    total_refunds: 0, total_amount: 0, completed_count: 0,
    pending_count: 0, failed_count: 0, today_amount: 0, month_amount: 0,
});

// ─── 列表 ───
const refunds = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const filters = reactive({ search: '', 'filter.status': '' });

// ─── 详情 ───
const showDetail = ref(false);
const detail = ref(null);

// ─── 创建退款 ───
const showCreateDialog = ref(false);
const createForm = ref({ license_id: null, amount: 100, refund_type: 'full', payment_method: 'original', reason: '' });
const createRules = {
    license_id: [{ required: true, message: '请选择 License', trigger: 'change' }],
    amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
};
const creating = ref(false);
const licenseOptions = ref([]);
const searchingLicense = ref(false);

function searchLicenses(query) {
    if (!query) return;
    searchingLicense.value = true;
    import('@/api/license').then(m => m.searchLicenses?.(query) ?? m.default.searchLicenses?.(query))
        .then(res => { licenseOptions.value = res.data ?? [] })
        .catch(() => {})
        .finally(() => { searchingLicense.value = false });
}

async function handleCreateWithRisk() {
    creating.value = true;
    try {
        const res = await refundApi.storeWithRisk(createForm.value);
        const data = res.data?.data || res;
        ElMessage.success('退款已提交风控评估');
        showCreateDialog.value = false;
        createForm.value = { license_id: null, amount: 100, refund_type: 'full', payment_method: 'original', reason: '' };
        await fetchData();
        // 如果决策结果需要审核，提示
        if (data.decision_result?.action === 'require_review') {
            ElMessage.info('退款需人工审核，请前往风控看板处理');
        }
    } catch (e) {
        ElMessage.error('提交失败');
    } finally {
        creating.value = false;
    }
}

// ─── 审核 ───
const showReviewDialog = ref(false);
const reviewRefund = ref(null);
const reviewAction = ref('approve');
const reviewNote = ref('');
const reviewing = ref(false);

function handleReview(row) {
    reviewRefund.value = row;
    reviewAction.value = 'approve';
    reviewNote.value = '';
    showReviewDialog.value = true;
}

async function handleDoReview() {
    reviewing.value = true;
    try {
        await refundApi.reviewRefund(reviewRefund.value.id, reviewAction.value, reviewNote.value);
        ElMessage.success(reviewAction.value === 'approve' ? '退款已批准' : '退款已拒绝');
        showReviewDialog.value = false;
        await fetchData();
    } catch (e) {
        ElMessage.error('操作失败');
    } finally {
        reviewing.value = false;
    }
}

// ─── 风控看板 ───
const riskStats = ref({});
const riskRules = ref([]);
const loadingRules = ref(false);

const riskLevelChart = computed(() => {
    const byLevel = riskStats.value.by_risk_level || {};
    const totalRisk = Object.values(byLevel).reduce((a, b) => a + b, 0);
    if (!totalRisk) return [];
    const labels = { low: '低风险', medium: '中风险', high: '高风险', critical: '极高风险' };
    const colors = { low: '#67c23a', medium: '#e6a23c', high: '#f56c6c', critical: '#f56c6c' };
    return Object.entries(byLevel).map(([k, v]) => ({
        name: k, label: labels[k] || k, value: v, percent: Math.round(v / totalRisk * 100), color: colors[k] || '#909399',
    }));
});

const riskDecisionChart = computed(() => {
    const byDecision = riskStats.value.by_decision || {};
    const totalDec = Object.values(byDecision).reduce((a, b) => a + b, 0);
    if (!totalDec) return [];
    const labels = { auto_approve: '自动批准', auto_reject: '自动拒绝', require_review: '需审核', partial_refund: '部分退款' };
    return Object.entries(byDecision).map(([k, v]) => ({
        name: k, label: labels[k] || k, value: v, percent: Math.round(v / totalDec * 100),
    }));
});

function ruleTypeTag(type) {
    return { time_window: 'primary', amount_threshold: 'danger', frequency: 'warning', customer_tier: 'info', license_age: 'success' }[type] || 'info';
}

function handleToggleRule(rule, val) {
    refundApi.updateRiskRule(rule.id, { is_active: val }).then(() => {
        ElMessage.success(val ? '规则已启用' : '规则已禁用');
    }).catch(() => {});
}

async function fetchRiskStats() {
    try {
        const res = await refundApi.riskStats();
        riskStats.value = res.data?.data || {};
    } catch { /* ignore */ }
}

async function fetchRiskRules() {
    loadingRules.value = true;
    try {
        const res = await refundApi.riskRules();
        riskRules.value = res.data?.data || res.data || [];
    } catch { /* ignore */ }
    finally { loadingRules.value = false; }
}

// ─── 工具 ───
function formatAmount(val) {
    if (val === null || val === undefined) return '0.00';
    return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const statusType = (s) => ({ completed: 'success', pending: 'warning', failed: 'danger', cancelled: 'info' }[s] || 'info');
const statusLabel = (s) => ({ completed: '已完成', pending: '处理中', failed: '失败', cancelled: '已取消' }[s] || s);
const paymentMethodLabel = (m) => ({ original: '原路退回', balance: '余额退回', other: '其他' }[m] || m || '-');

const decisionType = (d) => ({ auto_approve: 'success', auto_reject: 'danger', require_review: 'warning', partial_refund: 'info' }[d] || 'info');
const decisionLabel = (d) => ({ auto_approve: '自动批准', auto_reject: '自动拒绝', require_review: '需人工审核', partial_refund: '部分退款' }[d] || d);

// ─── 数据 ───
async function fetchData() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value, ...filters };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        const [listRes, statsRes] = await Promise.all([refundApi.list(params), refundApi.stats()]);
        refunds.value = listRes.data?.data || [];
        total.value = listRes.data?.meta?.total || 0;
        Object.assign(stats, statsRes.data?.data || {});
    } catch {
        ElMessage.error('获取退款列表失败');
    } finally {
        loading.value = false;
    }
}

function openDetail(row) {
    detail.value = row;
    showDetail.value = true;
}

onMounted(() => {
    fetchData();
    fetchRiskStats();
    fetchRiskRules();
});
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.stat-label.warn { color: var(--el-color-danger); }
.toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.refund-no { font-size: 12px; letter-spacing: 0.5px; }
.amount-text { font-weight: 600; color: var(--el-color-danger); }
.no-data { color: var(--el-text-color-placeholder); }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.risk-chart { padding: 8px 0; }
.risk-bar-item { margin-bottom: 12px; }
.risk-bar-label { display: inline-block; width: 80px; font-size: 13px; }
.empty-chart { text-align: center; padding: 40px 0; color: var(--el-text-color-placeholder); }
.rule-item { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--el-border-color-light); }
.rule-header { display: flex; align-items: center; gap: 8px; }
.rule-name { flex: 1; font-size: 13px; }
</style>
