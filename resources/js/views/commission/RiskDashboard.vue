<template>
  <div class="commission-risk-manager">
    <!-- 风控概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('risk_dashboard_page.stats.negative_balance_accounts') }}</div>
            <div class="stat-value" :class="{ 'text-danger': stats.negative_balance_accounts > 0 }">{{ stats.negative_balance_accounts || 0 }}</div>
            <div class="stat-sub" v-if="stats.total_negative_balance">{{ t('risk_dashboard_page.stats.total_negative', { amount: (stats.total_negative_balance || 0).toFixed(2) }) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('risk_dashboard_page.stats.pending_frozen') }}</div>
            <div class="stat-value">¥{{ (stats.pending_frozen_amount || 0).toLocaleString() }}</div>
            <div class="stat-sub">{{ t('risk_dashboard_page.stats.t30_protection') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('risk_dashboard_page.stats.pending_review_payouts') }}</div>
            <div class="stat-value" :class="{ 'text-warning': stats.pending_review_payouts > 0 }">{{ stats.pending_review_payouts || 0 }}</div>
            <div class="stat-sub" v-if="stats.pending_review_amount">{{ t('risk_dashboard_page.stats.pending_review_amount', { amount: (stats.pending_review_amount || 0).toFixed(2) }) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('risk_dashboard_page.stats.risk_events') }}</div>
            <div class="stat-value">{{ stats.today_risk_events || 0 }}</div>
            <div class="stat-sub">{{ t('risk_dashboard_page.stats.today_refund_clawback') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作工具栏 -->
    <el-card class="mb-4">
      <div class="flex justify-between items-center">
        <span class="font-semibold text-base">{{ t('risk_dashboard_page.toolbar.title') }}</span>
        <div>
          <el-button @click="runTask('release_freezes')" :loading="runningTask === 'release_freezes'" type="primary">
            {{ t('risk_dashboard_page.buttons.release_freezes') }}
          </el-button>
          <el-button @click="runTask('enforce_recovery')" :loading="runningTask === 'enforce_recovery'" type="warning">
            {{ t('risk_dashboard_page.buttons.enforce_recovery') }}
          </el-button>
          <el-button @click="fetchDashboard" :loading="loading" circle>
            <el-icon><Refresh /></el-icon>
          </el-button>
        </div>
      </div>
    </el-card>

    <el-tabs v-model="activeTab">
      <!-- 负余额账户 -->
      <el-tab-pane :label="t('risk_dashboard_page.tabs.negative_balance')" name="negative_balance">
        <el-table :data="negativeAccounts" v-loading="loadingNegative" stripe>
          <el-table-column :label="t('commission_page.cols.user')" width="180">
            <template #default="{ row }">{{ row.user?.name || 'N/A' }}<br><small>{{ row.user?.email }}</small></template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.negative_balance')" width="120" align="right">
            <template #default="{ row }"><span class="text-danger">¥{{ row.negative_balance.toFixed(2) }}</span></template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.available_balance')" width="120" align="right">
            <template #default="{ row }">¥{{ row.available_balance.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.pending_balance')" width="120" align="right">
            <template #default="{ row }">¥{{ row.pending_balance.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.days_overdue')" width="100">
            <template #default="{ row }">
              <el-tag v-if="row.days_overdue > 30" type="danger" size="small">{{ t('risk_dashboard_page.days_unit', { n: row.days_overdue }) }}</el-tag>
              <el-tag v-else-if="row.days_overdue > 0" type="warning" size="small">{{ t('risk_dashboard_page.days_unit', { n: row.days_overdue }) }}</el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.account_status')" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : row.status === 'frozen' ? 'danger' : 'info'" size="small">
                {{ accountStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('commission_page.cols.actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewNegativeDetail(row)">{{ t('actions.view') }}</el-button>
              <el-button size="small" type="warning" plain @click="openClearDialog(row)" :disabled="row.negative_balance <= 0">
                {{ t('risk_dashboard_page.buttons.clear_negative') }}
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="negativeTotal" :page-size="20"
          @current-change="page => fetchNegativeAccounts(page)" />
      </el-tab-pane>

      <!-- 待审核提现 -->
      <el-tab-pane :label="t('risk_dashboard_page.tabs.pending_review')" name="pending_review">
        <el-table :data="pendingReviewPayouts" v-loading="loadingReview" stripe>
          <el-table-column :label="t('commission_page.cols.agent')" width="160">
            <template #default="{ row }">{{ row.agent?.user?.name || 'N/A' }}<br><small>{{ row.agent?.agent_code }}</small></template>
          </el-table-column>
          <el-table-column :label="t('commission_page.cols.amount')" width="120" align="right">
            <template #default="{ row }">¥{{ row.amount.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('commission_page.cols.fee')" width="100" align="right">
            <template #default="{ row }">¥{{ row.fee.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.net_amount')" width="120" align="right">
            <template #default="{ row }"><strong>¥{{ row.net_amount.toFixed(2) }}</strong></template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.payout_method')" width="100">
            <template #default="{ row }">{{ methodLabel(row.payout_method) }}</template>
          </el-table-column>
          <el-table-column :label="t('risk_dashboard_page.cols.requested_at')" width="160">
            <template #default="{ row }">{{ formatDate(row.requested_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('commission_page.cols.actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="success" @click="reviewPayout(row, 'approve')">{{ t('actions.approve') }}</el-button>
              <el-button size="small" type="danger" @click="reviewPayout(row, 'reject')">{{ t('actions.reject') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="reviewTotal" :page-size="20"
          @current-change="page => fetchPendingReview(page)" />
      </el-tab-pane>

      <!-- 风控日志 -->
      <el-tab-pane :label="t('risk_dashboard_page.tabs.logs')" name="logs">
        <p class="text-gray mb-3">{{ t('risk_dashboard_page.logs.hint') }}</p>
        <el-alert :title="t('risk_dashboard_page.logs.alert_title')" type="info" :closable="false" show-icon>
          <template #default>
            {{ t('risk_dashboard_page.logs.alert_body') }}
          </template>
        </el-alert>
      </el-tab-pane>
    </el-tabs>

    <!-- 清除负余额对话框 -->
    <el-dialog v-model="showClearDialog" :title="t('risk_dashboard_page.clear_dialog.title')" width="400px">
      <el-form :model="clearForm" label-width="100px">
        <el-form-item :label="t('risk_dashboard_page.clear_dialog.current_negative')">
          <span class="text-danger font-semibold">¥{{ clearForm.currentNegative.toFixed(2) }}</span>
        </el-form-item>
        <el-form-item :label="t('risk_dashboard_page.clear_dialog.amount')">
          <el-input-number v-model="clearForm.amount" :min="0.01" :max="clearForm.currentNegative" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showClearDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitClear" :loading="submittingClear">{{ t('risk_dashboard_page.clear_dialog.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api/commission';

const { t, locale } = useI18n();

const activeTab = ref('negative_balance');
const loading = ref(false);
const stats = ref({});

const payoutMethodLabels = computed(() => ({
  bank_transfer: t('risk_dashboard_page.payout_methods.bank_transfer'),
  alipay: t('risk_dashboard_page.payout_methods.alipay'),
  wechat: t('risk_dashboard_page.payout_methods.wechat'),
  balance: t('risk_dashboard_page.payout_methods.balance'),
}));

const accountStatusLabels = computed(() => ({
  active: t('risk_dashboard_page.account_status.active'),
  frozen: t('risk_dashboard_page.account_status.frozen'),
}));

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

// ── 风控总览 ──
async function fetchDashboard() {
  loading.value = true;
  try {
    const res = await api.risk.dashboard();
    stats.value = res.data.data || {};
  } catch (e) {
    console.error('Failed to load risk data', e);
  } finally {
    loading.value = false;
  }
}

// ── 负余额账户 ──
const negativeAccounts = ref([]);
const loadingNegative = ref(false);
const negativeTotal = ref(0);

async function fetchNegativeAccounts(page = 1) {
  loadingNegative.value = true;
  try {
    const res = await api.risk.listNegativeBalance({ page, per_page: 20 });
    negativeAccounts.value = res.data.data?.data || [];
    negativeTotal.value = res.data.data?.total || 0;
  } catch (e) {
    console.error('Failed to load negative balance list', e);
  } finally {
    loadingNegative.value = false;
  }
}

// ── 待审核提现 ──
const pendingReviewPayouts = ref([]);
const loadingReview = ref(false);
const reviewTotal = ref(0);

async function fetchPendingReview(page = 1) {
  loadingReview.value = true;
  try {
    const res = await api.risk.listPendingReviewPayouts({ page, per_page: 20 });
    pendingReviewPayouts.value = res.data.data?.data || [];
    reviewTotal.value = res.data.data?.total || 0;
  } catch (e) {
    console.error('Failed to load pending review payouts', e);
  } finally {
    loadingReview.value = false;
  }
}

// ── 清除负余额 ──
const showClearDialog = ref(false);
const clearForm = reactive({ accountId: null, currentNegative: 0, amount: 0 });
const submittingClear = ref(false);

function openClearDialog(row) {
  clearForm.accountId = row.id;
  clearForm.currentNegative = row.negative_balance;
  clearForm.amount = row.negative_balance;
  showClearDialog.value = true;
}

async function submitClear() {
  if (!clearForm.amount || clearForm.amount <= 0) {
    ElMessage.warning(t('risk_dashboard_page.messages.enter_clear_amount'));
    return;
  }
  submittingClear.value = true;
  try {
    await api.risk.clearNegativeBalance(clearForm.accountId, { amount: clearForm.amount });
    ElMessage.success(t('risk_dashboard_page.messages.clear_success'));
    showClearDialog.value = false;
    fetchNegativeAccounts();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('messages.failed'));
  } finally {
    submittingClear.value = false;
  }
}

// ── 审批评审 ──
async function reviewPayout(payout, action) {
  const confirmText = action === 'approve'
    ? t('risk_dashboard_page.confirm.approve_payout')
    : t('risk_dashboard_page.confirm.reject_payout');
  try {
    await ElMessageBox.confirm(confirmText, t('risk_dashboard_page.confirm.payout_review_title'), { type: 'warning' });
    await api.risk.reviewPayout(payout.id, { action });
    ElMessage.success(action === 'approve'
      ? t('risk_dashboard_page.messages.payout_approved')
      : t('risk_dashboard_page.messages.payout_rejected'));
    fetchPendingReview();
    fetchDashboard();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('messages.failed'));
  }
}

// ── 风控任务 ──
const runningTask = ref(null);

async function runTask(task) {
  runningTask.value = task;
  try {
    await api.risk.runTask(task);
    ElMessage.success(t('risk_dashboard_page.messages.task_executed'));
    fetchDashboard();
    fetchNegativeAccounts();
  } catch (e) {
    ElMessage.error(t('risk_dashboard_page.messages.task_failed'));
  } finally {
    runningTask.value = null;
  }
}

// ── Helper ──
function methodLabel(m) {
  return payoutMethodLabels.value[m] || m;
}

function accountStatusLabel(status) {
  return accountStatusLabels.value[status] || status;
}

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleString(dateLocale.value);
}

// ── Init ──
onMounted(() => {
  fetchDashboard();
  fetchNegativeAccounts();
  fetchPendingReview();
});
</script>

<style scoped>
.stat-box { text-align: center; padding: 8px 0; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-sub { font-size: 12px; color: #909399; margin-top: 4px; }
.text-danger { color: #f56c6c !important; }
.text-warning { color: #e6a23c !important; }
.text-gray { color: #909399; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.font-semibold { font-weight: 600; }
.text-base { font-size: 16px; }
:deep(.el-card) { margin-bottom: 0; }
</style>
