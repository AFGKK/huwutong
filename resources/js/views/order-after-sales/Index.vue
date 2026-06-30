<template>
  <div class="order-after-sales-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Service /></el-icon>
        🛒 订单售后工单
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleCreate" :loading="submitting">
          <el-icon><Plus /></el-icon> 新建工单
        </el-button>
        <el-button @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">工单总数</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.open }}</div>
          <div class="stat-label">待处理</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-warning">{{ stats.by_priority?.urgent || 0 }}</div>
          <div class="stat-label">紧急工单</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.resolved }}</div>
          <div class="stat-label">已解决</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.closed }}</div>
          <div class="stat-label">已关闭</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.avg_response_time ? Math.round(stats.avg_response_time) + 'min' : '-' }}</div>
          <div class="stat-label">平均响应时间</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索/筛选栏 -->
    <el-card shadow="hover" class="mb-4">
      <el-form :model="filters" inline>
        <el-form-item label="状态">
          <el-select v-model="filters.status" clearable placeholder="全部状态" style="width:130px">
            <el-option label="待处理" value="open" />
            <el-option label="处理中" value="in_progress" />
            <el-option label="已回复" value="replied" />
            <el-option label="已解决" value="resolved" />
            <el-option label="已关闭" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-select v-model="filters.priority" clearable placeholder="全部优先级" style="width:120px">
            <el-option label="紧急" value="urgent" />
            <el-option label="高" value="high" />
            <el-option label="中" value="medium" />
            <el-option label="低" value="low" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因">
          <el-select v-model="filters.reason" clearable placeholder="全部原因" style="width:120px">
            <el-option v-for="(cfg, key) in reasons" :key="key" :label="cfg.label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单号">
          <el-input v-model="filters.order_id" placeholder="订单ID" style="width:120px" clearable />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="filters.keyword" placeholder="搜索主题/描述" style="width:200px" clearable @keyup.enter="loadList" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadList">搜索</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 工单列表 -->
    <el-card shadow="hover">
      <el-table :data="tickets" v-loading="loading" stripe @row-click="openDetail">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="id" label="工单号" width="80" />
        <el-table-column label="主题" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="subject-text">{{ row.subject }}</span>
          </template>
        </el-table-column>
        <el-table-column label="客户" width="120">
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="优先级" width="80">
          <template #default="{ row }">
            <el-tag :type="priorityType(row.priority)" size="small">{{ priorityLabel(row.priority) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="客服" width="100">
          <template #default="{ row }">{{ row.assignee?.name || '未分配' }}</template>
        </el-table-column>
        <el-table-column label="评价" width="70">
          <template #default="{ row }">
            <span v-if="row.satisfaction" class="score-stars">{{ '★'.repeat(row.satisfaction.score) }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">{{ row.created_at }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button text type="primary" size="small" @click.stop="openDetail(row)">详情</el-button>
            <el-button
              v-if="row.status === 'open' || row.status === 'in_progress' || row.status === 'replied'"
              text type="success" size="small" @click.stop="handleResolve(row)"
            >解决</el-button>
            <el-button
              v-if="row.status !== 'closed'"
              text type="info" size="small" @click.stop="handleClose(row)"
            >关闭</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <!-- 创建工单对话框 -->
    <el-dialog v-model="createVisible" title="新建售后工单" width="600px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="订单" prop="order_id" required>
          <el-select v-model="form.order_id" filterable style="width:100%" placeholder="搜索订单号...">
            <el-option v-for="o in orders" :key="o.id" :label="`#${o.id} - ¥${o.total_amount} (${o.status})`" :value="o.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="客户" prop="customer_id" required>
          <el-select v-model="form.customer_id" filterable style="width:100%" placeholder="选择客户">
            <el-option v-for="c in customers" :key="c.id" :label="c.name || c.company || `ID:${c.id}`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="售后原因" prop="reason" required>
          <el-select v-model="form.reason" style="width:100%">
            <el-option v-for="(cfg, key) in reasons" :key="key" :label="cfg.label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="问题描述" required>
          <el-input v-model="form.description" type="textarea" :rows="4" placeholder="请详细描述问题..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateSubmit" :loading="submitting">提交</el-button>
      </template>
    </el-dialog>

    <!-- 工单详情抽屉 -->
    <el-drawer v-model="detailVisible" :title="detailTicket?.subject" size="600px" direction="rtl">
      <template v-if="detailTicket">
        <div class="detail-section">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="工单号">{{ detailTicket.id }}</el-descriptions-item>
            <el-descriptions-item label="订单号">#{{ detailTicket.metadata?.order_id }}</el-descriptions-item>
            <el-descriptions-item label="优先级">
              <el-tag :type="priorityType(detailTicket.priority)" size="small">{{ priorityLabel(detailTicket.priority) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="statusType(detailTicket.status)" size="small">{{ statusLabel(detailTicket.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="客户">{{ detailTicket.customer?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="客服">{{ detailTicket.assignee?.name || '未分配' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ detailTicket.created_at }}</el-descriptions-item>
            <el-descriptions-item label="SLA 截止">{{ detailTicket.sla_due_at || '-' }}</el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="detail-section">
          <h4>问题描述</h4>
          <div class="description-box">{{ detailTicket.description }}</div>
        </div>

        <!-- 满意度评价 -->
        <div v-if="detailTicket.satisfaction" class="detail-section">
          <h4>满意度评价</h4>
          <div class="satisfaction-box">
            <span class="score-stars-lg">{{ '★'.repeat(detailTicket.satisfaction.score) }}{{ '☆'.repeat(5 - detailTicket.satisfaction.score) }}</span>
            <span v-if="detailTicket.satisfaction.comment" class="satisfaction-comment">{{ detailTicket.satisfaction.comment }}</span>
          </div>
        </div>

        <!-- 回复列表 -->
        <div class="detail-section">
          <h4>回复记录 ({{ detailTicket.replies?.length || 0 }})</h4>
          <div v-if="detailTicket.replies?.length" class="reply-list">
            <div v-for="r in detailTicket.replies" :key="r.id" class="reply-item" :class="{ 'internal': r.is_internal }">
              <div class="reply-header">
                <strong>{{ r.user?.name || '系统' }}</strong>
                <span class="reply-time">{{ r.created_at }}</span>
                <el-tag v-if="r.is_internal" type="warning" size="small">内部备注</el-tag>
              </div>
              <div class="reply-content">{{ r.content }}</div>
            </div>
          </div>
          <el-empty v-else description="暂无回复" :image-size="40" />
        </div>

        <!-- 回复输入 -->
        <div class="detail-section">
          <h4>回复</h4>
          <el-input v-model="replyContent" type="textarea" :rows="3" placeholder="输入回复内容..." />
          <div class="reply-actions">
            <el-checkbox v-model="replyInternal">内部备注</el-checkbox>
            <div>
              <el-button type="primary" size="small" @click="handleReply" :loading="replying">发送回复</el-button>
              <el-button
                v-if="detailTicket.status === 'open' || detailTicket.status === 'in_progress' || detailTicket.status === 'replied'"
                type="success" size="small" @click="handleResolve(detailTicket)"
              >标记解决</el-button>
              <el-button
                v-if="detailTicket.status !== 'closed'"
                type="info" size="small" @click="handleClose(detailTicket)"
              >关闭工单</el-button>
            </div>
          </div>
        </div>

        <!-- 分配 -->
        <div class="detail-section">
          <h4>分配客服</h4>
          <div class="assign-row">
            <el-select v-model="assignUserId" filterable placeholder="选择客服" style="width:200px">
              <el-option v-for="u in agents" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
            <el-button type="primary" size="small" @click="handleAssign" :loading="assigning">分配</el-button>
          </div>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Service, Refresh, Plus } from '@element-plus/icons-vue';
import afterSalesApi from '@/api/orderAfterSales';
import adminUserApi from '@/api/adminUser';

const loading = ref(false);
const submitting = ref(false);
const replying = ref(false);
const assigning = ref(false);
const stats = ref({});
const reasons = ref({});
const orders = ref([]);
const customers = ref([]);
const agents = ref([]);
const tickets = ref([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(15);

// 筛选
const filters = reactive({
  status: '', priority: '', reason: '', order_id: '', keyword: '',
});

// 创建表单
const createVisible = ref(false);
const form = reactive({ order_id: '', customer_id: '', reason: 'not_received', description: '' });

// 详情抽屉
const detailVisible = ref(false);
const detailTicket = ref(null);
const replyContent = ref('');
const replyInternal = ref(false);
const assignUserId = ref('');

onMounted(() => {
  refreshAll();
  afterSalesApi.getReasons().then(r => { reasons.value = r.data || {}; }).catch(() => {});
  loadOptions();
  loadAgents();
});

async function refreshAll() {
  await Promise.all([loadList(), loadStats()]);
}

async function loadStats() {
  try {
    const res = await afterSalesApi.getStats();
    stats.value = res.data;
  } catch {}
}

async function loadList() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: pageSize.value };
    Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
    const res = await afterSalesApi.list(params);
    tickets.value = res.data?.data || [];
    total.value = res.data?.total || 0;
  } finally { loading.value = false; }
}

async function loadOptions() {
  try {
    const [oRes, cRes] = await Promise.all([
      import('@/api/order').then(m => m.default.list({ per_page: 200 })),
      import('@/api/customer').then(m => m.default.list({ per_page: 200 })),
    ]);
    orders.value = oRes.data?.data || [];
    customers.value = cRes.data?.data || [];
  } catch {}
}

async function loadAgents() {
  try {
    const res = await adminUserApi.list({ per_page: 200 });
    agents.value = res.data?.data || [];
  } catch {}
}

function resetFilters() {
  filters.status = ''; filters.priority = ''; filters.reason = '';
  filters.order_id = ''; filters.keyword = '';
  page.value = 1;
  loadList();
}

// 创建工单
function openCreateDialog() {
  form.order_id = ''; form.customer_id = ''; form.reason = 'not_received'; form.description = '';
  createVisible.value = true;
}
async function handleCreateSubmit() {
  if (!form.order_id || !form.customer_id || !form.reason || !form.description) {
    ElMessage.warning('请填写完整信息');
    return;
  }
  submitting.value = true;
  try {
    await afterSalesApi.createTicket(form);
    ElMessage.success('售后工单已创建');
    createVisible.value = false;
    refreshAll();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '创建失败');
  } finally { submitting.value = false; }
}

// 详情
async function openDetail(row) {
  try {
    const res = await afterSalesApi.detail(row.id);
    detailTicket.value = res.data;
    replyContent.value = '';
    replyInternal.value = false;
    assignUserId.value = detailTicket.value?.assignee?.id || '';
    detailVisible.value = true;
  } catch {}
}

// 回复
async function handleReply() {
  if (!replyContent.value.trim()) {
    ElMessage.warning('请输入回复内容');
    return;
  }
  replying.value = true;
  try {
    await afterSalesApi.reply(detailTicket.value.id, {
      content: replyContent.value,
      is_internal: replyInternal.value,
    });
    ElMessage.success('回复成功');
    replyContent.value = '';
    openDetail(detailTicket.value);
    loadList();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '回复失败');
  } finally { replying.value = false; }
}

// 解决
async function handleResolve(row) {
  try {
    await ElMessageBox.confirm('确认将此工单标记为已解决？', '确认');
    await afterSalesApi.resolve(row.id);
    ElMessage.success('工单已解决');
    detailVisible.value = false;
    refreshAll();
  } catch {}
}

// 关闭
async function handleClose(row) {
  try {
    await ElMessageBox.confirm('确认关闭此工单？', '确认');
    await afterSalesApi.close(row.id);
    ElMessage.success('工单已关闭');
    detailVisible.value = false;
    refreshAll();
  } catch {}
}

// 分配
async function handleAssign() {
  if (!assignUserId.value) {
    ElMessage.warning('请选择客服');
    return;
  }
  assigning.value = true;
  try {
    await afterSalesApi.assign(detailTicket.value.id, assignUserId.value);
    ElMessage.success('工单已分配');
    openDetail(detailTicket.value);
    loadList();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '分配失败');
  } finally { assigning.value = false; }
}

// 标签映射
function statusType(s) {
  const map = { open: 'danger', in_progress: 'warning', replied: 'primary', resolved: 'success', closed: 'info' };
  return map[s] || 'info';
}
function statusLabel(s) {
  const map = { open: '待处理', in_progress: '处理中', replied: '已回复', resolved: '已解决', closed: '已关闭' };
  return map[s] || s;
}
function priorityType(p) {
  const map = { urgent: 'danger', high: 'warning', medium: 'primary', low: 'info' };
  return map[p] || 'info';
}
function priorityLabel(p) {
  const map = { urgent: '紧急', high: '高', medium: '中', low: '低' };
  return map[p] || p;
}
</script>

<style scoped>
.order-after-sales-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-warning { color: #E6A23C; }
.stat-danger { color: #F56C6C; }
.text-muted { color: #c0c4cc; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.subject-text { font-weight: 500; cursor: pointer; }
.subject-text:hover { color: #409EFF; }
.detail-section { margin-bottom: 20px; }
.detail-section h4 { margin: 0 0 8px; font-size: 15px; color: #303133; border-left: 3px solid #409EFF; padding-left: 8px; }
.description-box { background: #f5f7fa; padding: 12px; border-radius: 4px; white-space: pre-wrap; font-size: 13px; line-height: 1.6; }
.reply-list { max-height: 400px; overflow-y: auto; }
.reply-item { padding: 10px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 8px; }
.reply-item.internal { background: #fdf6ec; border-color: #e6a23c33; }
.reply-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
.reply-time { color: #909399; font-size: 12px; }
.reply-content { white-space: pre-wrap; font-size: 13px; line-height: 1.5; }
.reply-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.assign-row { display: flex; align-items: center; gap: 8px; }
.score-stars { color: #f7ba2a; font-size: 13px; }
.score-stars-lg { color: #f7ba2a; font-size: 18px; letter-spacing: 2px; }
.satisfaction-box { padding: 10px; background: #f0f9eb; border-radius: 4px; }
.satisfaction-comment { display: block; margin-top: 4px; font-size: 13px; color: #606266; }
</style>
