<template>
    <div class="metered-billing-page">
        <div class="page-header">
            <h2>{{ t('metered_billing_page.title') }}</h2>
            <div class="header-actions">
                <el-button @click="refreshAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('metered_billing_page.refresh') }}
                </el-button>
            </div>
        </div>

        <!-- 概览卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('metered_billing_page.stat_active_prices') }}</div>
                    <div class="stat-value">{{ overview.active_prices }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">{{ t('metered_billing_page.stat_active_metered_subs') }}</div>
                    <div class="stat-value">{{ overview.active_subscriptions }}</div>
                    <div class="stat-sub">{{ t('metered_billing_page.stat_total_subs_suffix', { total: overview.total_subscriptions }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">{{ t('metered_billing_page.stat_adoption_rate') }}</div>
                    <div class="stat-value">{{ overview.metered_adoption_rate }}%</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">{{ t('metered_billing_page.stat_monthly_amount') }}</div>
                    <div class="stat-value">¥{{ overview.monthly_metered_amount }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- Tab 1: 价格配置 -->
            <el-tab-pane :label="t('metered_billing_page.tab_prices')" name="prices">
                <el-card>
                    <div class="mb-3">
                        <el-button type="primary" @click="openPriceDialog">
                            <el-icon><Plus /></el-icon> {{ t('metered_billing_page.btn_new_price') }}
                        </el-button>
                        <el-button @click="loadPrices" :loading="loadingPrices">{{ t('metered_billing_page.refresh') }}</el-button>
                    </div>

                    <el-table :data="prices" v-loading="loadingPrices" stripe>
                        <el-table-column prop="name" :label="t('metered_billing_page.col_name')" min-width="140" />
                        <el-table-column prop="metric_key" :label="t('metered_billing_page.col_metric')" width="180" />
                        <el-table-column prop="unit" :label="t('metered_billing_page.col_unit')" width="80" />
                        <el-table-column prop="billing_period" :label="t('metered_billing_page.col_billing_period')" width="100">
                            <template #default="{ row }">
                                {{ billingPeriodLabel(row.billing_period) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_price_tiers')" min-width="220">
                            <template #default="{ row }">
                                <div v-if="row.tiers?.length">
                                    <div v-for="(tier, i) in row.tiers" :key="i" class="tier-item">
                                        {{ formatTierRange(i, tier, row.unit) }}
                                    </div>
                                </div>
                                <span v-else class="text-gray-400">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_base_fee')" width="80" align="right">
                            <template #default="{ row }">¥{{ row.base_fee }}</template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_included_qty')" width="90" align="right">
                            <template #default="{ row }">{{ row.included_quantity }}</template>
                        </el-table-column>
                        <el-table-column prop="is_active" :label="t('metered_billing_page.col_status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? t('metered_billing_page.status_active') : t('metered_billing_page.status_inactive') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_actions')" width="100" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text type="primary" @click="editPrice(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="handleDeletePrice(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 2: 订阅配置 -->
            <el-tab-pane :label="t('metered_billing_page.tab_subscriptions')" name="subscriptions">
                <el-card>
                    <div class="mb-3">
                        <el-button @click="loadSubscriptions" :loading="loadingSubs">{{ t('metered_billing_page.refresh') }}</el-button>
                        <el-button @click="handleBatchGenerate" type="warning">
                            <el-icon><Coin /></el-icon> {{ t('metered_billing_page.btn_batch_generate') }}
                        </el-button>
                    </div>

                    <el-table :data="subscriptions" v-loading="loadingSubs" stripe>
                        <el-table-column :label="t('billing_page.col_customer')" width="140">
                            <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_product')" width="120">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_metered')" width="90">
                            <template #default="{ row }">
                                <el-tag v-if="row.metered_config?.enabled" type="success" size="small">{{ t('metered_billing_page.metered_enabled') }}</el-tag>
                                <el-tag v-else type="info" size="small">{{ t('metered_billing_page.metered_disabled') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_billing_period')" width="90">
                            <template #default="{ row }">
                                {{ billingPeriodLabel(row.metered_config?.billing_period) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_overage_cap')" width="90">
                            <template #default="{ row }">
                                {{ capTypeLabel(row.metered_config?.cap_type) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_cap_amount')" width="100" align="right">
                            <template #default="{ row }">
                                {{ row.metered_config?.monthly_cap ? '¥' + row.metered_config.monthly_cap : '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_last_billed')" width="170">
                            <template #default="{ row }">{{ formatTime(row.last_billed_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_actions')" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text type="primary"
                                    @click="openSubConfigDialog(row)">{{ t('metered_billing_page.btn_configure') }}</el-button>
                                <el-button size="small" text type="warning"
                                    @click="handleGenerateSingle(row)">{{ t('metered_billing_page.btn_generate_invoice') }}</el-button>
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
            <el-tab-pane :label="t('metered_billing_page.tab_invoices')" name="invoices">
                <el-card>
                    <el-table :data="overview.recent_invoices" stripe>
                        <el-table-column prop="invoice_no" :label="t('billing_page.col_invoice_no')" width="220" />
                        <el-table-column prop="customer_name" :label="t('billing_page.col_customer')" width="140" />
                        <el-table-column :label="t('billing_page.col_amount')" width="120" align="right">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.paid ? 'success' : 'warning'" size="small">
                                    {{ row.paid ? t('metered_billing_page.status_paid') : row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('metered_billing_page.col_line_items')" width="80" align="center">
                            <template #default="{ row }">{{ row.line_items_count }}</template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="t('billing_page.col_created')" width="170">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 价格配置对话框 -->
        <el-dialog v-model="showPriceDialog" :title="isEditPrice ? t('metered_billing_page.dialog_edit_price') : t('metered_billing_page.dialog_new_price')" width="650px">
            <el-form :model="priceForm" label-width="120px" ref="priceFormRef" :rules="priceRules">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('metered_billing_page.col_metric')" prop="metric_key" required>
                            <el-select v-model="priceForm.metric_key" filterable allow-create :placeholder="t('metered_billing_page.ph_select_metric')">
                                <el-option v-for="m in availableMetrics" :key="m.metric_key"
                                    :label="`${m.name} (${m.metric_key})`" :value="m.metric_key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('metered_billing_page.label_display_name')" prop="name" required>
                            <el-input v-model="priceForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('metered_billing_page.label_pricing_unit')" prop="unit" required>
                            <el-input v-model="priceForm.unit" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('metered_billing_page.col_billing_period')" prop="billing_period">
                            <el-select v-model="priceForm.billing_period">
                                <el-option v-for="opt in billingPeriodOptions" :key="opt.value"
                                    :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('metered_billing_page.label_base_fee')" prop="base_fee">
                            <el-input-number v-model="priceForm.base_fee" :min="0" :precision="2" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('metered_billing_page.col_included_qty')" prop="included_quantity">
                            <el-input-number v-model="priceForm.included_quantity" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('metered_billing_page.label_max_quantity')" prop="max_quantity">
                            <el-input-number v-model="priceForm.max_quantity" :min="0" :max="999999999" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('metered_billing_page.label_tiers')" required>
                    <div v-for="(tier, i) in priceForm.tiers" :key="i" class="tier-row">
                        <span class="tier-label">{{ t('metered_billing_page.tier_label', { n: i + 1 }) }}</span>
                        <el-input-number v-model="tier.from" :min="0" size="small" style="width: 100px" :placeholder="t('metered_billing_page.ph_from')" />
                        <span class="mx-1">~</span>
                        <el-input-number v-model="tier.to" :min="(tier.from || 0) + 1" size="small"
                            style="width: 100px" :disabled="i === priceForm.tiers.length - 1" :placeholder="t('metered_billing_page.ph_to')" />
                        <span class="mx-1">× ¥</span>
                        <el-input-number v-model="tier.unit_price" :min="0" :precision="4" size="small"
                            style="width: 120px" :placeholder="t('metered_billing_page.ph_unit_price')" />
                        <el-button v-if="priceForm.tiers.length > 1" type="danger" text size="small"
                            @click="priceForm.tiers.splice(i, 1)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button size="small" @click="addTier" class="mt-1">{{ t('metered_billing_page.btn_add_tier') }}</el-button>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPriceDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSavePrice" :loading="savingPrice">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 订阅配置对话框 -->
        <el-dialog v-model="showSubConfigDialog" :title="t('metered_billing_page.dialog_sub_config')" width="550px">
            <el-form :model="subConfigForm" label-width="130px">
                <el-form-item :label="t('metered_billing_page.label_enable_metered')">
                    <el-switch v-model="subConfigForm.enabled" />
                </el-form-item>
                <el-form-item :label="t('metered_billing_page.col_billing_period')" v-if="subConfigForm.enabled">
                    <el-select v-model="subConfigForm.billing_period">
                        <el-option v-for="opt in billingPeriodOptions" :key="opt.value"
                            :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('metered_billing_page.col_overage_cap')" v-if="subConfigForm.enabled">
                    <el-radio-group v-model="subConfigForm.cap_type">
                        <el-radio v-for="opt in capTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('metered_billing_page.label_monthly_cap')" v-if="subConfigForm.enabled && subConfigForm.cap_type === 'hard'">
                    <el-input-number v-model="subConfigForm.monthly_cap" :min="0" :precision="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSubConfigDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSaveSubConfig" :loading="savingSubConfig">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import meteredBillingApi from '@/api/meteredBilling';

const { t } = useI18n();

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

const billingPeriodOptions = computed(() => [
    { value: 'monthly', label: t('metered_billing_page.period_monthly') },
    { value: 'quarterly', label: t('metered_billing_page.period_quarterly') },
    { value: 'yearly', label: t('metered_billing_page.period_yearly') },
]);

const capTypeOptions = computed(() => [
    { value: 'soft', label: t('metered_billing_page.cap_soft_warn') },
    { value: 'hard', label: t('metered_billing_page.cap_hard_stop') },
]);

const priceRules = computed(() => ({
    metric_key: [{ required: true, message: t('metered_billing_page.validation_metric_required') }],
    name: [{ required: true, message: t('metered_billing_page.validation_display_name_required') }],
    unit: [{ required: true, message: t('metered_billing_page.validation_unit_required') }],
}));

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

function billingPeriodLabel(period) {
    const map = {
        monthly: t('metered_billing_page.period_monthly'),
        quarterly: t('metered_billing_page.period_quarterly'),
        yearly: t('metered_billing_page.period_yearly'),
    };
    return map[period] || period || '-';
}

function capTypeLabel(type) {
    if (type === 'hard') return t('metered_billing_page.cap_hard');
    if (type === 'soft') return t('metered_billing_page.cap_soft');
    return type || '-';
}

function formatTierRange(index, tier, unit) {
    const toPart = tier.to ? `~${tier.to}` : '+';
    return t('metered_billing_page.tier_range_fmt', {
        n: index + 1,
        from: tier.from,
        to: toPart,
        price: tier.unit_price,
        unit,
    });
}

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
        ElMessage.error(t('metered_billing_page.err_load_prices'));
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
        ElMessage.error(t('metered_billing_page.err_load_subs'));
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
        ElMessage.success(t('metered_billing_page.msg_price_saved'));
        showPriceDialog.value = false;
        await loadPrices();
        await loadOverview();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        savingPrice.value = false;
    }
}

async function handleDeletePrice(row) {
    try {
        await ElMessageBox.confirm(
            t('metered_billing_page.confirm_delete_price', { name: row.name }),
            t('metered_billing_page.confirm_delete_title'),
        );
        await meteredBillingApi.deletePrice(row.id);
        ElMessage.success(t('metered_billing_page.msg_deleted'));
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
        ElMessage.success(t('metered_billing_page.msg_sub_config_saved'));
        showSubConfigDialog.value = false;
        await loadSubscriptions();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        savingSubConfig.value = false;
    }
}

async function handleGenerateSingle(row) {
    try {
        await ElMessageBox.confirm(
            t('metered_billing_page.confirm_generate_single', { name: row.customer?.name || row.id }),
            t('metered_billing_page.confirm_generate_title'),
        );
        const res = await meteredBillingApi.generateInvoice(row.id, { dry_run: false });
        ElMessage.success(t('metered_billing_page.msg_invoice_generated', {
            amount: res.data?.data?.totals?.amount || 0,
        }));
        await loadSubscriptions();
        await loadOverview();
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(err.response?.data?.message || t('metered_billing_page.err_generate_invoice'));
        }
    }
}

async function handleBatchGenerate() {
    try {
        await ElMessageBox.confirm(
            t('metered_billing_page.confirm_batch_generate'),
            t('metered_billing_page.confirm_batch_title'),
        );
        const res = await meteredBillingApi.batchGenerateInvoices({ dry_run: false });
        ElMessage.success(t('metered_billing_page.msg_batch_processed', {
            count: res.data?.data?.total || 0,
        }));
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
.stat-info .stat-value { color: #0f172a; }
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
