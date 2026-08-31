<template>
    <div class="billing-detail-page" v-loading="loading">
        <div class="page-header">
            <div class="header-left">
                <el-button text @click="goBack">
                    <el-icon><ArrowLeft /></el-icon>
                    {{ t('actions.back') }}
                </el-button>
                <h2>{{ t('billing_detail_page.title') }}</h2>
            </div>
            <div class="header-right" v-if="subscription">
                <el-button
                    v-if="subscription.status === 'active'"
                    type="warning"
                    plain
                    @click="openChangePlan"
                >
                    <el-icon><Coin /></el-icon>
                    {{ t('billing_detail_page.change_plan') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'active'"
                    type="info"
                    plain
                    @click="handleSuspend"
                >
                    <el-icon><VideoPause /></el-icon>
                    {{ t('billing_detail_page.suspend') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'suspended'"
                    type="primary"
                    plain
                    @click="handleResume"
                >
                    <el-icon><CircleCheck /></el-icon>
                    {{ t('billing_detail_page.resume_sub') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'active' || subscription.status === 'grace'"
                    type="danger"
                    plain
                    @click="handleCancel"
                >
                    <el-icon><CircleClose /></el-icon>
                    {{ t('billing_detail_page.cancel_sub') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'canceled'"
                    type="primary"
                    @click="handleResume"
                >
                    <el-icon><CircleCheck /></el-icon>
                    {{ t('billing_detail_page.resume_sub') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'active' && subscription.auto_renew"
                    type="success"
                    @click="handleRenew"
                >
                    <el-icon><Refresh /></el-icon>
                    {{ t('billing_detail_page.manual_renew') }}
                </el-button>
                <el-button
                    v-if="subscription.status === 'active'"
                    type="primary"
                    plain
                    @click="showToggleAutoRenew"
                >
                    <el-icon :class="{ 'is-active': subscription.auto_renew }"><SwitchButton /></el-icon>
                    {{ subscription.auto_renew ? t('billing_detail_page.btn_auto_renew_off') : t('billing_detail_page.btn_auto_renew_on') }}
                </el-button>
            </div>
        </div>

        <div v-if="subscription">
            <el-card shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>{{ t('billing_detail_page.basic_info') }}</span>
                        <div class="header-tags">
                            <el-tag :type="statusType(subscription.status)" size="large" effect="dark">
                                {{ statusLabel(subscription.status) }}
                            </el-tag>
                            <el-tag v-if="subscription.auto_renew" type="success" size="small" effect="plain" class="ml-2">
                                {{ t('billing_detail_page.tag_auto_renew') }}
                            </el-tag>
                            <el-tag v-else size="small" effect="plain" class="ml-2">
                                {{ t('billing_detail_page.tag_manual_renew') }}
                            </el-tag>
                        </div>
                    </div>
                </template>

                <el-descriptions :column="3" border>
                    <el-descriptions-item :label="t('billing_detail_page.cols.subscription_id')">#{{ subscription.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_customer')">
                        <router-link :to="`/customers/${subscription.customer_id}`" class="link">
                            {{ subscription.customer?.name || subscription.customer?.user?.name || emDash }}
                        </router-link>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.email')">{{ subscription.customer?.email || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_product')">{{ subscription.product?.name || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.form_plan')">
                        <el-tag size="small">{{ subscription.plan }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.form_billing_period')">{{ periodLabel(subscription.billing_period) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.unit_price')">
                        <span class="price">¥{{ subscription.price }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.form_currency')">{{ subscription.currency || 'CNY' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.total_paid')">
                        <span class="price">¥{{ (subscription.total_paid || 0).toFixed(2) }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.billing_cycles')">
                        {{ t('billing_detail_page.cycles_suffix', { n: subscription.billing_cycles_completed || 0 }) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.grace_days')">{{ t('billing_page.days_suffix', { n: subscription.grace_days || 7 }) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.form_trial_days')">{{ t('billing_page.days_suffix', { n: subscription.trial_days || 0 }) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.starts_at')">{{ formatDate(subscription.starts_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_expires')">{{ formatDate(subscription.ends_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.next_billing')">{{ formatDate(subscription.next_billing_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.last_billed')">{{ formatDate(subscription.last_billed_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.grace_ends')">{{ formatDate(subscription.grace_ends_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.canceled_at')">{{ formatDate(subscription.canceled_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.cancel_reason')">{{ subscription.cancellation_reason || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.form_license')">
                        <el-tag v-if="subscription.license_id" size="small" effect="plain">#{{ subscription.license_id }}</el-tag>
                        <span v-else>{{ emDash }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('billing_page.col_created')">{{ formatDate(subscription.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('billing_detail_page.cols.updated_at')">{{ formatDate(subscription.updated_at) }}</el-descriptions-item>
                </el-descriptions>
            </el-card>

            <el-card shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>{{ t('billing_detail_page.activity_log') }}</span>
                    </div>
                </template>
                <el-timeline v-if="activityLog.length > 0">
                    <el-timeline-item
                        v-for="(log, idx) in activityLog"
                        :key="idx"
                        :timestamp="formatDate(log.created_at)"
                        :type="log.type === 'success' ? 'success' : log.type === 'warning' ? 'warning' : 'primary'"
                    >
                        {{ log.description || log.action || log.message || emDash }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else :image-size="60" :description="t('billing_detail_page.no_activity')" />
            </el-card>

            <el-card shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>{{ t('billing_detail_page.recent_invoices') }}</span>
                        <el-button text size="small" type="primary" @click="$router.push('/billing?tab=invoices')">
                            {{ t('billing_detail_page.view_all') }}
                        </el-button>
                    </div>
                </template>

                <el-table :data="subscription.invoices || []" stripe size="small">
                    <el-table-column prop="invoice_no" :label="t('billing_page.col_invoice_no')" width="180" />
                    <el-table-column :label="t('billing_page.col_amount')" width="120">
                        <template #default="{ row }">
                            <span class="price">¥{{ row.amount }}</span>
                            <el-tag v-if="row.discount_amount > 0" size="small" type="warning" class="ml-1">{{ t('billing_page.discounted') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('billing_page.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="invoiceStatusType(row.status)" size="small">
                                {{ invoiceStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="billing_reason" :label="t('billing_page.col_reason')" width="140" />
                    <el-table-column prop="created_at" :label="t('billing_page.col_created')" width="170">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('billing_page.col_actions')" width="80">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="viewInvoice(row)">{{ t('billing_page.detail') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-if="!subscription.invoices?.length" :image-size="60" :description="t('billing_detail_page.no_invoices')" />
            </el-card>
        </div>

        <el-dialog v-model="showChangePlan" :title="t('billing_detail_page.dialog_change_plan')" width="550px" :close-on-click-modal="false">
            <el-form ref="changePlanFormRef" :model="changePlanForm" :rules="changePlanRules" label-position="top">
                <el-alert type="info" :closable="false" class="mb-3">
                    <template #title>
                        {{ t('billing_detail_page.current_plan_alert', {
                            plan: subscription?.plan,
                            price: subscription?.price,
                            period: periodLabel(subscription?.billing_period),
                        }) }}
                    </template>
                </el-alert>
                <el-divider />
                <el-form-item :label="t('billing_detail_page.select_plan')" prop="plan_slug">
                    <el-select v-model="changePlanForm.plan_slug" filterable :placeholder="t('billing_detail_page.search_plan_ph')" style="width:100%"
                        @change="onPlanSelect">
                        <el-option v-for="p in planOptions" :key="p.slug" :label="`${p.name} (${p.slug})`" :value="p.slug">
                            <div class="plan-option">
                                <span>{{ p.name }}</span>
                                <span class="plan-price">{{ t('billing_detail_page.price_per_month', { price: p.price_monthly || emDash }) }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_billing_period')" prop="billing_period">
                    <el-select v-model="changePlanForm.billing_period" style="width:160px">
                        <el-option v-for="opt in billingPeriodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_detail_page.new_price')">
                    <el-input-number v-model="changePlanForm.price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item :label="t('billing_detail_page.change_reason')">
                    <el-input v-model="changePlanForm.reason" type="textarea" :rows="2" :placeholder="t('billing_page.ph_optional')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showChangePlan = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleChangePlan" :loading="changingPlan">{{ t('billing_detail_page.confirm_change') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showSuspend" :title="t('billing_detail_page.suspend')" width="450px">
            <el-form :model="suspendForm" label-position="top">
                <el-alert type="warning" :closable="false" class="mb-3">
                    <template #title>{{ t('billing_detail_page.suspend_warning') }}</template>
                </el-alert>
                <el-form-item :label="t('billing_detail_page.suspend_reason')">
                    <el-input v-model="suspendForm.reason" type="textarea" :rows="3" :placeholder="t('billing_detail_page.suspend_reason_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSuspend = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="warning" @click="confirmSuspend" :loading="suspending">{{ t('billing_detail_page.confirm_suspend') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowLeft, Coin, VideoPause, CircleClose, CircleCheck, Refresh, SwitchButton } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import billingApi from '@/api/billing';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const loading = ref(false);
const changingPlan = ref(false);
const suspending = ref(false);
const subscription = ref(null);
const showChangePlan = ref(false);
const showSuspend = ref(false);
const activityLog = ref([]);
const planOptions = ref([]);
const emDash = '—';

const changePlanFormRef = ref(null);
const changePlanForm = reactive({
    plan_slug: '',
    price: 0,
    billing_period: 'monthly',
    reason: '',
});

const suspendForm = reactive({
    reason: '',
});

const STATUS_TYPES = {
    active: 'success',
    canceled: 'warning',
    expired: 'danger',
    grace: 'warning',
    suspended: 'danger',
    trialing: 'primary',
};

const billingPeriodOptions = computed(() => [
    { label: t('billing_page.period_monthly'), value: 'monthly' },
    { label: t('billing_page.period_quarterly'), value: 'quarterly' },
    { label: t('billing_page.period_semi_annually'), value: 'semi_annually' },
    { label: t('billing_page.period_yearly'), value: 'yearly' },
]);

const periodLabels = computed(() => ({
    monthly: t('billing_page.period_monthly'),
    quarterly: t('billing_page.period_quarterly'),
    semi_annually: t('billing_page.period_semi_annually'),
    yearly: t('billing_page.period_yearly'),
}));

const subscriptionStatusLabels = computed(() => ({
    active: t('billing_page.sub_active'),
    grace: t('billing_page.sub_grace'),
    expired: t('billing_page.sub_expired'),
    canceled: t('billing_page.sub_canceled'),
    suspended: t('billing_page.sub_suspended'),
    trialing: t('billing_page.sub_trialing'),
}));

const invoiceStatusLabels = computed(() => ({
    paid: t('billing_page.inv_paid'),
    pending: t('billing_page.inv_pending'),
    cancelled: t('billing_page.inv_cancelled'),
    refunded: t('billing_page.inv_refunded'),
}));

const changePlanRules = computed(() => ({
    plan_slug: [{ required: true, message: t('billing_detail_page.validation.plan_required'), trigger: 'change' }],
    billing_period: [{ required: true, message: t('billing_detail_page.validation.period_required'), trigger: 'change' }],
}));

function statusType(s) { return STATUS_TYPES[s] || 'info'; }
function statusLabel(s) { return subscriptionStatusLabels.value[s] || s; }
function periodLabel(p) { return periodLabels.value[p] || p; }
function invoiceStatusType(s) {
    const map = { paid: 'success', pending: 'warning', cancelled: 'info', refunded: 'danger' };
    return map[s] || 'warning';
}
function invoiceStatusLabel(s) { return invoiceStatusLabels.value[s] || s; }

function formatDate(dateStr) {
    if (!dateStr) return emDash;
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false,
    });
}

function goBack() { router.push('/billing'); }

function onPlanSelect(slug) {
    const plan = planOptions.value.find(p => p.slug === slug);
    if (plan) {
        changePlanForm.price = plan.getPrice ? (plan.getPrice(changePlanForm.billing_period) || plan.price_monthly || 0) : (plan.price_modal || plan.price_monthly || 0);
    }
}

async function loadSubscription() {
    const id = route.params.id;
    if (!id) return;

    loading.value = true;
    try {
        const { data: res } = await billingApi.show(id);
        subscription.value = res.data || null;
    } catch {
        ElMessage.error(t('billing_detail_page.messages.load_failed'));
        subscription.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadPlans() {
    try {
        const { data: res } = await billingApi.getPlans({ is_active: true, per_page: 50 });
        if (res.success) {
            planOptions.value = res.data?.data || res.data || [];
        }
    } catch { /* empty */ }
}

async function loadActivity() {
    try {
        const { data: res } = await apiClient.get('/admin/renewal-dashboard/activity-log', {
            params: { subscription_id: route.params.id, per_page: 20 }
        }).catch(() => ({ data: { success: false } }));
        if (res.success) {
            activityLog.value = res.data || [];
        }
    } catch { /* ignore */ }
}

async function openChangePlan() {
    changePlanForm.plan_slug = subscription.value.plan || '';
    changePlanForm.price = subscription.value.price || 0;
    changePlanForm.billing_period = subscription.value.billing_period || 'monthly';
    changePlanForm.reason = '';
    showChangePlan.value = true;
}

async function handleChangePlan() {
    const valid = await changePlanFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    changingPlan.value = true;
    try {
        await apiClient.put(`/billing/subscriptions/${subscription.value.id}/plan`, {
            plan_slug: changePlanForm.plan_slug,
            price: changePlanForm.price,
            billing_period: changePlanForm.billing_period,
        });
        ElMessage.success(t('billing_detail_page.messages.plan_changed'));
        showChangePlan.value = false;
        loadSubscription();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('billing_detail_page.messages.change_failed'));
    } finally {
        changingPlan.value = false;
    }
}

async function handleCancel() {
    try {
        const { value: reason } = await ElMessageBox.prompt(
            t('billing_detail_page.cancel.message'),
            t('billing_detail_page.cancel.title'),
            {
                confirmButtonText: t('billing_detail_page.cancel.confirm'),
                cancelButtonText: t('billing_detail_page.cancel.dismiss'),
                inputPlaceholder: t('billing_detail_page.cancel.reason_ph'),
                inputType: 'textarea',
                type: 'warning',
            }
        );
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/cancel`, {
            reason: reason || t('billing_detail_page.cancel.admin_reason'),
        });
        ElMessage.success(t('billing_page.subscription_canceled'));
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleResume() {
    try {
        let msg = t('billing_detail_page.resume.generic');
        if (subscription.value.status === 'canceled') {
            msg = t('billing_detail_page.resume.from_canceled');
        } else if (subscription.value.status === 'suspended') {
            msg = t('billing_detail_page.resume.from_suspended');
        }

        await ElMessageBox.confirm(msg, t('billing_detail_page.resume.title'), {
            confirmButtonText: t('billing_detail_page.resume.confirm'),
            cancelButtonText: t('actions.cancel'),
            type: 'info',
        });
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/resume`);
        ElMessage.success(t('billing_page.subscription_resumed'));
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleRenew() {
    try {
        const amount = subscription.value.price || 0;
        await ElMessageBox.confirm(
            t('billing_detail_page.renew.message', { amount }),
            t('billing_detail_page.renew.title'),
            {
                confirmButtonText: t('billing_detail_page.renew.confirm'),
                cancelButtonText: t('actions.cancel'),
                type: 'info',
            }
        );
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/renew`);
        ElMessage.success(t('billing_detail_page.messages.renew_success'));
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleSuspend() {
    showSuspend.value = true;
}

async function confirmSuspend() {
    suspending.value = true;
    try {
        await billingApi.suspend(subscription.value.id);
        ElMessage.success(t('billing_detail_page.messages.suspended'));
        showSuspend.value = false;
        suspendForm.reason = '';
        loadSubscription();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('billing_detail_page.messages.suspend_failed'));
    } finally {
        suspending.value = false;
    }
}

async function showToggleAutoRenew() {
    const current = subscription.value.auto_renew;
    const action = current
        ? t('billing_detail_page.auto_renew.action_off')
        : t('billing_detail_page.auto_renew.action_on');
    try {
        if (current) {
            await ElMessageBox.confirm(
                t('billing_detail_page.auto_renew.disable_warning'),
                t('billing_detail_page.auto_renew.confirm_title', { action }),
                {
                    confirmButtonText: t('billing_detail_page.auto_renew.confirm_btn', { action }),
                    cancelButtonText: t('actions.cancel'),
                    type: 'warning',
                }
            );
        }
        if (current) {
            await apiClient.post(`/billing/subscriptions/${subscription.value.id}/cancel`, {
                reason: t('billing_detail_page.auto_renew.admin_disable_reason'),
            });
        } else {
            await apiClient.post(`/billing/subscriptions/${subscription.value.id}/resume`);
        }
        ElMessage.success(t('billing_detail_page.auto_renew.success', { action }));
        loadSubscription();
    } catch { /* cancelled */ }
}

function viewInvoice(invoice) {
    ElMessageBox.alert(
        `<div style="font-size:14px">
            <p><b>${t('billing_page.col_invoice_no')}:</b> ${invoice.invoice_no}</p>
            <p><b>${t('billing_page.col_amount')}:</b> ¥${invoice.amount}</p>
            <p><b>${t('billing_page.col_status')}:</b> ${invoiceStatusLabel(invoice.status)}</p>
            <p><b>${t('billing_page.col_reason')}:</b> ${invoice.billing_reason || emDash}</p>
            <p><b>${t('billing_page.col_created')}:</b> ${formatDate(invoice.created_at)}</p>
            <p><b>${t('billing_page.col_paid_at')}:</b> ${invoice.paid_at ? formatDate(invoice.paid_at) : emDash}</p>
        </div>`,
        t('billing_page.dialog_invoice_detail'),
        { dangerouslyUseHTMLString: true, confirmButtonText: t('actions.close') }
    );
}

onMounted(() => {
    loadSubscription();
    loadPlans();
    loadActivity();
});
</script>

<style scoped>
.billing-detail-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 8px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-right {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.ml-2 { margin-left: 8px; }
.ml-1 { margin-left: 4px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.header-tags {
    display: flex;
    align-items: center;
    gap: 4px;
}

.price {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    color: #0f172a;
}

.link {
    color: #0f172a;
    text-decoration: none;
}
.link:hover {
    text-decoration: underline;
}

.plan-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
.plan-price {
    font-size: 12px;
    color: #909399;
}

:deep(.el-card__body) { padding: 16px; }

.is-active {
    color: #0f172a;
}
</style>
