<template>
  <div class="flash-sale-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Lightning /></el-icon>
        秒杀/抢购防护
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">活动总数</div></el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card"><div class="stat-value stat-success">{{ stats.active }}</div><div class="stat-label">进行中</div></el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card"><div class="stat-value stat-primary">{{ stats.scheduled }}</div><div class="stat-label">待开始</div></el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.totalOrders }}</div><div class="stat-label">总订单</div></el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.paidOrders }}</div><div class="stat-label">已支付</div></el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span>秒杀活动</span>
          <el-button type="danger" size="small" @click="showCreate">
            <el-icon><Plus /></el-icon> 新建秒杀
          </el-button>
        </div>
      </template>
      <el-table :data="sales" stripe v-loading="salesLoading">
        <el-table-column label="名称" prop="name" min-width="160" />
        <el-table-column label="SKU" width="100">{{ row => row.sku?.id }}</el-table-column>
        <el-table-column label="原价" width="90" align="center">
          <template #default="{ row }">¥{{ ((row.original_price || 0) / 100).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="秒杀价" width="90" align="center">
          <template #default="{ row }"><span class="text-danger">¥{{ ((row.flash_price || 0) / 100).toFixed(2) }}</span></template>
        </el-table-column>
        <el-table-column label="库存" prop="stock" width="60" align="center" />
        <el-table-column label="每人限购" prop="max_per_user" width="80" align="center" />
        <el-table-column label="开始时间" width="150">{{ formatTime(row.start_time) }}</el-table-column>
        <el-table-column label="结束时间" width="150">{{ formatTime(row.end_time) }}</el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'danger' : row.status === 'scheduled' ? 'primary' : row.status === 'paused' ? 'warning' : 'info'" size="small">
              {{ { scheduled: '待开始', active: '进行中', paused: '已暂停', ended: '已结束' }[row.status] || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'scheduled'" size="small" type="danger" @click="handleStatus(row, 'active')">启动</el-button>
            <el-button v-if="row.status === 'active'" size="small" type="warning" @click="handleStatus(row, 'paused')">暂停</el-button>
            <el-button v-if="row.status === 'active'" size="small" type="info" @click="handleReleaseExpired(row)">释放</el-button>
            <el-button v-if="row.status !== 'ended'" size="small" @click="handleStatus(row, 'ended')">结束</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
        <el-pagination v-model:current-page="pagination.current_page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="loadSales" />
      </div>
    </el-card>

    <el-dialog v-model="createVisible" title="新建秒杀活动" width="520px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="名称" prop="name"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="SKU" prop="sku_id">
          <el-select v-model="form.sku_id" filterable style="width:100%"><el-option v-for="s in skus" :key="s.id" :label="`#${s.id} - ${s.product_name || ''}`" :value="s.id" /></el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="原价"><el-input-number v-model="form.original_price" :min="1" style="width:100%" /><div class="text-[10px] text-gray-400 mt-1">单位：分（如 ¥199 → 19900）</div></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="秒杀价"><el-input-number v-model="form.flash_price" :min="1" style="width:100%" /><div class="text-[10px] text-gray-400 mt-1">单位：分</div></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="库存"><el-input-number v-model="form.stock" :min="1" style="width:100%" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="每人限购"><el-input-number v-model="form.max_per_user" :min="1" :max="100" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="开始时间"><el-date-picker v-model="form.start_time" type="datetime" style="width:100%" /></el-form-item>
        <el-form-item label="结束时间"><el-date-picker v-model="form.end_time" type="datetime" style="width:100%" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="danger" @click="handleCreate" :loading="submitting">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Lightning, Refresh, Plus } from '@element-plus/icons-vue';
import flashApi from '@/api/flashSale';

const loading = ref(false);
const submitting = ref(false);
const salesLoading = ref(false);

const stats = ref({});
const sales = ref([]);
const skus = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const createVisible = ref(false);
const formRef = ref(null);
const form = reactive({
  name: '', sku_id: '', flash_price: 100, original_price: 200,
  stock: 100, max_per_user: 1, start_time: '', end_time: '',
});
const rules = { name: [{ required: true }], sku_id: [{ required: true }] };

onMounted(() => { refreshAll(); loadSkus(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await flashApi.dashboard();
    stats.value = res.data;
  } finally { loading.value = false; }
  loadSales();
}

async function loadSales() {
  salesLoading.value = true;
  try {
    const res = await flashApi.list({ page: pagination.current_page });
    sales.value = res.data.data || [];
    Object.assign(pagination, res.data);
  } finally { salesLoading.value = false; }
}

async function loadSkus() {
  try {
    const res = await import('@/api/shop').then(m => m.default.getSkus?.({ per_page: 999 }));
    skus.value = res?.data?.data || [];
  } catch {}
}

function showCreate() {
  form.name = ''; form.sku_id = ''; form.flash_price = 100; form.original_price = 200;
  form.stock = 100; form.max_per_user = 1; form.start_time = ''; form.end_time = '';
  createVisible.value = true;
}

async function handleCreate() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await flashApi.create(form);
    ElMessage.success('秒杀活动已创建');
    createVisible.value = false;
    loadSales(); refreshAll();
  } finally { submitting.value = false; }
}

async function handleStatus(row, status) {
  await flashApi.updateStatus(row.id, status);
  ElMessage.success('状态已更新');
  loadSales();
}

async function handleReleaseExpired(row) {
  const res = await flashApi.releaseExpired(row.id);
  ElMessage.success(res.message || '已释放');
  loadSales();
}

function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : '—'; }
</script>

<style scoped>
.flash-sale-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; } .stat-primary { color: #409EFF; }
.text-danger { color: #F56C6C; font-weight: 700; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 12px; }
</style>
