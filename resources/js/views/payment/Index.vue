<template>
    <div class="payment-dashboard">
        <div class="page-header">
            <h2>支付管理</h2>
            <div class="header-actions">
                <el-tag v-if="currentGateway" :type="currentGateway === 'mock' ? 'warning' : 'success'">
                    当前网关: {{ currentGateway }}
                </el-tag>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ── Tab 1: 概览 ── -->
            <el-tab-pane label="概览" name="overview">
                <!-- Stats Cards -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value">¥{{ stats.total_revenue || 0 }}</div>
                                <div class="stat-label">总收入</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #67c23a">¥{{ stats.recent_revenue_30d || 0 }}</div>
                                <div class="stat-label">近30天收入</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #e6a23c">{{ stats.pending_count }}</div>
                                <div class="stat-label">待支付</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #f56c6c">{{ stats.failed_count }}</div>
                                <div class="stat-label">支付失败</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- Channel Stats -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <span>支付渠道统计</span>
                    </template>
                    <el-row :gutter="16">
                        <el-col v-for="(count, channel) in stats.channel_stats || {}" :key="channel" :span="6">
                            <div class="channel-stat">
                                <div class="channel-icon" :class="'channel-' + channel">
                                    <el-icon v-if="channel === 'alipay'"><Money /></el-icon>
                                    <el-icon v-else-if="channel === 'wechat'"><ChatDotSquare /></el-icon>
                                    <el-icon v-else-if="channel === 'stripe'"><CreditCard /></el-icon>
                                    <el-icon v-else-if="channel === 'paypal'"><Coin /></el-icon>
                                    <el-icon v-else><Coin /></el-icon>
                                </div>
                                <div class="channel-info">
                                    <div class="channel-name">{{ channelLabels[channel] || channel }}</div>
                                    <div class="channel-count">{{ count }} 笔</div>
                                </div>
                            </div>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- Gateway Config -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <span>支付网关配置</span>
                    </template>
                    <el-table :data="gateways" stripe>
                        <el-table-column prop="name" label="渠道" width="120" />
                        <el-table-column label="状态" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.enabled" type="success" size="small">已启用</el-tag>
                                <el-tag v-else type="info" size="small">未启用</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="配置" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.configured" type="success" size="small" effect="plain">已配置</el-tag>
                                <el-tag v-else type="danger" size="small" effect="plain">未配置</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="说明">
                            <template #default="{ row }">
                                <span v-if="!row.configured" class="text-muted">请在 .env 中配置相关参数</span>
                                <span v-else class="text-success">配置完成</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- Recent Transactions -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>最近交易</span>
                            <el-button text type="primary" @click="$router.push('/admin/payment/transactions')">
                                查看全部
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="recentTransactions" stripe v-loading="loading">
                        <el-table-column prop="invoice_no" label="单号" width="160" />
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column prop="payment_method" label="渠道" width="100">
                            <template #default="{ row }">{{ channelLabels[row.payment_method] || row.payment_method || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'paid'" type="success" size="small">已支付</el-tag>
                                <el-tag v-else-if="row.status === 'pending'" type="warning" size="small">待支付</el-tag>
                                <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">失败</el-tag>
                                <el-tag v-else-if="row.status === 'refunded'" type="info" size="small">已退款</el-tag>
                                <el-tag v-else size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" width="180" />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- ── Tab 2: 支付记录 (M1.1-27) ── -->
            <el-tab-pane label="支付记录" name="records">
                <!-- 统计卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value">¥{{ recordStats.total_revenue || 0 }}</div>
                                <div class="stat-label">总支付金额</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #67c23a">¥{{ recordStats.today_revenue || 0 }}</div>
                                <div class="stat-label">今日收入</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #e6a23c">{{ recordStats.pending_count }}</div>
                                <div class="stat-label">待支付</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-card">
                                <div class="stat-value" style="color: #f56c6c">{{ recordStats.refunded_count }}</div>
                                <div class="stat-label">已退款</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 筛选栏 -->
                <el-card shadow="never" class="mb-4">
                    <el-form :inline="true" :model="filters" size="small">
                        <el-form-item label="状态">
                            <el-select v-model="filters.status" clearable placeholder="全部" style="width:130px">
                                <el-option label="待支付" value="pending" />
                                <el-option label="已完成" value="completed" />
                                <el-option label="失败" value="failed" />
                                <el-option label="已退款" value="refunded" />
                                <el-option label="部分退款" value="partially_refunded" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="渠道">
                            <el-select v-model="filters.channel" clearable placeholder="全部" style="width:130px">
                                <el-option label="支付宝" value="alipay" />
                                <el-option label="微信" value="wechat" />
                                <el-option label="Stripe" value="stripe" />
                                <el-option label="PayPal" value="paypal" />
                                <el-option label="模拟" value="mock" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="搜索">
                            <el-input v-model="filters.search" placeholder="交易号/描述" clearable style="width:200px" />
                        </el-form-item>
                        <el-form-item label="日期">
                            <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="loadRecords(1)">查询</el-button>
                            <el-button @click="resetFilters">重置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 记录表格 -->
                <el-card shadow="never">
                    <el-table :data="records" stripe v-loading="recordsLoading" @row-click="showDetail">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="transaction_id" label="交易号" width="180" show-overflow-tooltip />
                        <el-table-column label="渠道" width="90">
                            <template #default="{ row }">{{ channelLabels[row.channel] || row.channel }}</template>
                        </el-table-column>
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">¥{{ row.amount }}</template>
                        </el-table-column>
                        <el-table-column label="手续费" width="100">
                            <template #default="{ row }">¥{{ row.fee }}</template>
                        </el-table-column>
                        <el-table-column label="实收" width="100">
                            <template #default="{ row }">¥{{ row.net_amount }}</template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="120">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'completed'" type="success" size="small">已完成</el-tag>
                                <el-tag v-else-if="row.status === 'pending'" type="warning" size="small">待支付</el-tag>
                                <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">失败</el-tag>
                                <el-tag v-else-if="row.status === 'refunded'" type="info" size="small">已退款</el-tag>
                                <el-tag v-else-if="row.status === 'partially_refunded'" type="warning" size="small">部分退款</el-tag>
                                <el-tag v-else size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="已退款" width="100">
                            <template #default="{ row }">¥{{ row.refunded_amount }}</template>
                        </el-table-column>
                        <el-table-column prop="paid_at" label="支付时间" width="170" />
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text type="primary" size="small" @click.stop="showDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'completed' || row.status === 'partially_refunded'" text type="danger" size="small" @click.stop="handleRefund(row)">退款</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="page"
                            :page-size="perPage"
                            :total="total"
                            layout="total, prev, pager, next"
                            @current-change="loadRecords" />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 详情抽屉 -->
        <el-drawer v-model="detailVisible" title="支付记录详情" size="500px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="ID" :span="2">{{ detail.id }}</el-descriptions-item>
                    <el-descriptions-item label="交易号" :span="2">{{ detail.transaction_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="渠道">{{ channelLabels[detail.channel] || detail.channel }}</el-descriptions-item>
                    <el-descriptions-item label="金额">¥{{ detail.amount }}</el-descriptions-item>
                    <el-descriptions-item label="手续费">¥{{ detail.fee }}</el-descriptions-item>
                    <el-descriptions-item label="实收">¥{{ detail.net_amount }}</el-descriptions-item>
                    <el-descriptions-item label="已退款">¥{{ detail.refunded_amount }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag v-if="detail.status === 'completed'" type="success" size="small">已完成</el-tag>
                        <el-tag v-else-if="detail.status === 'pending'" type="warning" size="small">待支付</el-tag>
                        <el-tag v-else-if="detail.status === 'failed'" type="danger" size="small">失败</el-tag>
                        <el-tag v-else-if="detail.status === 'refunded'" type="info" size="small">已退款</el-tag>
                        <el-tag v-else-if="detail.status === 'partially_refunded'" type="warning" size="small">部分退款</el-tag>
                        <el-tag v-else size="small">{{ detail.status }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="支付时间">{{ detail.paid_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="退款时间">{{ detail.refunded_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="描述" :span="2">{{ detail.description || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="租户ID">{{ detail.tenant_id }}</el-descriptions-item>
                    <el-descriptions-item label="订单ID">{{ detail.order_id || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-drawer>

        <!-- 退款弹窗 -->
        <el-dialog v-model="refundVisible" title="退款" width="400px">
            <el-form :model="refundForm" label-width="80px">
                <el-form-item label="可退金额">
                    <span class="text-success">¥{{ refundMaxAmount }}</span>
                </el-form-item>
                <el-form-item label="退款金额">
                    <el-input-number v-model="refundForm.amount" :min="0.01" :max="refundMaxAmount" :precision="2" style="width:200px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="refundVisible = false">取消</el-button>
                <el-button type="danger" :loading="refunding" @click="confirmRefund">确认退款</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { getPaymentStats, getTransactions, getGatewayConfig, getPaymentsDashboard, getPaymentRecords, getPaymentRecordDetail, refundPayment, getPaymentTrend } from '@/api/payment';

const loading = ref(false);
const stats = ref({});
const gateways = ref([]);
const recentTransactions = ref([]);
const currentGateway = ref('');
const activeTab = ref('overview');

const channelLabels = {
    alipay: '支付宝',
    wechat: '微信支付',
    stripe: 'Stripe',
    paypal: 'PayPal',
    mock: '模拟支付',
};

// ── 概览 ──
const loadStats = async () => {
    try {
        const res = await getPaymentStats();
        if (res.data.success) {
            stats.value = res.data.data;
            currentGateway.value = res.data.data.gateway;
        }
    } catch (e) { /* ignore */ }
};

const loadGateways = async () => {
    try {
        const res = await getGatewayConfig();
        if (res.data.success) {
            gateways.value = Object.entries(res.data.data).map(([key, val]) => ({
                key,
                ...val,
            }));
        }
    } catch (e) { /* ignore */ }
};

const loadRecentTransactions = async () => {
    loading.value = true;
    try {
        const res = await getTransactions({ per_page: 10 });
        if (res.data.success) {
            recentTransactions.value = res.data.data.data || [];
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

// ── 支付记录 (M1.1-27) ──
const recordStats = ref({});
const records = ref([]);
const recordsLoading = ref(false);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const detailVisible = ref(false);
const detail = ref(null);
const refundVisible = ref(false);
const refunding = ref(false);
const refundTarget = ref(null);
const refundForm = reactive({ amount: 0 });
const refundMaxAmount = ref(0);

const filters = reactive({
    status: '',
    channel: '',
    search: '',
});
const dateRange = ref([]);

const loadRecordDashboard = async () => {
    try {
        const res = await getPaymentsDashboard();
        if (res.data.success) {
            recordStats.value = res.data.data;
        }
    } catch (e) { /* ignore */ }
};

const loadRecords = async (p = 1) => {
    page.value = p;
    recordsLoading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            ...filters,
        };
        if (dateRange.value && dateRange.value.length === 2) {
            params.date_from = dateRange.value[0];
            params.date_to = dateRange.value[1];
        }
        // 清理空值
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await getPaymentRecords(params);
        if (res.data.success) {
            records.value = res.data.data.items || [];
            total.value = res.data.data.total || 0;
        }
    } catch (e) { /* ignore */ }
    finally { recordsLoading.value = false; }
};

const resetFilters = () => {
    filters.status = '';
    filters.channel = '';
    filters.search = '';
    dateRange.value = [];
    loadRecords(1);
};

const showDetail = async (row) => {
    try {
        const res = await getPaymentRecordDetail(row.id);
        if (res.data.success) {
            detail.value = res.data.data;
            detailVisible.value = true;
        }
    } catch (e) { /* ignore */ }
};

const handleRefund = (row) => {
    refundTarget.value = row;
    refundMaxAmount.value = row.amount - row.refunded_amount;
    refundForm.amount = refundMaxAmount.value;
    refundVisible.value = true;
};

const confirmRefund = async () => {
    if (!refundTarget.value) return;
    refunding.value = true;
    try {
        const res = await refundPayment(refundTarget.value.id, { amount: refundForm.amount });
        if (res.data.success) {
            ElMessage.success('退款成功');
            refundVisible.value = false;
            loadRecords(page.value);
            loadRecordDashboard();
        } else {
            ElMessage.error(res.data.message || '退款失败');
        }
    } catch (e) {
        ElMessage.error('退款请求失败');
    }
    finally { refunding.value = false; }
};

// 切换 Tab 时加载
watch(activeTab, (tab) => {
    if (tab === 'records' && records.value.length === 0) {
        loadRecordDashboard();
        loadRecords();
    }
});

onMounted(() => {
    loadStats();
    loadGateways();
    loadRecentTransactions();
});
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.channel-stat { display: flex; align-items: center; gap: 12px; padding: 12px; }
.channel-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; }
.channel-alipay { background: #1677ff; }
.channel-wechat { background: #07c160; }
.channel-stripe { background: #635bff; }
.channel-mock { background: #909399; }
.channel-name { font-weight: 600; font-size: 14px; }
.channel-count { font-size: 12px; color: #909399; margin-top: 2px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; }
.text-success { color: #67c23a; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
</style>
