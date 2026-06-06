<template>
    <div class="portal-payment-methods">
        <div class="page-header">
            <div>
                <h2>支付方式管理</h2>
                <p class="text-muted">管理您的支付方式，用于自动续费和账单支付。</p>
            </div>
            <el-button type="primary" @click="showAddDialog = true" :icon="Plus">
                添加支付方式
            </el-button>
        </div>

        <el-row :gutter="16">
            <el-col :span="16">
                <!-- 已保存的支付方式 -->
                <el-card shadow="never">
                    <template #header>
                        <span>已保存的支付方式</span>
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
                                        <el-tag v-if="pm.is_default" size="small" type="primary" class="default-tag">默认</el-tag>
                                    </div>
                                    <div class="pm-detail">
                                        {{ pmDetail(pm) }}
                                    </div>
                                    <div class="pm-expiry" v-if="pm.expires_at">
                                        有效期至 {{ pm.expires_at }}
                                        <span v-if="isExpiringSoon(pm.expires_at)" class="expiry-warning">即将过期</span>
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
                                    设为默认
                                </el-button>
                                <el-button
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDelete(pm)"
                                >
                                    删除
                                </el-button>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else description="暂无支付方式" :image-size="80">
                        <p class="empty-hint">添加支付方式后，系统将使用它进行自动续费扣款。</p>
                    </el-empty>
                </el-card>
            </el-col>

            <el-col :span="8">
                <!-- 默认支付方式概览 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>自动续费设置</span>
                    </template>
                    <div class="auto-renewal-info">
                        <div class="info-row">
                            <span class="label">默认支付方式</span>
                            <span class="value" v-if="defaultPM">{{ maskCard(defaultPM) }}</span>
                            <span class="value text-muted" v-else>未设置</span>
                        </div>
                        <div class="info-row">
                            <span class="label">自动续费状态</span>
                            <el-tag :type="autoRenewalEnabled ? 'success' : 'info'" size="small">
                                {{ autoRenewalEnabled ? '已开启' : '未开启' }}
                            </el-tag>
                        </div>
                        <el-divider />
                        <p class="renewal-note">
                            开启后，系统将在订阅到期前自动使用默认支付方式扣款。扣款失败会发送通知提醒。
                        </p>
                        <el-button
                            :type="autoRenewalEnabled ? 'default' : 'primary'"
                            class="w-full"
                            @click="toggleAutoRenewal"
                        >
                            {{ autoRenewalEnabled ? '关闭自动续费' : '开启自动续费' }}
                        </el-button>
                    </div>
                </el-card>

                <!-- 安全提示 -->
                <el-card>
                    <template #header>
                        <span>安全信息</span>
                    </template>
                    <div class="security-info">
                        <el-icon :size="20" color="#67c23a"><Lock /></el-icon>
                        <p>您的支付信息通过加密存储，我们不会保存完整的卡号信息。</p>
                        <el-icon :size="20" color="#409eff" class="mt-2"><InfoFilled /></el-icon>
                        <p>删除支付方式前，请确保没有未完成的自动续费订单。</p>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 添加支付方式对话框 -->
        <el-dialog v-model="showAddDialog" title="添加支付方式" width="500px">
            <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-position="top">
                <el-form-item label="卡号" prop="card_number">
                    <el-input
                        v-model="addForm.card_number"
                        placeholder="请输入卡号"
                        maxlength="19"
                        @input="formatCardNumber"
                    />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="有效期" prop="expiry">
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
                <el-form-item label="持卡人姓名" prop="cardholder_name">
                    <el-input v-model="addForm.cardholder_name" placeholder="请输入持卡人姓名" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="addForm.set_default">设为默认支付方式</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">取消</el-button>
                <el-button type="primary" @click="handleAdd" :loading="adding">确认添加</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Plus, CreditCard, Wallet, Money, Lock, InfoFilled,
} from '@element-plus/icons-vue';

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

const addRules = {
    card_number: [
        { required: true, message: '请输入卡号', trigger: 'blur' },
        { min: 13, message: '卡号格式不正确', trigger: 'blur' },
    ],
    expiry: [
        { required: true, message: '请输入有效期', trigger: 'blur' },
        { pattern: /^\d{2}\/\d{2}$/, message: '格式: MM/YY', trigger: 'blur' },
    ],
    cvv: [
        { required: true, message: '请输入 CVV', trigger: 'blur' },
        { pattern: /^\d{3,4}$/, message: 'CVV 格式不正确', trigger: 'blur' },
    ],
    cardholder_name: [
        { required: true, message: '请输入持卡人姓名', trigger: 'blur' },
    ],
};

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
    const map = { card: '#409eff', alipay: '#1677ff', wechat: '#07c160', paypal: '#003087' };
    return map[type] || '#909399';
}

function pmName(pm) {
    if (pm.type === 'card') {
        const brands = { visa: 'Visa', mastercard: 'Mastercard', amex: 'American Express', discover: 'Discover', unionpay: '银联' };
        return brands[pm.brand] || pm.brand || '银行卡';
    }
    return pm.type_label || pm.type || '未知';
}

function pmDetail(pm) {
    if (pm.masked_number) return pm.masked_number;
    if (pm.last_four) return `**** **** **** ${pm.last_four}`;
    return '';
}

function maskCard(pm) {
    if (!pm) return '未设置';
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
        ElMessage.success('支付方式添加成功');
        showAddDialog.value = false;
        addForm.value = { card_number: '', expiry: '', cvv: '', cardholder_name: '', set_default: true };
        await fetchPaymentMethods();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '添加失败');
    } finally {
        adding.value = false;
    }
}

async function handleSetDefault(pm) {
    try {
        await apiClient.post(`/billing/payment-methods/${pm.id}/default`);
        ElMessage.success('已设为默认支付方式');
        await fetchPaymentMethods();
    } catch {
        ElMessage.error('设置失败');
    }
}

async function handleDelete(pm) {
    try {
        await ElMessageBox.confirm(
            `确定要删除此支付方式吗？`,
            '确认删除',
            { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' }
        );
        await apiClient.delete(`/billing/payment-methods/${pm.id}`);
        ElMessage.success('支付方式已删除');
        await fetchPaymentMethods();
    } catch {
        // cancelled
    }
}

async function toggleAutoRenewal() {
    try {
        await apiClient.post('/billing/auto-renewal', { enabled: !autoRenewalEnabled.value });
        autoRenewalEnabled.value = !autoRenewalEnabled.value;
        ElMessage.success(autoRenewalEnabled.value ? '自动续费已开启' : '自动续费已关闭');
    } catch {
        ElMessage.error('操作失败');
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
    border-color: #409eff;
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.1);
}

.payment-card.default {
    background: linear-gradient(135deg, #ecf5ff 0%, #f0f9ff 100%);
    border-color: #409eff;
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
    background: #ecf5ff;
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
