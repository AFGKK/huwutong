<template>
    <div class="earnings-portal">
        <!-- 页面标题 -->
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.earnings_title') }}</h2>
                <p class="text-muted">{{ $t('portal.earnings_subtitle') }}</p>
            </div>
            <el-button type="primary" @click="loadAll" :loading="loading" :icon="Refresh">{{ $t('portal.refresh') }}</el-button>
        </div>

        <!-- 余额概览卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card available">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.available_balance) }}</div>
                            <div class="balance-label">{{ $t('portal.available_balance') }}</div>
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
                            <div class="balance-label">{{ $t('portal.frozen_t30') }}</div>
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
                            <div class="balance-label">{{ $t('portal.total_withdrawn') }}</div>
                        </div>
                        <el-icon :size="40" color="#0f172a"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="balance-card earned">
                    <div class="balance-content">
                        <div class="balance-info">
                            <div class="balance-value">¥{{ formatMoney(balance.total_earned) }}</div>
                            <div class="balance-label">{{ $t('portal.total_earned') }}</div>
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
                    <span><el-icon><Link /></el-icon> {{ $t('portal.promo_perf') }}</span>
                    <el-tag size="small" type="warning" v-if="promotionStats.level_label">{{ promotionStats.level_label }}</el-tag>
                </div>
            </template>
            <el-row :gutter="16">
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">{{ promotionStats.active_subscriptions }}</div>
                        <div class="promo-label">{{ $t('portal.active_subs') }}</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">{{ promotionStats.total_referrals }}</div>
                        <div class="promo-label">{{ $t('portal.total_referrals') }}</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="promo-stat">
                        <div class="promo-value">¥{{ formatMoney(promotionStats.total_earned) }}</div>
                        <div class="promo-label">{{ $t('portal.promo_earnings') }}</div>
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <!-- 月度收益趋势 -->
        <el-card class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><TrendCharts /></el-icon> {{ $t('portal.monthly_trend') }}</span>
                </div>
            </template>
            <div v-if="monthlyTrend.length" class="trend-table">
                <el-table :data="monthlyTrend" stripe size="small" max-height="300">
                    <el-table-column prop="period" :label="$t('portal.month')" width="120" />
                    <el-table-column :label="$t('portal.earnings_amount')" min-width="150">
                        <template #default="{ row }">
                            <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="count" :label="$t('portal.settle_count')" width="100" />
                </el-table>
            </div>
            <el-empty v-else :description="$t('portal.no_earnings_data')" />
        </el-card>

        <!-- 主 tabs：收益明细 / 提现记录 / 发起提现 -->
        <el-card>
            <el-tabs v-model="activeTab">
                <!-- Tab 1: 收益明细 -->
                <el-tab-pane :label="$t('portal.commissions_tab')" name="commissions">
                    <div class="mb-3 flex items-center gap-2">
                        <el-button size="small" :type="commissionFilter === '' ? 'primary' : ''" @click="commissionFilter = ''; loadCommissions()">{{ $t('portal.all') }}</el-button>
                        <el-button size="small" :type="commissionFilter === 'frozen' ? 'primary' : ''" @click="commissionFilter = 'frozen'; loadCommissions()">{{ $t('portal.frozen_filter') }}</el-button>
                        <el-button size="small" :type="commissionFilter === 'released' ? 'primary' : ''" @click="commissionFilter = 'released'; loadCommissions()">{{ $t('portal.withdrawable') }}</el-button>
                        <el-button size="small" icon="Download" @click="exportCommissions" class="ml-auto">{{ $t('portal.export_csv') }}</el-button>
                    </div>
                    <el-table v-if="commissions.length" :data="commissions" stripe v-loading="loadingCommissions">
                        <el-table-column :label="$t('portal.amount')" width="120">
                            <template #default="{ row }">
                                <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="rate" :label="$t('portal.commission_rate')" width="80">
                            <template #default="{ row }">{{ row.rate }}%</template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'released' ? 'success' : row.status === 'frozen' ? 'warning' : 'info'" size="small">
                                    {{ commissionStatusLabel(row.status, row.status_label) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="frozen_until" :label="$t('portal.unfreeze_date')" width="120">
                            <template #default="{ row }">{{ formatDate(row.frozen_until) }}</template>
                        </el-table-column>
                        <el-table-column prop="settled_at" :label="$t('portal.settled_at')" width="180">
                            <template #default="{ row }">{{ formatDate(row.settled_at) }}</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="$t('portal.no_commissions')" />

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
                <el-tab-pane :label="$t('portal.withdrawals_tab')" name="withdrawals">
                    <el-table v-if="recentWithdrawals.length" :data="recentWithdrawals" stripe>
                        <el-table-column :label="$t('portal.amount')" width="120">
                            <template #default="{ row }">
                                <span class="amount-text">¥{{ formatMoney(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="channel_display" :label="$t('portal.channel')" width="90" />
                        <el-table-column prop="channel_account_masked" :label="$t('portal.payout_account')" width="160" />
                        <el-table-column :label="$t('portal.status')" width="100">
                            <template #default="{ row }">
                                <el-tag
                                    :type="row.status === 'completed' ? 'success' : row.status === 'failed' || row.status === 'rejected' ? 'danger' : row.status === 'cancelled' ? 'info' : 'warning'"
                                    size="small"
                                >
                                    {{ withdrawalStatusLabel(row.status, row.status_label) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.fee')" width="80">
                            <template #default="{ row }">¥{{ formatMoney(row.fee) }}</template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="$t('portal.applied_at')" width="170">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column prop="completed_at" :label="$t('portal.completed_at')" width="170">
                            <template #default="{ row }">{{ formatDate(row.completed_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.fail_reason')" min-width="120">
                            <template #default="{ row }">
                                <span v-if="row.failure_reason" class="text-danger">{{ row.failure_reason }}</span>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.actions')" width="80" fixed="right">
                            <template #default="{ row }">
                                <el-button
                                    v-if="row.status === 'pending_review' || row.status === 'pending'"
                                    text type="danger"
                                    size="small"
                                    @click="handleCancel(row)"
                                >
                                    {{ $t('actions.cancel') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="$t('portal.no_withdrawals')" />
                </el-tab-pane>

                <!-- Tab 3: 发起提现 -->
                <el-tab-pane :label="$t('portal.new_withdrawal_tab')" name="new-withdrawal">
                    <div class="withdrawal-form-wrap">
                        <!-- 余额提示 -->
                        <el-alert
                            :title="$t('portal.available_balance_title', { amount: formatMoney(balance.available_balance) })"
                            :type="balance.available_balance > 0 ? 'success' : 'warning'"
                            :closable="false"
                            show-icon
                            class="mb-4"
                        />

                        <!-- 渠道选择 -->
                        <el-form ref="formRef" :model="withdrawalForm" :rules="withdrawalRules" label-width="100px">
                            <el-form-item :label="$t('portal.payout_channel')" prop="channel">
                                <el-radio-group v-model="withdrawalForm.channel" @change="onChannelChange">
                                    <el-radio-button v-for="ch in channels" :key="ch.id" :value="ch.id" :disabled="ch.id !== selectedChannel && !isChannelSelected(ch.id)">
                                        <el-icon style="vertical-align: middle; margin-right: 4px;"><component :is="ch.icon" /></el-icon>
                                        {{ ch.name }}
                                    </el-radio-button>
                                </el-radio-group>
                                <div class="channel-hint" v-if="selectedChannel && selectedChannelInfo">
                                    <div class="mt-2">
                                        <span class="text-muted">{{ $t('portal.limit_range', { min: selectedChannelInfo.min_amount, max: selectedChannelInfo.max_amount }) }} | </span>
                                        <span class="text-muted">{{ $t('portal.fee_rate_label', { rate: (selectedChannelInfo.fee_rate * 100).toFixed(1) }) }}{{ selectedChannel === 'paypal' ? '+$0.39' : '' }} | </span>
                                        <span class="text-muted">{{ $t('portal.daily_limit', { amount: formatMoney(selectedChannelInfo.daily_limit) }) }}</span>
                                    </div>
                                </div>
                            </el-form-item>

                            <el-form-item :label="$t('portal.withdraw_amount')" prop="amount">
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
                                    {{ $t('portal.fee_net', {
                                        fee: formatMoney(calcFee(withdrawalForm.amount, selectedChannel)),
                                        net: formatMoney(withdrawalForm.amount - calcFee(withdrawalForm.amount, selectedChannel)),
                                    }) }}
                                </div>
                            </el-form-item>

                            <!-- 银行卡 -->
                            <template v-if="selectedChannel === 'bank'">
                                <el-form-item :label="$t('portal.bank_name_field')" prop="bank_name">
                                    <el-input v-model="withdrawalForm.bank_name" :placeholder="$t('portal.bank_name_eg')" style="width: 300px" />
                                </el-form-item>
                                <el-form-item :label="$t('portal.bank_branch')" prop="bank_branch">
                                    <el-input v-model="withdrawalForm.bank_branch" :placeholder="$t('portal.bank_branch_eg')" style="width: 300px" />
                                </el-form-item>
                                <el-form-item :label="$t('portal.account_name')" prop="bank_account_name">
                                    <el-input v-model="withdrawalForm.bank_account_name" :placeholder="$t('portal.cardholder_ph')" style="width: 300px" />
                                </el-form-item>
                                <el-form-item :label="$t('portal.bank_card_no')" prop="bank_account_no">
                                    <el-input v-model="withdrawalForm.bank_account_no" :placeholder="$t('portal.bank_account_ph')" style="width: 300px" maxlength="19" />
                                </el-form-item>
                            </template>

                            <!-- 支付宝 -->
                            <template v-if="selectedChannel === 'alipay'">
                                <el-form-item :label="$t('portal.alipay_account')" prop="alipay_account">
                                    <el-input v-model="withdrawalForm.alipay_account" :placeholder="$t('portal.phone_or_email')" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <!-- 微信 -->
                            <template v-if="selectedChannel === 'wechat'">
                                <el-form-item :label="$t('portal.wechat_account')" prop="wechat_account">
                                    <el-input v-model="withdrawalForm.wechat_account" :placeholder="$t('portal.wechat_id_ph')" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <!-- PayPal -->
                            <template v-if="selectedChannel === 'paypal'">
                                <el-form-item :label="$t('portal.paypal_email')" prop="paypal_email">
                                    <el-input v-model="withdrawalForm.paypal_email" placeholder="your@email.com" style="width: 300px" />
                                </el-form-item>
                            </template>

                            <el-form-item>
                                <el-button type="primary" @click="submitWithdrawal" :loading="submitting" :disabled="balance.available_balance <= 0">
                                    {{ $t('portal.submit_withdrawal') }}
                                </el-button>
                                <el-button @click="resetForm">{{ $t('actions.reset') }}</el-button>
                            </el-form-item>
                        </el-form>

                        <el-divider />
                        <div class="withdrawal-notice">
                            <h4>{{ $t('portal.withdrawal_notes_title') }}</h4>
                            <ul>
                                <li>{{ $t('portal.withdrawal_note_1') }}</li>
                                <li>{{ $t('portal.withdrawal_note_2') }}</li>
                                <li>{{ $t('portal.withdrawal_note_3') }}</li>
                                <li>{{ $t('portal.withdrawal_note_4') }}</li>
                            </ul>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- Tab 4: 税务信息 -->
                <el-tab-pane :label="$t('portal.tax_tab')" name="tax">
                    <el-form :model="taxForm" label-width="120px">
                        <el-form-item :label="$t('portal.title_name')">
                            <el-input v-model="taxForm.invoice_title" :placeholder="$t('portal.invoice_title_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.tax_no')">
                            <el-input v-model="taxForm.tax_id" :placeholder="$t('portal.tax_no_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.company_name')">
                            <el-input v-model="taxForm.company_name" :placeholder="$t('portal.company_name_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.reg_address')">
                            <el-input v-model="taxForm.address" :placeholder="$t('portal.address_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.reg_phone')">
                            <el-input v-model="taxForm.phone" :placeholder="$t('portal.phone_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.opening_bank')">
                            <el-input v-model="taxForm.bank_name" :placeholder="$t('portal.bank_name_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.bank_account')">
                            <el-input v-model="taxForm.bank_account" :placeholder="$t('portal.bank_account_ph')" style="width:400px" />
                        </el-form-item>
                        <el-form-item :label="$t('portal.tax_jurisdiction')">
                            <el-select v-model="taxForm.tax_authority" style="width:200px">
                                <el-option :label="$t('portal.jurisdiction_cn')" value="cn" />
                                <el-option :label="$t('portal.jurisdiction_us')" value="us" />
                                <el-option :label="$t('portal.jurisdiction_eu')" value="eu" />
                                <el-option :label="$t('portal.jurisdiction_other')" value="other" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('portal.tax_rate')">
                            <el-input-number v-model="taxForm.tax_rate" :min="0" :max="100" :precision="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :loading="savingTax" @click="saveTaxInfo">{{ $t('portal.save_tax') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <!-- Tab 5: 结算日历 -->
                <el-tab-pane :label="$t('portal.calendar_tab')" name="calendar">
                    <el-card shadow="never" class="mb-3">
                        <el-row :gutter="16">
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value warning">¥{{ formatMoney(settlementCalendar.current_pending) }}</div>
                                    <div class="cal-label">{{ $t('portal.currently_frozen') }}</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value success">¥{{ formatMoney(settlementCalendar.available_balance) }}</div>
                                    <div class="cal-label">{{ $t('portal.usable_balance') }}</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value primary">¥{{ formatMoney(settlementCalendar.month_summary?.will_release_this_month) }}</div>
                                    <div class="cal-label">{{ $t('portal.release_this_month') }}</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="cal-stat">
                                    <div class="cal-value">¥{{ formatMoney(settlementCalendar.month_summary?.released_this_month) }}</div>
                                    <div class="cal-label">{{ $t('portal.released_this_month') }}</div>
                                </div>
                            </el-col>
                        </el-row>
                    </el-card>

                    <h4 class="mb-2">{{ $t('portal.upcoming_releases') }}</h4>
                    <el-table :data="settlementCalendar.upcoming_releases ?? []" stripe size="small" v-loading="loadingCalendar">
                        <el-table-column :label="$t('portal.amount')" width="120">
                            <template #default="{ row }">¥{{ formatMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.expected_release')" width="130" prop="frozen_until">
                            <template #default="{ row }">{{ formatDate(row.frozen_until) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('portal.days_left')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.days_left <= 3 ? 'success' : row.days_left <= 7 ? 'warning' : 'info'" size="small">
                                    {{ row.days_left > 0 ? $t('portal.days_n', { n: row.days_left }) : $t('portal.today') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!settlementCalendar.upcoming_releases?.length && !loadingCalendar" :description="$t('portal.no_pending_release')" />

                    <h4 class="mt-4 mb-2">{{ $t('portal.monthly_release') }}</h4>
                    <el-table :data="settlementCalendar.by_month ?? []" stripe size="small">
                        <el-table-column prop="month" :label="$t('portal.month')" width="120" />
                        <el-table-column :label="$t('portal.release_amount')" width="140">
                            <template #default="{ row }">¥{{ formatMoney(row.total) }}</template>
                        </el-table-column>
                        <el-table-column prop="count" :label="$t('portal.count_col')" width="80" />
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Refresh, Coin, Timer, TrendCharts, Money,
  Link,
  Wallet, ChatDotSquare, CreditCard,
} from '@element-plus/icons-vue';
import earningsPortalApi from '@/api/earningsPortal';
import withdrawalApi from '@/api/withdrawal';

const { t, locale } = useI18n();

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

const taxForm = reactive({
  invoice_title: '', tax_id: '', company_name: '',
  address: '', phone: '', bank_name: '', bank_account: '',
  tax_authority: 'cn', tax_rate: 0,
});
const savingTax = ref(false);

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

const selectedChannelInfo = computed(() => {
  return channels.value.find(c => c.id === selectedChannel.value) || null;
});

const withdrawalRules = computed(() => {
  const rules = {
    channel: [{ required: true, message: t('portal.title_required'), trigger: 'change' }],
    amount: [
      { required: true, message: t('portal.name_required'), trigger: 'blur' },
      { type: 'number', min: 1, message: t('portal.name_required'), trigger: 'blur' },
    ],
  };

  if (selectedChannel.value === 'bank') {
    rules.bank_name = [{ required: true, message: t('portal.name_required'), trigger: 'blur' }];
    rules.bank_account_name = [{ required: true, message: t('portal.cardholder_required'), trigger: 'blur' }];
    rules.bank_account_no = [{ required: true, message: t('portal.card_required'), trigger: 'blur' }];
  } else if (selectedChannel.value === 'alipay') {
    rules.alipay_account = [{ required: true, message: t('portal.name_required'), trigger: 'blur' }];
  } else if (selectedChannel.value === 'wechat') {
    rules.wechat_account = [{ required: true, message: t('portal.name_required'), trigger: 'blur' }];
  } else if (selectedChannel.value === 'paypal') {
    rules.paypal_email = [
      { required: true, message: t('portal.name_required'), trigger: 'blur' },
      { type: 'email', message: t('portal.email_invalid'), trigger: 'blur' },
    ];
  }

  return rules;
});

function formatMoney(val) {
  return (parseFloat(val) || 0).toFixed(2);
}

function formatDate(value) {
  if (!value || value === '-') return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return date.toLocaleString(loc, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function commissionStatusLabel(status, fallback) {
  const map = {
    released: t('portal.withdrawable'),
    frozen: t('portal.frozen_filter'),
  };
  return map[status] || fallback || status;
}

function withdrawalStatusLabel(status, fallback) {
  const map = {
    completed: t('portal.pay_success'),
    failed: t('portal.pay_fail'),
    rejected: t('portal.pay_fail'),
    cancelled: t('portal.cancelled_ok'),
    pending: t('portal.exp_pending'),
    pending_review: t('portal.exp_pending'),
  };
  return map[status] || fallback || status;
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
    ElMessage.success(t('portal.export_ok'));
  } catch (e) {
    ElMessage.error(t('portal.export_fail'));
  }
}

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
    ElMessage.success(t('portal.tax_saved'));
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('portal.save_failed'));
  } finally {
    savingTax.value = false;
  }
}

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
    ElMessage.error(t('portal.earnings_load_failed'));
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
    ElMessage.error(t('portal.commissions_load_failed'));
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
    ElMessage.success(res.data.message || t('portal.withdrawal_submitted'));
    resetForm();
    await loadAll();
    activeTab.value = 'withdrawals';
  } catch (e) {
    const msg = e.response?.data?.message || t('portal.submit_failed');
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
    await ElMessageBox.confirm(t('portal.cancel_order_confirm'), t('actions.confirm'), {
      type: 'warning',
      confirmButtonText: t('actions.confirm'),
      cancelButtonText: t('actions.cancel'),
    });
    await withdrawalApi.cancelWithdrawal(row.id);
    ElMessage.success(t('portal.withdrawal_cancelled'));
    await loadAll();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('portal.cancel_failed'));
    }
  }
}

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
.balance-card.withdrawn .balance-value { color: #0f172a; }
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
  color: #0f172a;
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
.cal-value.primary { color: #0f172a; }
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
