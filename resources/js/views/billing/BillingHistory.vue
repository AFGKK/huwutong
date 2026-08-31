<template>
  <div class="billing-history">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>{{ t('billing_history_page.title') }}</h2>
        <span class="header-subtitle">{{ t('billing_history_page.subtitle') }}</span>
      </div>
      <div class="header-right">
        <el-button @click="refreshData">
          <el-icon><Refresh /></el-icon> {{ t('billing_history_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ stats.total_invoices }}</div>
            <div class="stat-label">{{ t('billing_history_page.stat_total_invoices') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value revenue">&yen;{{ formatAmount(stats.total_revenue) }}</div>
            <div class="stat-label">{{ t('billing_history_page.stat_total_revenue') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value pending" v-if="stats.pending_amount > 0">&yen;{{ formatAmount(stats.pending_amount) }}</div>
            <div class="stat-value" v-else>&yen;0.00</div>
            <div class="stat-label">{{ t('billing_history_page.stat_pending_amount') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value month">&yen;{{ formatAmount(stats.this_month_revenue) }}</div>
            <div class="stat-label">{{ t('billing_page.stat_recent_revenue') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选区 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item :label="t('billing_page.col_status')">
          <el-select
            v-model="filters.status"
            :placeholder="t('customers_page.all_status')"
            clearable
            style="width: 130px"
            @change="handleFilterChange"
          >
            <el-option :label="t('customers_page.all_status')" value="" />
            <el-option
              v-for="(label, key) in filterOptions.statuses"
              :key="key"
              :label="label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('billing_history_page.filter_date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="t('licenses_page.date_range_sep')"
            :start-placeholder="t('licenses_page.date_start')"
            :end-placeholder="t('licenses_page.date_end')"
            value-format="YYYY-MM-DD"
            style="width: 260px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item :label="t('billing_page.col_reason')">
          <el-select
            v-model="filters.billing_reason"
            :placeholder="t('billing_history_page.ph_all')"
            clearable
            style="width: 150px"
            @change="handleFilterChange"
          >
            <el-option :label="t('billing_history_page.ph_all')" value="" />
            <el-option
              v-for="(label, key) in filterOptions.billing_reasons"
              :key="key"
              :label="label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
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
        <el-table-column prop="invoice_no" :label="t('billing_page.col_invoice_no')" width="160">
          <template #default="{ row }">
            <span class="invoice-no">{{ row.invoice_no }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('payment_page.col_time')" width="160">
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_history_page.col_summary')" min-width="180">
          <template #default="{ row }">
            <div class="invoice-summary">
              <span class="billing-reason-tag">{{ getBillingReasonLabel(row.billing_reason) }}</span>
              <span v-if="row.subscription" class="plan-name">{{ row.subscription.plan }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_amount')" width="130" align="right">
          <template #default="{ row }">
            <span class="amount">&yen;{{ formatAmount(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_status')" width="100">
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
        <el-table-column :label="t('portal.pay_method')" width="120">
          <template #default="{ row }">
            <span class="payment-method">{{ row.payment_method || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_actions')" width="80" fixed="right">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              size="small"
              @click="showDetail(row)"
            >
              {{ t('billing_page.detail') }}
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
      :title="t('billing_history_page.drawer_title')"
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
              <span>{{ t('billing_history_page.subtotal') }}</span>
              <span>&yen;{{ formatAmount(detail.subtotal) }}</span>
            </div>
            <div class="amount-row" v-if="detail.discount_amount > 0">
              <span>{{ t('billing_history_page.discount') }}</span>
              <span class="discount">-&yen;{{ formatAmount(detail.discount_amount) }}</span>
            </div>
            <div class="amount-row" v-if="detail.tax_amount > 0">
              <span>{{ t('billing_history_page.tax') }}</span>
              <span>&yen;{{ formatAmount(detail.tax_amount) }}</span>
            </div>
            <el-divider />
            <div class="amount-row total">
              <span>{{ t('billing_history_page.total') }}</span>
              <span>&yen;{{ formatAmount(detail.amount) }}</span>
            </div>
          </el-card>

          <!-- 基本信息 -->
          <div class="detail-item">
            <label>{{ t('billing_page.col_created') }}</label>
            <span>{{ formatTime(detail.created_at) }}</span>
          </div>
          <div class="detail-item" v-if="detail.paid_at">
            <label>{{ t('billing_page.col_paid_at') }}</label>
            <span>{{ formatTime(detail.paid_at) }}</span>
          </div>
          <div class="detail-item" v-if="detail.due_at">
            <label>{{ t('billing_page.col_due_at') }}</label>
            <span>{{ formatTime(detail.due_at) }}</span>
          </div>
          <div class="detail-item">
            <label>{{ t('billing_page.col_reason') }}</label>
            <span>{{ getBillingReasonLabel(detail.billing_reason) }}</span>
          </div>
          <div class="detail-item">
            <label>{{ t('portal.pay_method') }}</label>
            <span>{{ detail.payment_method || '-' }}</span>
          </div>
          <div class="detail-item" v-if="detail.subscription">
            <label>{{ t('billing_page.col_sub_plan') }}</label>
            <span>{{ detail.subscription.plan }}（{{ detail.subscription.billing_period }}）</span>
          </div>
          <div class="detail-item" v-if="detail.coupon">
            <label>{{ t('billing_history_page.coupon') }}</label>
            <span>{{ detail.coupon.code }}</span>
          </div>
          <div class="detail-item" v-if="detail.notes">
            <label>{{ t('billing_history_page.notes') }}</label>
            <span>{{ detail.notes }}</span>
          </div>

          <!-- 账单地址 -->
          <div class="detail-item" v-if="detail.billing_address_line1">
            <label>{{ t('billing_history_page.billing_address') }}</label>
            <span>{{ detail.billing_address_line1 }}</span>
            <span v-if="detail.billing_address_line2">, {{ detail.billing_address_line2 }}</span>
            <br v-if="detail.billing_city">
            <span v-if="detail.billing_city">{{ detail.billing_city }}, {{ detail.billing_region }}</span>
          </div>

          <!-- 税务信息 -->
          <div class="detail-item" v-if="detail.tax_lines && detail.tax_lines.length > 0">
            <label>{{ t('billing_history_page.tax_lines') }}</label>
            <div v-for="(line, idx) in detail.tax_lines" :key="idx" class="tax-line">
              {{ t('billing_history_page.tax_line_fmt', {
                name: line.name,
                rate: line.rate,
                amount: `\u00a5${formatAmount(line.tax_amount)}`,
              }) }}
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
              <span>{{ t('billing_history_page.overdue_title') }}</span>
              <el-tag v-if="failedPaymentsData.overdue_invoices?.length" type="danger" size="small">
                {{ failedPaymentsData.overdue_invoices.length }}
              </el-tag>
            </div>
          </template>
          <div v-if="!failedPaymentsData.overdue_invoices?.length" class="empty-state">
            <el-icon><CircleCheck /></el-icon>
            <span>{{ t('billing_history_page.no_overdue') }}</span>
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
                <span class="failed-date">{{ t('billing_history_page.overdue_prefix') }} {{ formatTime(inv.due_at) }}</span>
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
              <span>{{ t('billing_history_page.refunds_title') }}</span>
              <el-tag v-if="failedPaymentsData.refunds?.length" type="warning" size="small">
                {{ failedPaymentsData.refunds.length }}
              </el-tag>
            </div>
          </template>
          <div v-if="!failedPaymentsData.refunds?.length" class="empty-state">
            <el-icon><CircleCheck /></el-icon>
            <span>{{ t('billing_history_page.no_refunds') }}</span>
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
          <span>{{ t('billing_history_page.auto_renewal_title') }}</span>
          <div class="renewal-stats" v-if="autoRenewalData.total > 0">
            <span>{{ t('billing_history_page.renewal_success', { n: autoRenewalData.success_count }) }}</span>
            <span class="ml-2">{{ t('billing_history_page.renewal_failed', { n: autoRenewalData.failed_count }) }}</span>
          </div>
        </div>
      </template>
      <div v-if="!autoRenewalData.records?.length" class="empty-state">
        <el-icon><InfoFilled /></el-icon>
        <span>{{ t('billing_history_page.no_auto_renewal') }}</span>
      </div>
      <el-table
        v-else
        :data="autoRenewalData.records"
        stripe
        size="small"
      >
        <el-table-column :label="t('payment_page.col_time')" width="160">
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_sub_plan')" width="150">
          <template #default="{ row }">
            {{ row.subscription?.plan || '-' }}
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_amount')" width="120" align="right">
          <template #default="{ row }">
            &yen;{{ formatAmount(row.amount) }}
          </template>
        </el-table-column>
        <el-table-column :label="t('billing_page.col_status')" width="100">
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
        <el-table-column :label="t('portal.pay_method')" width="120">
          <template #default="{ row }">
            {{ row.payment_method || '-' }}
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, CircleCheck, InfoFilled } from '@element-plus/icons-vue';
import billingHistoryApi from '../../api/billingHistory';

export default {
  name: 'BillingHistory',
  components: { Refresh, CircleCheck, InfoFilled },
  setup() {
    const { t } = useI18n();

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

    const statusLabels = computed(() => ({
      paid: t('billing_page.inv_paid'),
      pending: t('billing_page.inv_pending'),
      refunded: t('billing_page.inv_refunded'),
      canceled: t('billing_page.inv_cancelled'),
    }));

    const billingReasonLabels = computed(() => ({
      subscription_create: t('billing_history_page.reasons.subscription_create'),
      renewal: t('billing_history_page.reasons.renewal'),
      manual_renewal: t('billing_history_page.reasons.manual_renewal'),
      plan_change: t('billing_history_page.reasons.plan_change'),
      upgrade: t('billing_history_page.reasons.upgrade'),
      downgrade: t('billing_history_page.reasons.downgrade'),
    }));

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
      return statusLabels.value[status] || status;
    }

    function getStatusType(status) {
      const map = { paid: 'success', pending: 'warning', refunded: 'danger', canceled: 'info' };
      return map[status] || '';
    }

    function getBillingReasonLabel(reason) {
      return billingReasonLabels.value[reason] || reason || '-';
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
        ElMessage.error(t('billing_history_page.messages.load_invoices_failed'));
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
        ElMessage.error(t('billing_history_page.messages.load_detail_failed'));
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
      t,
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
  color: #0f172a;
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
