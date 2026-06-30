<template>
    <div class="prepaid-balance-page">
        <div class="page-header">
            <h2>预付余额 & 信用额度管理</h2>
            <p class="text-muted">管理客户预付余额、充值扣款、信用额度设置与交易对账</p>
        </div>

        <!-- 统计概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_balance) }}</div>
                    <div class="stat-label">总余额</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_recharged) }}</div>
                    <div class="stat-label">累计充值</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_consumed) }}</div>
                    <div class="stat-label">累计消费</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.active_accounts }}</div>
                    <div class="stat-label">活跃账户数</div>
                    <div class="stat-sub">{{ stats.penetration_rate }}% 渗透率</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.recent_30d_recharges) }}</div>
                    <div class="stat-label">近30天充值</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.auto_recharge_users }}</div>
                    <div class="stat-label">自动充值用户</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 信用额度概览 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ formatMoney(stats.credit?.total_limit) }}</div>
                    <div class="stat-label">总授信额度</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ formatMoney(stats.credit?.total_used) }}</div>
                    <div class="stat-label">已使用额度</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ stats.credit?.utilization_rate }}%</div>
                    <div class="stat-label">额度使用率</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card credit">
                    <div class="stat-value">{{ stats.credit?.total_accounts }}</div>
                    <div class="stat-label">授信账户数</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :lg="4">
                <el-card shadow="hover" class="stat-card warning">
                    <div class="stat-value">{{ stats.low_balance_accounts }}</div>
                    <div class="stat-label">低余额账户</div>
                    <div class="stat-sub">&lt; ¥50</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 工具条 -->
        <el-card class="toolbar-card">
            <el-form :inline="true" :model="searchForm" size="small">
                <el-form-item label="交易类型">
                    <el-select v-model="searchForm.type" clearable placeholder="全部类型" style="width: 140px">
                        <el-option label="充值" value="recharge" />
                        <el-option label="消费" value="consume" />
                        <el-option label="退款" value="refund" />
                        <el-option label="调账" value="adjust" />
                        <el-option label="信用使用" value="credit_use" />
                        <el-option label="信用偿还" value="credit_repay" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" clearable placeholder="全部状态" style="width: 120px">
                        <el-option label="已完成" value="completed" />
                        <el-option label="处理中" value="pending" />
                        <el-option label="失败" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="客户ID">
                    <el-input v-model="searchForm.customer_id" placeholder="输入客户ID" style="width: 120px" clearable />
                </el-form-item>
                <el-form-item label="开始日期">
                    <el-date-picker v-model="searchForm.date_from" type="date" placeholder="开始日期" style="width: 140px" />
                </el-form-item>
                <el-form-item label="结束日期">
                    <el-date-picker v-model="searchForm.date_to" type="date" placeholder="结束日期" style="width: 140px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadTransactions" :icon="Search">查询</el-button>
                    <el-button @click="resetSearch" :icon="Refresh">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 交易列表 -->
        <el-card class="table-card">
            <template #header>
                <div class="card-header">
                    <span><el-icon><List /></el-icon> 交易流水</span>
                    <div>
                        <el-button size="small" @click="loadTransactions" :icon="Refresh">刷新</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="transactions" v-loading="loading" stripe empty-text="暂无交易记录" style="width: 100%">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="customer_id" label="客户" width="80">
                    <template #default="{ row }">
                        <router-link :to="`/customers/${row.customer_id}`" class="link">#{{ row.customer_id }}</router-link>
                    </template>
                </el-table-column>
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" label="金额" width="130">
                    <template #default="{ row }">
                        <span :class="row.amount >= 0 ? 'text-success' : 'text-danger'">
                            {{ row.amount >= 0 ? '+' : '' }}{{ formatMoney(row.amount) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="balance_before" label="前余额" width="100">
                    <template #default="{ row }">{{ formatMoney(row.balance_before) }}</template>
                </el-table-column>
                <el-table-column prop="balance_after" label="后余额" width="100">
                    <template #default="{ row }">{{ formatMoney(row.balance_after) }}</template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'pending' ? 'warning' : 'danger'" size="small">
                            {{ row.status === 'completed' ? '成功' : row.status === 'pending' ? '处理中' : '失败' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="payment_method" label="支付方式" width="100">
                    <template #default="{ row }">
                        <span>{{ methodLabel(row.payment_method) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="description" label="描述" min-width="180" show-overflow-tooltip />
                <el-table-column prop="created_at" label="时间" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="showTransactionDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="totalPages > 1">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="totalRecords"
                    layout="prev, pager, next, total"
                    @current-change="loadTransactions"
                />
            </div>
        </el-card>

        <!-- 交易详情弹窗 -->
        <el-dialog v-model="detailVisible" title="交易详情" width="600px">
            <el-descriptions v-if="selectedTransaction" :column="2" border>
                <el-descriptions-item label="交易ID">{{ selectedTransaction.id }}</el-descriptions-item>
                <el-descriptions-item label="类型">{{ typeLabel(selectedTransaction.type) }}</el-descriptions-item>
                <el-descriptions-item label="金额" :span="2">
                    <span :class="selectedTransaction.amount >= 0 ? 'text-success' : 'text-danger'">
                        {{ selectedTransaction.amount >= 0 ? '+' : '' }}{{ formatMoney(selectedTransaction.amount) }}
                    </span>
                </el-descriptions-item>
                <el-descriptions-item label="交易前余额">{{ formatMoney(selectedTransaction.balance_before) }}</el-descriptions-item>
                <el-descriptions-item label="交易后余额">{{ formatMoney(selectedTransaction.balance_after) }}</el-descriptions-item>
                <el-descriptions-item label="支付方式">{{ methodLabel(selectedTransaction.payment_method) }}</el-descriptions-item>
                <el-descriptions-item label="网关交易号">{{ selectedTransaction.gateway_transaction_id || '-' }}</el-descriptions-item>
                <el-descriptions-item label="币种">{{ selectedTransaction.currency }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="selectedTransaction.status === 'completed' ? 'success' : 'warning'" size="small">
                        {{ selectedTransaction.status }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="描述" :span="2">{{ selectedTransaction.description }}</el-descriptions-item>
                <el-descriptions-item label="完成时间">{{ selectedTransaction.completed_at || '-' }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ selectedTransaction.created_at }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 手动充值/扣款/调账弹窗 -->
        <el-dialog v-model="actionDialog.visible" :title="actionDialog.title" width="500px">
            <el-form :model="actionForm" :rules="actionRules" ref="actionFormRef" label-width="120px">
                <el-form-item label="客户" v-if="!actionForm.customer_id">
                    <el-select v-model="actionForm.customer_id" filterable placeholder="选择客户" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="`#${c.id} - ${c.user?.name || 'Unknown'}`"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="金额" prop="amount">
                    <el-input-number
                        v-model="actionForm.amount"
                        :min="actionDialog.type === 'deduct' ? 0.01 : undefined"
                        :max="999999.99"
                        :step="10"
                        :precision="2"
                        style="width: 200px"
                    />
                    <span class="ml-2">CNY</span>
                    <div v-if="actionDialog.type === 'adjust'" class="text-muted small">正数=增加，负数=扣减</div>
                </el-form-item>
                <el-form-item label="说明">
                    <el-input v-model="actionForm.description" type="textarea" :rows="2" maxlength="200" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="actionDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="executeAction" :loading="actionSubmitting">确认执行</el-button>
            </template>
        </el-dialog>

        <!-- 信用额度设置弹窗 -->
        <el-dialog v-model="creditDialog.visible" title="设置信用额度" width="500px">
            <el-form :model="creditForm" :rules="creditRules" ref="creditFormRef" label-width="140px">
                <el-form-item label="客户" v-if="!creditForm.customer_id">
                    <el-select v-model="creditForm.customer_id" filterable placeholder="选择客户" style="width: 100%">
                        <el-option
                            v-for="c in customerOptions"
                            :key="c.id"
                            :label="`#${c.id} - ${c.user?.name || 'Unknown'}`"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="信用额度上限" prop="credit_limit">
                    <el-input-number v-model="creditForm.credit_limit" :min="0" :max="9999999.99" :precision="2" style="width: 200px" />
                    <span class="ml-2">CNY</span>
                </el-form-item>
                <el-form-item label="宽限天数">
                    <el-input-number v-model="creditForm.grace_days" :min="0" :max="365" style="width: 200px" />
                    <div class="text-muted small">负余额宽限天数</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="creditDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="executeSetCreditLimit" :loading="creditSubmitting">确认设置</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Search, Refresh, List } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import prepaidBalanceApi from '../../api/prepaidBalance';
import customerApi from '@/api/customer';

// ─── 状态 ───
const loading = ref(false);
const transactions = ref([]);
const currentPage = ref(1);
const perPage = ref(20);
const totalRecords = ref(0);
const totalPages = computed(() => Math.ceil(totalRecords.value / perPage.value));

const stats = reactive({
    total_balance: 0,
    total_recharged: 0,
    total_consumed: 0,
    active_accounts: 0,
    total_customers: 0,
    penetration_rate: 0,
    auto_recharge_users: 0,
    low_balance_accounts: 0,
    recent_30d_recharges: 0,
    credit: {
        total_accounts: 0,
        total_limit: 0,
        total_used: 0,
        utilization_rate: 0,
    },
});

const searchForm = reactive({
    type: '',
    status: '',
    customer_id: '',
    date_from: '',
    date_to: '',
});

// 交易详情
const detailVisible = ref(false);
const selectedTransaction = ref(null);

// 操作弹窗
const actionDialog = reactive({
    visible: false,
    title: '',
    type: 'recharge', // recharge | deduct | adjust
});
const actionFormRef = ref(null);
const actionForm = reactive({
    customer_id: '',
    amount: 0,
    description: '',
});
const actionSubmitting = ref(false);

// 信用额度弹窗
const creditDialog = reactive({
    visible: false,
});
const creditFormRef = ref(null);
const creditForm = reactive({
    customer_id: '',
    credit_limit: 0,
    grace_days: 0,
});
const creditSubmitting = ref(false);

// 客户选项
const customerOptions = ref([]);

// ─── 校验规则 ───
const actionRules = {
    amount: [{ required: true, type: 'number', min: 0.01, message: '金额必须大于 0', trigger: 'blur' }],
};
const creditRules = {
    credit_limit: [{ required: true, type: 'number', min: 0, message: '请输入信用额度', trigger: 'blur' }],
};

// ─── 方法 ───

function formatMoney(val) {
    const num = parseFloat(val || 0);
    return '¥' + num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function typeLabel(type) {
    const map = {
        recharge: '充值',
        consume: '消费',
        refund: '退款',
        adjust: '调账',
        credit_use: '信用使用',
        credit_repay: '信用偿还',
    };
    return map[type] || type;
}

function typeTag(type) {
    const map = {
        recharge: 'success',
        consume: 'danger',
        refund: 'warning',
        adjust: 'info',
        credit_use: 'warning',
        credit_repay: 'success',
    };
    return map[type] || 'info';
}

function methodLabel(method) {
    const map = {
        alipay: '支付宝',
        wechat: '微信支付',
        offline: '线下打款',
        admin: '管理员操作',
        balance: '余额',
    };
    return map[method] || method || '-';
}

function resetSearch() {
    searchForm.type = '';
    searchForm.status = '';
    searchForm.customer_id = '';
    searchForm.date_from = '';
    searchForm.date_to = '';
    currentPage.value = 1;
    loadTransactions();
}

async function loadStats() {
    try {
        const res = await prepaidBalanceApi.getStats();
        Object.assign(stats, res.data);
    } catch (e) {
        console.error('Failed to load stats:', e);
    }
}

async function loadTransactions() {
    loading.value = true;
    try {
        const params = {
            page: currentPage.value,
            per_page: perPage.value,
            ...searchForm,
        };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await prepaidBalanceApi.allTransactions(params);
        const data = res.data;
        transactions.value = data.data || data;
        if (data.meta) {
            totalRecords.value = data.meta.total;
            currentPage.value = data.meta.current_page;
        } else if (Array.isArray(data)) {
            transactions.value = data;
        }
    } catch (e) {
        console.error('Failed to load transactions:', e);
        ElMessage.error('加载交易记录失败');
    } finally {
        loading.value = false;
    }
}

function showTransactionDetail(row) {
    selectedTransaction.value = row;
    detailVisible.value = true;
}

// 操作：充值/扣款/调账
function showActionDialog(type) {
    const titles = { recharge: '手动充值', deduct: '手动扣款', adjust: '余额调账' };
    actionDialog.type = type;
    actionDialog.title = titles[type];
    actionForm.customer_id = '';
    actionForm.amount = type === 'deduct' ? 0 : 0;
    actionForm.description = '';
    actionDialog.visible = true;
    loadCustomerOptions();
}

// 信用额度设置
function showCreditDialog() {
    creditDialog.visible = true;
    creditForm.customer_id = '';
    creditForm.credit_limit = 0;
    creditForm.grace_days = 0;
    loadCustomerOptions();
}

async function loadCustomerOptions() {
    try {
        const res = await customerApi.list({ per_page: 50 });
        customerOptions.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to load customers:', e);
    }
}

async function executeAction() {
    const valid = await actionFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    actionSubmitting.value = true;
    try {
        const { type } = actionDialog;
        const customerId = actionForm.customer_id;
        const data = {
            amount: type === 'deduct' ? Math.abs(actionForm.amount) : actionForm.amount,
            description: actionForm.description,
        };

        let res;
        if (type === 'recharge') {
            res = await prepaidBalanceApi.adminRecharge(customerId, data);
        } else if (type === 'deduct') {
            res = await prepaidBalanceApi.adminDeduct(customerId, data);
        } else {
            res = await prepaidBalanceApi.adminAdjust(customerId, data);
        }

        ElMessage.success(type === 'recharge' ? '充值成功' : type === 'deduct' ? '扣款成功' : '调账成功');
        actionDialog.visible = false;
        loadStats();
        loadTransactions();
    } catch (e) {
        ElMessage.error(e.response?.data?.error || '操作失败');
    } finally {
        actionSubmitting.value = false;
    }
}

async function executeSetCreditLimit() {
    const valid = await creditFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creditSubmitting.value = true;
    try {
        await prepaidBalanceApi.setCreditLimit(creditForm.customer_id, {
            credit_limit: creditForm.credit_limit,
            grace_days: creditForm.grace_days,
        });
        ElMessage.success('信用额度设置成功');
        creditDialog.visible = false;
        loadStats();
    } catch (e) {
        ElMessage.error(e.response?.data?.error || '设置失败');
    } finally {
        creditSubmitting.value = false;
    }
}

// ─── 初始化 ───
onMounted(() => {
    loadStats();
    loadTransactions();
});
</script>

<style scoped>
.prepaid-balance-page {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.text-muted {
    color: #909399;
    font-size: 13px;
}

.stats-row {
    margin-bottom: 16px;
}

.stat-card {
    text-align: center;
    margin-bottom: 12px;
}

.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #409eff;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.stat-card .stat-sub {
    font-size: 11px;
    color: #c0c4cc;
    margin-top: 2px;
}

.stat-card.credit .stat-value {
    color: #67c23a;
}

.stat-card.warning .stat-value {
    color: #e6a23c;
}

.toolbar-card {
    margin-bottom: 16px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.text-success {
    color: #67c23a;
    font-weight: 600;
}

.text-danger {
    color: #f56c6c;
    font-weight: 600;
}

.small {
    font-size: 12px;
}

.ml-2 {
    margin-left: 8px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: center;
}

.link {
    color: #409eff;
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}
</style>
