<template>
    <div class="token-meter-page">
        <div class="page-header">
            <h2>AI Token 用量计费追踪 <small class="text-muted">M2-77</small></h2>
            <div class="header-actions">
                <el-button @click="loadData">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <!-- ═══════════ 概览 ═══════════ -->
            <el-tab-pane label="概览" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">${{ formatNum(dash.totalMonthlyCost) }}</div><div class="stat-label">本月总费用</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ formatNum(dash.totalMonthlyTokens) }}</div><div class="stat-label">本月 Token 数</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ dash.totalRequests || 0 }}</div><div class="stat-label">请求次数</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-success">{{ dash.activeTenants || 0 }}</div><div class="stat-label">活跃租户</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按模型统计</span></template>
                            <el-table :data="dash.byModel || []" size="small" stripe>
                                <el-table-column prop="model" label="模型" />
                                <el-table-column prop="tokens" label="Token" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" label="费用" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按功能统计</span></template>
                            <el-table :data="dash.byFeature || []" size="small" stripe>
                                <el-table-column prop="feature" label="功能" />
                                <el-table-column prop="tokens" label="Token" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" label="费用" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按提供商统计</span></template>
                            <el-table :data="dash.byProvider || []" size="small" stripe>
                                <el-table-column prop="provider" label="提供商" />
                                <el-table-column prop="tokens" label="Token" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" label="费用" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ═══════════ 消耗记录 ═══════════ -->
            <el-tab-pane label="消耗记录" name="records">
                <el-card shadow="never">
                    <div class="toolbar">
                        <el-select v-model="filters.model" placeholder="模型" clearable style="width:140px" @change="loadRecords">
                            <el-option v-for="(p, m) in models" :key="m" :label="m" :value="m" />
                        </el-select>
                        <el-select v-model="filters.feature" placeholder="功能" clearable style="width:140px" @change="loadRecords">
                            <el-option v-for="(label, key) in features" :key="key" :label="label" :value="key" />
                        </el-select>
                        <el-date-picker v-model="dateRange" type="daterange" range-separator="至" value-format="YYYY-MM-DD" @change="onDateChange" />
                        <el-button type="primary" @click="showCreateRecord = true"><el-icon><Plus /></el-icon> 手动录入</el-button>
                    </div>
                    <el-table :data="records" v-loading="loading" stripe border style="width:100%">
                        <el-table-column prop="created_at" label="时间" width="160">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column prop="model" label="模型" width="130" />
                        <el-table-column prop="provider" label="提供商" width="90" />
                        <el-table-column prop="feature" label="功能" width="100" />
                        <el-table-column prop="input_tokens" label="输入" width="80" align="right" />
                        <el-table-column prop="output_tokens" label="输出" width="80" align="right" />
                        <el-table-column prop="total_tokens" label="总计" width="80" align="right" />
                        <el-table-column prop="cost" label="费用" width="80" align="right">
                            <template #default="{ row }">${{ row.cost }}</template>
                        </el-table-column>
                        <el-table-column prop="tenant_id" label="租户" width="60" />
                        <el-table-column prop="cached" label="缓存" width="60">
                            <template #default="{ row }"><el-tag :type="row.cached ? 'success' : 'info'" size="small">{{ row.cached ? '是' : '否' }}</el-tag></template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total" layout="total, sizes, prev, pager, next" @change="loadRecords" />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ═══════════ 预算管理 ═══════════ -->
            <el-tab-pane label="预算管理" name="budgets">
                <div class="toolbar mb-4">
                    <el-button type="primary" @click="showBudgetDialog = true"><el-icon><Plus /></el-icon> 新建预算</el-button>
                    <el-button @click="handleCheckAlerts"><el-icon><Refresh /></el-icon> 检查告警</el-button>
                </div>
                <el-table :data="budgets" stripe border>
                    <el-table-column prop="tenant_id" label="租户" width="80">
                        <template #default="{ row }">{{ row.tenant?.name || '全局' }}</template>
                    </el-table-column>
                    <el-table-column prop="period" label="周期" width="100" />
                    <el-table-column prop="budget_limit" label="预算限额" width="120" align="right">
                        <template #default="{ row }">${{ row.budget_limit }}</template>
                    </el-table-column>
                    <el-table-column label="告警阈值" width="200">
                        <template #default="{ row }">{{ row.alert_threshold_1 }}% / {{ row.alert_threshold_2 }}% / {{ row.alert_threshold_3 }}%</template>
                    </el-table-column>
                    <el-table-column prop="hard_cap" label="硬上限" width="80">
                        <template #default="{ row }"><el-tag :type="row.hard_cap ? 'danger' : 'info'" size="small">{{ row.hard_cap ? '是' : '否' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="is_active" label="状态" width="70">
                        <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '禁用' }}</el-tag></template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══════════ 告警 ═══════════ -->
            <el-tab-pane label="告警" name="alerts">
                <el-table :data="alerts" stripe border>
                    <el-table-column prop="created_at" label="时间" width="160">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column prop="type" label="类型" width="150">
                        <template #default="{ row }">
                            <el-tag :type="row.type === 'hard_cap_reached' ? 'danger' : 'warning'" size="small">{{ row.type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="threshold_pct" label="阈值" width="80" align="right">{{ row => row.threshold_pct }}%</el-table-column>
                    <el-table-column prop="current_spend" label="当前花费" width="120" align="right">
                        <template #default="{ row }">${{ row.current_spend }}</template>
                    </el-table-column>
                    <el-table-column prop="budget_limit" label="预算限额" width="120" align="right">
                        <template #default="{ row }">${{ row.budget_limit }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.resolved_at ? 'success' : 'danger'" size="small">{{ row.resolved_at ? '已解决' : '未解决' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="100">
                        <template #default="{ row }">
                            <el-button v-if="!row.resolved_at" size="small" type="primary" @click="handleResolveAlert(row.id)">解决</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══════════ 成本分摊 ═══════════ -->
            <el-tab-pane label="成本分摊" name="allocation">
                <div class="toolbar mb-4">
                    <el-date-picker v-model="allocationMonth" type="month" placeholder="选择月份" value-format="YYYY-MM" @change="loadAllocation" />
                    <el-button @click="loadAllocation"><el-icon><Refresh /></el-icon> 刷新</el-button>
                    <el-button type="primary" @click="handleExportAllocation">导出 CSV</el-button>
                </div>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">${{ formatNum(allocation.totalCost) }}</div><div class="stat-label">分摊总额</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ formatNum(allocation.totalTokens) }}</div><div class="stat-label">总 Token</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ allocation.totalRequests || 0 }}</div><div class="stat-label">总请求</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-warning">{{ summary.top3TenantPct || 0 }}%</div><div class="stat-label">Top 3 租户占比</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按租户分摊 <small>($)</small></span></template>
                            <el-table :data="allocation.byTenant || []" size="small" stripe>
                                <el-table-column prop="tenant_id" label="租户 ID" width="80" />
                                <el-table-column prop="cost" label="费用" width="90" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" label="占比" width="70" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                                <el-table-column prop="requests" label="请求" width="60" align="right" />
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按功能分摊 <small>($)</small></span></template>
                            <el-table :data="allocation.byFeature || []" size="small" stripe>
                                <el-table-column prop="feature_label" label="功能" min-width="100" />
                                <el-table-column prop="cost" label="费用" width="90" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" label="占比" width="70" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>按模型分摊 <small>($)</small></span></template>
                            <el-table :data="allocation.byModel || []" size="small" stripe>
                                <el-table-column prop="model" label="模型" width="120" />
                                <el-table-column prop="cost" label="费用" width="80" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" label="占比" width="60" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <!-- 创建记录对话框 -->
        <el-dialog v-model="showCreateRecord" title="手动录入 Token 消耗" width="480px">
            <el-form label-position="top">
                <el-form-item label="模型">
                    <el-select v-model="recordForm.model" style="width:100%">
                        <el-option v-for="(p, m) in models" :key="m" :label="m" :value="m" />
                    </el-select>
                </el-form-item>
                <el-form-item label="功能">
                    <el-select v-model="recordForm.feature" clearable style="width:100%">
                        <el-option v-for="(label, key) in features" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="输入 Token">
                            <el-input-number v-model="recordForm.input_tokens" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="输出 Token">
                            <el-input-number v-model="recordForm.output_tokens" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="租户 ID（可选）">
                    <el-input-number v-model="recordForm.tenant_id" :min="0" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateRecord = false">取消</el-button>
                <el-button type="primary" @click="handleRecord">记录</el-button>
            </template>
        </el-dialog>

        <!-- 新建预算对话框 -->
        <el-dialog v-model="showBudgetDialog" title="新建预算" width="480px">
            <el-form label-position="top">
                <el-form-item label="租户（留空为全局预算）">
                    <el-input-number v-model="budgetForm.tenant_id" :min="0" style="width:100%" />
                </el-form-item>
                <el-form-item label="周期">
                    <el-select v-model="budgetForm.period" style="width:100%">
                        <el-option label="月度" value="monthly" />
                        <el-option label="季度" value="quarterly" />
                        <el-option label="年度" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="预算限额 (USD)">
                    <el-input-number v-model="budgetForm.budget_limit" :min="0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item label="硬上限">
                    <el-switch v-model="budgetForm.hard_cap" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBudgetDialog = false">取消</el-button>
                <el-button type="primary" @click="handleCreateBudget">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import { getTokenDashboard, getTokenRecords, recordTokenConsumption, getTokenModels, getTokenFeatures, getTokenBudgets, upsertTokenBudget, getTokenAlerts, resolveTokenAlert, checkTokenAlerts, getCostAllocation, getAllocationSummary, exportAllocationCsv } from '@/api/tokenMeter';

const activeTab = ref('dashboard');
const loading = ref(false);
const dash = ref({});
const records = ref([]);
const models = ref({});
const features = ref({});
const budgets = ref([]);
const alerts = ref([]);
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
const dateRange = ref(null);

const filters = reactive({ model: '', feature: '', date_from: '', date_to: '' });

const showCreateRecord = ref(false);
const showBudgetDialog = ref(false);

const recordForm = reactive({ model: '', feature: '', input_tokens: 0, output_tokens: 0, tenant_id: null });
const budgetForm = reactive({ tenant_id: null, period: 'monthly', budget_limit: 100, hard_cap: false });

// ── 成本分摊 ──
const allocationMonth = ref(null);
const allocation = ref({});
const summary = ref({});

async function loadData() {
    loadDashboard();
    loadRecords();
    loadBudgets();
    loadAlerts();
    loadModels();
    loadFeatures();
    loadAllocation();
}

async function loadDashboard() {
    try {
        const { data: res } = await getTokenDashboard();
        dash.value = res.data || {};
    } catch { dash.value = {}; }
}

async function loadRecords() {
    loading.value = true;
    try {
        const params = { ...filters, page: page.value, per_page: perPage.value };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        const { data: res } = await getTokenRecords(params);
        records.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch { records.value = []; }
    finally { loading.value = false; }
}

async function loadModels() {
    try {
        const { data: res } = await getTokenModels();
        models.value = res.data || {};
    } catch { models.value = {}; }
}

async function loadFeatures() {
    try {
        const { data: res } = await getTokenFeatures();
        features.value = res.data || {};
    } catch { features.value = {}; }
}

async function loadBudgets() {
    try {
        const { data: res } = await getTokenBudgets();
        budgets.value = res.data || [];
    } catch { budgets.value = []; }
}

async function loadAlerts() {
    try {
        const { data: res } = await getTokenAlerts();
        alerts.value = res.data || [];
    } catch { alerts.value = []; }
}

function onDateChange(range) {
    if (range) {
        filters.date_from = range[0];
        filters.date_to = range[1];
    } else {
        filters.date_from = '';
        filters.date_to = '';
    }
    loadRecords();
}

async function handleRecord() {
    if (!recordForm.model) { ElMessage.warning('请选择模型'); return; }
    try {
        await recordTokenConsumption(recordForm);
        ElMessage.success('记录成功');
        showCreateRecord.value = false;
        recordForm.model = '';
        recordForm.feature = '';
        recordForm.input_tokens = 0;
        recordForm.output_tokens = 0;
        recordForm.tenant_id = null;
        loadDashboard();
        loadRecords();
    } catch { /* */ }
}

async function handleCreateBudget() {
    try {
        await upsertTokenBudget(budgetForm);
        ElMessage.success('保存成功');
        showBudgetDialog.value = false;
        loadBudgets();
    } catch { /* */ }
}

async function handleResolveAlert(id) {
    try {
        await resolveTokenAlert(id);
        ElMessage.success('已解决');
        loadAlerts();
    } catch { /* */ }
}

async function handleCheckAlerts() {
    try {
        const { data: res } = await checkTokenAlerts();
        ElMessage.success(`检查完成，产生 ${(res.data || []).length} 条新告警`);
        loadAlerts();
    } catch { /* */ }
}

// ── 成本分摊 ──

async function loadAllocation() {
    const params = {};
    if (allocationMonth.value) params.month = allocationMonth.value;
    try {
        const [allocRes, summRes] = await Promise.all([
            getCostAllocation(params),
            getAllocationSummary(params),
        ]);
        allocation.value = allocRes.data || {};
        summary.value = summRes.data || {};
    } catch {
        allocation.value = {};
        summary.value = {};
    }
}

function handleExportAllocation() {
    const params = {};
    if (allocationMonth.value) params.month = allocationMonth.value;
    const url = `/admin/token-meter/export-allocation?${new URLSearchParams(params)}`;
    window.open(url, '_blank');
}

function formatNum(n) {
    if (n === null || n === undefined) return '0';
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return Number(n).toFixed(n % 1 === 0 ? 0 : 2);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

onMounted(loadData);
</script>

<style scoped>
.token-meter-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); }
.mb-4 { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 12px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.text-primary { color: var(--el-color-primary); }
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
</style>
