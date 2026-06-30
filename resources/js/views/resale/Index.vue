<template>
  <div class="resale-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6" v-for="item in statItems" :key="item.label">
        <el-card shadow="hover" :body-style="{ padding: '16px' }">
          <div class="stat-value text-2xl font-bold" :class="item.color">{{ item.value }}</div>
          <div class="stat-label text-gray-500 text-sm">{{ item.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Toolbar -->
    <el-card shadow="never" class="mb-4">
      <div class="flex justify-between items-center">
        <div class="flex gap-3">
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon> 创建挂牌
          </el-button>
          <el-button @click="activeTab = 'marketplace'" :type="activeTab === 'marketplace' ? 'primary' : ''">
            浏览市场
          </el-button>
          <el-button @click="activeTab = 'mine'" :type="activeTab === 'mine' ? 'primary' : ''">
            我的挂牌
          </el-button>
          <el-button @click="activeTab = 'transactions'" :type="activeTab === 'transactions' ? 'primary' : ''">
            交易记录
          </el-button>
        </div>
      </div>
    </el-card>

    <!-- 市场浏览 -->
    <el-card v-show="activeTab === 'marketplace'" shadow="never" v-loading="loadingMarket">
      <template #header><span class="font-semibold">二级市场</span></template>

      <!-- 搜索栏 -->
      <el-form :inline="true" size="small" class="mb-3">
        <el-form-item label="搜索">
          <el-input v-model="searchQuery" placeholder="搜索标题/描述" clearable @clear="loadMarketplace" @keyup.enter="loadMarketplace" />
        </el-form-item>
        <el-form-item label="价格范围">
          <el-input-number v-model="minPrice" :min="0" placeholder="最低" size="small" style="width:120px" />
          <span class="mx-2">—</span>
          <el-input-number v-model="maxPrice" :min="0" placeholder="最高" size="small" style="width:120px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadMarketplace">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="marketplaceItems" stripe size="small" @row-click="viewListingDetail">
        <el-table-column label="参考号" prop="reference" width="140" />
        <el-table-column label="标题" prop="title" min-width="200" />
        <el-table-column label="产品" width="140">
          <template #default="{ row }">
            {{ row.license?.product?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="售价" width="120" align="right">
          <template #default="{ row }">
            <span class="text-red-500 font-semibold">¥{{ row.asking_price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="卖家" width="140">
          <template #default="{ row }">
            {{ row.seller_customer?.user?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="挂牌时间" width="160">
          <template #default="{ row }">{{ row.listed_at ? new Date(row.listed_at).toLocaleDateString() : '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click.stop="handleBuy(row)">购买</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-3" v-if="marketplaceTotal > marketplacePerPage">
        <el-pagination
          v-model:current-page="marketplacePage"
          :page-size="marketplacePerPage"
          :total="marketplaceTotal"
          layout="prev, pager, next"
          @current-change="loadMarketplace"
        />
      </div>
    </el-card>

    <!-- 我的挂牌 -->
    <el-card v-show="activeTab === 'mine'" shadow="never" v-loading="loadingMine">
      <template #header><span class="font-semibold">我的挂牌</span></template>

      <el-table :data="myListings" stripe size="small">
        <el-table-column label="参考号" prop="reference" width="140" />
        <el-table-column label="标题" prop="title" min-width="180" />
        <el-table-column label="售价" width="100" align="right">
          <template #default="{ row }">¥{{ row.asking_price }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="statusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="交易数" width="80" align="center">
          <template #default="{ row }">{{ row.transactions?.length || 0 }}</template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click.stop="editListing(row)" :disabled="row.status !== 'draft'">编辑</el-button>
            <el-button size="small" type="primary" @click.stop="publishMyListing(row)" :disabled="row.status !== 'draft'">发布</el-button>
            <el-button
              size="small"
              type="danger"
              plain
              @click.stop="cancelMyListing(row)"
              :disabled="row.status === 'sold' || row.status === 'cancelled'"
            >取消</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-3" v-if="mineTotal > minePerPage">
        <el-pagination
          v-model:current-page="minePage"
          :page-size="minePerPage"
          :total="mineTotal"
          layout="prev, pager, next"
          @current-change="loadMyListings"
        />
      </div>
    </el-card>

    <!-- 交易记录 -->
    <el-card v-show="activeTab === 'transactions'" shadow="never">
      <template #header><span class="font-semibold">交易记录</span></template>
      <el-empty description="暂无交易记录" />
    </el-card>

    <!-- 创建挂牌对话框 -->
    <el-dialog v-model="showCreateDialog" title="创建挂牌" width="600px" :close-on-click-modal="false">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px" v-loading="loadingSellable">
        <el-form-item label="License" prop="license_id">
          <el-select v-model="createForm.license_id" placeholder="选择要转售的 License" filterable style="width:100%">
            <el-option
              v-for="lic in sellableLicenses"
              :key="lic.id"
              :label="`#${lic.id} - ${lic.license_key} (${lic.product?.name || '-'})`"
              :value="lic.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="标题" prop="title">
          <el-input v-model="createForm.title" placeholder="例：专业版 License 一年转售" />
        </el-form-item>

        <el-form-item label="描述" prop="description">
          <el-input v-model="createForm.description" type="textarea" :rows="3" placeholder="描述 License 情况、转让条款等" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="售价" prop="asking_price">
              <el-input-number v-model="createForm.asking_price" :min="0.01" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="币种" prop="currency">
              <el-select v-model="createForm.currency">
                <el-option label="CNY (人民币)" value="CNY" />
                <el-option label="USD (美元)" value="USD" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="手续费率(%)" prop="commission_rate">
          <el-input-number v-model="createForm.commission_rate" :min="0" :max="100" :precision="2" />
          <span class="ml-2 text-gray-400 text-sm">平台佣金百分比</span>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">创建挂牌</el-button>
      </template>
    </el-dialog>

    <!-- 购买确认对话框 -->
    <el-dialog v-model="showBuyDialog" title="确认购买" width="500px">
      <div v-if="buyTarget">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="挂牌">{{ buyTarget.reference }} - {{ buyTarget.title }}</el-descriptions-item>
          <el-descriptions-item label="售价">¥{{ buyTarget.asking_price }}</el-descriptions-item>
          <el-descriptions-item label="卖家">
            {{ buyTarget.seller_customer?.user?.name || buyTarget.seller_customer_id }}
          </el-descriptions-item>
          <el-descriptions-item label="产品">{{ buyTarget.license?.product?.name || '-' }}</el-descriptions-item>
        </el-descriptions>
        <el-alert type="warning" :closable="false" class="mt-3">
          <template #title>确认购买后，您需要完成付款。交易完成后 License 的所有权将转移给您。</template>
        </el-alert>
      </div>
      <template #footer>
        <el-button @click="showBuyDialog = false">取消</el-button>
        <el-button type="primary" :loading="buying" @click="handleBuyConfirm">确认购买</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import resaleApi from '@/api/resale';

// ─── 状态 ───
const activeTab = ref('marketplace');

// 统计
const stats = reactive({
  active_listings: 0,
  total_sold: 0,
  total_commission_revenue: 0,
  average_sale_price: 0,
});

const statItems = computed(() => [
  { label: '市场上架', value: stats.active_listings, color: 'text-blue-500' },
  { label: '已成交', value: stats.total_sold, color: 'text-green-500' },
  { label: '佣金收入', value: `¥${stats.total_commission_revenue}`, color: 'text-orange-500' },
  { label: '均价', value: `¥${stats.average_sale_price}`, color: 'text-purple-500' },
]);

// 市场浏览
const loadingMarket = ref(false);
const marketplaceItems = ref([]);
const marketplacePage = ref(1);
const marketplacePerPage = ref(20);
const marketplaceTotal = ref(0);
const searchQuery = ref('');
const minPrice = ref(null);
const maxPrice = ref(null);

// 我的挂牌
const loadingMine = ref(false);
const myListings = ref([]);
const minePage = ref(1);
const minePerPage = ref(20);
const mineTotal = ref(0);

// 创建
const showCreateDialog = ref(false);
const loadingSellable = ref(false);
const creating = ref(false);
const sellableLicenses = ref([]);
const createFormRef = ref(null);

const createForm = reactive({
  license_id: null,
  title: '',
  description: '',
  asking_price: 0,
  currency: 'CNY',
  commission_rate: 5.00,
});

const createRules = {
  license_id: [{ required: true, message: '请选择 License' }],
  title: [{ required: true, message: '请输入标题' }],
  asking_price: [{ required: true, message: '请输入售价' }],
};

// 购买
const showBuyDialog = ref(false);
const buyTarget = ref(null);
const buying = ref(false);

// ─── 方法 ───

function statusType(status) {
  const map = { draft: 'info', published: 'warning', pending_review: 'warning', active: 'success', sold: '', cancelled: 'danger', expired: 'info' };
  return map[status] || 'info';
}

function statusLabel(status) {
  const map = { draft: '草稿', published: '已发布', pending_review: '待审核', active: '上架中', sold: '已售出', cancelled: '已取消', expired: '已过期' };
  return map[status] || status;
}

async function loadMarketplace() {
  loadingMarket.value = true;
  try {
    const params = {
      page: marketplacePage.value,
      per_page: marketplacePerPage.value,
    };
    if (searchQuery.value) params.search = searchQuery.value;
    if (minPrice.value !== null) params.min_price = minPrice.value;
    if (maxPrice.value !== null) params.max_price = maxPrice.value;

    const { data } = await resaleApi.browseMarketplace(params);
    const result = data?.data;
    marketplaceItems.value = result?.items || [];
    marketplaceTotal.value = result?.total || 0;
  } catch (e) {
    ElMessage.error('加载市场数据失败');
  } finally {
    loadingMarket.value = false;
  }
}

async function loadMyListings() {
  loadingMine.value = true;
  try {
    const { data } = await resaleApi.getMyListings({ page: minePage.value, per_page: minePerPage.value });
    const result = data?.data;
    myListings.value = result?.items || [];
    mineTotal.value = result?.total || 0;
  } catch (e) {
    ElMessage.error('加载我的挂牌失败');
  } finally {
    loadingMine.value = false;
  }
}

async function loadStats() {
  try {
    const { data } = await resaleApi.getMarketStats();
    Object.assign(stats, data?.data || {});
  } catch (e) { /* ignore */ }
}

async function loadSellableLicenses() {
  loadingSellable.value = true;
  try {
    const { data } = await resaleApi.getSellableLicenses();
    sellableLicenses.value = data?.data || [];
  } catch (e) {
    ElMessage.error('加载可挂牌 License 失败');
  } finally {
    loadingSellable.value = false;
  }
}

function resetSearch() {
  searchQuery.value = '';
  minPrice.value = null;
  maxPrice.value = null;
  marketplacePage.value = 1;
  loadMarketplace();
}

async function handleCreate() {
  const valid = await createFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  creating.value = true;
  try {
    await resaleApi.createListing(createForm);
    ElMessage.success('挂牌已创建');
    showCreateDialog.value = false;
    loadMyListings();
    loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '创建失败');
  } finally {
    creating.value = false;
  }
}

function viewListingDetail(row) {
  // 双击可查看详情
}

async function handleBuy(row) {
  buyTarget.value = row;
  showBuyDialog.value = true;
}

async function handleBuyConfirm() {
  if (!buyTarget.value) return;
  buying.value = true;
  try {
    await resaleApi.purchaseListing(buyTarget.value.id);
    ElMessage.success('购买请求已提交');
    showBuyDialog.value = false;
    loadMarketplace();
    loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '购买失败');
  } finally {
    buying.value = false;
  }
}

function editListing(row) {
  Object.assign(createForm, {
    license_id: row.license_id,
    title: row.title,
    description: row.description,
    asking_price: row.asking_price,
    currency: row.currency,
    commission_rate: row.commission_rate,
  });
  showCreateDialog.value = true;
}

async function publishMyListing(row) {
  try {
    await resaleApi.publishListing(row.id);
    ElMessage.success('挂牌已提交审核');
    loadMyListings();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '发布失败');
  }
}

async function cancelMyListing(row) {
  try {
    await ElMessageBox.confirm('确定要取消此挂牌吗？');
    await resaleApi.cancelListing(row.id);
    ElMessage.success('挂牌已取消');
    loadMyListings();
    loadStats();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.response?.data?.message || '取消失败');
    }
  }
}

onMounted(() => {
  loadMarketplace();
  loadStats();
});
</script>

<style scoped>
.flex {
  display: flex;
}
.justify-between {
  justify-content: space-between;
}
.justify-center {
  justify-content: center;
}
.items-center {
  align-items: center;
}
.gap-3 {
  gap: 12px;
}
.mb-3 {
  margin-bottom: 12px;
}
.mb-4 {
  margin-bottom: 16px;
}
.mt-3 {
  margin-top: 12px;
}
.mx-2 {
  margin: 0 8px;
}
.ml-2 {
  margin-left: 8px;
}
.text-gray-400 {
  color: #909399;
}
.text-gray-500 {
  color: #909399;
}
.text-red-500 {
  color: #f56c6c;
}
.text-2xl {
  font-size: 24px;
}
.font-bold {
  font-weight: 700;
}
.font-semibold {
  font-weight: 600;
}
.text-sm {
  font-size: 13px;
}
</style>
