<template>
  <div class="marketplace-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Shop /></el-icon>
        {{ t('admin.menu.license_marketplace') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('license_marketplace_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.activeListings }}</div>
          <div class="stat-label">{{ t('license_marketplace_page.stats.active_listings') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-warning">{{ stats.pendingApproval }}</div>
          <div class="stat-label">{{ t('license_marketplace_page.stats.pending_approval') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalTransactions }}</div>
          <div class="stat-label">{{ t('license_marketplace_page.stats.total_transactions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">¥{{ stats.totalRevenue?.toFixed(0) }}</div>
          <div class="stat-label">{{ t('license_marketplace_page.stats.total_revenue') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.openDisputes > 0 ? 'stat-danger' : ''">{{ stats.openDisputes }}</div>
          <div class="stat-label">{{ t('license_marketplace_page.stats.open_disputes') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 挂牌管理 -->
        <el-tab-pane :label="t('license_marketplace_page.tabs.listings')" name="listings">
          <div class="tab-toolbar">
            <el-select v-model="listingFilter.status" :placeholder="t('licenses_page.status')" clearable style="width:130px;margin-right:8px" @change="loadListings">
              <el-option v-for="opt in listingStatusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-input v-model="listingFilter.search" :placeholder="t('license_marketplace_page.filters.search_ph')" clearable style="width:200px" @clear="loadListings" @keyup.enter="loadListings" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateListing">
              <el-icon><Plus /></el-icon> {{ t('license_marketplace_page.btn_create') }}
            </el-button>
          </div>
          <el-table :data="listings" stripe v-loading="listingsLoading">
            <el-table-column :label="t('licenses_page.license_key')" width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.seller')" width="120">
              <template #default="{ row }">{{ row.seller?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('product.price')" width="100">
              <template #default="{ row }">¥{{ row.price }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.commission')" width="80">
              <template #default="{ row }">¥{{ row.commission }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="listingStatusType(row.status)" size="small">{{ listingStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.notes')" min-width="140">
              <template #default="{ row }">{{ row.notes || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.col_created_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.col_actions')" width="200" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">{{ t('actions.approve') }}</el-button>
                <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="handleReject(row)">{{ t('actions.reject') }}</el-button>
                <el-button v-if="row.status === 'approved'" size="small" type="warning" @click="showPurchase(row)">{{ t('license_marketplace_page.btn_purchase') }}</el-button>
                <el-button v-if="['pending','approved'].includes(row.status)" size="small" @click="handleCancel(row)">{{ t('actions.cancel') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="listingPagination.total > listingPagination.per_page">
            <el-pagination
              v-model:current-page="listingPagination.current_page"
              :page-size="listingPagination.per_page"
              :total="listingPagination.total"
              layout="prev, pager, next"
              @current-change="loadListings"
            />
          </div>
        </el-tab-pane>

        <!-- 交易记录 -->
        <el-tab-pane :label="t('license_marketplace_page.tabs.transactions')" name="transactions">
          <el-table :data="transactions" stripe v-loading="txLoading">
            <el-table-column :label="t('license_marketplace_page.columns.transaction_id')" width="180">
              <template #default="{ row }">{{ row.transaction_id }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.license_key')" width="160">
              <template #default="{ row }">{{ row.listing?.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.buyer')" width="120">
              <template #default="{ row }">{{ row.buyer?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('product.price')" width="100">
              <template #default="{ row }">¥{{ row.price }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.commission')" width="80">
              <template #default="{ row }">¥{{ row.commission }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.seller_payout')" width="100">
              <template #default="{ row }">¥{{ row.seller_payout }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : 'danger'" size="small">{{ txStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.completed_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.completed_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="txPagination.total > txPagination.per_page">
            <el-pagination
              v-model:current-page="txPagination.current_page"
              :page-size="txPagination.per_page"
              :total="txPagination.total"
              layout="prev, pager, next"
              @current-change="loadTransactions"
            />
          </div>
        </el-tab-pane>

        <!-- 纠纷管理 -->
        <el-tab-pane :label="t('license_marketplace_page.tabs.disputes')" name="disputes">
          <div class="tab-toolbar">
            <el-select v-model="disputeFilter.status" :placeholder="t('licenses_page.status')" clearable style="width:130px" @change="loadDisputes">
              <el-option v-for="opt in disputeStatusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
          <el-table :data="disputes" stripe v-loading="disputeLoading">
            <el-table-column :label="t('license_marketplace_page.columns.transaction')" width="180">
              <template #default="{ row }">{{ row.transaction?.transaction_id || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.raiser')" width="120">
              <template #default="{ row }">{{ row.raiser?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.type')" width="140">
              <template #default="{ row }">{{ disputeTypeLabel(row.type) }}</template>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.description')" min-width="180">
              <template #default="{ row }">
                <el-tooltip :content="row.description"><span>{{ row.description?.substring(0, 40) }}...</span></el-tooltip>
              </template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.status')" width="80">
              <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">{{ disputeStatusLabel(row.status) }}</el-tag>
            </el-table-column>
            <el-table-column :label="t('license_marketplace_page.columns.resolution')" width="120">
              <template #default="{ row }">{{ row.resolution || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.col_created_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('licenses_page.col_actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'open'" size="small" type="warning" @click="showResolveDispute(row)">{{ t('license_marketplace_page.btn_resolve') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="disputePagination.total > disputePagination.per_page">
            <el-pagination
              v-model:current-page="disputePagination.current_page"
              :page-size="disputePagination.per_page"
              :total="disputePagination.total"
              layout="prev, pager, next"
              @current-change="loadDisputes"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建挂牌对话框 -->
    <el-dialog v-model="createVisible" :title="t('license_marketplace_page.create_dialog.title')" width="500px">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="120px">
        <el-form-item :label="t('licenses_page.license_key')" prop="license_id">
          <el-select v-model="createForm.license_id" filterable style="width:100%" :placeholder="t('license_marketplace_page.create_dialog.select_license_ph')">
            <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('license_marketplace_page.create_dialog.seller_customer')" prop="seller_customer_id">
          <el-select v-model="createForm.seller_customer_id" filterable style="width:100%" :placeholder="t('license_marketplace_page.create_dialog.select_seller_ph')">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('product.price')" prop="price">
          <el-input-number v-model="createForm.price" :min="1" :max="999999" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('license_marketplace_page.columns.notes')">
          <el-input v-model="createForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('license_marketplace_page.create_dialog.submit') }}</el-button>
      </template>
    </el-dialog>

    <!-- 成交对话框 -->
    <el-dialog v-model="purchaseVisible" :title="t('license_marketplace_page.purchase_dialog.title')" width="450px">
      <el-form :model="purchaseForm" label-width="100px">
        <el-form-item :label="t('license_marketplace_page.purchase_dialog.buyer_customer')">
          <el-select v-model="purchaseForm.buyer_customer_id" filterable style="width:100%" :placeholder="t('license_marketplace_page.purchase_dialog.select_buyer_ph')">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('product.price')">
          <strong>¥{{ purchaseListing?.price }}</strong>
          <span class="text-muted" style="margin-left:8px">({{ t('license_marketplace_page.purchase_dialog.commission_note', { amount: purchaseListing?.commission }) }})</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="purchaseVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="success" @click="handlePurchase" :loading="submitting">{{ t('license_marketplace_page.purchase_dialog.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 纠纷处理对话框 -->
    <el-dialog v-model="resolveVisible" :title="t('license_marketplace_page.resolve_dialog.title')" width="500px">
      <el-form :model="resolveForm" label-width="100px">
        <el-form-item :label="t('license_marketplace_page.resolve_dialog.resolution')">
          <el-select v-model="resolveForm.resolution" style="width:100%">
            <el-option v-for="opt in resolutionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('license_marketplace_page.resolve_dialog.notes')">
          <el-input v-model="resolveForm.notes" type="textarea" :rows="4" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resolveVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleResolve" :loading="submitting">{{ t('license_marketplace_page.resolve_dialog.submit') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Shop, Refresh, Plus } from '@element-plus/icons-vue';
import marketplaceApi from '@/api/licenseMarketplace';

const { t, locale } = useI18n();

const loading = ref(false);
const submitting = ref(false);
const activeTab = ref('listings');

// 统计
const stats = ref({
  activeListings: 0, pendingApproval: 0, totalTransactions: 0,
  totalRevenue: 0, openDisputes: 0,
});

// 挂牌
const listings = ref([]);
const listingsLoading = ref(false);
const listingFilter = reactive({ status: '', search: '' });
const listingPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 交易
const transactions = ref([]);
const txLoading = ref(false);
const txPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 纠纷
const disputes = ref([]);
const disputeLoading = ref(false);
const disputeFilter = reactive({ status: '' });
const disputePagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 新建
const createVisible = ref(false);
const createFormRef = ref(null);
const createForm = reactive({ license_id: '', seller_customer_id: '', price: 100, notes: '' });
const createRules = computed(() => ({
  license_id: [{ required: true, message: t('license_marketplace_page.validation.license_required') }],
  seller_customer_id: [{ required: true, message: t('license_marketplace_page.validation.seller_required') }],
  price: [{ required: true, message: t('license_marketplace_page.validation.price_required') }],
}));

// 成交
const purchaseVisible = ref(false);
const purchaseListing = ref(null);
const purchaseForm = reactive({ buyer_customer_id: '' });

// 纠纷处理
const resolveVisible = ref(false);
const resolveDisputeRecord = ref(null);
const resolveForm = reactive({ resolution: 'refund_buyer', notes: '' });

// 选项
const licenseOptions = ref([]);
const customerOptions = ref([]);

const listingStatusFilterOptions = computed(() => [
  { label: t('licenses_page.all'), value: '' },
  { label: t('license_marketplace_page.listing_status.pending'), value: 'pending' },
  { label: t('license_marketplace_page.listing_status.approved'), value: 'approved' },
  { label: t('license_marketplace_page.listing_status.rejected'), value: 'rejected' },
  { label: t('license_marketplace_page.listing_status.sold'), value: 'sold' },
  { label: t('license_marketplace_page.listing_status.cancelled'), value: 'cancelled' },
]);

const disputeStatusFilterOptions = computed(() => [
  { label: t('licenses_page.all'), value: '' },
  { label: t('license_marketplace_page.dispute_status.open'), value: 'open' },
  { label: t('license_marketplace_page.dispute_status.investigation'), value: 'investigation' },
  { label: t('license_marketplace_page.dispute_status.resolved'), value: 'resolved' },
]);

const listingStatusTagLabels = computed(() => ({
  pending: t('license_marketplace_page.listing_status_tag.pending'),
  approved: t('license_marketplace_page.listing_status_tag.approved'),
  rejected: t('license_marketplace_page.listing_status_tag.rejected'),
  sold: t('license_marketplace_page.listing_status_tag.sold'),
  cancelled: t('license_marketplace_page.listing_status_tag.cancelled'),
}));

const disputeTypeLabels = computed(() => ({
  license_not_valid: t('license_marketplace_page.dispute_type.license_not_valid'),
  misrepresentation: t('license_marketplace_page.dispute_type.misrepresentation'),
  non_delivery: t('license_marketplace_page.dispute_type.non_delivery'),
  other: t('license_marketplace_page.dispute_type.other'),
}));

const disputeStatusLabels = computed(() => ({
  open: t('license_marketplace_page.dispute_status.open'),
  investigation: t('license_marketplace_page.dispute_status.investigation'),
  resolved: t('license_marketplace_page.dispute_status.resolved'),
}));

const txStatusLabels = computed(() => ({
  completed: t('license_marketplace_page.tx_status.completed'),
  failed: t('license_marketplace_page.tx_status.failed'),
  pending: t('license_marketplace_page.tx_status.pending'),
}));

const resolutionOptions = computed(() => [
  { label: t('license_marketplace_page.resolution.refund_buyer'), value: 'refund_buyer' },
  { label: t('license_marketplace_page.resolution.partial_refund'), value: 'partial_refund' },
  { label: t('license_marketplace_page.resolution.uphold_seller'), value: 'uphold_seller' },
  { label: t('license_marketplace_page.resolution.compromise'), value: 'compromise' },
]);

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await marketplaceApi.dashboard();
    stats.value = res.data?.data || res.data;
  } finally {
    loading.value = false;
  }
  loadListings();
  loadTransactions();
  loadDisputes();
}

// ═══════ 挂牌 ═══════

async function loadListings() {
  listingsLoading.value = true;
  try {
    const res = await marketplaceApi.listListings({ ...listingFilter, page: listingPagination.current_page });
    const d = res.data?.data || {};
    listings.value = d.data || [];
    Object.assign(listingPagination, d);
  } finally {
    listingsLoading.value = false;
  }
}

async function showCreateListing() {
  try {
    const [licRes, custRes] = await Promise.all([
      import('@/api/license').then(m => m.default.list({ per_page: 200 })),
      import('@/api/customer').then(m => m.default.list({ per_page: 200 })),
    ]);
    licenseOptions.value = licRes.data?.data || [];
    customerOptions.value = custRes.data?.data || [];
  } catch {
    // 使用空列表
  }
  createForm.license_id = '';
  createForm.seller_customer_id = '';
  createForm.price = 100;
  createForm.notes = '';
  createVisible.value = true;
}

async function handleCreate() {
  const valid = await createFormRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await marketplaceApi.createListing(createForm);
    ElMessage.success(t('license_marketplace_page.messages.listing_submitted'));
    createVisible.value = false;
    loadListings();
    refreshAll();
  } finally { submitting.value = false; }
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(
      t('license_marketplace_page.confirm.approve', { key: row.license?.license_key }),
      t('actions.confirm'),
    );
    await marketplaceApi.approveListing(row.id);
    ElMessage.success(t('license_marketplace_page.messages.approved'));
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('messages.failed')); }
}

async function handleReject(row) {
  try {
    const { value } = await ElMessageBox.prompt(
      t('license_marketplace_page.messages.reject_reason_ph'),
      t('license_marketplace_page.confirm.reject_title'),
    );
    await marketplaceApi.rejectListing(row.id, value);
    ElMessage.success(t('license_marketplace_page.messages.rejected'));
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('messages.failed')); }
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm(
      t('license_marketplace_page.confirm.cancel_listing'),
      t('actions.confirm'),
    );
    await marketplaceApi.cancelListing(row.id);
    ElMessage.success(t('license_marketplace_page.messages.cancelled'));
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('messages.failed')); }
}

// ═══════ 成交 ═══════

function showPurchase(row) {
  purchaseListing.value = row;
  purchaseForm.buyer_customer_id = '';
  purchaseVisible.value = true;
}

async function handlePurchase() {
  if (!purchaseForm.buyer_customer_id) {
    ElMessage.warning(t('license_marketplace_page.messages.select_buyer'));
    return;
  }
  submitting.value = true;
  try {
    await marketplaceApi.purchaseListing(purchaseListing.value.id, purchaseForm.buyer_customer_id);
    ElMessage.success(t('license_marketplace_page.messages.transaction_done'));
    purchaseVisible.value = false;
    loadListings();
    loadTransactions();
    refreshAll();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('license_marketplace_page.messages.transaction_failed'));
  } finally { submitting.value = false; }
}

// ═══════ 交易 ═══════

async function loadTransactions() {
  txLoading.value = true;
  try {
    const res = await marketplaceApi.listTransactions({ page: txPagination.current_page });
    transactions.value = res.data.data || [];
    Object.assign(txPagination, res.data?.meta || {});
  } finally { txLoading.value = false; }
}

// ═══════ 纠纷 ═══════

async function loadDisputes() {
  disputeLoading.value = true;
  try {
    const res = await marketplaceApi.listDisputes({ ...disputeFilter, page: disputePagination.current_page });
    disputes.value = res.data.data || [];
    Object.assign(disputePagination, res.data?.meta || {});
  } finally { disputeLoading.value = false; }
}

function showResolveDispute(row) {
  resolveDisputeRecord.value = row;
  resolveForm.resolution = 'refund_buyer';
  resolveForm.notes = '';
  resolveVisible.value = true;
}

async function handleResolve() {
  submitting.value = true;
  try {
    await marketplaceApi.resolveDispute(resolveDisputeRecord.value.id, resolveForm);
    ElMessage.success(t('license_marketplace_page.messages.dispute_resolved'));
    resolveVisible.value = false;
    loadDisputes();
    refreshAll();
  } catch (e) {
    ElMessage.error(t('license_marketplace_page.messages.resolve_failed'));
  } finally { submitting.value = false; }
}

// ═══════ 工具 ═══════

function listingStatusType(s) {
  return { pending: 'warning', approved: 'success', rejected: 'danger', sold: 'primary', cancelled: 'info' }[s] || 'info';
}
function listingStatusLabel(s) {
  return listingStatusTagLabels.value[s] || s;
}
function disputeTypeLabel(type) {
  return disputeTypeLabels.value[type] || type;
}
function disputeStatusLabel(s) {
  return disputeStatusLabels.value[s] || s;
}
function txStatusLabel(s) {
  return txStatusLabels.value[s] || s;
}
function formatTime(time) {
  if (!time) return '—';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(time).toLocaleString(loc);
}
</script>

<style scoped>
.marketplace-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #0f172a; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-muted { color: #909399; }
</style>
