<template>
    <div class="earnings-portal">
        <!-- 页面标题 -->
        <div class="page-header">
            <div>
                <h2>收益账户</h2>
                <p class="text-muted">查看收益明细、发起提现、管理收款账户</p>
            </div>
            <el-button type="primary" @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <!-- 余额概览卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card available">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.available_balance) }}</div>
                            <div class="balance-label">可提现余额</div>
                        </div>
                        <el-icon :size="40" color="#67c23a"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card pending">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.pending_balance) }}</div>
                            <div class="balance-label">冻结中（T+30）</div>
                        </div>
                        <el-icon :size="40" color="#e6a23c"><Timer /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card withdrawn">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.total_withdrawn) }}</div>
                            <div class="balance-label">已提现总额</div>
                        </div>
                        <el-icon :size="40" color="#409eff"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card earned">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.total_earned) }}</div>
                            <div class="balance-label">累计收益</div>
                        </div>
                        <el-icon :size="40" color="#b37feb"><Money /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 推广业绩卡片（仅当有代理数据时显示） -->
        <el-card v-if="promotionStats.agent_code" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Link /></el-icon> 推广业绩</span>
                    <el-tag size="small" type="warning" v-if="promotionStats.level_label">{{ promotionStats.level_label }}</el-tag>
                </div>
            </template>
            <el-row :gutter="16">
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">{{ promotionStats.active_subscriptions }}</div>
                        <div class="promo-label">活跃订阅</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">{{ promotionStats.total_referrals }}</div>
                        <div class="promo-label">累计推广</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">¥{{ formatMoney(promotionStats.total_earned) }}</div>
                        <div class="promo-label">推广收益</div>
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <!-- 月度收益趋势 -->
        <el-card class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><TrendCharts /></el-icon> 月度收益趋势</span>
                </div>
            </template>
            <div v-if="monthlyTrend.length" class="trend-table">
                <el-table :data="monthlyTrend" stripe size="small" max-height="300">
                    <el-table-column prop="period" label="月份" width="120" />
                    <el-table-column label="收益金额" min-width="150">
                        <template #default="{ row }">
                            <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="count" label="结算笔数" width="100" />
                </el-table>
            </div>
            <el-empty v-else description="暂无收益数据" />
        </el-card>

        <!-- 主 tabs：收益明细 / 提现记录 / 发起提现 -->
        <el-card>
            <el-tabs v-model="activeTab">
                <!-- Tab 1: 收益明细 -->
                <el-tab-pane label="收益明细" name="commissions">
                    <div class="mb-3 flex items-center gap-2">
                        <el-button size="small" :type="commissionFilter === '' ? 'primary' : ''" @click="commissionFilter = ''; loadCommissions()">全部</el-button>
                        <el-button size="small" :type="commissionFilter === 'frozen' ? 'primary' : ''" @click="commissionFilter = 'frozen'; loadCommissions()">冻结中</el-button>
                        <el-button size="small" :type="commissionFilter === 'released' ? 'primary' : ''" @click="commissionFilter = 'released'; loadCommissions()">可提现</el-button>
                        <el-button size="small" icon="Download" @click="exportCommissions" class="ml-auto">导出 CSV</el-button>
                    </div>
                    <el-table v-if="commissions.length" :data="commissions" stripe v-loading="loadingCommissions">
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">
                                <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="rate" label="佣金率" width="80">
                            <template #default="{ row }">{{ row.rate }}%</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'released' ? 'success' : row.status === 'frozen' ? 'warning' : 'info'" size="small">
                                    {{ row.status_label }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="frozen_until" label="解冻日期" width="120">
                            <template #default="{ row }">{{ row.frozen_until || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="settled_at" label="入账时间" width="180" />
                    </el-table>
                    <el-empty v-else description="暂无收益明细" />

                    <!-- 分页 -->
                    <div class="pagination-wrap" v-if="commissionsPagination.total > commissionsPagination.per_page">
                        <el-pagination
                            v-model:current-page="commissionsPagination.current_page"
                            :page-size="commissionsPagination.per_page"
                            :total="commissionsPagination.total"
                            layout="prev, pager, next"
                            @current-change="loadCommissions"
                            small
                        />
                    </div>
                </el-tab-pane>

                <!-- Tab 2: 提现记录 -->
                <el-tab-pane label="提现记录" name="withdrawals">
                    <el-table v-if="recentWithdrawals.length" :data="recentWithdrawals" stripe>
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">
                                <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="channel_display" label="渠道" width="90" />
                        <el-table-column prop="channel_account_masked" label="收款账号" width="160" />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag
                                    :type="row.status === 'completed' ? 'success' : row.status === 'failed' || row.status === 'rejected' ? 'danger' : row.status === 'cancelled' ? 'info' : 'warning'"
                                    size="small"
                                >
                                    {{ row.status_label }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="手续费" width="80">
                            <template #default="{ row }">¥{{ formatMoney(row.fee) }}</template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="申请时间" width="170" />
                        <el-table-column prop="completed_at" label="到账时间" width="170">
                            <template #default="{ row }">{{ row.completed_at || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="失败原因" min-width="120">
                            <template #default="{ row }">
                                <span v-if="row.failure_reason" class="text-danger">{{ row.failure_reason }}</span>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="80" fixed="right">
                            <template #default="{ row }">
                                <el-button
                                    v-if="row.status === 'pending_review' || row.status === 'pending'"
                                    text type="danger"
                                    size="small"
                                    @click="handleCancel(row)"
                                >
                                    取消
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无提现记录" />
                </el-tab-pane>

                <!-- Tab 3: 发起提现 -->
                <el-tab-pane label="发起提现" name="new-withdrawal">
                    <div class="withdrawal-form-wrap">
                        <!-- 余额提示 -->
                        <el-alert
                            :title="`可提现余额：¥${formatMoney(balance.available_balance)}`"
                            :type="balance.available_balance > 0 ? 'success' : 'warning'"
                            :closable="false"
                            show-icon
                            class="mb-4"
                        />

                        <!-- 渠道选择 -->
                        <el-form ref="formRef" :model="withdrawalForm" :rules="withdrawalRules" label-width="100px">
                            <el-form-item label="收款渠道" prop="channel">
                                <el-radio-group v-model="withdrawalForm.channel" @change="onChannelChange">
                                    <el-radio-button v-for="ch in channels" :key="ch.id" :value="ch.id" :disabled="ch.id !== selectedChannel && !isChannelSelected(ch.id)">
                                        <el-icon style="vertical-align: middle; margin-right: 4px;"><component :is="ch.icon" /></el-icon>
                                        {{ ch.name }}
                                    </el-radio-button>
                                </el-radio-group>
                                <div class="channel-hint" v-if="selectedChannel && selectedChannelInfo">
                                    <div class="mt-2">
                                        <span class="text-muted">限额：¥{{ selectedChannelInfo.min_amount }} ~ ¥{{ selectedChannelInfo.max_amount }} | </span>
                                        <span class="text-muted">手续费：{{ (selectedChannelInfo.fee_rate * 100).toFixed(1) }}%{{ selectedChannel === 'paypal' ? '+$0.39' : '' }} | </span>
                                        <span class="text-muted">日限额：¥{{ formatMoney(selectedChannelInfo.daily_limit) }}</span>
                                    </div>
                                </div>
                            </el-form-item>

                            <el-form-item label="提现金额" prop="amount">
                                <el-input-number
                                    v-model="withdrawalForm.amount"
                                    :min="selectedChannelInfo?.min_amount || 1"
                                    :max="Math.min(selectedChannelInfo?.max_amount || 50000, balance.available_balance)"
                                    :step="100"
                                    :precision="2"
                                    controls-position="right"
                                    style="width: 240px"
                                />
                                <div class="fee-hint mt-1" v-if="withdrawalForm.amount > 0 && selectedChannelInfo">
                                    手续费：¥{{ formatMoney(calcFee(withdrawalForm.amount, selectedChannel)) }}，实际到账：¥{{ formatMoney(withdrawalForm.amount - calcFee(withdrawalForm.amount, selectedChannel)) }}
                                </div>
                            </el-form-item>

                            <!-- 银行卡 -->
                            <template v-if="selectedChannel === 'bank'">
                                <el-form-item label="银行名称" prop="bank_name">
                                    <el-input v-model="withdrawalForm.bank_name" placeholder="如：中国银行" style="width: 300px" />
                                </el-form-item>
                                <el-form-item label="开户支行" prop="bank_branch">
                                    <el-input v-model="withdrawalForm.bank_branch" placeholder="如：北京朝阳支行" style="width: 300px" />
                                </el-form-item>
                                <el-form-item label="开户姓名" prop="bank_account_name">
                                    <el-input v-model="withdrawalForm.bank_account_name" placeholder="持卡人姓名" style="width: 300px" />
                                </el-form-item>
                                <el-form-item label="银行卡号" prop="bank_account_no">
                                    <el-input v-model="withdrawalForm.bank_account_no" placeholder="银行卡号" style="width: 300px" maxlength="19" />
                                </el-form-item>
                            </template>

                            <!-- 支付宝 -->
                            <template v-if="selectedChannel === 'alipay'">
                                <el-form-item label="支付宝账号" prop="alipay_account">
                                    <el-input v-model="withdrawalForm.alipay_account" placeholder="手机号或邮箱" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <!-- 微信 -->
                            <template v-if="selectedChannel === 'wechat'">
                                <el-form-item label="微信账号" prop="wechat_account">
                                    <el-input v-model="withdrawalForm.wechat_account" placeholder="微信号" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <!-- PayPal -->
                            <template v-if="selectedChannel === 'paypal'">
                                <el-form-item label="PayPal邮箱" prop="paypal_email">
                                    <el-input v-model="withdrawalForm.paypal_email" placeholder="your@email.com" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <el-form-item>
                                <el-button type="primary" @click="submitWithdrawal" :loading="submitting" :disabled="balance.available_balance <= 0">
                                    提交提现申请
                                </el-button>
                                <el-button @click="resetForm">重置</el-button>
                            </el-form-item>
                        </el-form>

                        <el-divider />
                        <div class="withdrawal-notice">
                            <h4>提现须知</h4>
                            <ul>
                                <li>冻结中的收益（T+30）需等待解冻后方可提现</li>
                                <li>单笔提现金额超过 ¥5,000 需人工审核，预计 1-2 个工作日处理</li>
                                <li>提现申请提交后可在提现记录中查看进度</li>
                                <li>待审核的提现可自行取消</li>
                            </ul>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- Tab 4: 税务信息 -->
                <el-tab-pane label="📋 税务信息" name="tax">
                    <el-form :model="taxForm" label-width="120px">
                        <el-form-item label="发票抬头">
                            <el-input v-model="taxForm.invoice_title" placeholder="公司全称或个人姓名" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="税号">
                            <el-input v-model="taxForm.tax_id" placeholder="统一社会信用代码" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="公司名称">
                            <el-input v-model="taxForm.company_name" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="注册地址">
                            <el-input v-model="taxForm.address" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="注册电话">
                            <el-input v-model="taxForm.phone" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="开户银行">
                            <el-input v-model="taxForm.bank_name" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="银行账号">
                            <el-input v-model="taxForm.bank_account" style="width:400px" />
                        </el-form-item>
                        <el-form-item label="税务辖区">
                            <el-select v-model="taxForm.tax_authority" style="width:200px">
                                <el-option label="中国大陆" value="cn" />
                                <el-option label="美国" value="us" />
                                <el-option label="欧盟" value="eu" />
                                <el-option label="其他" value="other" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="税率(%)">
                            <el-input-number v-model="taxForm.tax_rate" :min="0" :max="100" :precision="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :loading="savingTax" @click="saveTaxInfo">保存税务信息</el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <!-- Tab 5: 结算日历 -->
                <el-tab-pane label="📅 结算日历" name="calendar">
                    <el-card shadow="never" class="mb-3">
                        <el-row :gutter="16">
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value warning">¥{{ formatMoney(settlementCalendar.current_pending) }}</div>
                                    <div class="cal-label">当前冻结中</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value success">¥{{ formatMoney(settlementCalendar.available_balance) }}</div>
                                    <div class="cal-label">可用余额</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value primary">¥{{ formatMoney(settlementCalendar.month_summary?.will_release_this_month) }}</div>
                                    <div class="cal-label">本月将解冻</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value">¥{{ formatMoney(settlementCalendar.month_summary?.released_this_month) }}</div>
                                    <div class="cal-label">本月已解冻</div>
                                </div>
                            </el-col>
                        </el-row>
                    </el-card>

                    <h4 class="mb-2">即将解冻 (最近30笔)</h4>
                    <el-table :data="settlementCalendar.upcoming_releases ?? []" stripe size="small" v-loading="loadingCalendar">
                        <el-table-column label="金额" width="120">
                            <template #default="{ row }">¥{{ formatMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column label="预计解冻日" width="130" prop="frozen_until" />
                        <el-table-column label="剩余天数" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.days_left <= 3 ? 'success' : row.days_left <= 7 ? 'warning' : 'info'" size="small">
                                    {{ row.days_left > 0 ? row.days_left + '天' : '今日' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!settlementCalendar.upcoming_releases?.length && !loadingCalendar" description="暂无待解冻佣金" />

                    <h4 class="mt-4 mb-2">月度解冻分布</h4>
                    <el-table :data="settlementCalendar.by_month ?? []" stripe size="small">
                        <el-table-column prop="month" label="月份" width="120" />
                        <el-table-column label="解冻金额" width="140">
                            <template #default="{ row }">¥{{ formatMoney(row.total) }}</template>
                        </el-table-column>
                        <el-table-column prop="count" label="笔数" width="80" />
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Refresh, Coin, Timer, TrendCharts, Money,
  Link,
  Wallet, ChatDotSquare, CreditCard,
} from '@element-plus/icons-vue';
import earningsPortalApi from '@/api/earningsPortal';
import withdrawalApi from '@/api/withdrawal';

// ── States ──
const loading = ref(false);
const loadingCommissions = ref(false);
const submitting = ref(false);
const activeTab = ref('commissions');
const commissionFilter = ref('');

const balance = reactive({
  available_balance: 0,
  pending_balance: 0,
  total_withdrawn: 0,
  total_earned: 0,
});
const monthlyTrend = ref([]);
const recentWithdrawals = ref([]);
const promotionStats = reactive({
  total_referrals: 0,
  active_subscriptions: 0,
  total_earned: 0,
  level: null,
  level_label: null,
  agent_code: null,
});
const commissions = ref([]);
const commissionsPagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
});
const channels = ref([]);
const selectedChannel = ref('');

// ── M3-74 税务信息 ──
const taxForm = reactive({
  invoice_title: '', tax_id: '', company_name: '',
  address: '', phone: '', bank_name: '', bank_account: '',
  tax_authority: 'cn', tax_rate: 0,
});
const savingTax = ref(false);

// ── M3-74 结算日历 ──
const settlementCalendar = reactive({
  upcoming_releases: [],
  month_summary: {},
  by_month: [],
  current_pending: 0,
  available_balance: 0,
});
const loadingCalendar = ref(false);

const withdrawalForm = reactive({
  channel: '',
  amount: 100,
  bank_name: '',
  bank_branch: '',
  bank_account_name: '',
  bank_account_no: '',
  alipay_account: '',
  wechat_account: '',
  paypal_email: '',
});

const formRef = ref(null);

// ── Computed ──
const selectedChannelInfo = computed(() => {
  return channels.value.find(c => c.id === selectedChannel.value) || null;
});

// ── Form Rules ──
const withdrawalRules = computed(() => {
  const rules = {
    channel: [{ required: true, message: '请选择收款渠道', trigger: 'change' }],
    amount: [
      { required: true, message: '请输入提现金额', trigger: 'blur' },
      { type: 'number', min: 1, message: '金额必须大于 0', trigger: 'blur' },
    ],
  };

  if (selectedChannel.value === 'bank') {
    rules.bank_name = [{ required: true, message: '请输入银行名称', trigger: 'blur' }];
    rules.bank_account_name = [{ required: true, message: '请输入开户姓名', trigger: 'blur' }];
    rules.bank_account_no = [{ required: true, message: '请输入银行卡号', trigger: 'blur' }];
  } else if (selectedChannel.value === 'alipay') {
    rules.alipay_account = [{ required: true, message: '请输入支付宝账号', trigger: 'blur' }];
  } else if (selectedChannel.value === 'wechat') {
    rules.wechat_account = [{ required: true, message: '请输入微信账号', trigger: 'blur' }];
  } else if (selectedChannel.value === 'paypal') {
    rules.paypal_email = [{ required: true, message: '请输入PayPal邮箱', trigger: 'blur' }, { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }];
  }

  return rules;
});

// ── Methods ──
function formatMoney(val) {
  return (parseFloat(val) || 0).toFixed(2);
}

function calcFee(amount, channel) {
  const ch = channels.value.find(c => c.id === channel);
  if (!ch) return 0;
  let fee = amount * ch.fee_rate;
  if (channel === 'paypal') fee += 0.39;
  return Math.round(fee * 100) / 100;
}

function isChannelSelected(chId) {
  return withdrawalForm.channel === chId;
}

function onChannelChange(ch) {
  selectedChannel.value = ch;
}

// ── M3-74 导出佣金 ──
async function exportCommissions() {
  try {
    const res = await earningsPortalApi.exportCommissions({ status: commissionFilter.value || undefined });
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'commissions_export_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('导出成功');
  } catch (e) {
    ElMessage.error('导出失败');
  }
}

// ── M3-74 税务信息 ──
async function loadTaxInfo() {
  try {
    const res = await earningsPortalApi.getTaxInfo();
    if (res.data?.data) Object.assign(taxForm, res.data.data);
  } catch (e) {}
}

async function saveTaxInfo() {
  savingTax.value = true;
  try {
    await earningsPortalApi.saveTaxInfo({ ...taxForm });
    ElMessage.success('税务信息已保存');
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败');
  } finally {
    savingTax.value = false;
  }
}

// ── M3-74 结算日历 ──
async function loadSettlementCalendar() {
  loadingCalendar.value = true;
  try {
    const res = await earningsPortalApi.settlementCalendar();
    if (res.data?.data) Object.assign(settlementCalendar, res.data.data);
  } catch (e) {
    console.error(e);
  } finally {
    loadingCalendar.value = false;
  }
}

async function loadAll() {
  loading.value = true;
  try {
    const [dashboardRes, channelsRes] = await Promise.all([
      earningsPortalApi.dashboard(),
      withdrawalApi.userChannels(),
    ]);

    const data = dashboardRes.data.data;
    Object.assign(balance, data.balance);
    monthlyTrend.value = data.monthly_trend || [];
    recentWithdrawals.value = data.recent_withdrawals || [];
    Object.assign(promotionStats, data.promotion_stats);

    channels.value = channelsRes.data.data || [];
  } catch (e) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
}

async function loadCommissions() {
  loadingCommissions.value = true;
  try {
    const res = await earningsPortalApi.commissions({
      status: commissionFilter.value || undefined,
      per_page: commissionsPagination.per_page,
      page: commissionsPagination.current_page,
    });
    const data = res.data.data;
    commissions.value = data.data || [];
    commissionsPagination.current_page = data.current_page;
    commissionsPagination.per_page = data.per_page;
    commissionsPagination.total = data.total;
  } catch (e) {
    ElMessage.error('加载收益明细失败');
  } finally {
    loadingCommissions.value = false;
  }
}

async function submitWithdrawal() {
  if (!formRef.value) return;
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  submitting.value = true;
  try {
    const payload = {
      channel: withdrawalForm.channel,
      amount: withdrawalForm.amount,
    };

    // Add channel-specific fields
    switch (withdrawalForm.channel) {
      case 'bank':
        payload.bank_name = withdrawalForm.bank_name;
        payload.bank_branch = withdrawalForm.bank_branch;
        payload.bank_account_name = withdrawalForm.bank_account_name;
        payload.bank_account_no = withdrawalForm.bank_account_no;
        break;
      case 'alipay':
        payload.alipay_account = withdrawalForm.alipay_account;
        break;
      case 'wechat':
        payload.wechat_account = withdrawalForm.wechat_account;
        break;
      case 'paypal':
        payload.paypal_email = withdrawalForm.paypal_email;
        break;
    }

    const res = await withdrawalApi.requestWithdrawal(payload);
    ElMessage.success(res.data.message || '提现申请已提交');
    resetForm();
    // Refresh data
    await loadAll();
    activeTab.value = 'withdrawals';
  } catch (e) {
    const msg = e.response?.data?.message || '提现申请失败';
    ElMessage.error(msg);
  } finally {
    submitting.value = false;
  }
}

function resetForm() {
  withdrawalForm.channel = '';
  withdrawalForm.amount = 100;
  withdrawalForm.bank_name = '';
  withdrawalForm.bank_branch = '';
  withdrawalForm.bank_account_name = '';
  withdrawalForm.bank_account_no = '';
  withdrawalForm.alipay_account = '';
  withdrawalForm.wechat_account = '';
  withdrawalForm.paypal_email = '';
  selectedChannel.value = '';
  formRef.value?.resetFields();
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm(`确定取消这笔 ¥${formatMoney(row.amount)} 的提现申请？`, '确认取消', {
      type: 'warning',
      confirmButtonText: '确定取消',
      cancelButtonText: '再想想',
    });
    await withdrawalApi.cancelWithdrawal(row.id);
    ElMessage.success('提现已取消');
    await loadAll();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error('取消失败');
    }
  }
}

