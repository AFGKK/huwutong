<template>
    <div class="metered-billing-page">
        <div class="page-header">
            <h2>用量计费 Metered Billing</h2>
            <div class="header-actions">
                <el-button @click="refreshAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 概览卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-label">活跃价格配置</div>
                    <div class="stat-value">{{ overview.active_prices }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">启用用量计费订阅</div>
                    <div class="stat-value">{{ overview.active_subscriptions }}</div>
                    <div class="stat-sub">/ {{ overview.total_subscriptions }} 总订阅</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">用量计费采用率</div>
                    <div class="stat-value">{{ overview.metered_adoption_rate }}%</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">本月用量计费金额</div>
                    <div class="stat-value">¥{{ overview.monthly_metered_amount }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- Tab 1: 价格配置 -->
            <el-tab-pane label="价格配置" name="prices">
                <el-card>
                    <div class="mb-3">
                        <el-button type="primary" @click="openPriceDialog">
                            <el-icon><Plus /></el-icon> 新增价格配置
                        </el-button>
                        <el-button @click="loadPrices" :loading="loadingPrices">刷新</el-button>
                    </div>

                    <el-table :data="prices" v-loading="loadingPrices" stripe>
                        <el-table-column prop="name" label="名称" min-width="140" />
                        <el-table-column prop="metric_key" label="计量指标" width="180" />
                        <el-table-column prop="unit" label="单位" width="80" />
                        <el-table-column prop="billing_period" label="结算周期" width="100">
                            <template #default="{ row }">
                                {{ row.billing_period === 'monthly' ? '月度' : row.billing_period === 'quarterly' ? '季度' : '年度' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="价格阶梯" min-width="220">
                            <template #default="{ row }">
                                <div v-if="row.tiers?.length">
                                    <div v-for="(tier, i) in row.tiers" :key="i" class="tier-item">
                                        第{{ i + 1 }}阶梯: {{ tier.from }}{{ tier.to ? '~' + tier.to : '+' }} × ¥{{ tier.unit_price }}/{{ row.unit }}
                                    </div>
                                </div>
                                <span v-else class="text-gray-400">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="基础费" width="80" align="right">
                            <template #default="{ row }">¥{{ row.base_fee }}</template>
                        </el-table-column>
                        <el-table-column label="包含用量" width="90" align="right">
                            <template #default="{ row }">{{ row.included_quantity }}</template>
                        </el-table-column>
                        <el-table-column prop="is_active" label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="100" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text type="primary" @click="editPrice(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="handleDeletePrice(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 2: 订阅配置 -->
            <el-tab-pane label="订阅配置" name="subscriptions">
                <el-card>
                    <div class="mb-3">
                        <el-button @click="loadSubscriptions" :loading="loadingSubs">刷新</el-button>
                        <el-button @click="handleBatchGenerate" type="warning">
                            <el-icon><Coin /></el-icon> 批量生成账单
                        </el-button>
                    </div>

                    <el-table :data="subscriptions" v-loading="loadingSubs" stripe>
                        <el-table-column label="客户" width="140">
                            <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="产品" width="120">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="用量计费" width="90">
                            <template #default="{ row }">
                                <el-tag v-if="row.metered_config?.enabled" type="success" size="small">已启用</el-tag>
                                <el-tag v-else type="info" size="small">未启用</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="结算周期" width="90">
                            <template #default="{ row }">
                                {{ row.metered_config?.billing_period === 'monthly' ? '月度' : row.metered_config?.billing_period === 'quarterly' ? '季度' : '年度' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="超额保护" width="90">
                            <template #default="{ row }">
                                {{ row.metered_config?.cap_type === 'hard' ? '硬上限' : '软上限' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="上限金额" width="100" align="right">
                            <template #default="{ row }">
                                {{ row.metered_config?.monthly_cap ? '¥' + row.metered_config.monthly_cap : '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="最后结算" width="170">
                            <template #default="{ row }">{{ formatTime(row.last_billed_at) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text type="primary"
                                    @click="openSubConfigDialog(row)">配置</el-button>
                                <el-button size="small" text type="warning"
                                    @click="handleGenerateSingle(row)">生成账单</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap" v-if="subsMeta">
                        <el-pagination
                            v-model:current-page="subsMeta.current_page"
                            :page-size="subsMeta.per_page"
                            :total="subsMeta.total"
                            layout="total, prev, pager, next"
                            @current-change="loadSubscriptions" />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 最近发票 -->
            <el-tab-pane label="最近发票" name="invoices">
                <el-card>
                    <el-table :data="overview.recent_invoices" stripe>
                        <el-table-column prop="invoice_no" label="发票号" width="220" />
                        <el-table-column prop="customer_name" label="客户" width="140" />
                        <el-table-column label="金额" width="120" align="right">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.paid ? 'success' : 'warning'" size="small">
                                    {{ row.paid ? '已支付' : row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="行项目" width="80" align="center">
                            <template #default="{ row }">{{ row.line_items_count }}</template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="创建时间" width="170">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 价格配置对话框 -->
        <el-dialog v-model="showPriceDialog" :title="isEditPrice ? '编辑价格配置' : '新增价格配置'" width="650px">
            <el-form :model="priceForm" label-width="120px" ref="priceFormRef" :rules="priceRules">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="计量指标" prop="metric_key" required>
                            <el-select v-model="priceForm.metric_key" filterable allow-create placeholder="选择或输入">
                                <el-option v-for="m in availableMetrics" :key="m.metric_key"
                                    :label="`${m.name} (${m.metric_key})`" :value="m.metric_key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="显示名称" prop="name" required>
                            <el-input v-model="priceForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="计价单位" prop="unit" required>
                            <el-input v-model="priceForm.unit" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="结算周期" prop="billing_period">
                            <el-select v-model="priceForm.billing_period">
                                <el-option label="月度" value="monthly" />
                                <el-option label="季度" value="quarterly" />
                                <el-option label="年度" value="yearly" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="基础费" prop="base_fee">
                            <el-input-number v-model="priceForm.base_fee" :min="0" :precision="2" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="包含用量" prop="included_quantity">
                            <el-input-number v-model="priceForm.included_quantity" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="用量上限" prop="max_quantity">
                            <el-input-number v-model="priceForm.max_quantity" :min="0" :max="999999999" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="价格阶梯" required>
                    <div v-for="(tier, i) in priceForm.tiers" :key="i" class="tier-row">
                        <span class="tier-label">第{{ i + 1 }}阶梯</span>
                        <el-input-number v-model="tier.from" :min="0" size="small" style="width: 100px" placeholder="from" />
                        <span class="mx-1">~</span>
                        <el-input-number v-model="tier.to" :min="(tier.from || 0) + 1" size="small"
                            style="width: 100px" :disabled="i === priceForm.tiers.length - 1" placeholder="to" />
                        <span class="mx-1">× ¥</span>
                        <el-input-number v-model="tier.unit_price" :min="0" :precision="4" size="small"
                            style="width: 120px" placeholder="单价" />
                        <el-button v-if="priceForm.tiers.length > 1" type="danger" text size="small"
                            @click="priceForm.tiers.splice(i, 1)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button size="small" @click="addTier" class="mt-1">+ 添加阶梯</el-button>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPriceDialog = false">取消</el-button>
                <el-button type="primary" @click="handleSavePrice" :loading="savingPrice">保存</el-button>
            </template>
        </el-dialog>

        <!-- 订阅配置对话框 -->
        <el-dialog v-model="showSubConfigDialog" title="用量计费配置" width="550px">
            <el-form :model="subConfigForm" label-width="130px">
                <el-form-item label="启用用量计费">
                    <el-switch v-model="subConfigForm.enabled" />
                </el-form-item>
                <el-form-item label="结算周期" v-if="subConfigForm.enabled">
                    <el-select v-model="subConfigForm.billing_period">
                        <el-option label="月度" value="monthly" />
                        <el-option label="季度" value="quarterly" />
                        <el-option label="年度" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="超额保护" v-if="subConfigForm.enabled">
                    <el-radio-group v-model="subConfigForm.cap_type">
                        <el-radio value="soft">软上限（仅警告）</el-radio>
                        <el-radio value="hard">硬上限（封顶）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="月度封顶金额" v-if="subConfigForm.enabled && subConfigForm.cap_type === 'hard'">
                    <el-input-number v-model="subConfigForm.monthly_cap" :min="0" :precision="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSubConfigDialog = false">取消</el-button>
                <el-button type="primary" @click="handleSaveSubConfig" :loading="savingSubConfig">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import meteredBillingApi from '@/api/meteredBilling';

const loading = ref(true);
const activeTab = ref('prices');
const overview = ref({ active_prices: 0, active_subscriptions: 0, total_subscriptions: 0, metered_adoption_rate: 0, monthly_metered_amount: 0, recent_invoices: [] });

// 价格配置
const prices = ref([]);
const loadingPrices = ref(false);
const showPriceDialog = ref(false);
const isEditPrice = ref(false);
const editingPriceId = ref(null);
const savingPrice = ref(false);
const availableMetrics = ref([]);
const priceFormRef = ref(null);
const priceForm = reactive({
    metric_key: '', name: '', unit: 'count',
    billing_period: 'monthly',
    base_fee: 0, included_quantity: 0, max_quantity: null,
    tiers: [{ from: 0, to: 1000, unit_price: 0.01 }],
});

const priceRules = {
    metric_key: [{ required: true, message: '请选择计量指标' }],
    name: [{ required: true, message: '请输入显示名称' }],
    unit: [{ required: true, message: '请输入计价单位' }],
};

// 订阅配置
const subscriptions = ref([]);
const subsMeta = ref(null);
const loadingSubs = ref(false);
const showSubConfigDialog = ref(false);
const savingSubConfig = ref(false);
const editingSub = ref(null);
const subConfigForm = reactive({
    enabled: false,
    billing_period: 'monthly',
    cap_type: 'soft',
    monthly_cap: null,
});

function formatTime(val) {
    if (!val) return null;
    try {
        const d = new Date(val);
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch { return val; }
}

function addTier() {
    const last = priceForm.tiers[priceForm.tiers.length - 1];
    const nextFrom = (last?.to || last?.from || 0) + 1;
    priceForm.tiers.push({ from: nextFrom, to: null, unit_price: 0.005 });
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadOverview(), loadPrices(), loadAvailableMetrics()]);
    loading.value = false;
}

async function loadOverview() {
    try {
        const res = await meteredBillingApi.getOverview();
        overview.value = res.data?.data || {};
    } catch { }
}

async function loadPrices() {
    loadingPrices.value = true;
    try {
        const res = await meteredBillingApi.getPrices();
        prices.value = res.data?.data || [];
    } catch {
        ElMessage.error('加载价格配置失败');
    } finally {
        loadingPrices.value = false;
    }
}

async function loadAvailableMetrics() {
    try {
        const res = await meteredBillingApi.getAvailableMetrics();
        availableMetrics.value = res.data?.data || [];
    } catch { }
}

async function loadSubscriptions(page = 1) {
    loadingSubs.value = true;
    try {
        const res = await meteredBillingApi.getMeteredSubscriptions({ page, per_page: 20 });
        subscriptions.value = res.data?.data || [];
        subsMeta.value = res.data?.meta;
    } catch {
        ElMessage.error('加载订阅列表失败');
    } finally {
        loadingSubs.value = false;
    }
}

function openPriceDialog() {
    isEditPrice.value = false;
    editingPriceId.value = null;
    Object.assign(priceForm, {
        metric_key: '', name: '', unit: 'count',
        billing_period: 'monthly',
        base_fee: 0, included_quantity: 0, max_quantity: null,
        tiers: [{ from: 0, to: 1000, unit_price: 0.01 }],
    });
    showPriceDialog.value = true;
}

function editPrice(row) {
    isEditPrice.value = true;
    editingPriceId.value = row.id;
    Object.assign(priceForm, {
        metric_key: row.metric_key,
        name: row.name,
        unit: row.unit,
        billing_period: row.billing_period,
        base_fee: row.base_fee ?? 0,
        included_quantity: row.included_quantity ?? 0,
        max_quantity: row.max_quantity ?? null,
        tiers: JSON.parse(JSON.stringify(row.tiers || [{ from: 0, to: 1000, unit_price: 0.01 }])),
    });
    showPriceDialog.value = true;
}

async function handleSavePrice() {
    savingPrice.value = true;
    try {
        await meteredBillingApi.upsertPrice(priceForm);
        ElMessage.success('价格配置已保存');
        showPriceDialog.value = false;
        await loadPrices();
        await loadOverview();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        savingPrice.value = false;
    }
}

async function handleDeletePrice(row) {
    try {
        await ElMessageBox.confirm(`确认删除 "${row.name}" 的价格配置？`, '确认删除');
        await meteredBillingApi.deletePrice(row.id);
        ElMessage.success('已删除');
        await loadPrices();
        await loadOverview();
    } catch { }
}

function openSubConfigDialog(row) {
    editingSub.value = row;
    const cfg = row.metered_config || {};
    subConfigForm.enabled = cfg.enabled ?? false;
    subConfigForm.billing_period = cfg.billing_period ?? 'monthly';
    subConfigForm.cap_type = cfg.cap_type ?? 'soft';
    subConfigForm.monthly_cap = cfg.monthly_cap ?? null;
    showSubConfigDialog.value = true;
}

async function handleSaveSubConfig() {
    savingSubConfig.value = true;
    try {
        await meteredBillingApi.updateSubscriptionConfig(editingSub.value.id, subConfigForm);
        ElMessage.success('订阅配置已更新');
        showSubConfigDialog.value = false;
        await loadSubscriptions();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        savingSubConfig.value = false;
    }
}

async function handleGenerateSingle(row) {
    try {
        await ElMessageBox.confirm(`确认为 "${row.customer?.name || row.id}" 生成用量账单？`, '确认生成');
        const res = await meteredBillingApi.generateInvoice(row.id, { dry_run: false });
        ElMessage.success(`账单已生成，金额 ¥${res.data?.data?.totals?.amount || 0}`);
        await loadSubscriptions();
        await loadOverview();
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(err.response?.data?.message || '生成账单失败');
        }
    }
}

async function handleBatchGenerate() {
    try {
        await ElMessageBox.confirm('确认批量生成所有已启用用量计费的订阅的账单？', '确认批量生成');
        const res = await meteredBillingApi.batchGenerateInvoices({ dry_run: false });
        ElMessage.success(`已处理 ${res.data?.data?.total || 0} 个订阅`);
        await loadSubscriptions();
        await loadOverview();
    } catch { }
}

watch(activeTab, (tab) => {
    if (tab === 'subscriptions') loadSubscriptions();
    if (tab === 'invoices') loadOverview();
});

onMounted(refreshAll);
</script>

<style scoped>
.metered-billing-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-sub { font-size: 12px; color: #c0c4cc; }
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-info .stat-value { color: #409eff; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-1 { margin-top: 4px; }
.mx-1 { margin: 0 6px; }
.tier-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.tier-label { min-width: 70px; font-size: 13px; color: #606266; }
.tier-item { font-size: 12px; color: #606266; line-height: 1.8; }
.text-gray-400 { color: #909399; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
</style>
