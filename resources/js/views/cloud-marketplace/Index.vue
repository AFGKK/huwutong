<template>
  <div class="cloud-marketplace-page">
    <!-- 页面标题 -->
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ t('nav.cloud_marketplace') }}</h1>
      <p class="text-sm text-gray-500 mt-1">{{ t('cloud_marketplace_page.subtitle') }}</p>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading" class="text-center py-16">
      <el-skeleton :rows="5" animated />
    </div>

    <template v-else>
      <!-- 状态卡片 -->
      <el-row :gutter="20" class="mb-6">
        <el-col :span="6" v-for="mp in marketplaces" :key="mp.key">
          <el-card shadow="hover" :class="['marketplace-card', mp.enabled ? 'border-l-4 border-l-green-500' : 'border-l-4 border-l-gray-300']">
            <div class="flex items-center gap-3">
              <div class="text-3xl">{{ mp.icon }}</div>
              <div>
                <div class="font-semibold text-gray-800">{{ mp.name }}</div>
                <el-tag :type="mp.enabled ? 'success' : 'info'" size="small">
                  {{ mp.enabled ? t('cloud_marketplace_page.status.enabled') : t('cloud_marketplace_page.status.disabled') }}
                </el-tag>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 统计概览 -->
      <el-row :gutter="20" class="mb-6">
        <el-col :span="6" v-for="stat in stats" :key="stat.label">
          <el-card shadow="never" class="text-center">
            <div class="text-2xl font-bold text-primary-500">{{ stat.value }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ stat.label }}</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- Tabs: 产品映射 / 订阅管理 / 计量记录 -->
      <el-card shadow="never">
        <el-tabs v-model="activeTab">
          <el-tab-pane :label="t('cloud_marketplace_page.tabs.products')" name="products">
            <div class="mb-4 flex justify-between items-center">
              <el-select v-model="productFilter.marketplace" :placeholder="t('cloud_marketplace_page.filters.marketplace_ph')" clearable size="small" style="width:200px" @change="fetchProducts">
                <el-option v-for="opt in marketplaceFilterOptions" :key="opt.value || 'all'" :label="opt.label" :value="opt.value" />
              </el-select>
              <el-button type="primary" size="small" @click="showProductDialog = true">
                <el-icon><Plus /></el-icon> {{ t('cloud_marketplace_page.btn_create_mapping') }}
              </el-button>
            </div>
            <el-table :data="products" v-loading="productLoading" stripe size="small">
              <el-table-column prop="marketplace" :label="t('cloud_marketplace_page.columns.marketplace')" width="140">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="offer_id" :label="t('cloud_marketplace_page.columns.offer_id')" min-width="200" />
              <el-table-column prop="offer_name" :label="t('cloud_marketplace_page.columns.name')" min-width="160" />
              <el-table-column prop="status" :label="t('licenses_page.status')" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ productStatusLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" :label="t('licenses_page.col_created_at')" width="160" />
              <el-table-column :label="t('licenses_page.col_actions')" width="160" fixed="right">
                <template #default="{ row }">
                  <el-button text size="small" @click="editProduct(row)">{{ t('actions.edit') }}</el-button>
                  <el-popconfirm :title="t('cloud_marketplace_page.confirm.delete_mapping')" @confirm="deleteProduct(row)">
                    <template #reference>
                      <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                    </template>
                  </el-popconfirm>
                </template>
              </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center" v-if="productPagination.total > productPagination.per_page">
              <el-pagination v-model:current-page="productPagination.current_page" :page-size="productPagination.per_page" :total="productPagination.total" layout="prev, pager, next" @current-change="fetchProducts" />
            </div>
          </el-tab-pane>

          <el-tab-pane :label="t('cloud_marketplace_page.tabs.subscriptions')" name="subscriptions">
            <div class="mb-4 flex gap-3">
              <el-select v-model="subFilter.marketplace" :placeholder="t('cloud_marketplace_page.filters.marketplace')" clearable size="small" style="width:160px" @change="fetchSubscriptions">
                <el-option v-for="opt in marketplaceShortFilterOptions" :key="opt.value || 'all'" :label="opt.label" :value="opt.value" />
              </el-select>
              <el-select v-model="subFilter.status" :placeholder="t('licenses_page.status')" clearable size="small" style="width:160px" @change="fetchSubscriptions">
                <el-option v-for="opt in subscriptionStatusFilterOptions" :key="opt.value || 'all'" :label="opt.label" :value="opt.value" />
              </el-select>
            </div>
            <el-table :data="subscriptions" v-loading="subLoading" stripe size="small">
              <el-table-column prop="marketplace" :label="t('cloud_marketplace_page.columns.marketplace')" width="100">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="marketplace_subscription_id" :label="t('cloud_marketplace_page.columns.marketplace_subscription_id')" min-width="200" />
              <el-table-column prop="customer_name" :label="t('licenses_page.customer')" min-width="150" />
              <el-table-column prop="tier" :label="t('cloud_marketplace_page.columns.tier')" width="120" />
              <el-table-column prop="status" :label="t('licenses_page.status')" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'warning' : 'info'" size="small">{{ subscriptionStatusLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="activated_at" :label="t('cloud_marketplace_page.columns.activated_at')" width="160" />
              <el-table-column :label="t('licenses_page.col_actions')" width="120" fixed="right">
                <template #default="{ row }">
                  <el-button text size="small" @click="viewSubscription(row)">{{ t('customers_page.detail') }}</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center" v-if="subPagination.total > subPagination.per_page">
              <el-pagination v-model:current-page="subPagination.current_page" :page-size="subPagination.per_page" :total="subPagination.total" layout="prev, pager, next" @current-change="fetchSubscriptions" />
            </div>
          </el-tab-pane>

          <el-tab-pane :label="t('cloud_marketplace_page.tabs.metering')" name="metering">
            <div class="mb-4">
              <el-button type="primary" size="small" @click="showMeteringDialog = true">
                <el-icon><Plus /></el-icon> {{ t('cloud_marketplace_page.btn_report_metering') }}
              </el-button>
            </div>
            <el-table :data="meteringRecords" v-loading="meteringLoading" stripe size="small">
              <el-table-column prop="marketplace" :label="t('cloud_marketplace_page.columns.marketplace')" width="100">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="dimension" :label="t('cloud_marketplace_page.columns.dimension')" width="140" />
              <el-table-column prop="quantity" :label="t('licenses_page.count')" width="100" />
              <el-table-column prop="metered_at" :label="t('cloud_marketplace_page.columns.metered_at')" width="160" />
              <el-table-column prop="reported_at" :label="t('cloud_marketplace_page.columns.reported_at')" width="160" />
              <el-table-column prop="status" :label="t('licenses_page.status')" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'reported' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ meteringStatusLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="error_message" :label="t('cloud_marketplace_page.columns.error_message')" min-width="200" />
            </el-table>
            <div class="mt-4 flex justify-center" v-if="meteringPagination.total > meteringPagination.per_page">
              <el-pagination v-model:current-page="meteringPagination.current_page" :page-size="meteringPagination.per_page" :total="meteringPagination.total" layout="prev, pager, next" @current-change="fetchMetering" />
            </div>
          </el-tab-pane>
        </el-tabs>
      </el-card>
    </template>

    <!-- 新建/编辑产品对话框 -->
    <el-dialog v-model="showProductDialog" :title="editingProduct ? t('cloud_marketplace_page.product_dialog.edit_title') : t('cloud_marketplace_page.product_dialog.create_title')" width="540px">
      <el-form :model="productForm" label-position="top" size="small">
        <el-form-item :label="t('cloud_marketplace_page.columns.marketplace')" required>
          <el-select v-model="productForm.marketplace" :disabled="!!editingProduct" style="width:100%">
            <el-option :label="t('cloud_marketplace_page.aws_marketplace')" value="aws" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('cloud_marketplace_page.columns.offer_id')" required>
          <el-input v-model="productForm.offer_id" :disabled="!!editingProduct" :placeholder="t('cloud_marketplace_page.product_dialog.offer_id_ph')" />
        </el-form-item>
        <el-form-item :label="t('cloud_marketplace_page.columns.name')">
          <el-input v-model="productForm.offer_name" :placeholder="t('cloud_marketplace_page.product_dialog.offer_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('licenses_page.status')">
          <el-select v-model="productForm.status" style="width:100%">
            <el-option v-for="opt in productStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('cloud_marketplace_page.product_dialog.description')">
          <el-input v-model="productForm.description" type="textarea" :rows="3" :placeholder="t('cloud_marketplace_page.product_dialog.description_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showProductDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveProduct" :loading="saving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 手动上报计量对话框 -->
    <el-dialog v-model="showMeteringDialog" :title="t('cloud_marketplace_page.metering_dialog.title')" width="480px">
      <el-form :model="meteringForm" label-position="top" size="small">
        <el-form-item :label="t('cloud_marketplace_page.metering_dialog.subscription')" required>
          <el-select v-model="meteringForm.subscription_id" filterable style="width:100%">
            <el-option v-for="sub in subscriptions" :key="sub.id" :label="`[${sub.marketplace}] ${sub.customer_name} - ${sub.tier}`" :value="sub.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('cloud_marketplace_page.columns.dimension')" required>
          <el-input v-model="meteringForm.dimension" :placeholder="t('cloud_marketplace_page.metering_dialog.dimension_ph')" />
        </el-form-item>
        <el-form-item :label="t('licenses_page.count')" required>
          <el-input-number v-model="meteringForm.quantity" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showMeteringDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveMetering" :loading="saving">{{ t('cloud_marketplace_page.btn_report') }}</el-button>
      </template>
    </el-dialog>

    <!-- 订阅详情对话框 -->
    <el-dialog v-model="showSubDialog" :title="t('cloud_marketplace_page.sub_dialog.title', { id: subDetail?.id || '' })" width="640px">
      <template v-if="subDetail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('cloud_marketplace_page.columns.marketplace')">{{ subDetail.marketplace?.toUpperCase() }}</el-descriptions-item>
          <el-descriptions-item :label="t('licenses_page.status')">
            <el-tag :type="subDetail.status === 'active' ? 'success' : 'info'" size="small">{{ subscriptionStatusLabel(subDetail.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('cloud_marketplace_page.columns.marketplace_subscription_id')" :span="2">{{ subDetail.marketplace_subscription_id }}</el-descriptions-item>
          <el-descriptions-item :label="t('cloud_marketplace_page.sub_dialog.customer_name')">{{ subDetail.customer_name }}</el-descriptions-item>
          <el-descriptions-item :label="t('cloud_marketplace_page.columns.tier')">{{ subDetail.tier }}</el-descriptions-item>
          <el-descriptions-item :label="t('cloud_marketplace_page.sub_dialog.subscribed_at')">{{ subDetail.subscribed_at }}</el-descriptions-item>
          <el-descriptions-item :label="t('cloud_marketplace_page.columns.activated_at')">{{ subDetail.activated_at }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="text-sm font-semibold text-gray-700 mt-4 mb-2">{{ t('cloud_marketplace_page.sub_dialog.recent_metering') }}</h4>
        <el-table :data="subDetail.metering || []" size="small" max-height="200">
          <el-table-column prop="dimension" :label="t('cloud_marketplace_page.columns.dimension_short')" />
          <el-table-column prop="quantity" :label="t('licenses_page.count')" />
          <el-table-column prop="metered_at" :label="t('cloud_marketplace_page.columns.time')" />
          <el-table-column prop="status" :label="t('licenses_page.status')">
            <template #default="{ row }">
              <el-tag :type="row.status === 'reported' ? 'success' : 'warning'" size="small">{{ meteringStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getMarketplaceStatus,
  getMarketplaceProducts,
  createMarketplaceProduct,
  updateMarketplaceProduct,
  deleteMarketplaceProduct,
  getMarketplaceSubscriptions,
  getMarketplaceSubscription,
  getMarketplaceMetering,
  reportMarketplaceMetering,
} from '@/api/cloudMarketplace';

const { t } = useI18n();

const loading = ref(true);
const activeTab = ref('products');

// ─── 云市场状态 ───
const marketplaces = ref([]);
const stats = ref([]);

// ─── 产品映射 ───
const products = ref([]);
const productLoading = ref(false);
const productFilter = reactive({ marketplace: '' });
const productPagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const showProductDialog = ref(false);
const editingProduct = ref(null);
const productForm = reactive({ marketplace: 'aws', offer_id: '', offer_name: '', status: 'active', description: '' });
const saving = ref(false);

// ─── 订阅管理 ───
const subscriptions = ref([]);
const subLoading = ref(false);
const subFilter = reactive({ marketplace: '', status: '' });
const subPagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const showSubDialog = ref(false);
const subDetail = ref(null);

// ─── 计量记录 ───
const meteringRecords = ref([]);
const meteringLoading = ref(false);
const meteringPagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const showMeteringDialog = ref(false);
const meteringForm = reactive({ subscription_id: null, dimension: '', quantity: 1 });

const marketplaceFilterOptions = computed(() => [
  { label: t('licenses_page.all'), value: '' },
  { label: t('cloud_marketplace_page.aws_marketplace'), value: 'aws' },
]);

const marketplaceShortFilterOptions = computed(() => [
  { label: t('licenses_page.all'), value: '' },
  { label: t('cloud_marketplace_page.aws'), value: 'aws' },
]);

const subscriptionStatusFilterOptions = computed(() => [
  { label: t('licenses_page.all'), value: '' },
  { label: t('cloud_marketplace_page.sub_status.subscribed'), value: 'subscribed' },
  { label: t('cloud_marketplace_page.sub_status.active'), value: 'active' },
  { label: t('cloud_marketplace_page.sub_status.suspended'), value: 'suspended' },
  { label: t('cloud_marketplace_page.sub_status.cancelled'), value: 'cancelled' },
]);

const productStatusOptions = computed(() => [
  { label: t('cloud_marketplace_page.product_status.active'), value: 'active' },
  { label: t('cloud_marketplace_page.product_status.inactive'), value: 'inactive' },
  { label: t('cloud_marketplace_page.product_status.deprecated'), value: 'deprecated' },
]);

const productStatusLabels = computed(() => ({
  active: t('cloud_marketplace_page.product_status.active'),
  inactive: t('cloud_marketplace_page.product_status.inactive'),
  deprecated: t('cloud_marketplace_page.product_status.deprecated'),
}));

const subscriptionStatusLabels = computed(() => ({
  subscribed: t('cloud_marketplace_page.sub_status.subscribed'),
  active: t('cloud_marketplace_page.sub_status.active'),
  suspended: t('cloud_marketplace_page.sub_status.suspended'),
  cancelled: t('cloud_marketplace_page.sub_status.cancelled'),
}));

const meteringStatusLabels = computed(() => ({
  reported: t('cloud_marketplace_page.metering_status.reported'),
  failed: t('cloud_marketplace_page.metering_status.failed'),
  pending: t('cloud_marketplace_page.metering_status.pending'),
}));

function productStatusLabel(status) {
  return productStatusLabels.value[status] || status;
}

function subscriptionStatusLabel(status) {
  return subscriptionStatusLabels.value[status] || status;
}

function meteringStatusLabel(status) {
  return meteringStatusLabels.value[status] || status;
}

// ─── 加载初始数据 ───
async function loadData() {
  loading.value = true;
  try {
    const res = await getMarketplaceStatus();
    if (res.data?.success) {
      marketplaces.value = res.data.data.marketplaces;
      stats.value = [
        { label: t('cloud_marketplace_page.stats.product_mappings'), value: res.data.data.total_products },
        { label: t('cloud_marketplace_page.stats.active_subscriptions'), value: res.data.data.active_subscriptions },
        { label: t('cloud_marketplace_page.stats.total_subscriptions'), value: res.data.data.total_subscriptions },
        { label: t('cloud_marketplace_page.stats.pending_metering'), value: res.data.data.pending_metering },
      ];
    }
    await fetchProducts();
    await fetchSubscriptions();
    await fetchMetering();
  } catch (e) {
    ElMessage.error(t('cloud_marketplace_page.messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

// ─── 产品映射 CRUD ───
async function fetchProducts(page = 1) {
  productLoading.value = true;
  try {
    const res = await getMarketplaceProducts({ ...productFilter, page });
    if (res.data?.success) {
      products.value = res.data.data.data;
      productPagination.current_page = res.data.data.current_page;
      productPagination.total = res.data.data.total;
    }
  } finally {
    productLoading.value = false;
  }
}

function editProduct(row) {
  editingProduct.value = row;
  Object.assign(productForm, {
    marketplace: row.marketplace,
    offer_id: row.offer_id,
    offer_name: row.offer_name,
    status: row.status,
    description: row.description,
  });
  showProductDialog.value = true;
}

function resetProductForm() {
  editingProduct.value = null;
  productForm.marketplace = 'aws';
  productForm.offer_id = '';
  productForm.offer_name = '';
  productForm.status = 'active';
  productForm.description = '';
}

async function saveProduct() {
  saving.value = true;
  try {
    if (editingProduct.value) {
      await updateMarketplaceProduct(editingProduct.value.id, productForm);
      ElMessage.success(t('messages.success'));
    } else {
      await createMarketplaceProduct(productForm);
      ElMessage.success(t('messages.success'));
    }
    showProductDialog.value = false;
    resetProductForm();
    await fetchProducts();
  } catch (e) {
    ElMessage.error(t('messages.failed'));
  } finally {
    saving.value = false;
  }
}

async function deleteProduct(row) {
  try {
    await deleteMarketplaceProduct(row.id);
    ElMessage.success(t('cloud_marketplace_page.messages.delete_ok'));
    await fetchProducts();
  } catch (e) {
    ElMessage.error(t('cloud_marketplace_page.messages.delete_failed'));
  }
}

// ─── 订阅管理 ───
async function fetchSubscriptions(page = 1) {
  subLoading.value = true;
  try {
    const res = await getMarketplaceSubscriptions({ ...subFilter, page });
    if (res.data?.success) {
      subscriptions.value = res.data.data.data;
      subPagination.current_page = res.data.data.current_page;
      subPagination.total = res.data.data.total;
    }
  } finally {
    subLoading.value = false;
  }
}

async function viewSubscription(row) {
  try {
    const res = await getMarketplaceSubscription(row.id);
    if (res.data?.success) {
      subDetail.value = res.data.data;
      showSubDialog.value = true;
    }
  } catch (e) {
    ElMessage.error(t('cloud_marketplace_page.messages.load_sub_failed'));
  }
}

// ─── 计量记录 ───
async function fetchMetering(page = 1) {
  meteringLoading.value = true;
  try {
    const res = await getMarketplaceMetering({ page });
    if (res.data?.success) {
      meteringRecords.value = res.data.data.data;
      meteringPagination.current_page = res.data.data.current_page;
      meteringPagination.total = res.data.data.total;
    }
  } finally {
    meteringLoading.value = false;
  }
}

async function saveMetering() {
  saving.value = true;
  try {
    await reportMarketplaceMetering(meteringForm);
    ElMessage.success(t('cloud_marketplace_page.messages.metering_reported'));
    showMeteringDialog.value = false;
    meteringForm.subscription_id = null;
    meteringForm.dimension = '';
    meteringForm.quantity = 1;
    await fetchMetering();
  } catch (e) {
    ElMessage.error(t('cloud_marketplace_page.messages.report_failed'));
  } finally {
    saving.value = false;
  }
}

onMounted(loadData);
</script>

<style scoped>
.cloud-marketplace-page {
  padding: 24px;
}
.marketplace-card {
  transition: all 0.2s;
}
.marketplace-card:hover {
  transform: translateY(-2px);
}
</style>