// ── Lifecycle ──
onMounted(() => {
  loadAll();
  loadTaxInfo();
  loadSettlementCalendar();
});
</script>

<style scoped>
.earnings-portal {
  max-width: 1200px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
}

.text-muted {
  color: #909399;
  font-size: 13px;
  margin: 4px 0 0;
}

.mb-4 {
  margin-bottom: 16px;
}

.mb-3 {
  margin-bottom: 12px;
}

.mt-2 {
  margin-top: 8px;
}

.mt-1 {
  margin-top: 4px;
}

.balance-card .balance-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.balance-info {
  display: flex;
  flex-direction: column;
}

.balance-value {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.2;
}

.balance-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.balance-card.available .balance-value { color: #67c23a; }
.balance-card.pending .balance-value { color: #e6a23c; }
.balance-card.withdrawn .balance-value { color: #409eff; }
.balance-card.earned .balance-value { color: #b37feb; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header span {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.promo-stat {
  text-align: center;
  padding: 12px 0;
}

.promo-value {
  font-size: 24px;
  font-weight: 700;
  color: #409eff;
  margin-bottom: 4px;
}

.promo-label {
  font-size: 13px;
  color: #909399;
}

.amount-text {
  font-weight: 600;
  color: #e6a23c;
}

.text-danger {
  color: #f56c6c;
}
.ml-auto { margin-left: auto; }
.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }
.mt-4 { margin-top: 16px; }
.mb-2 { margin-bottom: 8px; }

.cal-stat { text-align: center; padding: 8px 0; }
.cal-value { font-size: 22px; font-weight: 700; }
.cal-value.warning { color: #e6a23c; }
.cal-value.success { color: #67c23a; }
.cal-value.primary { color: #409eff; }
.cal-label { font-size: 13px; color: #909399; margin-top: 4px; }

.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: center;
}

.withdrawal-form-wrap {
  max-width: 600px;
}

.channel-hint {
  font-size: 12px;
}

.fee-hint {
  font-size: 12px;
  color: #909399;
}

.withdrawal-notice {
  font-size: 13px;
  color: #606266;
}

.withdrawal-notice h4 {
  margin: 0 0 8px;
  font-size: 14px;
}

.withdrawal-notice ul {
  padding-left: 20px;
  line-height: 2;
}

.withdrawal-notice li {
  list-style: disc;
}

.trend-table {
  max-height: 300px;
  overflow-y: auto;
}
</style>
