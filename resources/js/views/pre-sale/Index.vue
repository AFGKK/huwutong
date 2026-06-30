<template>
  <div class="pre-sale-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="4" v-for="s in statItems" :key="s.label">
        <el-card shadow="hover" :body-style="{ padding: '16px' }">
          <div class="stat-value text-2xl font-bold" :class="s.color">{{ s.value }}</div>
          <div class="stat-label text-gray-500 text-sm">{{ s.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <div class="flex gap-2">
          <el-button type="primary" @click="openCreate">新建活动</el-button>
          <el-button @click="refresh">刷新</el-button>
        </div>
        <div class="flex gap-2">
          <el-select v-model="filters.type" clearable placeholder="活动类型" style="width:120px" @change="search">
            <el-option label="预售" value="pre_sale" />
            <el-option label="众筹" value="crowdfunding" />
          </el-select>
          <el-select v-model="filters.status" clearable placeholder="状态" style="width:120px" @change="search">
            <el-option label="草稿" value="draft" />
            <el-option label="进行中" value="active" />
            <el-option label="已成功" value="success" />
            <el-option label="已失败" value="failed" />
            <el-option label="已取消" value="cancelled" />
            <el-option label="已完成" value="completed" />
          </el-select>
          <el-input v-model="filters.search" placeholder="搜索活动/商品..." clearable style="width:200px" @clear="search" @keyup.enter="search" />
          <el-select v-model="filters.sort" style="width:140px" @change="search">
            <el-option label="最新" value="latest" />
            <el-option label="最旧" value="oldest" />
            <el-option label="即将结束" value="ending_soon" />
            <el-option label="筹集最多" value="most_raised" />
          </el-select>
        </div>
      </div>

      <!-- 活动列表 -->
      <el-table :data="campaigns" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="类型" width="80">
          <template #default="{ row }">
            <el-tag :type="row.type === 'crowdfunding' ? 'warning' : 'primary'" size="small">
              {{ row.type === 'pre_sale' ? '预售' : '众筹' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="活动名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="product.name" label="关联商品" width="140" show-overflow-tooltip />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="160">
          <template #default="{ row }">
            <div class="flex items-center gap-2">
              <el-progress :percentage="row.progress_percent" :stroke-width="12" :status="row.progress_percent >= 100 ? 'success' : ''" style="width:100px" />
              <span class="text-xs text-gray-500">{{ row.progress_percent }}%</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="筹集" width="140">
          <template #default="{ row }">
            <span class="text-sm">¥{{ formatMoney(row.raised_amount) }}</span>
            <span class="text-xs text-gray-400 ml-1">/ ¥{{ formatMoney(row.target_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支持者" width="80">
          <template #default="{ row }">{{ row.current_backers }} / {{ row.target_backers || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="280">
          <template #default="{ row }">
            <div class="text-xs">
              <div>{{ formatDate(row.start_at) }} → {{ formatDate(row.end_at) }}</div>
              <div v-if="row.is_active" class="text-green-500">剩余 {{ row.remaining_days }} 天</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text @click="viewCampaign(row)">详情</el-button>
            <el-button size="small" text v-if="row.status === 'draft'" @click="editCampaign(row)">编辑</el-button>
            <el-button size="small" text type="primary" v-if="row.status === 'draft'" @click="handlePublish(row)">发布</el-button>
            <el-button size="small" text type="primary" v-if="row.status === 'active'" @click="handleCheckStatus(row)">检查状态</el-button>
            <el-button size="small" text type="success" v-if="row.status === 'success'" @click="handleComplete(row)">完成</el-button>
            <el-button size="small" text type="danger" v-if="['draft','active'].includes(row.status)" @click="handleCancel(row)">取消</el-button>
            <el-button size="small" text type="danger" v-if="['draft','failed','cancelled'].includes(row.status)" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-4" v-if="pagination">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next, total"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <!-- 创建/编辑活动对话框 -->
    <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑活动' : '新建活动'" width="720px" :close-on-click-modal="false">
      <el-form :model="form" label-width="120px" v-loading="saving">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="活动类型" required>
              <el-radio-group v-model="form.type" :disabled="isEditing">
                <el-radio value="pre_sale">预售</el-radio>
                <el-radio value="crowdfunding">众筹</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商  品" required>
              <el-select v-model="form.product_id" filterable remote :disabled="isEditing" :remote-method="searchProducts" :loading="productLoading" placeholder="搜索商品" style="width:100%">
                <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="活动名称" required>
          <el-input v-model="form.name" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="活动描述">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="开始时间" required>
              <el-date-picker v-model="form.start_at" type="datetime" placeholder="选择时间" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="结束时间" required>
              <el-date-picker v-model="form.end_at" type="datetime" placeholder="选择时间" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="预计发货">
              <el-date-picker v-model="form.estimated_delivery_at" type="datetime" placeholder="选填" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="目标金额">
              <el-input v-model="form.target_amount" type="number" min="0" placeholder="众筹目标" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="定金比例(%)">
              <el-input v-model="form.deposit_rate" type="number" min="0" max="100">
                <template #suffix>%</template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="固定定金">
              <el-input v-model="form.deposit_amount" type="number" min="0" placeholder="留空按比例" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="目标支持人数">
              <el-input v-model="form.target_backers" type="number" min="1" placeholder="非必填" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="货币">
              <el-select v-model="form.currency" style="width:100%">
                <el-option label="CNY" value="CNY" />
                <el-option label="USD" value="USD" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="save" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 活动详情对话框 -->
    <el-dialog v-model="detailVisible" title="活动详情" width="800px">
      <div v-if="detail" v-loading="detailLoading">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="名称">{{ detail.name }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ detail.type === 'pre_sale' ? '预售' : '众筹' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="商品">{{ detail.product?.name }}</el-descriptions-item>
          <el-descriptions-item label="目标金额">¥{{ formatMoney(detail.target_amount) }}</el-descriptions-item>
          <el-descriptions-item label="已筹集">¥{{ formatMoney(detail.raised_amount) }}</el-descriptions-item>
          <el-descriptions-item label="进度">
            <el-progress :percentage="detail.progress_percent" :status="detail.progress_percent >= 100 ? 'success' : ''" />
          </el-descriptions-item>
          <el-descriptions-item label="支持者">{{ detail.current_backers }} / {{ detail.target_backers || '-' }}</el-descriptions-item>
          <el-descriptions-item label="时间">{{ formatDate(detail.start_at) }} → {{ formatDate(detail.end_at) }}</el-descriptions-item>
          <el-descriptions-item label="剩余">{{ detail.remaining_days }} 天</el-descriptions-item>
        </el-descriptions>

        <!-- 活动更新 -->
        <div class="mt-4">
          <div class="flex justify-between items-center mb-2">
            <h3 class="text-lg font-medium">活动更新</h3>
            <el-button size="small" @click="showPostUpdate = true">发布更新</el-button>
          </div>
          <div v-if="detail.updates && detail.updates.length > 0">
            <el-timeline>
              <el-timeline-item
                v-for="u in detail.updates"
                :key="u.id"
                :timestamp="formatDate(u.created_at)"
                :type="u.is_pinned ? 'primary' : ''"
              >
                <div class="flex justify-between">
                  <span class="font-medium">{{ u.title }}</span>
                  <el-button size="small" text type="danger" @click="handleDeleteUpdate(u.id)">删除</el-button>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ u.content }}</p>
                <el-tag size="mini" v-if="u.type !== 'update'">{{ u.type }}</el-tag>
              </el-timeline-item>
            </el-timeline>
          </div>
          <el-empty v-else description="暂无更新" />
        </div>

        <!-- 订单标签页 -->
        <el-tabs v-model="orderTab" class="mt-4">
          <el-tab-pane label="订单列表" name="orders">
            <el-table :data="detailOrders" v-loading="ordersLoading" size="small" stripe>
              <el-table-column prop="order_no" label="订单号" width="160" />
              <el-table-column label="用户" width="120">
                <template #default="{ row }">{{ row.user?.name || row.user?.email }}</template>
              </el-table-column>
              <el-table-column label="金额" width="100">
                <template #default="{ row }">¥{{ formatMoney(row.total_amount) }}</template>
              </el-table-column>
              <el-table-column label="定金" width="80">
                <template #default="{ row }">¥{{ formatMoney(row.deposit_paid) }}</template>
              </el-table-column>
              <el-table-column label="尾款" width="80">
                <template #default="{ row }">¥{{ formatMoney(row.final_paid) }}</template>
              </el-table-column>
              <el-table-column label="支付状态" width="120">
                <template #default="{ row }">
                  <el-tag :type="paymentStatusTag(row.payment_status)" size="small">{{ paymentStatusLabel(row.payment_status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="发货状态" width="100">
                <template #default="{ row }">
                  <el-tag size="small">{{ fulfillmentStatusLabel(row.fulfillment_status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="160">
                <template #default="{ row }">
                  <el-button size="small" text v-if="row.payment_status === 'deposit_pending'" @click="handlePayDeposit(row)">付定金</el-button>
                  <el-button size="small" text v-if="row.payment_status === 'deposit_paid'" type="warning" @click="handlePayFinal(row)">付尾款</el-button>
                  <el-select
                    v-if="row.payment_status === 'final_paid'"
                    size="small"
                    :model-value="row.fulfillment_status"
                    @change="(v) => handleUpdateFulfillment(row, v)"
                    style="width:100px"
                  >
                    <el-option label="待处理" value="pending" />
                    <el-option label="处理中" value="processing" />
                    <el-option label="已发货" value="shipped" />
                    <el-option label="已交付" value="delivered" />
                  </el-select>
                </template>
              </el-table-column>
            </el-table>
          </el-tab-pane>
        </el-tabs>
      </div>
    </el-dialog>

    <!-- 发布更新对话框 -->
    <el-dialog v-model="showPostUpdate" title="发布活动更新" width="500px">
      <el-form :model="updateForm" label-width="80px">
        <el-form-item label="标题" required>
          <el-input v-model="updateForm.title" maxlength="200" />
        </el-form-item>
        <el-form-item label="内容" required>
          <el-input v-model="updateForm.content" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="updateForm.type">
            <el-option label="更新" value="update" />
            <el-option label="里程碑" value="milestone" />
            <el-option label="公告" value="announcement" />
          </el-select>
        </el-form-item>
        <el-form-item label="置顶">
          <el-switch v-model="updateForm.is_pinned" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPostUpdate = false">取消</el-button>
        <el-button type="primary" @click="handlePostUpdate" :loading="updating">发布</el-button>
      </template>
    </el-dialog>

    <!-- 支付确认 -->
    <el-dialog v-model="showPayDialog" title="确认支付" width="400px">
      <p class="mb-3">订单：{{ payTarget?.order_no }}</p>
      <el-form label-width="80px" size="small">
        <el-form-item label="支付类型"><span>{{ payPhase === 'deposit' ? '定金' : '尾款' }}</span></el-form-item>
        <el-form-item label="支付方式">
          <el-select v-model="payMethod" style="width:100%">
            <el-option label="支付网关" value="gateway" />
            <el-option label="预付余额" value="prepaid" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayDialog = false">取消</el-button>
        <el-button type="primary" :loading="payLoading" @click="submitPay">确认支付</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import preSaleApi from '@/api/preSale';
import apiClient from '@/api/client';

// ─── 统计 ───
const statItems = ref([]);

async function loadStats() {
  try {
    const { data } = await preSaleApi.stats();
    if (data?.data) {
      statItems.value = [
        { label: '总活动', value: data.data.total, color: 'text-blue-500' },
        { label: '进行中', value: data.data.active, color: 'text-green-500' },
        { label: '已成功', value: data.data.success, color: 'text-teal-500' },
        { label: '已失败', value: data.data.failed, color: 'text-red-500' },
        { label: '总筹集', value: '¥' + formatMoney(data.data.totalRaised), color: 'text-yellow-600' },
        { label: '总支持者', value: data.data.totalBackers, color: 'text-purple-500' },
      ];
    }
  } catch (e) {
    console.error('Failed to load stats:', e);
  }
}

// ─── 列表 ───
const campaigns = ref([]);
const loading = ref(false);
const pagination = ref(null);
const filters = reactive({
  type: '',
  status: '',
  search: '',
  sort: 'latest',
});

async function loadData(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters, page };
    const { data } = await preSaleApi.list(params);
    campaigns.value = data?.data?.data || [];
    pagination.value = data?.data || null;
  } catch (e) {
    ElMessage.error('加载活动列表失败');
  } finally {
    loading.value = false;
  }
}

function search() {
  loadData(1);
}

function refresh() {
  loadStats();
  loadData(pagination.value?.current_page || 1);
}

// ─── 创建/编辑 ───
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = reactive({
  tenant_id: 1,
  type: 'pre_sale',
  name: '',
  description: '',
  product_id: null,
  target_amount: null,
  deposit_rate: 0,
  deposit_amount: null,
  currency: 'CNY',
  start_at: null,
  end_at: null,
  estimated_delivery_at: null,
  target_backers: null,
});

function resetForm() {
  form.tenant_id = 1;
  form.type = 'pre_sale';
  form.name = '';
  form.description = '';
  form.product_id = null;
  form.target_amount = null;
  form.deposit_rate = 0;
  form.deposit_amount = null;
  form.currency = 'CNY';
  form.start_at = null;
  form.end_at = null;
  form.estimated_delivery_at = null;
  form.target_backers = null;
  isEditing.value = false;
  editingId.value = null;
}

function openCreate() {
  resetForm();
  dialogVisible.value = true;
}

function editCampaign(row) {
  resetForm();
  isEditing.value = true;
  editingId.value = row.id;
  Object.assign(form, {
    tenant_id: row.tenant_id,
    type: row.type,
    name: row.name,
    description: row.description,
    product_id: row.product_id,
    target_amount: row.target_amount,
    deposit_rate: row.deposit_rate,
    deposit_amount: row.deposit_amount,
    currency: row.currency,
    start_at: row.start_at,
    end_at: row.end_at,
    estimated_delivery_at: row.estimated_delivery_at,
    target_backers: row.target_backers,
  });
  dialogVisible.value = true;
}

async function save() {
  if (!form.name || !form.product_id || !form.start_at || !form.end_at) {
    ElMessage.warning('请填写必要信息');
    return;
  }
  saving.value = true;
  try {
    const payload = {
      ...form,
      start_at: form.start_at instanceof Date ? form.start_at.toISOString() : form.start_at,
      end_at: form.end_at instanceof Date ? form.end_at.toISOString() : form.end_at,
      estimated_delivery_at: form.estimated_delivery_at instanceof Date ? form.estimated_delivery_at.toISOString() : form.estimated_delivery_at,
    };
    if (isEditing.value) {
      await preSaleApi.update(editingId.value, payload);
      ElMessage.success('已更新');
    } else {
      await preSaleApi.create(payload);
      ElMessage.success('活动已创建');
    }
    dialogVisible.value = false;
    refresh();
  } catch (e) {
    ElMessage.error('保存失败');
  } finally {
    saving.value = false;
  }
}

// ─── 商品搜索 ───
const productOptions = ref([]);
const productLoading = ref(false);

async function searchProducts(query) {
  if (!query) return;
  productLoading.value = true;
  try {
    const { data } = await apiClient.get('/admin/products', { params: { search: query, per_page: 10 } });
    productOptions.value = data?.data?.data || [];
  } catch (e) {
    productOptions.value = [];
  } finally {
    productLoading.value = false;
  }
}

// ─── 操作 ───
async function handlePublish(row) {
  try {
    await ElMessageBox.confirm(`确定发布活动"${row.name}"？`, '确认');
    await preSaleApi.publish(row.id);
    ElMessage.success('已发布');
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('发布失败');
  }
}

async function handleCancel(row) {
  try {
    const { value: reason } = await ElMessageBox.prompt('请输入取消原因（选填）', '取消活动', { inputType: 'textarea' });
    await preSaleApi.cancel(row.id, reason || '');
    ElMessage.success('已取消');
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('取消失败');
  }
}

async function handleComplete(row) {
  try {
    await ElMessageBox.confirm(`确认完成活动"${row.name}"？`, '确认');
    await preSaleApi.complete(row.id);
    ElMessage.success('已完成');
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('操作失败');
  }
}

async function handleCheckStatus(row) {
  try {
    const { data } = await preSaleApi.checkStatus(row.id);
    ElMessage.success(`状态已更新: ${statusLabel(data.data.status)}`);
    refresh();
  } catch (e) {
    ElMessage.error('检查失败');
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除活动"${row.name}"？此操作不可恢复。`, '警告', { type: 'warning' });
    await preSaleApi.destroy(row.id);
    ElMessage.success('已删除');
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('删除失败');
  }
}

// ─── 详情 ───
const detailVisible = ref(false);
const detail = ref(null);
const detailLoading = ref(false);
const detailOrders = ref([]);
const ordersLoading = ref(false);
const orderTab = ref('orders');

async function viewCampaign(row) {
  detailLoading.value = true;
  detailVisible.value = true;
  try {
    const { data: detailData } = await preSaleApi.show(row.id);
    detail.value = detailData?.data || null;

    const { data: ordersData } = await preSaleApi.listOrders({ campaign_id: row.id });
    detailOrders.value = ordersData?.data?.data || [];
  } catch (e) {
    ElMessage.error('加载详情失败');
  } finally {
    detailLoading.value = false;
  }
}

const payMethod = ref('gateway');
const showPayDialog = ref(false);
const payLoading = ref(false);
const payTarget = ref(null);
const payPhase = ref('deposit');

function openPayDialog(order, phase) {
  payTarget.value = order;
  payPhase.value = phase;
  payMethod.value = 'gateway';
  showPayDialog.value = true;
}

async function submitPay() {
  if (!payTarget.value) return;
  payLoading.value = true;
  try {
    const fn = payPhase.value === 'deposit' ? preSaleApi.payDeposit : preSaleApi.payFinal;
    await fn(payTarget.value.id, payMethod.value);
    ElMessage.success(payPhase.value === 'deposit' ? '定金已收取' : '尾款已收取');
    showPayDialog.value = false;
    await viewCampaign(detail.value);
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '支付失败');
  } finally {
    payLoading.value = false;
  }
}

// ─── 订单操作 ───
async function handlePayDeposit(order) {
  openPayDialog(order, 'deposit');
}

async function handlePayFinal(order) {
  openPayDialog(order, 'final');
}

async function handleUpdateFulfillment(order, status) {
  try {
    await preSaleApi.updateFulfillment(order.id, status);
    ElMessage.success('发货状态已更新');
    order.fulfillment_status = status;
  } catch (e) {
    ElMessage.error('更新失败');
  }
}

// ─── 活动更新 ───
const showPostUpdate = ref(false);
const updating = ref(false);
const updateForm = reactive({
  title: '',
  content: '',
  type: 'update',
  is_pinned: false,
});

async function handlePostUpdate() {
  if (!updateForm.title || !updateForm.content) {
    ElMessage.warning('请填写标题和内容');
    return;
  }
  updating.value = true;
  try {
    await preSaleApi.postUpdate(detail.value.id, { ...updateForm });
    ElMessage.success('更新已发布');
    showPostUpdate.value = false;
    updateForm.title = '';
    updateForm.content = '';
    updateForm.type = 'update';
    updateForm.is_pinned = false;
    await viewCampaign(detail.value);
  } catch (e) {
    ElMessage.error('发布失败');
  } finally {
    updating.value = false;
  }
}

async function handleDeleteUpdate(updateId) {
  try {
    await ElMessageBox.confirm('确定删除此更新？', '确认');
    await preSaleApi.deleteUpdate(updateId);
    ElMessage.success('已删除');
    await viewCampaign(detail.value);
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('删除失败');
  }
}

// ─── 工具 ───
function formatMoney(v) {
  if (v === null || v === undefined) return '0.00';
  return Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(d) {
  if (!d) return '-';
  const dt = new Date(d);
  return dt.toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function statusTag(s) {
  const map = { draft: 'info', pending: 'warning', active: 'primary', success: 'success', failed: 'danger', cancelled: 'info', completed: 'success' };
  return map[s] || 'info';
}

function statusLabel(s) {
  const map = { draft: '草稿', pending: '待审核', active: '进行中', success: '已成功', failed: '已失败', cancelled: '已取消', completed: '已完成' };
  return map[s] || s;
}

function paymentStatusLabel(s) {
  const map = { deposit_pending: '待付定金', deposit_paid: '定金已付', final_pending: '待付尾款', final_paid: '尾款已付', refunding: '退款中', refunded: '已退款' };
  return map[s] || s;
}

function paymentStatusTag(s) {
  const map = { deposit_pending: 'info', deposit_paid: 'primary', final_pending: 'warning', final_paid: 'success', refunding: 'danger', refunded: 'info' };
  return map[s] || 'info';
}

function fulfillmentStatusLabel(s) {
  const map = { pending: '待处理', processing: '处理中', shipped: '已发货', delivered: '已交付' };
  return map[s] || s;
}

onMounted(() => {
  loadStats();
  loadData();
});
</script>
