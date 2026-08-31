<template>
    <div class="token-meter-page">
        <div class="page-header">
            <h2>{{ t('token_meter_page.title') }} <small class="text-muted">M2-77</small></h2>
            <div class="header-actions">
                <el-button @click="loadData">
                    <el-icon><Refresh /></el-icon> {{ t('token_meter_page.refresh') }}
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="tabLabels.dashboard" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">${{ formatNum(dash.totalMonthlyCost) }}</div><div class="stat-label">{{ t('token_meter_page.stats.monthly_cost') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ formatNum(dash.totalMonthlyTokens) }}</div><div class="stat-label">{{ t('token_meter_page.stats.monthly_tokens') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ dash.totalRequests || 0 }}</div><div class="stat-label">{{ t('token_meter_page.stats.request_count') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-success">{{ dash.activeTenants || 0 }}</div><div class="stat-label">{{ t('token_meter_page.stats.active_tenants') }}</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.by_model') }}</span></template>
                            <el-table :data="dash.byModel || []" size="small" stripe>
                                <el-table-column prop="model" :label="colLabels.model" />
                                <el-table-column prop="tokens" :label="colLabels.tokens" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" :label="colLabels.cost" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.by_feature') }}</span></template>
                            <el-table :data="dash.byFeature || []" size="small" stripe>
                                <el-table-column prop="feature" :label="colLabels.feature" />
                                <el-table-column prop="tokens" :label="colLabels.tokens" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" :label="colLabels.cost" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.by_provider') }}</span></template>
                            <el-table :data="dash.byProvider || []" size="small" stripe>
                                <el-table-column prop="provider" :label="colLabels.provider" />
                                <el-table-column prop="tokens" :label="colLabels.tokens" align="right" width="100">
                                    <template #default="{ row }">{{ formatNum(row.tokens) }}</template>
                                </el-table-column>
                                <el-table-column prop="cost" :label="colLabels.cost" align="right" width="80">
                                    <template #default="{ row }">${{ formatNum(row.cost) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.records" name="records">
                <el-card shadow="never">
                    <div class="toolbar">
                        <el-select v-model="filters.model" :placeholder="t('token_meter_page.filters.model_ph')" clearable style="width:140px" @change="loadRecords">
                            <el-option v-for="(p, m) in models" :key="m" :label="m" :value="m" />
                        </el-select>
                        <el-select v-model="filters.feature" :placeholder="t('token_meter_page.filters.feature_ph')" clearable style="width:140px" @change="loadRecords">
                            <el-option v-for="(label, key) in features" :key="key" :label="label" :value="key" />
                        </el-select>
                        <el-date-picker v-model="dateRange" type="daterange" :range-separator="t('licenses_page.date_range_sep')" value-format="YYYY-MM-DD" @change="onDateChange" />
                        <el-button type="primary" @click="showCreateRecord = true"><el-icon><Plus /></el-icon> {{ t('token_meter_page.buttons.manual_entry') }}</el-button>
                    </div>
                    <el-table :data="records" v-loading="loading" stripe border style="width:100%">
                        <el-table-column prop="created_at" :label="colLabels.time" width="160">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column prop="model" :label="colLabels.model" width="130" />
                        <el-table-column prop="provider" :label="colLabels.provider" width="90" />
                        <el-table-column prop="feature" :label="colLabels.feature" width="100" />
                        <el-table-column prop="input_tokens" :label="colLabels.input" width="80" align="right" />
                        <el-table-column prop="output_tokens" :label="colLabels.output" width="80" align="right" />
                        <el-table-column prop="total_tokens" :label="colLabels.total" width="80" align="right" />
                        <el-table-column prop="cost" :label="colLabels.cost" width="80" align="right">
                            <template #default="{ row }">${{ row.cost }}</template>
                        </el-table-column>
                        <el-table-column prop="tenant_id" :label="colLabels.tenant" width="60" />
                        <el-table-column prop="cached" :label="colLabels.cache" width="60">
                            <template #default="{ row }"><el-tag :type="row.cached ? 'success' : 'info'" size="small">{{ row.cached ? statusLabels.yes : statusLabels.no }}</el-tag></template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total" layout="total, sizes, prev, pager, next" @change="loadRecords" />
                    </div>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.budgets" name="budgets">
                <div class="toolbar mb-4">
                    <el-button type="primary" @click="showBudgetDialog = true"><el-icon><Plus /></el-icon> {{ t('actions.create') }}</el-button>
                    <el-button @click="handleCheckAlerts"><el-icon><Refresh /></el-icon> {{ t('token_meter_page.buttons.check_alerts') }}</el-button>
                </div>
                <el-table :data="budgets" stripe border>
                    <el-table-column prop="tenant_id" :label="colLabels.tenant" width="80">
                        <template #default="{ row }">{{ row.tenant?.name || statusLabels.global }}</template>
                    </el-table-column>
                    <el-table-column prop="period" :label="colLabels.period" width="100">
                        <template #default="{ row }">{{ formatPeriod(row.period) }}</template>
                    </el-table-column>
                    <el-table-column prop="budget_limit" :label="colLabels.budget_limit" width="120" align="right">
                        <template #default="{ row }">${{ row.budget_limit }}</template>
                    </el-table-column>
                    <el-table-column :label="colLabels.alert_thresholds" width="200">
                        <template #default="{ row }">{{ row.alert_threshold_1 }}% / {{ row.alert_threshold_2 }}% / {{ row.alert_threshold_3 }}%</template>
                    </el-table-column>
                    <el-table-column prop="hard_cap" :label="colLabels.hard_cap" width="80">
                        <template #default="{ row }"><el-tag :type="row.hard_cap ? 'danger' : 'info'" size="small">{{ row.hard_cap ? statusLabels.yes : statusLabels.no }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="is_active" :label="colLabels.status" width="70">
                        <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? statusLabels.enabled : statusLabels.disabled }}</el-tag></template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.alerts" name="alerts">
                <el-table :data="alerts" stripe border>
                    <el-table-column prop="created_at" :label="colLabels.time" width="160">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column prop="type" :label="colLabels.type" width="150">
                        <template #default="{ row }">
                            <el-tag :type="row.type === 'hard_cap_reached' ? 'danger' : 'warning'" size="small">{{ row.type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="threshold_pct" :label="colLabels.threshold" width="80" align="right">{{ row => row.threshold_pct }}%</el-table-column>
                    <el-table-column prop="current_spend" :label="colLabels.current_spend" width="120" align="right">
                        <template #default="{ row }">${{ row.current_spend }}</template>
                    </el-table-column>
                    <el-table-column prop="budget_limit" :label="colLabels.budget_limit" width="120" align="right">
                        <template #default="{ row }">${{ row.budget_limit }}</template>
                    </el-table-column>
                    <el-table-column :label="colLabels.status" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.resolved_at ? 'success' : 'danger'" size="small">{{ row.resolved_at ? statusLabels.resolved : statusLabels.unresolved }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="colLabels.actions" width="100">
                        <template #default="{ row }">
                            <el-button v-if="!row.resolved_at" size="small" type="primary" @click="handleResolveAlert(row.id)">{{ t('token_meter_page.buttons.resolve') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.allocation" name="allocation">
                <div class="toolbar mb-4">
                    <el-date-picker v-model="allocationMonth" type="month" :placeholder="t('token_meter_page.filters.select_month_ph')" value-format="YYYY-MM" @change="loadAllocation" />
                    <el-button @click="loadAllocation"><el-icon><Refresh /></el-icon> {{ t('token_meter_page.refresh') }}</el-button>
                    <el-button type="primary" @click="handleExportAllocation">{{ t('actions.export') }} CSV</el-button>
                </div>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">${{ formatNum(allocation.totalCost) }}</div><div class="stat-label">{{ t('token_meter_page.stats.allocation_total') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ formatNum(allocation.totalTokens) }}</div><div class="stat-label">{{ t('token_meter_page.stats.total_tokens') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ allocation.totalRequests || 0 }}</div><div class="stat-label">{{ t('token_meter_page.stats.total_requests') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-warning">{{ summary.top3TenantPct || 0 }}%</div><div class="stat-label">{{ t('token_meter_page.stats.top3_tenant_pct') }}</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.allocation_by_tenant') }} <small>($)</small></span></template>
                            <el-table :data="allocation.byTenant || []" size="small" stripe>
                                <el-table-column prop="tenant_id" :label="colLabels.tenant_id" width="80" />
                                <el-table-column prop="cost" :label="colLabels.cost" width="90" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" :label="colLabels.pct" width="70" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                                <el-table-column prop="requests" :label="colLabels.requests" width="60" align="right" />
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.allocation_by_feature') }} <small>($)</small></span></template>
                            <el-table :data="allocation.byFeature || []" size="small" stripe>
                                <el-table-column prop="feature_label" :label="colLabels.feature" min-width="100" />
                                <el-table-column prop="cost" :label="colLabels.cost" width="90" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" :label="colLabels.pct" width="70" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('token_meter_page.sections.allocation_by_model') }} <small>($)</small></span></template>
                            <el-table :data="allocation.byModel || []" size="small" stripe>
                                <el-table-column prop="model" :label="colLabels.model" width="120" />
                                <el-table-column prop="cost" :label="colLabels.cost" width="80" align="right">
                                    <template #default="{ row }">${{ row.cost }}</template>
                                </el-table-column>
                                <el-table-column prop="pct" :label="colLabels.pct" width="60" align="right">
                                    <template #default="{ row }">{{ row.pct }}%</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showCreateRecord" :title="t('token_meter_page.dialogs.manual_entry_title')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="t('token_meter_page.form.model')">
                    <el-select v-model="recordForm.model" style="width:100%">
                        <el-option v-for="(p, m) in models" :key="m" :label="m" :value="m" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('token_meter_page.form.feature')">
                    <el-select v-model="recordForm.feature" clearable style="width:100%">
                        <el-option v-for="(label, key) in features" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('token_meter_page.form.input_tokens')">
                            <el-input-number v-model="recordForm.input_tokens" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('token_meter_page.form.output_tokens')">
                            <el-input-number v-model="recordForm.output_tokens" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('token_meter_page.form.tenant_id_optional')">
                    <el-input-number v-model="recordForm.tenant_id" :min="0" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateRecord = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleRecord">{{ t('token_meter_page.buttons.record') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showBudgetDialog" :title="t('token_meter_page.dialogs.new_budget_title')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="t('token_meter_page.form.tenant_global_hint')">
                    <el-input-number v-model="budgetForm.tenant_id" :min="0" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t('token_meter_page.form.period')">
                    <el-select v-model="budgetForm.period" style="width:100%">
                        <el-option v-for="opt in periodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('token_meter_page.form.budget_limit_usd')">
                    <el-input-number v-model="budgetForm.budget_limit" :min="0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t('token_meter_page.form.hard_cap')">
                    <el-switch v-model="budgetForm.hard_cap" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBudgetDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreateBudget">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import { getTokenDashboard, getTokenRecords, recordTokenConsumption, getTokenModels, getTokenFeatures, getTokenBudgets, upsertTokenBudget, getTokenAlerts, resolveTokenAlert, checkTokenAlerts, getCostAllocation, getAllocationSummary, exportAllocationCsv } from '@/api/tokenMeter';

const { t, locale } = useI18n();

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

const allocationMonth = ref(null);
const allocation = ref({});
const summary = ref({});

const tabLabels = computed(() => ({
    dashboard: t('token_meter_page.tabs.dashboard'),
    records: t('token_meter_page.tabs.records'),
    budgets: t('token_meter_page.tabs.budgets'),
    alerts: t('token_meter_page.tabs.alerts'),
    allocation: t('token_meter_page.tabs.allocation'),
}));

const colLabels = computed(() => ({
    model: t('token_meter_page.cols.model'),
    tokens: t('token_meter_page.cols.tokens'),
    cost: t('token_meter_page.cols.cost'),
    feature: t('token_meter_page.cols.feature'),
    provider: t('token_meter_page.cols.provider'),
    time: t('token_meter_page.cols.time'),
    input: t('token_meter_page.cols.input'),
    output: t('token_meter_page.cols.output'),
    total: t('token_meter_page.cols.total'),
    tenant: t('token_meter_page.cols.tenant'),
    tenant_id: t('token_meter_page.cols.tenant_id'),
    cache: t('token_meter_page.cols.cache'),
    period: t('token_meter_page.cols.period'),
    budget_limit: t('token_meter_page.cols.budget_limit'),
    alert_thresholds: t('token_meter_page.cols.alert_thresholds'),
    hard_cap: t('token_meter_page.cols.hard_cap'),
    status: t('token_meter_page.cols.status'),
    type: t('token_meter_page.cols.type'),
    threshold: t('token_meter_page.cols.threshold'),
    current_spend: t('token_meter_page.cols.current_spend'),
    pct: t('token_meter_page.cols.pct'),
    requests: t('token_meter_page.cols.requests'),
    actions: t('token_meter_page.cols.actions'),
}));

const statusLabels = computed(() => ({
    yes: t('token_meter_page.status.yes'),
    no: t('token_meter_page.status.no'),
    enabled: t('token_meter_page.status.enabled'),
    disabled: t('token_meter_page.status.disabled'),
    resolved: t('token_meter_page.status.resolved'),
    unresolved: t('token_meter_page.status.unresolved'),
    global: t('token_meter_page.status.global'),
}));

const periodLabels = computed(() => ({
    monthly: t('token_meter_page.period.monthly'),
    quarterly: t('token_meter_page.period.quarterly'),
    yearly: t('token_meter_page.period.yearly'),
}));

const periodOptions = computed(() => [
    { label: periodLabels.value.monthly, value: 'monthly' },
    { label: periodLabels.value.quarterly, value: 'quarterly' },
    { label: periodLabels.value.yearly, value: 'yearly' },
]);

function formatPeriod(period) {
    return periodLabels.value[period] || period;
}

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
    if (!recordForm.model) { ElMessage.warning(t('token_meter_page.messages.select_model')); return; }
    try {
        await recordTokenConsumption(recordForm);
        ElMessage.success(t('token_meter_page.messages.record_success'));
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
        ElMessage.success(t('token_meter_page.messages.save_success'));
        showBudgetDialog.value = false;
        loadBudgets();
    } catch { /* */ }
}

async function handleResolveAlert(id) {
    try {
        await resolveTokenAlert(id);
        ElMessage.success(t('token_meter_page.messages.resolved'));
        loadAlerts();
    } catch { /* */ }
}

async function handleCheckAlerts() {
    try {
        const { data: res } = await checkTokenAlerts();
        ElMessage.success(t('token_meter_page.messages.check_complete', { count: (res.data || []).length }));
        loadAlerts();
    } catch { /* */ }
}

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
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(dateStr).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
