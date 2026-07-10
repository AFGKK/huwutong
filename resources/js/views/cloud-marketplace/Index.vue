<template>
  <div class="cloud-marketplace-page">
    <!-- 页面标题 -->
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">☁️ 云市场集成</h1>
      <p class="text-sm text-gray-500 mt-1">管理 AWS Marketplace 的对接</p>
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
                  {{ mp.enabled ? '已启用' : '未启用' }}
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
          <el-tab-pane label="📦 产品/Offer 映射" name="products">
            <div class="mb-4 flex justify-between items-center">
              <el-select v-model="productFilter.marketplace" placeholder="筛选云市场" clearable size="small" style="width:200px" @change="fetchProducts">
                <el-option label="全部" value="" />
                <el-option label="AWS Marketplace" value="aws" />

              </el-select>
              <el-button type="primary" size="small" @click="showProductDialog = true">
                <el-icon><Plus /></el-icon> 新建映射
              </el-button>
            </div>
            <el-table :data="products" v-loading="productLoading" stripe size="small">
              <el-table-column prop="marketplace" label="云市场" width="140">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="offer_id" label="Offer ID" min-width="200" />
              <el-table-column prop="offer_name" label="名称" min-width="160" />
              <el-table-column prop="status" label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="创建时间" width="160" />
              <el-table-column label="操作" width="160" fixed="right">
                <template #default="{ row }">
                  <el-button text size="small" @click="editProduct(row)">编辑</el-button>
                  <el-popconfirm title="删除此映射？" @confirm="deleteProduct(row)">
                    <template #reference>
                      <el-button text size="small" type="danger">删除</el-button>
                    </template>
                  </el-popconfirm>
                </template>
              </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center" v-if="productPagination.total > productPagination.per_page">
              <el-pagination v-model:current-page="productPagination.current_page" :page-size="productPagination.per_page" :total="productPagination.total" layout="prev, pager, next" @current-change="fetchProducts" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="🔗 订阅管理" name="subscriptions">
            <div class="mb-4 flex gap-3">
              <el-select v-model="subFilter.marketplace" placeholder="云市场" clearable size="small" style="width:160px" @change="fetchSubscriptions">
                <el-option label="全部" value="" />
                <el-option label="AWS" value="aws" />

              </el-select>
              <el-select v-model="subFilter.status" placeholder="状态" clearable size="small" style="width:160px" @change="fetchSubscriptions">
                <el-option label="全部" value="" />
                <el-option label="已订阅" value="subscribed" />
                <el-option label="活跃" value="active" />
                <el-option label="已暂停" value="suspended" />
                <el-option label="已取消" value="cancelled" />
              </el-select>
            </div>
            <el-table :data="subscriptions" v-loading="subLoading" stripe size="small">
              <el-table-column prop="marketplace" label="云市场" width="100">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="marketplace_subscription_id" label="三方订阅ID" min-width="200" />
              <el-table-column prop="customer_name" label="客户" min-width="150" />
              <el-table-column prop="tier" label="套餐" width="120" />
              <el-table-column prop="status" label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'warning' : 'info'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="activated_at" label="激活时间" width="160" />
              <el-table-column label="操作" width="120" fixed="right">
                <template #default="{ row }">
                  <el-button text size="small" @click="viewSubscription(row)">详情</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center" v-if="subPagination.total > subPagination.per_page">
              <el-pagination v-model:current-page="subPagination.current_page" :page-size="subPagination.per_page" :total="subPagination.total" layout="prev, pager, next" @current-change="fetchSubscriptions" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="📊 计量记录" name="metering">
            <div class="mb-4">
              <el-button type="primary" size="small" @click="showMeteringDialog = true">
                <el-icon><Plus /></el-icon> 手动上报计量
              </el-button>
            </div>
            <el-table :data="meteringRecords" v-loading="meteringLoading" stripe size="small">
              <el-table-column prop="marketplace" label="云市场" width="100">
                <template #default="{ row }">
                  <el-tag type="warning" size="small">{{ row.marketplace.toUpperCase() }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="dimension" label="计量维度" width="140" />
              <el-table-column prop="quantity" label="数量" width="100" />
              <el-table-column prop="metered_at" label="计量时间" width="160" />
              <el-table-column prop="reported_at" label="上报时间" width="160" />
              <el-table-column prop="status" label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'reported' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="error_message" label="错误信息" min-width="200" />
            </el-table>
            <div class="mt-4 flex justify-center" v-if="meteringPagination.total > meteringPagination.per_page">
              <el-pagination v-model:current-page="meteringPagination.current_page" :page-size="meteringPagination.per_page" :total="meteringPagination.total" layout="prev, pager, next" @current-change="fetchMetering" />
            </div>
          </el-tab-pane>
        </el-tabs>
      </el-card>
    </template>

    <!-- 新建/编辑产品对话框 -->
    <el-dialog v-model="showProductDialog" :title="editingProduct ? '编辑产品映射' : '新建产品映射'" width="540px">
      <el-form :model="productForm" label-position="top" size="small">
        <el-form-item label="云市场" required>
          <el-select v-model="productForm.marketplace" :disabled="!!editingProduct" style="width:100%">
            <el-option label="AWS Marketplace" value="aws" />

          </el-select>
        </el-form-item>
        <el-form-item label="Offer ID" required>
          <el-input v-model="productForm.offer_id" :disabled="!!editingProduct" placeholder="三方平台的 Offer/Product Code" />
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="productForm.offer_name" placeholder="可读名称" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="productForm.status" style="width:100%">
            <el-option label="启用" value="active" />
            <el-option label="停用" value="inactive" />
            <el-option label="已弃用" value="deprecated" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="productForm.description" type="textarea" :rows="3" placeholder="可选描述" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showProductDialog = false">取消</el-button>
        <el-button type="primary" @click="saveProduct" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 手动上报计量对话框 -->
    <el-dialog v-model="showMeteringDialog" title="手动上报计量" width="480px">
      <el-form :model="meteringForm" label-position="top" size="small">
        <el-form-item label="订阅" required>
          <el-select v-model="meteringForm.subscription_id" filterable style="width:100%">
            <el-option v-for="sub in subscriptions" :key="sub.id" :label="`[${sub.marketplace}] ${sub.customer_name} - ${sub.tier}`" :value="sub.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="计量维度" required>
          <el-input v-model="meteringForm.dimension" placeholder="如: api_calls, storage_gb, users" />
        </el-form-item>
        <el-form-item label="数量" required>
          <el-input-number v-model="meteringForm.quantity" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showMeteringDialog = false">取消</el-button>
        <el-button type="primary" @click="saveMetering" :loading="saving">上报</el-button>
      </template>
    </el-dialog>

    <!-- 订阅详情对话框 -->
    <el-dialog v-model="showSubDialog" :title="'订阅详情 #' + (subDetail?.id || '')" width="640px">
      <template v-if="subDetail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="云市场">{{ subDetail.marketplace?.toUpperCase() }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="subDetail.status === 'active' ? 'success' : 'info'" size="small">{{ subDetail.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="三方订阅ID" :span="2">{{ subDetail.marketplace_subscription_id }}</el-descriptions-item>
          <el-descriptions-item label="客户名称">{{ subDetail.customer_name }}</el-descriptions-item>
          <el-descriptions-item label="套餐">{{ subDetail.tier }}</el-descriptions-item>
          <el-descriptions-item label="订阅时间">{{ subDetail.subscribed_at }}</el-descriptions-item>
          <el-descriptions-item label="激活时间">{{ subDetail.activated_at }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="text-sm font-semibold text-gray-700 mt-4 mb-2">最近计量记录</h4>
        <el-table :data="subDetail.metering || []" size="small" max-height="200">
          <el-table-column prop="dimension" label="维度" />
          <el-table-column prop="quantity" label="数量" />
          <el-table-column prop="metered_at" label="时间" />
          <el-table-column prop="status" label="状态">
            <template #default="{ row }">
              <el-tag :type="row.status === 'reported' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
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

// ─── 加载初始数据 ───
async function loadData() {
  loading.value = true;
  try {
    const res = await getMarketplaceStatus();
    if (res.data?.success) {
      marketplaces.value = res.data.data.marketplaces;
      stats.value = [
        { label: '产品映射', value: res.data.data.total_products },
        { label: '活跃订阅', value: res.data.data.active_subscriptions },
        { label: '总订阅数', value: res.data.data.total_subscriptions },
        { label: '待上报计量', value: res.data.data.pending_metering },
      ];
    }
    await fetchProducts();
    await fetchSubscriptions();
    await fetchMetering();
  } catch (e) {
    ElMessage.error('加载云市场数据失败');
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
      ElMessage.success('更新成功');
    } else {
      await createMarketplaceProduct(productForm);
      ElMessage.success('创建成功');
    }
    showProductDialog.value = false;
    resetProductForm();
    await fetchProducts();
  } catch (e) {
    ElMessage.error('操作失败');
  } finally {
    saving.value = false;
  }
}

async function deleteProduct(row) {
  try {
    await deleteMarketplaceProduct(row.id);
    ElMessage.success('删除成功');
    await fetchProducts();
  } catch (e) {
    ElMessage.error('删除失败');
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
    ElMessage.error('加载订阅详情失败');
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
    ElMessage.success('计量已上报');
    showMeteringDialog.value = false;
    meteringForm.subscription_id = null;
    meteringForm.dimension = '';
    meteringForm.quantity = 1;
    await fetchMetering();
  } catch (e) {
    ElMessage.error('上报失败');
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
