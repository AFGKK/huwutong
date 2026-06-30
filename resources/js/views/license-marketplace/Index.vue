<template>
  <div class="marketplace-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Shop /></el-icon>
        License 二级市场
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.activeListings }}</div>
          <div class="stat-label">在售挂牌</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-warning">{{ stats.pendingApproval }}</div>
          <div class="stat-label">待审核</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalTransactions }}</div>
          <div class="stat-label">成交总数</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">¥{{ stats.totalRevenue?.toFixed(0) }}</div>
          <div class="stat-label">平台收入</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.openDisputes > 0 ? 'stat-danger' : ''">{{ stats.openDisputes }}</div>
          <div class="stat-label">待处理纠纷</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 挂牌管理 -->
        <el-tab-pane label="挂牌管理" name="listings">
          <div class="tab-toolbar">
            <el-select v-model="listingFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadListings">
              <el-option label="全部" value="" />
              <el-option label="待审核" value="pending" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
              <el-option label="已售出" value="sold" />
              <el-option label="已取消" value="cancelled" />
            </el-select>
            <el-input v-model="listingFilter.search" placeholder="搜索 License..." clearable style="width:200px" @clear="loadListings" @keyup.enter="loadListings" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateListing">
              <el-icon><Plus /></el-icon> 新建挂牌
            </el-button>
          </div>
          <el-table :data="listings" stripe v-loading="listingsLoading">
            <el-table-column label="License" width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="卖家" width="120">
              <template #default="{ row }">{{ row.seller?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="价格" width="100">
              <template #default="{ row }">¥{{ row.price }}</template>
            </el-table-column>
            <el-table-column label="抽成" width="80">
              <template #default="{ row }">¥{{ row.commission }}</template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="listingStatusType(row.status)" size="small">{{ listingStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="备注" min-width="140">
              <template #default="{ row }">{{ row.notes || '—' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">通过</el-button>
                <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="handleReject(row)">拒绝</el-button>
                <el-button v-if="row.status === 'approved'" size="small" type="warning" @click="showPurchase(row)">成交</el-button>
                <el-button v-if="['pending','approved'].includes(row.status)" size="small" @click="handleCancel(row)">取消</el-button>
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
        <el-tab-pane label="交易记录" name="transactions">
          <el-table :data="transactions" stripe v-loading="txLoading">
            <el-table-column label="交易ID" width="180">
              <template #default="{ row }">{{ row.transaction_id }}</template>
            </el-table-column>
            <el-table-column label="License" width="160">
              <template #default="{ row }">{{ row.listing?.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="买家" width="120">
              <template #default="{ row }">{{ row.buyer?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="价格" width="100">¥{{ row.price }}</el-table-column>
            <el-table-column label="抽成" width="80">¥{{ row.commission }}</el-table-column>
            <el-table-column label="卖家实收" width="100">¥{{ row.seller_payout }}</el-table-column>
            <el-table-column label="状态" width="80">
              <el-tag :type="row.status === 'completed' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </el-table-column>
            <el-table-column label="成交时间" width="160">{{ formatTime(row.completed_at) }}</el-table-column>
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
        <el-tab-pane label="纠纷管理" name="disputes">
          <div class="tab-toolbar">
            <el-select v-model="disputeFilter.status" placeholder="状态" clearable style="width:130px" @change="loadDisputes">
              <el-option label="全部" value="" />
              <el-option label="待处理" value="open" />
              <el-option label="调查中" value="investigation" />
              <el-option label="已解决" value="resolved" />
            </el-select>
          </div>
          <el-table :data="disputes" stripe v-loading="disputeLoading">
            <el-table-column label="交易" width="180">
              <template #default="{ row }">{{ row.transaction?.transaction_id || '—' }}</template>
            </el-table-column>
            <el-table-column label="发起方" width="120">
              <template #default="{ row }">{{ row.raiser?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="类型" width="140">{{ disputeTypeLabel(row.type) }}</el-table-column>
            <el-table-column label="描述" min-width="180">
              <template #default="{ row }">
                <el-tooltip :content="row.description"><span>{{ row.description?.substring(0, 40) }}...</span></el-tooltip>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
            </el-table-column>
            <el-table-column label="解决方案" width="120">
              <template #default="{ row }">{{ row.resolution || '—' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">{{ formatTime(row.created_at) }}</el-table-column>
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'open'" size="small" type="warning" @click="showResolveDispute(row)">处理</el-button>
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
    <el-dialog v-model="createVisible" title="新建挂牌" width="500px">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="120px">
        <el-form-item label="License" prop="license_id">
          <el-select v-model="createForm.license_id" filterable style="width:100%" placeholder="选择要转让的 License">
            <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="卖家客户" prop="seller_customer_id">
          <el-select v-model="createForm.seller_customer_id" filterable style="width:100%" placeholder="选择卖家">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="价格" prop="price">
          <el-input-number v-model="createForm.price" :min="1" :max="999999" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">提交挂牌</el-button>
      </template>
    </el-dialog>

    <!-- 成交对话框 -->
    <el-dialog v-model="purchaseVisible" title="执行交易" width="450px">
      <el-form :model="purchaseForm" label-width="100px">
        <el-form-item label="买家客户">
          <el-select v-model="purchaseForm.buyer_customer_id" filterable style="width:100%" placeholder="选择买家">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="价格">
          <strong>¥{{ purchaseListing?.price }}</strong>
          <span class="text-muted" style="margin-left:8px">(抽成: ¥{{ purchaseListing?.commission }})</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="purchaseVisible = false">取消</el-button>
        <el-button type="success" @click="handlePurchase" :loading="submitting">确认成交</el-button>
      </template>
    </el-dialog>

    <!-- 纠纷处理对话框 -->
    <el-dialog v-model="resolveVisible" title="处理纠纷" width="500px">
      <el-form :model="resolveForm" label-width="100px">
        <el-form-item label="解决方案">
          <el-select v-model="resolveForm.resolution" style="width:100%">
            <el-option label="全额退款给买家" value="refund_buyer" />
            <el-option label="部分退款" value="partial_refund" />
            <el-option label="支持卖家" value="uphold_seller" />
            <el-option label="双方妥协" value="compromise" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理备注">
          <el-input v-model="resolveForm.notes" type="textarea" :rows="4" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resolveVisible = false">取消</el-button>
        <el-button type="warning" @click="handleResolve" :loading="submitting">提交处理</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Shop, Refresh, Plus } from '@element-plus/icons-vue';
