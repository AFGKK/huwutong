<template>
    <div class="portal-payment-methods">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.pm_title') }}</h2>
                <p class="text-muted">{{ $t('portal.pm_subtitle') }}</p>
            </div>
            <el-button type="primary" @click="showAddDialog = true" :icon="Plus">
                {{ $t('portal.add_pm') }}
            </el-button>
        </div>

        <el-row :gutter="16">
            <el-col :span="16">
                <!-- 已保存的支付方式 -->
                <el-card shadow="never">
                    <template #header>
                        <span>{{ $t('portal.saved_pm') }}</span>
                    </template>

                    <div v-if="paymentMethods.length" class="payment-list">
                        <div
                            v-for="pm in paymentMethods"
                            :key="pm.id"
                            class="payment-card"
                            :class="{ default: pm.is_default }"
                        >
                            <div class="pm-left">
                                <div class="pm-icon">
                                    <el-icon :size="32" :color="pmIconColor(pm.type)">
                                        <CreditCard v-if="pm.type === 'card' || !pm.type" />
                                        <Wallet v-else-if="pm.type === 'alipay'" />
                                        <Money v-else />
                                    </el-icon>
                                </div>
                                <div class="pm-info">
                                    <div class="pm-name">
                                        {{ pmName(pm) }}
                                        <el-tag v-if="pm.is_default" size="small" type="primary" class="default-tag">{{ $t('portal.default_tag') }}</el-tag>
                                    </div>
                                    <div class="pm-detail">
                                        {{ pmDetail(pm) }}
                                    </div>
                                    <div class="pm-expiry" v-if="pm.expires_at">
                                        {{ $t('portal.expires_on', { date: pm.expires_at }) }}
                                        <span v-if="isExpiringSoon(pm.expires_at)" class="expiry-warning">{{ $t('portal.expiring_soon_tag') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pm-actions">
                                <el-button
                                    v-if="!pm.is_default"
                                    text
                                    size="small"
                                    type="primary"
                                    @click="handleSetDefault(pm)"
                                >
                                    {{ $t('portal.set_default') }}
                                </el-button>
                                <el-button
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDelete(pm)"
                                >
                                    {{ $t('actions.delete') }}
                                </el-button>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('portal.no_pm')" :image-size="80">
                        <p class="empty-hint">{{ $t('portal.no_pm_hint') }}</p>
                    </el-empty>
                </el-card>
            </el-col>

            <el-col :span="8">
                <!-- 默认支付方式概览 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.auto_renewal') }}</span>
                    </template>
                    <div class="auto-renewal-info">
                        <div class="info-row">
                            <span class="label">{{ $t('portal.default_pm') }}</span>
                            <span class="value" v-if="defaultPM">{{ maskCard(defaultPM) }}</span>
                            <span class="value text-muted" v-else>{{ $t('portal.not_set') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">{{ $t('portal.auto_renewal_status') }}</span>
                            <el-tag :type="autoRenewalEnabled ? 'success' : 'info'" size="small">
                                {{ autoRenewalEnabled ? $t('portal.enabled') : $t('portal.disabled') }}
                            </el-tag>
                        </div>
                        <el-divider />
                        <p class="renewal-note">
                            {{ $t('portal.auto_renewal_note') }}
                        </p>
                        <el-button
                            :type="autoRenewalEnabled ? 'default' : 'primary'"
                            class="w-full"
                            @click="toggleAutoRenewal"
                        >
                            {{ autoRenewalEnabled ? $t('portal.disable_auto_renewal') : $t('portal.enable_auto_renewal') }}
                        </el-button>
                    </div>
                </el-card>

                <!-- 安全提示 -->
                <el-card>
                    <template #header>
                        <span>{{ $t('portal.security_info') }}</span>
                    </template>
                    <div class="security-info">
                        <el-icon :size="20" color="#67c23a"><Lock /></el-icon>
                        <p>{{ $t('portal.security_encrypt') }}</p>
                        <el-icon :size="20" color="#0f172a" class="mt-2"><InfoFilled /></el-icon>
                        <p>{{ $t('portal.security_delete_hint') }}</p>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 添加支付方式对话框 -->
        <el-dialog v-model="showAddDialog" :title="$t('portal.add_pm')" width="500px">
            <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-position="top">
                <el-form-item :label="$t('portal.card_number')" prop="card_number">
                    <el-input
                        v-model="addForm.card_number"
                        :placeholder="$t('portal.card_number_ph')"
                        maxlength="19"
                        @input="formatCardNumber"
                    />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('portal.card_expiry')" prop="expiry">
                            <el-input
                                v-model="addForm.expiry"
                                placeholder="MM/YY"
                                maxlength="5"
                                @input="formatExpiry"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="CVV" prop="cvv">
                            <el-input
                                v-model="addForm.cvv"
                                placeholder="CVV"
                                maxlength="4"
                                show-password
                                type="password"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="$t('portal.cardholder')" prop="cardholder_name">
                    <el-input v-model="addForm.cardholder_name" :placeholder="$t('portal.cardholder_ph')" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="addForm.set_default">{{ $t('portal.set_default_pm') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAdd" :loading="adding">{{ $t('portal.confirm_add') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import apiClient from '@/api/client';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Plus, CreditCard, Wallet, Money, Lock, InfoFilled,
} from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const adding = ref(false);
const showAddDialog = ref(false);
const addFormRef = ref(null);
const paymentMethods = ref([]);
const autoRenewalEnabled = ref(false);

const defaultPM = computed(() => paymentMethods.value.find(pm => pm.is_default));

const addForm = ref({
    card_number: '',
    expiry: '',
    cvv: '',
    cardholder_name: '',
    set_default: true,
});

const addRules = computed(() => ({
    card_number: [
        { required: true, message: t('portal.card_required'), trigger: 'blur' },
        { min: 13, message: t('portal.card_invalid'), trigger: 'blur' },
    ],
    expiry: [
        { required: true, message: t('portal.expiry_required'), trigger: 'blur' },
        { pattern: /^\d{2}\/\d{2}$/, message: t('portal.expiry_format'), trigger: 'blur' },
    ],
    cvv: [
        { required: true, message: t('portal.cvv_required'), trigger: 'blur' },
        { pattern: /^\d{3,4}$/, message: t('portal.cvv_invalid'), trigger: 'blur' },
    ],
    cardholder_name: [
        { required: true, message: t('portal.cardholder_required'), trigger: 'blur' },
    ],
}));

function formatCardNumber() {
    addForm.value.card_number = addForm.value.card_number
        .replace(/\D/g, '')
        .replace(/(\d{4})(?=\d)/g, '$1 ');
}

function formatExpiry() {
    addForm.value.expiry = addForm.value.expiry
        .replace(/\D/g, '')
        .replace(/(\d{2})(?=\d)/, '$1/')
        .substring(0, 5);
}

function pmIconColor(type) {
    const map = { card: '#0f172a', alipay: '#1677ff', wechat: '#07c160', paypal: '#003087' };
    return map[type] || '#909399';
}

function pmName(pm) {
    if (pm.type === 'card') {
        const brands = { visa: 'Visa', mastercard: 'Mastercard', amex: 'American Express', discover: 'Discover', unionpay: t('portal.unionpay') };
        return brands[pm.brand] || pm.brand || t('portal.bank_card');
    }
    return pm.type_label || pm.type || t('portal.unknown');
}

function pmDetail(pm) {
    if (pm.masked_number) return pm.masked_number;
    if (pm.last_four) return `**** **** **** ${pm.last_four}`;
    return '';
}

function maskCard(pm) {
    if (!pm) return t('portal.not_set');
    return pmDetail(pm);
}

function isExpiringSoon(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    return diff < 90 * 24 * 60 * 60 * 1000;
}

async function fetchPaymentMethods() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/billing/payment-methods');
        paymentMethods.value = res.data || [];
    } catch {
        paymentMethods.value = [];
    } finally {
        loading.value = false;
    }
}

async function fetchAutoRenewalStatus() {
    try {
        const { data: res } = await apiClient.get('/billing/auto-renewal');
        autoRenewalEnabled.value = res.data?.enabled ?? false;
    } catch {
        // ignore
    }
}

async function handleAdd() {
    const valid = await addFormRef.value.validate().catch(() => false);
    if (!valid) return;

    adding.value = true;
    try {
        const payload = {
            card_number: addForm.value.card_number.replace(/\s/g, ''),
            expiry: addForm.value.expiry,
            cvv: addForm.value.cvv,
            cardholder_name: addForm.value.cardholder_name,
            set_default: addForm.value.set_default,
        };
        await apiClient.post('/billing/payment-methods', payload);
        ElMessage.success(t('portal.pm_added'));
        showAddDialog.value = false;
        addForm.value = { card_number: '', expiry: '', cvv: '', cardholder_name: '', set_default: true };
        await fetchPaymentMethods();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.add_failed'));
    } finally {
        adding.value = false;
    }
}

async function handleSetDefault(pm) {
    try {
        await apiClient.post(`/billing/payment-methods/${pm.id}/default`);
        ElMessage.success(t('portal.set_default_ok'));
        await fetchPaymentMethods();
    } catch {
        ElMessage.error(t('portal.set_failed'));
    }
}

async function handleDelete(pm) {
    try {
        await ElMessageBox.confirm(
            t('portal.delete_pm_confirm'),
            t('portal.confirm_delete'),
            { confirmButtonText: t('portal.confirm_delete_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await apiClient.delete(`/billing/payment-methods/${pm.id}`);
        ElMessage.success(t('portal.pm_deleted'));
        await fetchPaymentMethods();
    } catch {
        // cancelled
    }
}

async function toggleAutoRenewal() {
    try {
        await apiClient.post('/billing/auto-renewal', { enabled: !autoRenewalEnabled.value });
        autoRenewalEnabled.value = !autoRenewalEnabled.value;
        ElMessage.success(autoRenewalEnabled.value ? t('portal.auto_renewal_on') : t('portal.auto_renewal_off'));
    } catch {
        ElMessage.error(t('messages.failed'));
    }
}

onMounted(() => {
    fetchPaymentMethods();
    fetchAutoRenewalStatus();
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0 0 4px; }

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }

.payment-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    transition: all 0.2s;
}

.payment-card:hover {
    border-color: #0f172a;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1);
}

.payment-card.default {
    background: linear-gradient(135deg, #f1f5f9 0%, #f0f9ff 100%);
    border-color: #0f172a;
}

.pm-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.pm-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
    border-radius: 8px;
}

.payment-card.default .pm-icon {
    background: #f1f5f9;
}

.pm-name {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pm-detail {
    font-size: 14px;
    color: #606266;
    margin-bottom: 2px;
}

.pm-expiry {
    font-size: 12px;
    color: #909399;
}

.expiry-warning {
    color: #e6a23c;
    margin-left: 8px;
    font-weight: 500;
}

.pm-actions {
    display: flex;
    gap: 8px;
}

.default-tag {
    font-size: 11px;
}

.empty-hint {
    color: #909399;
    font-size: 13px;
    margin-top: 8px;
}

.auto-renewal-info .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 14px;
}

.auto-renewal-info .label {
    color: #909399;
}

.auto-renewal-info .value {
    font-weight: 500;
    color: #303133;
}

.renewal-note {
    font-size: 13px;
    color: #909399;
    line-height: 1.5;
    margin: 0 0 12px;
}

.security-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
}

.security-info p {
    font-size: 13px;
    color: #909399;
    margin: 0;
    line-height: 1.5;
}

.w-full { width: 100%; }
</style>
