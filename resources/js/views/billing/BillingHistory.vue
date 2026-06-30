<template>
  <div class="billing-history">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>账单历史</h2>
        <span class="header-subtitle">查看所有发票和付款记录</span>
      </div>
      <div class="header-right">
        <el-button @click="refreshData">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ stats.total_invoices }}</div>
            <div class="stat-label">总账单数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value revenue">&yen;{{ formatAmount(stats.total_revenue) }}</div>
            <div class="stat-label">总收入</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value pending" v-if="stats.pending_amount > 0">&yen;{{ formatAmount(stats.pending_amount) }}</div>
            <div class="stat-value" v-else>&yen;0.00</div>
            <div class="stat-label">待支付</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value month">&yen;{{ formatAmount(stats.this_month_revenue) }}</div>
            <div class="stat-label">本月收入</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选区 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item label="状态">
          <el-select
            v-model="filters.status"
            placeholder="全部状态"
            clearable
            style="width: 130px"
            @change="handleFilterChange"
          >
            <el-option label="全部状态" value="" />
            <el-option
              v-for="(label, key) in filterOptions.statuses"
              :key="key"
              :label="label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 260px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item label="计费原因">
          <el-select
            v-model="filters.billing_reason"
            placeholder="全部"
            clearable
            style="width: 150px"
            @change="handleFilterChange"
          >
            <el-option label="全部" value="" />
            <el-option
              v-for="(label, key) in filterOptions.billing_reasons"
              :key="key"
              :label="label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 账单列表 -->
    <el-card shadow="never">
      <el-table
        :data="invoices"
        v-loading="loading"
        stripe
        style="width: 100%"
      >
        <el-table-column prop="invoice_no" label="发票号" width="160">
          <template #default="{ row }">
            <span class="invoice-no">{{ row.invoice_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="160">
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="摘要" min-width="180">
          <template #default="{ row }">
            <div class="invoice-summary">
              <span class="billing-reason-tag">{{ getBillingReasonLabel(row.billing_reason) }}</span>
              <span v-if="row.subscription" class="plan-name">{{ row.subscription.plan }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount">&yen;{{ formatAmount(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag
              :type="getStatusType(row.status)"
              size="small"
              effect="plain"
            >
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="120">
          <template #default="{ row }">
            <span class="payment-method">{{ row.payment_method || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              size="small"
              @click="showDetail(row)"
            >
              详情
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="perPage"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <!-- 详情抽屉 -->
    <el-drawer
      v-model="detailVisible"
      title="账单详情"
      size="500px"
    >
      <template v-if="detail">
        <div class="detail-section">
          <!-- 发票号与状态 -->
          <div class="detail-header">
            <span class="detail-invoice-no">{{ detail.invoice_no }}</span>
            <el-tag
              :type="getStatusType(detail.status)"
              size="small"
            >
              {{ getStatusLabel(detail.status) }}
            </el-tag>
          </div>

          <!-- 金额概览 -->
          <el-card shadow="never" class="amount-card">
            <div class="amount-row">
              <span>小计</span>
              <span>&yen;{{ formatAmount(detail.subtotal) }}</span>
            </div>
            <div class="amount-row" v-if="detail.discount_amount > 0">
              <span>优惠</span>
              <span class="discount">-&yen;{{ formatAmount(detail.discount_amount) }}</span>
            </div>
            <div class="amount-row" v-if="detail.tax_amount > 0">
              <span>税费</span>
              <span>&yen;{{ formatAmount(detail.tax_amount) }}</span>
            </div>
            <el-divider />
            <div class="amount-row total">
              <span>合计</span>
              <span>&yen;{{ formatAmount(detail.amount) }}</span>
            </div>
          </el-card>

          <!-- 基本信息 -->
          <div class="detail-item">
            <label>创建时间</label>
            <span>{{ formatTime(detail.created_at) }}</span>
          </div>
          <div class="detail-item" v-if="detail.paid_at">
            <label>支付时间</label>
            <span>{{ formatTime(detail.paid_at) }}</span>
          </div>
          <div class="detail-item" v-if="detail.due_at">
            <label>到期时间</label>
            <span>{{ formatTime(detail.due_at) }}</span>
          </div>
          <div class="detail-item">
            <label>计费原因</label>
            <span>{{ getBillingReasonLabel(detail.billing_reason) }}</span>
          </div>
          <div class="detail-item">
            <label>支付方式</label>
            <span>{{ detail.payment_method || '-' }}</span>
          </div>
          <div class="detail-item" v-if="detail.subscription">
            <label>订阅方案</label>
            <span>{{ detail.subscription.plan }}（{{ detail.subscription.billing_period }}）</span>
          </div>
          <div class="detail-item" v-if="detail.coupon">
            <label>优惠券</label>
            <span>{{ detail.coupon.code }}</span>
          </div>
          <div class="detail-item" v-if="detail.notes">
            <label>备注</label>
            <span>{{ detail.notes }}</span>
          </div>

          <!-- 账单地址 -->
          <div class="detail-item" v-if="detail.billing_address_line1">
            <label>账单地址</label>
            <span>{{ detail.billing_address_line1 }}</span>
            <span v-if="detail.billing_address_line2">, {{ detail.billing_address_line2 }}</span>
            <br v-if="detail.billing_city">
            <span v-if="detail.billing_city">{{ detail.billing_city }}, {{ detail.billing_region }}</span>
          </div>

          <!-- 税务信息 -->
          <div class="detail-item" v-if="detail.tax_lines && detail.tax_lines.length > 0">
            <label>税务明细</label>
            <div v-for="(line, idx) in detail.tax_lines" :key="idx" class="tax-line">
              {{ line.name }} ({{ line.rate }}%)：&yen;{{ formatAmount(line.tax_amount) }}
            </div>
          </div>
        </div>
      </template>
    </el-drawer>

    <!-- 支付失败/退款记录 -->
    <el-row :gutter="16" class="mt-4">
      <!-- 逾期账单 -->
      <el-col :span="12">
        <el-card shadow="never" class="mb-4">
          <template #header>
            <div class="card-header">
              <span>逾期未付账单</span>
              <el-tag v-if="failedPaymentsData.overdue_invoices?.length" type="danger" size="small">
                {{ failedPaymentsData.overdue_invoices.length }}
              </el-tag>
            </div>
          </template>
          <div v-if="!failedPaymentsData.overdue_invoices?.length" class="empty-state">
            <el-icon><CircleCheck /></el-icon>
            <span>暂无逾期账单</span>
          </div>
          <div v-else>
            <div
              v-for="inv in failedPaymentsData.overdue_invoices"
              :key="inv.id"
              class="failed-item"
            >
              <div class="failed-info">
                <span class="failed-invoice-no">{{ inv.invoice_no }}</span>
                <span class="failed-plan" v-if="inv.subscription">{{ inv.subscription.plan }}</span>
              </div>
              <div class="failed-amount">
                <span>&yen;{{ formatAmount(inv.amount) }}</span>
                <span class="failed-date">逾期: {{ formatTime(inv.due_at) }}</span>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 退款记录 -->
      <el-col :span="12">
        <el-card shadow="never" class="mb-4">
          <template #header>
            <div class="card-header">
              <span>退款记录</span>
              <el-tag v-if="failedPaymentsData.refunds?.length" type="warning" size="small">
                {{ failedPaymentsData.refunds.length }}
              </el-tag>
            </div>
          </template>
          <div v-if="!failedPaymentsData.refunds?.length" class="empty-state">
            <el-icon><CircleCheck /></el-icon>
            <span>暂无退款记录</span>
          </div>
          <div v-else>
            <div
              v-for="refund in failedPaymentsData.refunds"
              :key="refund.id"
              class="failed-item"
            >
              <div class="failed-info">
                <span class="failed-invoice-no">{{ refund.refund_no }}</span>
                <span class="failed-reason">{{ refund.reason }}</span>
              </div>
              <div class="failed-amount">
                <span class="refund-amount">-&yen;{{ formatAmount(refund.amount) }}</span>
                <span class="failed-date">{{ formatTime(refund.created_at) }}</span>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 自动续费记录 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>自动续费记录</span>
          <div class="renewal-stats" v-if="autoRenewalData.total > 0">
            <span>成功: {{ autoRenewalData.success_count }}</span>
            <span class="ml-2">失败: {{ autoRenewalData.failed_count }}</span>
          </div>
        </div>
      </template>
      <div v-if="!autoRenewalData.records?.length" class="empty-state">
        <el-icon><InfoFilled /></el-icon>
        <span>暂无自动续费记录</span>
      </div>
      <el-table
        v-else
        :data="autoRenewalData.records"
        stripe
        size="small"
      >
        <el-table-column label="时间" width="160">
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="订阅方案" width="150">
          <template #default="{ row }">
            {{ row.subscription?.plan || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120" align="right">
          <template #default="{ row }">
            &yen;{{ formatAmount(row.amount) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag
              :type="getStatusType(row.status)"
              size="small"
              effect="plain"
            >
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="120">
          <template #default="{ row }">
            {{ row.payment_method || '-' }}
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, CircleCheck, InfoFilled } from '@element-plus/icons-vue';
import billingHistoryApi from '../../api/billingHistory';

export default {
  name: 'BillingHistory',
  components: { Refresh, CircleCheck, InfoFilled },
  setup() {
    const loading = ref(false);
    const invoices = ref([]);
    const total = ref(0);
    const currentPage = ref(1);
    const perPage = ref(20);

    const stats = reactive({
      total_invoices: 0,
      total_revenue: 0,
      pending_amount: 0,
      this_month_revenue: 0,
    });

    const filters = reactive({
      status: '',
      billing_reason: '',
    });

    const dateRange = ref(null);
    const filterOptions = reactive({
      statuses: {},
      billing_reasons: {},
      payment_methods: {},
    });

    const detailVisible = ref(false);
    const detail = ref(null);

    const failedPaymentsData = reactive({
      overdue_invoices: [],
      failed_renewals: [],
      refunds: [],
    });

    const autoRenewalData = reactive({
      records: [],
      total: 0,
      success_count: 0,
      failed_count: 0,
    });

    function formatTime(time) {
      if (!time) return '-';
      const d = new Date(time);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function formatAmount(amount) {
      if (amount === null || amount === undefined) return '0.00';
      return Number(amount).toFixed(2);
    }

    function getStatusLabel(status) {
      const map = { paid: '已支付', pending: '待支付', refunded: '已退款', canceled: '已取消' };
      return map[status] || status;
    }

    function getStatusType(status) {
      const map = { paid: 'success', pending: 'warning', refunded: 'danger', canceled: 'info' };
      return map[status] || '';
    }

    function getBillingReasonLabel(reason) {
      const map = {
        subscription_create: '订阅创建',
        renewal: '自动续费',
        manual_renewal: '手动续费',
        plan_change: '方案变更',
        upgrade: '升级',
        downgrade: '降级',
      };
      return map[reason] || reason || '-';
    }

    async function fetchInvoices() {
      loading.value = true;
      try {
        const params = {
          per_page: perPage.value,
          page: currentPage.value,
        };
        if (filters.status) params['status'] = filters.status;
        if (filters.billing_reason) params['billing_reason'] = filters.billing_reason;
        if (dateRange.value) {
          params['date_from'] = dateRange.value[0];
          params['date_to'] = dateRange.value[1];
        }

        const response = await billingHistoryApi.invoices(params);
        const data = response.data;
        invoices.value = data.data || [];
        total.value = data.meta?.total || data.total || 0;
      } catch (err) {
        console.error('Failed to fetch invoices:', err);
        ElMessage.error('获取账单列表失败');
      } finally {
        loading.value = false;
      }
    }

    async function fetchStats() {
      try {
        const response = await billingHistoryApi.stats();
        const data = response.data;
        if (data) Object.assign(stats, data);
      } catch (err) {
        console.error('Failed to fetch stats:', err);
      }
    }

    async function fetchFilterOptions() {
      try {
        const response = await billingHistoryApi.filterOptions();
        const data = response.data;
        if (data) Object.assign(filterOptions, data);
      } catch (err) {
        console.error('Failed to fetch filter options:', err);
      }
    }

    async function fetchFailedPayments() {
      try {
        const response = await billingHistoryApi.failedPayments();
        const data = response.data;
        if (data) Object.assign(failedPaymentsData, data);
      } catch (err) {
        console.error('Failed to fetch failed payments:', err);
      }
    }

    async function fetchAutoRenewals() {
      try {
        const response = await billingHistoryApi.autoRenewals();
        const data = response.data;
        if (data) Object.assign(autoRenewalData, data);
      } catch (err) {
        console.error('Failed to fetch auto renewals:', err);
      }
    }

    function handleFilterChange() {
      currentPage.value = 1;
      fetchInvoices();
    }

    function resetFilters() {
      filters.status = '';
      filters.billing_reason = '';
      dateRange.value = null;
      currentPage.value = 1;
      fetchInvoices();
    }

    function handlePageChange(page) {
      currentPage.value = page;
      fetchInvoices();
    }

    function handleSizeChange(size) {
      perPage.value = size;
      currentPage.value = 1;
      fetchInvoices();
    }

    function handleDateChange() {
      currentPage.value = 1;
      fetchInvoices();
    }

    async function showDetail(row) {
      try {
        const response = await billingHistoryApi.invoiceDetail(row.id);
        detail.value = response.data;
        detailVisible.value = true;
      } catch (err) {
        ElMessage.error('获取账单详情失败');
      }
    }

    async function refreshData() {
      await Promise.all([
        fetchInvoices(),
        fetchStats(),
        fetchFailedPayments(),
        fetchAutoRenewals(),
      ]);
    }

    onMounted(() => {
      fetchFilterOptions();
      fetchInvoices();
      fetchStats();
      fetchFailedPayments();
      fetchAutoRenewals();
    });

    return {
      loading,
      invoices,
      total,
      currentPage,
      perPage,
      filters,
      dateRange,
      filterOptions,
      stats,
      detailVisible,
      detail,
      failedPaymentsData,
      autoRenewalData,
      formatTime,
      formatAmount,
      getStatusLabel,
      getStatusType,
      getBillingReasonLabel,
      handleFilterChange,
      resetFilters,
      handlePageChange,
      handleSizeChange,
      handleDateChange,
      showDetail,
      refreshData,
    };
  },
};
</script>

<style scoped>
.billing-history {
  padding: 20px;
}

.mb-4 {
  margin-bottom: 16px;
}

.mt-4 {
  margin-top: 16px;
}

.ml-2 {
  margin-left: 8px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-left h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-subtitle {
  font-size: 13px;
  color: #909399;
  margin-left: 12px;
}

.stat-box {
  text-align: center;
  padding: 8px 0;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}

.stat-value.revenue {
  color: #67c23a;
}

.stat-value.pending {
  color: #e6a23c;
}

.stat-value.month {
  color: #409eff;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.invoice-no {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 13px;
  color: #303133;
}

.invoice-summary {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.billing-reason-tag {
  font-size: 12px;
  color: #909399;
}

.plan-name {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
}

.amount {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-weight: 600;
  color: #303133;
}

.payment-method {
  font-size: 13px;
  color: #606266;
}

.pagination-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.detail-section {
  padding: 0 8px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.detail-invoice-no {
  font-size: 16px;
  font-weight: 600;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

.amount-card {
  margin-bottom: 16px;
}

.amount-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 14px;
  color: #606266;
}

.amount-row.total {
  font-size: 16px;
  font-weight: 700;
  color: #303133;
}

.discount {
  color: #67c23a;
}

.detail-item {
  margin-bottom: 14px;
}

.detail-item label {
  display: block;
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
  font-weight: 500;
}

.detail-item > span {
  font-size: 14px;
  color: #303133;
}

.tax-line {
  font-size: 13px;
  color: #606266;
  padding: 2px 0;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 24px;
  color: #c0c4cc;
  font-size: 14px;
}

.failed-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;
}

.failed-item:last-child {
  border-bottom: none;
}

.failed-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.failed-invoice-no {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
}

.failed-plan,
.failed-reason {
  font-size: 12px;
  color: #909399;
}

.failed-amount {
  text-align: right;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.failed-amount > span {
  font-size: 14px;
  font-weight: 600;
  color: #f56c6c;
}

.refund-amount {
  color: #e6a23c !important;
}

.failed-date {
  font-size: 11px !important;
  font-weight: 400 !important;
  color: #c0c4cc !important;
}

.renewal-stats {
  font-size: 13px;
  color: #909399;
}
</style>
