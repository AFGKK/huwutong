<template>
  <div class="sku-management">
    <div class="page-header">
      <div>
        <h2>SKU 管理</h2>
        <p class="text-muted">管理商品 SKU、定价和库存</p>
      </div>
      <el-button type="primary" @click="openCreateDialog">新建 SKU</el-button>
    </div>

    <!-- 筛选 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" @keyup.enter="doSearch">
        <el-form-item label="产品">
          <el-select v-model="filters.product_id" placeholder="全部产品" clearable style="width: 200px;">
            <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="doSearch">搜索</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- SKU 列表 -->
    <el-card shadow="never">
      <el-table :data="skus" v-loading="loading" stripe>
        <el-table-column label="SKU" width="220">
          <template #default="{ row }">
            <div style="display: flex; align-items: center; gap: 8px;">
              <el-avatar
                v-if="row.image_url"
                :size="32"
                shape="square"
                :src="row.image_url"
              />
              <el-avatar
                v-else
                :size="32"
                shape="square"
                icon="Picture"
                style="background: var(--el-fill-color-light); color: var(--el-text-color-secondary);"
              />
              <div>
                <div style="font-weight: 500;">{{ row.name }}</div>
                <code style="font-size: 11px;">{{ row.sku_code }}</code>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="产品" width="150">
          <template #default="{ row }">{{ row.product?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="price" label="售价" width="100">
          <template #default="{ row }">¥{{ row.price }}</template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="80">
          <template #default="{ row }">{{ row.stock === -1 ? '∞' : row.stock }}</template>
        </el-table-column>
        <el-table-column prop="sold_count" label="已售" width="70" />
        <el-table-column prop="billing_cycle" label="周期" width="100">
          <template #default="{ row }">{{ cycleLabel(row.billing_cycle) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '上架' : '下架' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="editSku(row)">编辑</el-button>
            <el-button :type="row.is_active ? 'warning' : 'success'" link size="small" @click="toggleActive(row)">
              {{ row.is_active ? '下架' : '上架' }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="mt-4 flex-center">
        <el-pagination
          v-model:current-page="page" v-model:page-size="perPage"
          :total="total" :page-sizes="[10,20,50,100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadSkus" @current-change="loadSkus"
        />
      </div>
    </el-card>

    <!-- 新建/编辑对话框 -->
    <el-dialog v-model="showDialog" :title="editingId ? '编辑 SKU' : '新建 SKU'" width="600px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" size="small">
        <el-form-item label="产品" prop="product_id">
          <el-select v-model="form.product_id" placeholder="选择产品" style="width:100%">
            <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="SKU 编码" prop="sku_code">
          <el-input v-model="form.sku_code" placeholder="如: PRO-ANNUAL-01" />
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="如: 专业版-年付" />
        </el-form-item>
        <el-form-item label="SKU 图片">
          <div class="image-upload-wrapper">
            <template v-if="form.image_url">
              <div class="image-preview">
                <el-image :src="form.image_url" fit="cover" style="width: 80px; height: 80px; border-radius: 4px;" />
                <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.image_url = ''">
                  <el-icon><Close /></el-icon>
                </el-button>
              </div>
            </template>
            <el-upload :show-file-list="false" :before-upload="handleSkuImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
              <el-button type="primary" plain size="small">
                <el-icon><Upload /></el-icon> 上传图片
              </el-button>
            </el-upload>
          </div>
        </el-form-item>
        <el-form-item label="售价" prop="price">
          <el-input-number v-model="form.price" :precision="2" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item label="划线价">
          <el-input-number v-model="form.compare_at_price" :precision="2" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item label="库存">
          <el-input-number v-model="form.stock" :min="-1" style="width:200px" />
          <span style="margin-left:8px;color:#909399;font-size:12px;">-1 表示无限</span>
        </el-form-item>
        <el-form-item label="周期">
          <el-select v-model="form.billing_cycle" placeholder="选择周期" style="width:200px">
            <el-option label="一次性" value="one-time" />
            <el-option label="月付" value="monthly" />
            <el-option label="季付" value="quarterly" />
            <el-option label="年付" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.is_active" active-text="上架" inactive-text="下架" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Upload, Close } from '@element-plus/icons-vue';
import orderApi from '@/api/order';
import productApi from '@/api/product';

const loading = ref(false);
const saving = ref(false);
const skus = ref([]);
const products = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const showDialog = ref(false);
const editingId = ref(null);
const formRef = ref(null);

const filters = reactive({ product_id: '' });

const form = reactive({
  product_id: null, sku_code: '', name: '', image_url: '',
  price: 0, compare_at_price: null, stock: -1,
  billing_cycle: 'one-time', is_active: true,
});

const rules = {
  product_id: [{ required: true, message: '请选择产品' }],
  sku_code: [{ required: true, message: '请输入 SKU 编码' }],
  name: [{ required: true, message: '请输入 SKU 名称' }],
  price: [{ required: true, message: '请输入售价' }],
};

function cycleLabel(cycle) {
  return { monthly: '月付', quarterly: '季付', yearly: '年付', 'one-time': '一次性' }[cycle] || cycle || '-';
}

async function loadProducts() {
  try {
    const { data: res } = await productApi.list({ per_page: 200 });
    products.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
  } catch { /* ignore */ }
}

async function loadSkus() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: perPage.value, admin: 1 };
    if (filters.product_id) params.product_id = filters.product_id;
    const { data: res } = await orderApi.skus(params);
    skus.value = res.data?.data || [];
    total.value = res.data?.total || 0;
  } catch { ElMessage.error('加载 SKU 失败'); }
  finally { loading.value = false; }
}

