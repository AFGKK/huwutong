<template>
    <div class="earnings-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <el-button v-if="!account" type="primary" @click="initAccount" :loading="initLoading">{{ t(`${P}.init_account`) }}</el-button>
        </div>

        <template v-if="account">
            <el-row :gutter="16" class="mb-4">
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value success">¥{{ fmt(account.available_balance) }}</div><div class="stat-label">{{ t(`${P}.stats.available_balance`) }}</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value warning">¥{{ fmt(account.pending_balance) }}</div><div class="stat-label">{{ t(`${P}.stats.pending_balance`) }}</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value">¥{{ fmt(account.total_withdrawn) }}</div><div class="stat-label">{{ t(`${P}.stats.total_withdrawn`) }}</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value primary">¥{{ fmt(devEarnings?.total_gross) }}</div><div class="stat-label">{{ t(`${P}.stats.total_gross`) }}</div></el-card>
                </el-col>
            </el-row>

            <el-card shadow="never">
                <el-tabs v-model="activeTab">
                    <el-tab-pane :label="t(`${P}.tabs.earnings`)" name="earnings">
                        <el-table :data="devEarnings?.earnings_by_app || []" stripe>
                            <el-table-column :label="t(`${P}.cols.app`)" prop="name" min-width="180" />
                            <el-table-column :label="t(`${P}.cols.price`)" width="100"><template #default="{ row }">¥{{ fmt(row.price) }}</template></el-table-column>
                            <el-table-column :label="t(`${P}.cols.install_count`)" width="100" prop="install_count" align="center" />
                            <el-table-column :label="t(`${P}.cols.gross`)" width="130"><template #default="{ row }">¥{{ fmt(row.gross) }}</template></el-table-column>
                            <el-table-column :label="t(`${P}.cols.platform_fee`)" width="100"><template #default="{ row }">{{ platformFee }}%</template></el-table-column>
                            <el-table-column :label="t(`${P}.cols.net`)" width="120"><template #default="{ row }">¥{{ fmt(row.net) }}</template></el-table-column>
                        </el-table>
                        <el-empty v-if="!devEarnings?.earnings_by_app?.length" :description="t(`${P}.empty.no_paid_apps`)" :image-size="50" />
                    </el-tab-pane>

                    <el-tab-pane :label="t(`${P}.tabs.withdrawals`)" name="withdrawals">
                        <div class="toolbar">
                            <el-button type="primary" @click="showWithdrawDialog"><el-icon><Plus /></el-icon> {{ t(`${P}.buttons.request_withdraw`) }}</el-button>
                        </div>
                        <el-table :data="withdrawals" v-loading="wdLoading" stripe>
                            <el-table-column :label="t(`${W}.cols.amount`)" width="120"><template #default="{ row }">¥{{ fmt(row.amount) }}</template></el-table-column>
                            <el-table-column :label="t(`${W}.cols.fee`)" width="80"><template #default="{ row }">¥{{ fmt(row.fee) }}</template></el-table-column>
                            <el-table-column :label="t(`${W}.cols.net_amount`)" width="120"><template #default="{ row }">¥{{ fmt(row.net_amount) }}</template></el-table-column>
                            <el-table-column :label="t(`${W}.cols.channel`)" width="80"><template #default="{ row }">{{ channelLabel(row.channel) }}</template></el-table-column>
                            <el-table-column :label="t(`${W}.cols.status`)" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="wdStatusTag(row.status)" size="small">{{ wdStatusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${W}.cols.created_at`)" width="160"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                        </el-table>
                        <el-empty v-if="!withdrawals.length && !wdLoading" :description="t(`${P}.empty.no_withdrawals`)" :image-size="50" />
                    </el-tab-pane>

                    <el-tab-pane :label="t(`${P}.tabs.tax`)" name="tax">
                        <el-form :model="taxForm" label-width="120px" style="max-width:500px">
                            <el-form-item :label="t(`${P}.form.tax_id`)">
                                <el-input v-model="taxForm.tax_id" :placeholder="t(`${P}.form.tax_id_ph`)" />
                            </el-form-item>
                            <el-form-item :label="t(`${P}.form.tax_type`)">
                                <el-select v-model="taxForm.tax_type" style="width:100%">
                                    <el-option v-for="opt in taxTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t(`${P}.form.legal_name`)">
                                <el-input v-model="taxForm.company_name" :placeholder="t(`${P}.form.legal_name_ph`)" />
                            </el-form-item>
                            <el-form-item :label="t(`${P}.form.address`)">
                                <el-input v-model="taxForm.address" type="textarea" :rows="2" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :loading="taxSaving" @click="saveTaxInfo">{{ t('actions.save') }}</el-button>
                            </el-form-item>
                        </el-form>
                    </el-tab-pane>
                </el-tabs>
            </el-card>
        </template>

        <el-dialog v-model="wdVisible" :title="t(`${P}.dialogs.withdraw_title`)" width="450px">
            <el-form :model="wdForm" label-width="100px">
                <el-form-item :label="t(`${P}.form.available_balance`)">
                    <span class="text-muted">¥{{ fmt(account?.available_balance) }}</span>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.withdraw_amount`)" required>
                    <el-input-number v-model="wdForm.amount" :min="1" :max="account?.available_balance || 0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.payout_channel`)" required>
                    <el-select v-model="wdForm.channel" style="width:100%">
                        <el-option v-for="opt in channelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.payout_account`)" required>
                    <el-input v-model="wdForm.account" :placeholder="payoutAccountPlaceholder" />
                </el-form-item>
                <el-form-item v-if="wdForm.channel === 'bank'" :label="t(`${P}.form.bank_name`)">
                    <el-input v-model="wdForm.bank_name" :placeholder="t(`${P}.form.bank_name_ph`)" />
                </el-form-item>
                <el-form-item v-if="wdForm.channel === 'bank'" :label="t(`${P}.form.account_name`)">
                    <el-input v-model="wdForm.account_name" :placeholder="t(`${P}.form.account_name_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="wdVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="wdSubmitting" @click="submitWithdraw">{{ t(`${P}.buttons.submit_withdraw`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';

const P = 'developer_earnings_page';
const W = 'withdrawal_page';
const { t, locale } = useI18n();

const activeTab = ref('earnings');
const account = ref(null);
const devEarnings = ref(null);
const initLoading = ref(false);
const withdrawals = ref([]);
const wdLoading = ref(false);
const wdVisible = ref(false);
const wdSubmitting = ref(false);
const wdForm = reactive({ amount: 100, channel: 'alipay', account: '', bank_name: '', account_name: '', account_no: '' });
const taxForm = reactive({ tax_id: '', tax_type: 'individual', company_name: '', address: '' });
const taxSaving = ref(false);

const platformFee = computed(() => 20);

const CHANNEL_KEYS = ['alipay', 'wechat', 'bank', 'paypal'];
const WD_STATUS_KEYS = ['pending_review', 'pending', 'processing', 'completed', 'failed', 'rejected', 'cancelled'];

const channelOptions = computed(() =>
    CHANNEL_KEYS.map((value) => ({ value, label: t(`${W}.channel.${value}`) })),
);

const channelLabels = computed(() =>
    Object.fromEntries(CHANNEL_KEYS.map((key) => [key, t(`${W}.channel.${key}`)])),
);

const wdStatusLabels = computed(() =>
    Object.fromEntries(WD_STATUS_KEYS.map((key) => [key, t(`${W}.status.${key}`)])),
);

const taxTypeOptions = computed(() => [
    { value: 'enterprise', label: t(`${P}.form.tax_type_enterprise`) },
    { value: 'individual', label: t(`${P}.form.tax_type_individual`) },
]);

const payoutAccountPlaceholder = computed(() => {
    const ch = wdForm.channel;
    if (ch === 'alipay') return t(`${P}.form.alipay_account_ph`);
    if (ch === 'wechat') return t(`${P}.form.wechat_account_ph`);
    if (ch === 'paypal') return t(`${P}.form.paypal_account_ph`);
    return t(`${P}.form.bank_account_ph`);
});

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

function fmt(v) { return (v || 0).toLocaleString(dateLocale.value, { minimumFractionDigits: 2 }); }
function fmtDate(d) { if (!d) return '-'; return new Date(d).toLocaleString(dateLocale.value, { year: 'numeric', month: '2-digit', day: '2-digit' }); }
function channelLabel(c) { return channelLabels.value[c] || c; }
function wdStatusTag(s) { return { pending_review: 'warning', pending: 'warning', processing: 'primary', completed: 'success', failed: 'danger', rejected: 'danger', cancelled: 'info' }[s] || ''; }
function wdStatusLabel(s) { return wdStatusLabels.value[s] || s; }

async function loadEarnings() {
    try { const { data: r } = await api.myEarnings(); if (r.success) { devEarnings.value = r.data; account.value = r.data?.account; } }
    catch {}
}

async function loadWithdrawals() {
    wdLoading.value = true;
    try { const { data: r } = await api.myWithdrawals({ per_page: 50 }); withdrawals.value = r.data?.data || r.data || []; }
    catch {}
    finally { wdLoading.value = false; }
}

async function initAccount() {
    initLoading.value = true;
    try { const { data: r } = await api.initEarnings(); if (r.success) { ElMessage.success(t(`${P}.messages.account_initialized`)); loadEarnings(); } }
    catch {} finally { initLoading.value = false; }
}

function showWithdrawDialog() { wdVisible.value = true; }

async function submitWithdraw() {
    wdSubmitting.value = true;
    try {
        const { data: r } = await api.requestWithdrawal({
            amount: wdForm.amount, channel: wdForm.channel,
            account: wdForm.account, bank_name: wdForm.bank_name || null,
            account_name: wdForm.account_name || null,
            account_no: wdForm.account_no || null,
        });
        if (r.success) { ElMessage.success(t(`${P}.messages.withdraw_submitted`)); wdVisible.value = false; loadWithdrawals(); loadEarnings(); }
    } catch {} finally { wdSubmitting.value = false; }
}

async function saveTaxInfo() {
    taxSaving.value = true;
    try {
        await api.updateTaxInfo(taxForm);
        ElMessage.success(t(`${P}.messages.tax_updated`));
    } catch {} finally { taxSaving.value = false; }
}

onMounted(() => { loadEarnings(); loadWithdrawals(); });
</script>

<style scoped>
.earnings-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.stat-value { font-size: 22px; font-weight: 600; color: #303133; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.primary { color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