import marketplaceApi from '@/api/licenseMarketplace';

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
const createRules = {
  license_id: [{ required: true, message: '请选择 License' }],
  seller_customer_id: [{ required: true, message: '请选择卖家' }],
  price: [{ required: true, message: '请输入价格' }],
};

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

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await marketplaceApi.dashboard();
    stats.value = res.data;
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
    listings.value = res.data.data || [];
    Object.assign(listingPagination, res.data);
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
    ElMessage.success('挂牌已提交');
    createVisible.value = false;
    loadListings();
    refreshAll();
  } finally { submitting.value = false; }
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(`通过挂牌 ${row.license?.license_key}？`, '确认');
    await marketplaceApi.approveListing(row.id);
    ElMessage.success('已通过');
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error('操作失败'); }
}

async function handleReject(row) {
  try {
    const { value } = await ElMessageBox.prompt('输入拒绝原因', '拒绝挂牌');
    await marketplaceApi.rejectListing(row.id, value);
    ElMessage.success('已拒绝');
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error('操作失败'); }
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm('确定取消此挂牌？', '确认');
    await marketplaceApi.cancelListing(row.id);
    ElMessage.success('已取消');
    loadListings();
  } catch (e) { if (e !== 'cancel') ElMessage.error('操作失败'); }
}

// ═══════ 成交 ═══════

function showPurchase(row) {
  purchaseListing.value = row;
  purchaseForm.buyer_customer_id = '';
  purchaseVisible.value = true;
}

async function handlePurchase() {
  if (!purchaseForm.buyer_customer_id) { ElMessage.warning('请选择买家'); return; }
  submitting.value = true;
  try {
    await marketplaceApi.purchaseListing(purchaseListing.value.id, purchaseForm.buyer_customer_id);
    ElMessage.success('交易完成');
    purchaseVisible.value = false;
    loadListings();
    loadTransactions();
    refreshAll();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '交易失败');
  } finally { submitting.value = false; }
}

// ═══════ 交易 ═══════

async function loadTransactions() {
  txLoading.value = true;
  try {
    const res = await marketplaceApi.listTransactions({ page: txPagination.current_page });
    transactions.value = res.data.data || [];
    Object.assign(txPagination, res.data);
  } finally { txLoading.value = false; }
}

// ═══════ 纠纷 ═══════

async function loadDisputes() {
  disputeLoading.value = true;
  try {
    const res = await marketplaceApi.listDisputes({ ...disputeFilter, page: disputePagination.current_page });
    disputes.value = res.data.data || [];
    Object.assign(disputePagination, res.data);
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
    ElMessage.success('纠纷已处理');
    resolveVisible.value = false;
    loadDisputes();
    refreshAll();
  } catch (e) {
    ElMessage.error('处理失败');
  } finally { submitting.value = false; }
}

// ═══════ 工具 ═══════

function listingStatusType(s) {
  return { pending: 'warning', approved: 'success', rejected: 'danger', sold: 'primary', cancelled: 'info' }[s] || 'info';
}
function listingStatusLabel(s) {
  return { pending: '待审核', approved: '在售', rejected: '已拒绝', sold: '已售', cancelled: '已取消' }[s] || s;
}
function disputeTypeLabel(t) {
  return { license_not_valid: 'License无效', misrepresentation: '描述不符', non_delivery: '未交付', other: '其他' }[t] || t;
}
function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
.stat-primary { color: #409EFF; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-muted { color: #909399; }
</style>