function doSearch() { page.value = 1; loadSkus(); }
function resetFilters() { filters.product_id = ''; doSearch(); }

function openCreateDialog() {
  editingId.value = null;
  Object.assign(form, {
    product_id: null, sku_code: '', name: '', image_url: '',
    price: 0, compare_at_price: null, stock: -1,
    billing_cycle: 'one-time', is_active: true,
  });
  showDialog.value = true;
}

function editSku(row) {
  editingId.value = row.id;
  Object.assign(form, {
    product_id: row.product_id, sku_code: row.sku_code, name: row.name, image_url: row.image_url || '',
    price: row.price, compare_at_price: row.compare_at_price, stock: row.stock,
    billing_cycle: row.billing_cycle, is_active: row.is_active,
  });
  showDialog.value = true;
}

async function handleSkuImageUpload(file) {
  const fd = new FormData();
  fd.append('file', file);
  try {
    const { data: res } = await productApi.uploadImage(fd);
    if (res.success) {
      form.image_url = res.data.url;
    } else {
      ElMessage.error(res.message || '上传失败');
    }
  } catch {
    ElMessage.error('图片上传失败');
  }
  return false;
}

async function toggleActive(row) {
  try {
    await orderApi.updateSku(row.id, { is_active: row.is_active ? 0 : 1 });
    ElMessage.success(row.is_active ? '已下架' : '已上架');
    loadSkus();
  } catch { /* ignore */ }
}

async function handleSave() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;
  saving.value = true;
  try {
    const payload = { ...form, is_active: form.is_active ? 1 : 0 };
    if (editingId.value) {
      await orderApi.updateSku(editingId.value, payload);
      ElMessage.success('SKU 已更新');
    } else {
      await orderApi.createSku(payload);
      ElMessage.success('SKU 已创建');
    }
    showDialog.value = false;
    loadSkus();
  } catch { ElMessage.error('保存失败'); }
  finally { saving.value = false; }
}

onMounted(() => { loadProducts(); loadSkus(); });
</script>

<style scoped>
.sku-management { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.flex-center { display: flex; justify-content: center; }
.image-upload-wrapper { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.image-preview { position: relative; display: inline-block; }
.image-remove-btn { position: absolute; top: -8px; right: -8px; width: 20px; height: 20px; padding: 0; }
</style>
