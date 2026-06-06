<template>
    <div class="billing-detail-page" v-loading="loading">
        <div class="page-header">
            <div class="header-left">
                <el-button text @click="goBack">
                    <el-icon><ArrowLeft /></el-icon>
                    返回
                </el-button>
                <h2>订阅详情</h2>
            </div>
            <div class="header-right" v-if="subscription">
                <el-button
                    v-if="subscription.status === 'active'"
                    type="warning"
                    plain
                    @click="showChangePlan = true"
                >
                    <el-icon><Coin /></el-icon>
                    变更套餐
                </el-button>
                <el-button
                    v-if="subscription.status === 'active' || subscription.status === 'grace'"
                    type="danger"
                    plain
                    @click="handleCancel"
                >
                    <el-icon><CircleClose /></el-icon>
                    取消订阅
                </el-button>
                <el-button
                    v-if="subscription.status === 'canceled'"
                    type="primary"
                    @click="handleResume"
                >
                    <el-icon><CircleCheck /></el-icon>
                    恢复订阅
                </el-button>
                <el-button
                    v-if="subscription.status === 'active' && subscription.auto_renew"
                    type="success"
                    @click="handleRenew"
                >
                    <el-icon><Refresh /></el-icon>
                    手动续费
                </el-button>
            </div>
        </div>

        <div v-if="subscription">
            <!-- 基本信息 -->
            <el-card shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>基本信息</span>
                        <el-tag :type="statusType(subscription.status)" size="large">
                            {{ statusLabel(subscription.status) }}
                        </el-tag>
                    </div>
                </template>

                <el-descriptions :column="3" border>
                    <el-descriptions-item label="订阅 ID">
                        #{{ subscription.id }}
                    </el-descriptions-item>
                    <el-descriptions-item label="客户">
                        {{ subscription.customer?.name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="客户邮箱">
                        {{ subscription.customer?.email || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="产品">
                        {{ subscription.product?.name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="套餐名称">
                        {{ subscription.plan || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="计费周期">
                        {{ periodLabel(subscription.billing_period) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="金额">
                        <span class="font-mono">¥{{ subscription.price }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="货币">
                        {{ subscription.currency || 'CNY' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="自动续费">
                        <el-tag :type="subscription.auto_renew ? 'success' : 'info'" size="small">
                            {{ subscription.auto_renew ? '开启' : '关闭' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="试用天数">
                        {{ subscription.trial_days || 0 }} 天
                    </el-descriptions-item>
                    <el-descriptions-item label="宽限期">
                        {{ subscription.grace_days || 7 }} 天
                    </el-descriptions-item>
                    <el-descriptions-item label="当前周期开始">
                        {{ formatDate(subscription.current_period_start) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="当前周期结束">
                        {{ formatDate(subscription.current_period_end) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="关联 License">
                        <el-tag v-if="subscription.license_id" size="small" effect="plain">
                            #{{ subscription.license_id }}
                        </el-tag>
                        <span v-else>-</span>
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- 最近发票 -->
            <el-card shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>最近发票</span>
                    </div>
                </template>

                <el-table :data="subscription.invoices || []" stripe size="small">
                    <el-table-column prop="invoice_no" label="发票号" width="180" />
                    <el-table-column label="金额" width="100">
                        <template #default="{ row }">
                            ¥{{ row.amount }}
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">
                                {{ row.status === 'paid' ? '已支付' : '待支付' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="创建时间" width="180">
                        <template #default="{ row }">
                            {{ formatDate(row.created_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="80">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="viewInvoice(row)">详情</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-if="!subscription.invoices?.length" :image-size="60" description="暂无发票记录" />
            </el-card>
        </div>

        <!-- 变更套餐 Dialog -->
        <el-dialog v-model="showChangePlan" title="变更套餐" width="480px">
            <el-form ref="changePlanFormRef" :model="changePlanForm" :rules="changePlanRules" label-position="top">
                <el-form-item label="当前套餐">
                    <el-tag>{{ subscription?.plan }}</el-tag>
                    <span style="margin-left: 8px;">¥{{ subscription?.price }} / {{ periodLabel(subscription?.billing_period) }}</span>
                </el-form-item>
                <el-divider />
                <el-form-item label="新套餐名称" prop="plan">
                    <el-input v-model="changePlanForm.plan" placeholder="如：Pro、Enterprise" />
                </el-form-item>
                <el-form-item label="新价格" prop="price">
                    <el-input-number v-model="changePlanForm.price" :min="0" :precision="2" style="width: 200px" />
                </el-form-item>
                <el-form-item label="计费周期">
                    <el-select v-model="changePlanForm.billing_period" style="width: 160px">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="半年付" value="semi_annually" />
                        <el-option label="年付" value="yearly" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showChangePlan = false">取消</el-button>
                <el-button type="primary" @click="handleChangePlan" :loading="changingPlan">确认变更</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowLeft, Coin, CircleClose, CircleCheck, Refresh } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const changingPlan = ref(false);
const subscription = ref(null);
const showChangePlan = ref(false);

const changePlanFormRef = ref(null);
const changePlanForm = reactive({
    plan: '',
    price: 0,
    billing_period: 'monthly',
});
const changePlanRules = {
    plan: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
    price: [{ required: true, type: 'number', min: 0, message: '请输入有效价格', trigger: 'blur' }],
};

const STATUS_MAP = {
    active: { type: 'success', label: '活跃' },
    canceled: { type: 'warning', label: '已取消' },
    expired: { type: 'danger', label: '已过期' },
    grace: { type: 'warning', label: '宽限期' },
    suspended: { type: 'danger', label: '已暂停' },
    trialing: { type: 'primary', label: '试用中' },
};

function statusType(s) { return STATUS_MAP[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP[s]?.label || s; }

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function periodLabel(p) {
    const map = { monthly: '月付', quarterly: '季付', semi_annually: '半年付', yearly: '年付' };
    return map[p] || p;
}

function goBack() {
    router.push('/billing');
}

async function loadSubscription() {
    const id = route.params.id;
    if (!id) return;

    loading.value = true;
    try {
        const { data: res } = await apiClient.get(`/billing/subscriptions/${id}`);
        subscription.value = res.data || null;
    } catch {
        ElMessage.error('加载订阅详情失败');
        subscription.value = null;
    } finally {
        loading.value = false;
    }
}

async function handleChangePlan() {
    const valid = await changePlanFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    changingPlan.value = true;
    try {
        await apiClient.put(`/billing/subscriptions/${subscription.value.id}/plan`, {
            plan: changePlanForm.plan,
            price: changePlanForm.price,
            billing_period: changePlanForm.billing_period,
        });
        ElMessage.success('套餐已变更');
        showChangePlan.value = false;
        loadSubscription();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '变更失败');
    } finally {
        changingPlan.value = false;
    }
}

async function handleCancel() {
    try {
        const { value: reason } = await ElMessageBox.prompt(
            '请输入取消原因（可选）',
            '确认取消订阅',
            { confirmButtonText: '确认取消', cancelButtonText: '取消', inputPlaceholder: '取消原因', inputType: 'textarea' }
        );
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/cancel`, { reason });
        ElMessage.success('订阅已取消（当前周期结束后不再续费）');
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleResume() {
    try {
        await ElMessageBox.confirm('确定要恢复此订阅吗？', '恢复订阅', {
            confirmButtonText: '确认恢复', cancelButtonText: '取消', type: 'info',
        });
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/resume`);
        ElMessage.success('订阅已恢复');
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleRenew() {
    try {
        await ElMessageBox.confirm('确定要手动续费此订阅吗？', '手动续费', {
            confirmButtonText: '确认续费', cancelButtonText: '取消', type: 'info',
        });
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/renew`);
        ElMessage.success('续费成功');
        loadSubscription();
    } catch { /* cancelled */ }
}

function viewInvoice(invoice) {
    ElMessage.info(`发票详情：${invoice.invoice_no}，金额 ¥${invoice.amount}`);
}

onMounted(() => {
    loadSubscription();
});
</script>

<style scoped>
.billing-detail-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-right {
    display: flex;
    gap: 8px;
}

.mb-4 { margin-bottom: 16px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.font-mono {
    font-family: 'Courier New', monospace;
    font-weight: 600;
}

:deep(.el-card__body) { padding: 16px; }
</style>
