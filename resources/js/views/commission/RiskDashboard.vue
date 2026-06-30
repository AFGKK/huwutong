<template>
  <div class="commission-risk-manager">
    <!-- 风控概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">负余额账户</div>
            <div class="stat-value" :class="{ 'text-danger': stats.negative_balance_accounts > 0 }">{{ stats.negative_balance_accounts || 0 }}</div>
            <div class="stat-sub" v-if="stats.total_negative_balance">总额 ¥{{ (stats.total_negative_balance || 0).toFixed(2) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">冻结中佣金</div>
            <div class="stat-value">¥{{ (stats.pending_frozen_amount || 0).toLocaleString() }}</div>
            <div class="stat-sub">T+30 保护期</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">待审核提现</div>
            <div class="stat-value" :class="{ 'text-warning': stats.pending_review_payouts > 0 }">{{ stats.pending_review_payouts || 0 }}</div>
            <div class="stat-sub" v-if="stats.pending_review_amount">金额 ¥{{ (stats.pending_review_amount || 0).toFixed(2) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">风控事件</div>
            <div class="stat-value">{{ stats.today_risk_events || 0 }}</div>
            <div class="stat-sub">今日退款回拨</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作工具栏 -->
    <el-card class="mb-4">
      <div class="flex justify-between items-center">
        <span class="font-semibold text-base">风控任务执行</span>
        <div>
          <el-button @click="runTask('release_freezes')" :loading="runningTask === 'release_freezes'" type="primary">
            释放到期冻结
          </el-button>
          <el-button @click="runTask('enforce_recovery')" :loading="runningTask === 'enforce_recovery'" type="warning">
            执行负余额追缴
          </el-button>
          <el-button @click="fetchDashboard" :loading="loading" circle>
            <el-icon><Refresh /></el-icon>
          </el-button>
        </div>
      </div>
    </el-card>

    <el-tabs v-model="activeTab">
      <!-- 负余额账户 -->
      <el-tab-pane label="负余额账户" name="negative_balance">
        <el-table :data="negativeAccounts" v-loading="loadingNegative" stripe>
          <el-table-column label="用户" width="180">
            <template #default="{ row }">{{ row.user?.name || 'N/A' }}<br><small>{{ row.user?.email }}</small></template>
          </el-table-column>
          <el-table-column label="负余额" width="120" align="right">
            <template #default="{ row }"><span class="text-danger">¥{{ row.negative_balance.toFixed(2) }}</span></template>
          </el-table-column>
          <el-table-column label="可用余额" width="120" align="right">
            <template #default="{ row }">¥{{ row.available_balance.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="冻结余额" width="120" align="right">
            <template #default="{ row }">¥{{ row.pending_balance.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="逾期天数" width="100">
            <template #default="{ row }">
              <el-tag v-if="row.days_overdue > 30" type="danger" size="small">{{ row.days_overdue }}天</el-tag>
              <el-tag v-else-if="row.days_overdue > 0" type="warning" size="small">{{ row.days_overdue }}天</el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="账户状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : row.status === 'frozen' ? 'danger' : 'info'" size="small">
                {{ row.status === 'active' ? '正常' : row.status === 'frozen' ? '冻结' : row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewNegativeDetail(row)">查看</el-button>
              <el-button size="small" type="warning" plain @click="openClearDialog(row)" :disabled="row.negative_balance <= 0">
                清除负余额
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="negativeTotal" :page-size="20"
          @current-change="page => fetchNegativeAccounts(page)" />
      </el-tab-pane>

      <!-- 待审核提现 -->
      <el-tab-pane label="待审核提现" name="pending_review">
        <el-table :data="pendingReviewPayouts" v-loading="loadingReview" stripe>
          <el-table-column label="代理" width="160">
            <template #default="{ row }">{{ row.agent?.user?.name || 'N/A' }}<br><small>{{ row.agent?.agent_code }}</small></template>
          </el-table-column>
          <el-table-column label="金额" width="120" align="right">
            <template #default="{ row }">¥{{ row.amount.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="手续费" width="100" align="right">
            <template #default="{ row }">¥{{ row.fee.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="实际到账" width="120" align="right">
            <template #default="{ row }"><strong>¥{{ row.net_amount.toFixed(2) }}</strong></template>
          </el-table-column>
          <el-table-column label="提现方式" width="100">
            <template #default="{ row }">{{ methodLabel(row.payout_method) }}</template>
          </el-table-column>
          <el-table-column label="申请时间" width="160">
            <template #default="{ row }">{{ formatDate(row.requested_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="success" @click="reviewPayout(row, 'approve')">通过</el-button>
              <el-button size="small" type="danger" @click="reviewPayout(row, 'reject')">拒绝</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="reviewTotal" :page-size="20"
          @current-change="page => fetchPendingReview(page)" />
      </el-tab-pane>

      <!-- 风控日志 -->
      <el-tab-pane label="风控日志" name="logs">
        <p class="text-gray mb-3">风控操作记录可在系统日志中查看（关键字：CommissionRiskGuard）</p>
        <el-alert title="提示" type="info" :closable="false" show-icon>
          <template #default>
            所有风控操作均记录在 Laravel 日志中，使用
            <code>grep CommissionRiskGuard storage/logs/laravel.log</code> 查看。<br>
            日志级别：<code>info</code>，包含完整的操作上下文（结算ID、账户ID、金额等）。
          </template>
        </el-alert>
      </el-tab-pane>
    </el-tabs>

    <!-- 清除负余额对话框 -->
    <el-dialog v-model="showClearDialog" title="清除负余额" width="400px">
      <el-form :model="clearForm" label-width="100px">
        <el-form-item label="当前负余额">
          <span class="text-danger font-semibold">¥{{ clearForm.currentNegative.toFixed(2) }}</span>
        </el-form-item>
        <el-form-item label="清除金额">
          <el-input-number v-model="clearForm.amount" :min="0.01" :max="clearForm.currentNegative" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showClearDialog = false">取消</el-button>
        <el-button type="primary" @click="submitClear" :loading="submittingClear">确认清除</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api/commission';

const activeTab = ref('negative_balance');
const loading = ref(false);
const stats = ref({});

// ── 风控总览 ──
async function fetchDashboard() {
  loading.value = true;
  try {
    const res = await api.risk.dashboard();
    stats.value = res.data.data || {};
  } catch (e) {
    console.error('获取风控数据失败', e);
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
    console.error('获取负余额列表失败', e);
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
    console.error('获取待审核提现失败', e);
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
    ElMessage.warning('请输入清除金额');
    return;
  }
  submittingClear.value = true;
  try {
    await api.risk.clearNegativeBalance(clearForm.accountId, { amount: clearForm.amount });
    ElMessage.success('负余额已清除');
    showClearDialog.value = false;
    fetchNegativeAccounts();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '操作失败');
  } finally {
    submittingClear.value = false;
  }
}

// ── 审批评审 ──
async function reviewPayout(payout, action) {
  const confirmText = action === 'approve' ? '确认通过此提现申请？' : '确认拒绝此提现申请？（余额将退还）';
  try {
    await ElMessageBox.confirm(confirmText, '提现审批', { type: 'warning' });
    await api.risk.reviewPayout(payout.id, { action });
    ElMessage.success(action === 'approve' ? '提现已审批通过' : '提现已拒绝');
    fetchPendingReview();
    fetchDashboard();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('操作失败');
  }
}

// ── 风控任务 ──
const runningTask = ref(null);

async function runTask(task) {
  runningTask.value = task;
  try {
    const res = await api.risk.runTask(task);
    ElMessage.success('风控任务已执行');
    fetchDashboard();
    fetchNegativeAccounts();
  } catch (e) {
    ElMessage.error('任务执行失败');
  } finally {
    runningTask.value = null;
  }
}

// ── Helper ──
function methodLabel(m) {
  return { bank_transfer: '银行卡', alipay: '支付宝', wechat: '微信', balance: '余额' }[m] || m;
}

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleString('zh-CN');
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
