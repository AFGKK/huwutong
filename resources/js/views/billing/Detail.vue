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
                    @click="openChangePlan"
                >
                    <el-icon><Coin /></el-icon>
                    变更套餐
                </el-button>
                <el-button
                    v-if="subscription.status === 'active'"
                    type="info"
                    plain
                    @click="handleSuspend"
                >
                    <el-icon><VideoPause /></el-icon>
                    暂停订阅
                </el-button>
                <el-button
                    v-if="subscription.status === 'suspended'"
                    type="primary"
                    plain
                    @click="handleResume"
                >
                    <el-icon><CircleCheck /></el-icon>
                    恢复订阅
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
                <el-button
                    v-if="subscription.status === 'active'"
                    type="primary"
                    plain
                    @click="showToggleAutoRenew"
                >
                    <el-icon :class="{ 'is-active': subscription.auto_renew }"><SwitchButton /></el-icon>
                    {{ subscription.auto_renew ? '关闭自动续费' : '开启自动续费' }}
                </el-button>
            </div>
        </div>

        <div v-if="subscription">
            <!-- 基本信息卡片 -->
            <el-card shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>基本信息</span>
                        <div class="header-tags">
                            <el-tag :type="statusType(subscription.status)" size="large" effect="dark">
                                {{ statusLabel(subscription.status) }}
                            </el-tag>
                            <el-tag v-if="subscription.auto_renew" type="success" size="small" effect="plain" class="ml-2">
                                自动续费
                            </el-tag>
                            <el-tag v-else size="small" effect="plain" class="ml-2">
                                手动续费
                            </el-tag>
                        </div>
                    </div>
                </template>

                <el-descriptions :column="3" border>
                    <el-descriptions-item label="订阅 ID">#{{ subscription.id }}</el-descriptions-item>
                    <el-descriptions-item label="客户">
                        <router-link :to="`/customers/${subscription.customer_id}`" class="link">
                            {{ subscription.customer?.name || subscription.customer?.user?.name || '-' }}
                        </router-link>
                    </el-descriptions-item>
                    <el-descriptions-item label="邮箱">{{ subscription.customer?.email || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="产品">{{ subscription.product?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="定价方案">
                        <el-tag size="small">{{ subscription.plan }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="计费周期">{{ periodLabel(subscription.billing_period) }}</el-descriptions-item>
                    <el-descriptions-item label="单价">
                        <span class="price">¥{{ subscription.price }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="货币">{{ subscription.currency || 'CNY' }}</el-descriptions-item>
                    <el-descriptions-item label="已付总额">
                        <span class="price">¥{{ (subscription.total_paid || 0).toFixed(2) }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="计费周期数">
                        {{ subscription.billing_cycles_completed || 0 }} 次
                    </el-descriptions-item>
                    <el-descriptions-item label="宽限期">{{ subscription.grace_days || 7 }} 天</el-descriptions-item>
                    <el-descriptions-item label="试用天数">{{ subscription.trial_days || 0 }} 天</el-descriptions-item>
                    <el-descriptions-item label="开始时间">{{ formatDate(subscription.starts_at) }}</el-descriptions-item>
                    <el-descriptions-item label="到期时间">{{ formatDate(subscription.ends_at) }}</el-descriptions-item>
                    <el-descriptions-item label="下次账单日">{{ formatDate(subscription.next_billing_at) }}</el-descriptions-item>
                    <el-descriptions-item label="上次账单日">{{ formatDate(subscription.last_billed_at) }}</el-descriptions-item>
                    <el-descriptions-item label="宽限期截止">{{ formatDate(subscription.grace_ends_at) }}</el-descriptions-item>
                    <el-descriptions-item label="取消时间">{{ formatDate(subscription.canceled_at) }}</el-descriptions-item>
                    <el-descriptions-item label="取消原因">{{ subscription.cancellation_reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="关联 License">
                        <el-tag v-if="subscription.license_id" size="small" effect="plain">#{{ subscription.license_id }}</el-tag>
                        <span v-else>-</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatDate(subscription.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="最近更新">{{ formatDate(subscription.updated_at) }}</el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- 操作历史 -->
            <el-card shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>操作日志</span>
                    </div>
                </template>
                <el-timeline v-if="activityLog.length > 0">
                    <el-timeline-item
                        v-for="(log, idx) in activityLog"
                        :key="idx"
                        :timestamp="formatDate(log.created_at)"
                        :type="log.type === 'success' ? 'success' : log.type === 'warning' ? 'warning' : 'primary'"
                    >
                        {{ log.description || log.action || log.message || '-' }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else :image-size="60" description="暂无操作日志" />
            </el-card>

            <!-- 最近发票 -->
            <el-card shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>最近发票</span>
                        <el-button text size="small" type="primary" @click="$router.push('/billing?tab=invoices')">
                            查看全部
                        </el-button>
                    </div>
                </template>

                <el-table :data="subscription.invoices || []" stripe size="small">
                    <el-table-column prop="invoice_no" label="发票号" width="180" />
                    <el-table-column label="金额" width="120">
                        <template #default="{ row }">
                            <span class="price">¥{{ row.amount }}</span>
                            <el-tag v-if="row.discount_amount > 0" size="small" type="warning" class="ml-1">折扣</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">
                                {{ row.status === 'paid' ? '已支付' : row.status === 'pending' ? '待支付' : row.status === 'refunded' ? '已退款' : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="billing_reason" label="原因" width="140" />
                    <el-table-column prop="created_at" label="创建时间" width="170">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
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

        <!-- 变更套餐 Dialog（使用定价方案选择） -->
        <el-dialog v-model="showChangePlan" title="变更套餐" width="550px" :close-on-click-modal="false">
            <el-form ref="changePlanFormRef" :model="changePlanForm" :rules="changePlanRules" label-position="top">
                <el-alert type="info" :closable="false" class="mb-3">
                    <template #title>
                        当前套餐: <strong>{{ subscription?.plan }}</strong> — ¥{{ subscription?.price }} / {{ periodLabel(subscription?.billing_period) }}
                    </template>
                </el-alert>
                <el-divider />
                <el-form-item label="选择定价方案" prop="plan_slug">
                    <el-select v-model="changePlanForm.plan_slug" filterable placeholder="搜索或选择方案" style="width:100%"
                        @change="onPlanSelect">
                        <el-option v-for="p in planOptions" :key="p.slug" :label="`${p.name} (${p.slug})`" :value="p.slug">
                            <div class="plan-option">
                                <span>{{ p.name }}</span>
                                <span class="plan-price">¥{{ p.price_monthly || '—' }}/月</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="计费周期" prop="billing_period">
                    <el-select v-model="changePlanForm.billing_period" style="width:160px">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="半年付" value="semi_annually" />
                        <el-option label="年付" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="新价格">
                    <el-input-number v-model="changePlanForm.price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item label="变更原因">
                    <el-input v-model="changePlanForm.reason" type="textarea" :rows="2" placeholder="可选" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showChangePlan = false">取消</el-button>
                <el-button type="primary" @click="handleChangePlan" :loading="changingPlan">确认变更</el-button>
            </template>
        </el-dialog>

        <!-- 暂停确认 Dialog -->
        <el-dialog v-model="showSuspend" title="暂停订阅" width="450px">
            <el-form :model="suspendForm" label-position="top">
                <el-alert type="warning" :closable="false" class="mb-3">
                    <template #title>暂停后订阅将变为「已暂停」状态，关联的 License 也会同步暂停</template>
                </el-alert>
                <el-form-item label="暂停原因">
                    <el-input v-model="suspendForm.reason" type="textarea" :rows="3" placeholder="请输入暂停原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSuspend = false">取消</el-button>
                <el-button type="warning" @click="confirmSuspend" :loading="suspending">确认暂停</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowLeft, Coin, VideoPause, CircleClose, CircleCheck, Refresh, SwitchButton } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import billingApi from '@/api/billing';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const changingPlan = ref(false);
const suspending = ref(false);
const subscription = ref(null);
const showChangePlan = ref(false);
const showSuspend = ref(false);
const activityLog = ref([]);

// Pricing Plan options
const planOptions = ref([]);

const changePlanFormRef = ref(null);
const changePlanForm = reactive({
    plan_slug: '',
    price: 0,
    billing_period: 'monthly',
    reason: '',
});
const changePlanRules = {
    plan_slug: [{ required: true, message: '请选择定价方案', trigger: 'change' }],
    billing_period: [{ required: true, message: '请选择计费周期', trigger: 'change' }],
};

const suspendForm = reactive({
    reason: '',
});

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
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

function periodLabel(p) {
    const map = { monthly: '月付', quarterly: '季付', semi_annually: '半年付', yearly: '年付' };
    return map[p] || p;
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
        ElMessage.error('加载订阅详情失败');
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
        // Try to load activity log from the renewal/activity endpoint
        const { data: res } = await apiClient.get('/renewal-dashboard/activity-log', {
            params: { subscription_id: route.params.id, per_page: 20 }
        }).catch(() => ({ data: { success: false } }));
        if (res.success) {
            activityLog.value = res.data || [];
        }
    } catch { /* ignore */ }
}

// ── Lifecycle Actions ──

async function openChangePlan() {
    // Preset current values
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
            '取消后当前周期结束后不再续费，但服务可用至周期结束。',
            '确认取消订阅',
            {
                confirmButtonText: '确认取消',
                cancelButtonText: '暂不取消',
                inputPlaceholder: '取消原因（可选）',
                inputType: 'textarea',
                type: 'warning',
            }
        );
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/cancel`, { reason: reason || '管理员取消' });
        ElMessage.success('订阅已取消');
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleResume() {
    try {
        const msg = subscription.value.status === 'canceled'
            ? '恢复后将重新开启自动续费，服务不受影响。'
            : subscription.value.status === 'suspended'
                ? '恢复后订阅及关联 License 将重新激活。'
                : '确定恢复此订阅？';

        await ElMessageBox.confirm(msg, '恢复订阅', {
            confirmButtonText: '确认恢复', cancelButtonText: '取消', type: 'info',
        });
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/resume`);
        ElMessage.success('订阅已恢复');
        loadSubscription();
    } catch { /* cancelled */ }
}

async function handleRenew() {
    try {
        const amount = subscription.value.price || 0;
        await ElMessageBox.confirm(
            `将为当前订阅手动续费 ¥${amount}，是否继续？`,
            '手动续费',
            { confirmButtonText: '确认续费', cancelButtonText: '取消', type: 'info' }
        );
        await apiClient.post(`/billing/subscriptions/${subscription.value.id}/renew`);
        ElMessage.success('续费成功');
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
        ElMessage.success('订阅已暂停');
        showSuspend.value = false;
        suspendForm.reason = '';
        loadSubscription();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '暂停失败');
    } finally {
        suspending.value = false;
    }
}

async function showToggleAutoRenew() {
    const current = subscription.value.auto_renew;
    const action = current ? '关闭' : '开启';
    try {
        if (current) {
            await ElMessageBox.confirm('关闭自动续费后，当前周期结束后不再自动续费。', `确认${action}自动续费`, {
                confirmButtonText: `确认${action}`, cancelButtonText: '取消', type: 'warning',
            });
        }
        // Toggle via cancel/resume mechanism
        if (current) {
            await apiClient.post(`/billing/subscriptions/${subscription.value.id}/cancel`, {
                reason: '管理员关闭自动续费',
            });
        } else {
            await apiClient.post(`/billing/subscriptions/${subscription.value.id}/resume`);
        }
        ElMessage.success(`自动续费已${action}`);
        loadSubscription();
    } catch { /* cancelled */ }
}

function viewInvoice(invoice) {
    ElMessageBox.alert(
        `<div style="font-size:14px">
            <p><b>发票号：</b>${invoice.invoice_no}</p>
            <p><b>金额：</b>¥${invoice.amount}</p>
            <p><b>状态：</b>${invoice.status === 'paid' ? '已支付' : '待支付'}</p>
            <p><b>原因：</b>${invoice.billing_reason || '-'}</p>
            <p><b>创建时间：</b>${formatDate(invoice.created_at)}</p>
            <p><b>支付时间：</b>${invoice.paid_at ? formatDate(invoice.paid_at) : '-'}</p>
        </div>`,
        '发票详情',
        { dangerouslyUseHTMLString: true, confirmButtonText: '关闭' }
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
    color: #409eff;
}

.link {
    color: #409eff;
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
    color: #409eff;
}
</style>
