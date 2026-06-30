<template>
    <div class="earnings-page">
        <div class="page-header">
            <div>
                <h2>开发者收益</h2>
                <p class="text-muted">付费应用收入、提现与税务管理</p>
            </div>
            <el-button v-if="!account" type="primary" @click="initAccount" :loading="initLoading">开通收益账户</el-button>
        </div>

        <template v-if="account">
            <el-row :gutter="16" class="mb-4">
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value success">¥{{ fmt(account.available_balance) }}</div><div class="stat-label">可提现余额</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value warning">¥{{ fmt(account.pending_balance) }}</div><div class="stat-label">冻结中</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value">¥{{ fmt(account.total_withdrawn) }}</div><div class="stat-label">已提现</div></el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never"><div class="stat-value primary">¥{{ fmt(devEarnings?.total_gross) }}</div><div class="stat-label">累计收入(税前)</div></el-card>
                </el-col>
            </el-row>

            <el-card shadow="never">
                <el-tabs v-model="activeTab">
                    <!-- 收益明细 -->
                    <el-tab-pane label="收益明细" name="earnings">
                        <el-table :data="devEarnings?.earnings_by_app || []" stripe>
                            <el-table-column label="应用" prop="name" min-width="180" />
                            <el-table-column label="价格" width="100"><template #default="{ row }">¥{{ fmt(row.price) }}</template></el-table-column>
                            <el-table-column label="安装次数" width="100" prop="install_count" align="center" />
                            <el-table-column label="总收入(税前)" width="130"><template #default="{ row }">¥{{ fmt(row.gross) }}</template></el-table-column>
                            <el-table-column label="平台抽成" width="100"><template #default="{ row }">{{ platformFee }}%</template></el-table-column>
                            <el-table-column label="净收入" width="120"><template #default="{ row }">¥{{ fmt(row.net) }}</template></el-table-column>
                        </el-table>
                        <el-empty v-if="!devEarnings?.earnings_by_app?.length" description="暂无付费应用数据" :image-size="50" />
                    </el-tab-pane>

                    <!-- 提现记录 -->
                    <el-tab-pane label="提现记录" name="withdrawals">
                        <div class="toolbar">
                            <el-button type="primary" @click="showWithdrawDialog"><el-icon><Plus /></el-icon> 发起提现</el-button>
                        </div>
                        <el-table :data="withdrawals" v-loading="wdLoading" stripe>
                            <el-table-column label="金额" width="120"><template #default="{ row }">¥{{ fmt(row.amount) }}</template></el-table-column>
                            <el-table-column label="手续费" width="80"><template #default="{ row }">¥{{ fmt(row.fee) }}</template></el-table-column>
                            <el-table-column label="净额" width="120"><template #default="{ row }">¥{{ fmt(row.net_amount) }}</template></el-table-column>
                            <el-table-column label="渠道" width="80"><template #default="{ row }">{{ channelLabel(row.channel) }}</template></el-table-column>
                            <el-table-column label="状态" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="wdStatusTag(row.status)" size="small">{{ wdStatusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="申请时间" width="160"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                        </el-table>
                        <el-empty v-if="!withdrawals.length && !wdLoading" description="暂无提现记录" :image-size="50" />
                    </el-tab-pane>

                    <!-- 税务信息 -->
                    <el-tab-pane label="税务信息" name="tax">
                        <el-form :model="taxForm" label-width="120px" style="max-width:500px">
                            <el-form-item label="税务登记号">
                                <el-input v-model="taxForm.tax_id" placeholder="统一社会信用代码/税务登记号" />
                            </el-form-item>
                            <el-form-item label="纳税人类型">
                                <el-select v-model="taxForm.tax_type" style="width:100%">
                                    <el-option label="企业" value="enterprise" />
                                    <el-option label="个人" value="individual" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="公司/个人名称">
                                <el-input v-model="taxForm.company_name" placeholder="与税务登记一致" />
                            </el-form-item>
                            <el-form-item label="注册地址">
                                <el-input v-model="taxForm.address" type="textarea" :rows="2" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :loading="taxSaving" @click="saveTaxInfo">保存</el-button>
                            </el-form-item>
                        </el-form>
                    </el-tab-pane>
                </el-tabs>
            </el-card>
        </template>

        <!-- 提现 Dialog -->
        <el-dialog v-model="wdVisible" title="发起提现" width="450px">
            <el-form :model="wdForm" label-width="100px">
                <el-form-item label="可提现余额">
                    <span class="text-muted">¥{{ fmt(account?.available_balance) }}</span>
                </el-form-item>
                <el-form-item label="提现金额" required>
                    <el-input-number v-model="wdForm.amount" :min="1" :max="account?.available_balance || 0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item label="收款渠道" required>
                    <el-select v-model="wdForm.channel" style="width:100%">
                        <el-option label="支付宝" value="alipay" />
                        <el-option label="微信支付" value="wechat" />
                        <el-option label="银行卡" value="bank" />
                        <el-option label="PayPal" value="paypal" />
                    </el-select>
                </el-form-item>
                <el-form-item label="收款账户" required>
                    <el-input v-model="wdForm.account" :placeholder="wdForm.channel === 'alipay' ? '支付宝账号' : wdForm.channel === 'wechat' ? '微信账号' : wdForm.channel === 'paypal' ? 'PayPal 邮箱' : '银行卡号'" />
                </el-form-item>
                <el-form-item v-if="wdForm.channel === 'bank'" label="开户行">
                    <el-input v-model="wdForm.bank_name" placeholder="如：中国银行北京分行" />
                </el-form-item>
                <el-form-item v-if="wdForm.channel === 'bank'" label="开户人">
                    <el-input v-model="wdForm.account_name" placeholder="银行卡开户人姓名" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="wdVisible = false">取消</el-button>
                <el-button type="primary" :loading="wdSubmitting" @click="submitWithdraw">提交提现</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';

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

const platformFee = computed(() => 20); // from config

function fmt(v) { return (v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 }); }
function fmtDate(d) { if (!d) return '-'; return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }); }
function channelLabel(c) { return { bank: '银行', alipay: '支付宝', wechat: '微信', paypal: 'PayPal' }[c] || c; }
function wdStatusTag(s) { return { pending_review: 'warning', pending: 'warning', processing: 'primary', completed: 'success', failed: 'danger', rejected: 'danger', cancelled: 'info' }[s] || ''; }
function wdStatusLabel(s) { return { pending_review: '待审核', pending: '处理中', processing: '打款中', completed: '已完成', failed: '失败', rejected: '已拒绝', cancelled: '已取消' }[s] || s; }

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
    try { const { data: r } = await api.initEarnings(); if (r.success) { ElMessage.success('收益账户已开通'); loadEarnings(); } }
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
        if (r.success) { ElMessage.success('提现申请已提交'); wdVisible.value = false; loadWithdrawals(); loadEarnings(); }
    } catch {} finally { wdSubmitting.value = false; }
}

async function saveTaxInfo() {
    taxSaving.value = true;
    try {
        await api.updateTaxInfo(taxForm);
        ElMessage.success('税务信息已更新');
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
.stat-value.primary { color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
