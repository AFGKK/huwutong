<template>
  <div class="commission-manager">
    <!-- 统计概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">代理总数</div><div class="stat-value">{{ stats.total_agents || 0 }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">活跃代理</div><div class="stat-value">{{ stats.active_agents || 0 }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">累计佣金</div><div class="stat-value">¥{{ (stats.total_settled || 0).toLocaleString() }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">本月佣金</div><div class="stat-value">¥{{ (stats.monthly_settled || 0).toLocaleString() }}</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <!-- 代理管理 -->
      <el-tab-pane label="代理管理" name="agents">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" size="small">
            <el-form-item><el-input v-model="searchAgent" placeholder="搜索代理" clearable @clear="fetchAgents" @keyup.enter="fetchAgents" /></el-form-item>
            <el-form-item><el-select v-model="filterAgentStatus" placeholder="状态" clearable @change="fetchAgents"><el-option label="活跃" value="active" /><el-option label="暂停" value="suspended" /><el-option label="终止" value="terminated" /></el-select></el-form-item>
            <el-form-item><el-button @click="fetchAgents">刷新</el-button></el-form-item>
          </el-form>
          <el-button type="primary" @click="openAgentDialog"><el-icon><Plus /></el-icon>添加代理</el-button>
        </div>
        <el-table :data="agents" v-loading="loadingAgents" stripe>
          <el-table-column label="代理编号" prop="agent_code" width="130" />
          <el-table-column label="姓名" width="120"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
          <el-table-column label="邮箱" width="180"><template #default="{ row }">{{ row.user?.email }}</template></el-table-column>
          <el-table-column label="等级" width="80"><template #default="{ row }"><el-tag :type="levelTag(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag></template></el-table-column>
          <el-table-column label="佣金率" width="80"><template #default="{ row }">{{ row.commission_rate || '默认' }}%</template></el-table-column>
          <el-table-column label="累计佣金" width="100"><template #default="{ row }">¥{{ row.total_earned.toFixed(2) }}</template></el-table-column>
          <el-table-column label="已提现" width="100"><template #default="{ row }">¥{{ row.total_withdrawn.toFixed(2) }}</template></el-table-column>
          <el-table-column label="状态" width="70"><template #default="{ row }"><el-tag v-if="row.status==='active'" type="success" size="small">活跃</el-tag><el-tag v-else type="info" size="small">{{ row.status }}</el-tag></template></el-table-column>
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewAgent(row)">查看</el-button>
              <el-button size="small" @click="editAgent(row)">编辑</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="agentTotal" :page-size="20" @current-change="page => fetchAgents(page)" />
      </el-tab-pane>

      <!-- 佣金计划 -->
      <el-tab-pane label="佣金计划" name="plans">
        <div class="flex justify-between mb-3">
          <span class="text-gray">配置不同产品×代理等级的佣金比例</span>
          <el-button type="primary" size="small" @click="openPlanDialog"><el-icon><Plus /></el-icon>新建计划</el-button>
        </div>

        <el-table :data="plans" v-loading="loadingPlans" stripe>
          <el-table-column prop="name" label="计划名称" width="160" />
          <el-table-column prop="slug" label="标识" width="120" />
          <el-table-column label="状态" width="70"><template #default="{ row }"><el-tag v-if="row.is_active" type="success" size="small">启用</el-tag><el-tag v-else type="info">停用</el-tag></template></el-table-column>
          <el-table-column label="操作" width="200">
            <template #default="{ row }">
              <el-button size="small" @click="editPlan(row)">编辑</el-button>
              <el-button size="small" type="primary" plain @click="managePlanItems(row)">管理明细</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 结算记录 -->
      <el-tab-pane label="结算记录" name="settlements">
        <el-form :inline="true" size="small" class="mb-3">
          <el-form-item><el-input v-model="filterSettlementAgent" placeholder="代理ID" style="width:120px" /></el-form-item>
          <el-form-item><el-select v-model="filterSettlementStatus" placeholder="状态" clearable @change="fetchSettlements"><el-option label="待处理" value="pending" /><el-option label="已释放" value="released" /><el-option label="已取消" value="cancelled" /><el-option label="已退款" value="refunded" /></el-select></el-form-item>
          <el-form-item><el-input v-model="filterPeriod" placeholder="月份 YYYY-MM" style="width:130px" /></el-form-item>
          <el-form-item><el-button @click="fetchSettlements">查询</el-button></el-form-item>
        </el-form>
        <el-table :data="settlements" v-loading="loadingSettlements" stripe>
          <el-table-column label="代理" width="120"><template #default="{ row }">{{ row.agent?.user?.name || row.agent_id }}</template></el-table-column>
          <el-table-column label="发票金额" width="100"><template #default="{ row }">¥{{ row.invoice_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column label="佣金率" width="70"><template #default="{ row }">{{ row.commission_rate }}%</template></el-table-column>
          <el-table-column label="佣金金额" width="100"><template #default="{ row }">¥{{ row.commission_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="settlementStatusTag(row.status)" size="small">{{ settlementStatusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column label="结算周期" width="90" prop="period" />
          <el-table-column label="结算日期" width="100"><template #default="{ row }">{{ row.settled_at ? new Date(row.settled_at).toLocaleDateString() : '-' }}</template></el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 提现管理 -->
      <el-tab-pane label="提现管理" name="payouts">
        <el-table :data="payouts" v-loading="loadingPayouts" stripe>
          <el-table-column label="代理" width="120"><template #default="{ row }">{{ row.agent?.user?.name || row.agent_id }}</template></el-table-column>
          <el-table-column label="金额" width="100"><template #default="{ row }">¥{{ row.amount.toFixed(2) }}</template></el-table-column>
          <el-table-column label="手续费" width="80"><template #default="{ row }">¥{{ row.fee.toFixed(2) }}</template></el-table-column>
          <el-table-column label="到账" width="100"><template #default="{ row }">¥{{ row.net_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column label="方式" width="100" prop="payout_method" />
          <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="payoutStatusTag(row.status)" size="small">{{ payoutStatusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column label="交易ID" width="140" prop="transaction_id" />
          <el-table-column label="操作" width="140">
            <template #default="{ row }">
              <el-button v-if="row.status === 'pending'" size="small" @click="editPayout(row)">处理</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 月度趋势 -->
      <el-tab-pane label="月度趋势" name="trend">
        <el-table :data="stats.monthly_trend || []" stripe>
          <el-table-column prop="period" label="月份" width="120" />
          <el-table-column prop="count" label="结算笔数" width="100" />
          <el-table-column label="金额"><template #default="{ row }">¥{{ (row.amount || 0).toFixed(2) }}</template></el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 代理 Dialog -->
    <el-dialog v-model="showAgentDialog" :title="editingAgent ? '编辑代理' : '添加代理'" width="520px">
      <el-form :model="agentForm" ref="agentFormRef" label-width="120px">
        <el-form-item label="用户" prop="user_id" v-if="!editingAgent">
          <el-select v-model="agentForm.user_id" filterable remote :remote-method="searchUsers" :loading="searchingUsers" style="width:100%">
            <el-option v-for="u in userOptions" :key="u.id" :label="`${u.name} (${u.email})`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="等级" prop="level">
          <el-select v-model="agentForm.level" style="width:100%">
            <el-option label="普通" value="regular" />
            <el-option label="白银" value="silver" />
            <el-option label="黄金" value="gold" />
            <el-option label="铂金" value="platinum" />
          </el-select>
        </el-form-item>
        <el-form-item label="自定义佣金率">
          <el-input-number v-model="agentForm.commission_rate" :min="0" :max="100" :precision="2" style="width:200px" /> %
        </el-form-item>
        <el-form-item label="联系人"><el-input v-model="agentForm.contact_name" /></el-form-item>
        <el-form-item label="电话"><el-input v-model="agentForm.contact_phone" /></el-form-item>
        <el-form-item label="公司"><el-input v-model="agentForm.company" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="agentForm.notes" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="状态" v-if="editingAgent">
          <el-select v-model="agentForm.status"><el-option label="活跃" value="active" /><el-option label="暂停" value="suspended" /><el-option label="终止" value="terminated" /></el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAgentDialog = false">取消</el-button>
        <el-button type="primary" @click="submitAgent" :loading="submittingAgent">保存</el-button>
      </template>
    </el-dialog>

    <!-- 佣金计划 Dialog -->
    <el-dialog v-model="showPlanDialog" :title="editingPlan ? '编辑计划' : '新建计划'" width="480px">
      <el-form :model="planForm" ref="planFormRef" label-width="100px">
        <el-form-item label="名称" prop="name"><el-input v-model="planForm.name" /></el-form-item>
        <el-form-item label="Slug" prop="slug" v-if="!editingPlan"><el-input v-model="planForm.slug" /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="planForm.is_active" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="planForm.description" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanDialog = false">取消</el-button>
        <el-button type="primary" @click="submitPlan" :loading="submittingPlan">保存</el-button>
      </template>
    </el-dialog>

    <!-- 计划明细 Dialog -->
    <el-dialog v-model="showPlanItemsDialog" :title="'计划明细 - ' + currentPlanName" width="700px">
      <div class="mb-3">
        <el-button type="primary" size="small" @click="openPlanItemDialog(null)">添加明细</el-button>
      </div>
      <el-table :data="planItems" v-loading="loadingPlanItems" stripe>
        <el-table-column label="产品" width="150"><template #default="{ row }">{{ row.product?.name || row.product_category || '全部' }}</template></el-table-column>
        <el-table-column label="代理等级" width="100" prop="agent_level" />
        <el-table-column label="佣金率" width="100"><template #default="{ row }">{{ row.commission_rate }}%</template></el-table-column>
        <el-table-column label="优先级" width="70" prop="priority" />
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button size="small" @click="openPlanItemDialog(row)">编辑</el-button>
            <el-popconfirm title="确定删除?" @confirm="deletePlanItem(row.id)">
              <template #reference><el-button size="small" type="danger" plain>删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <el-dialog v-model="showPlanItemDialog" :title="editingPlanItem ? '编辑明细' : '添加明细'" width="460px">
      <el-form :model="planItemForm" label-width="100px">
        <el-form-item label="产品"><el-select v-model="planItemForm.product_id" filterable clearable style="width:100%"><el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" /></el-select></el-form-item>
        <el-form-item label="产品分类"><el-input v-model="planItemForm.product_category" placeholder="或填写分类" /></el-form-item>
        <el-form-item label="代理等级"><el-select v-model="planItemForm.agent_level"><el-option label="全部" value="" /><el-option label="普通" value="regular" /><el-option label="白银" value="silver" /><el-option label="黄金" value="gold" /><el-option label="铂金" value="platinum" /></el-select></el-form-item>
        <el-form-item label="佣金比例"><el-input-number v-model="planItemForm.commission_rate" :min="0" :max="100" :precision="2" /> %</el-form-item>
        <el-form-item label="优先级"><el-input-number v-model="planItemForm.priority" :min="0" :max="99" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanItemDialog = false">取消</el-button>
        <el-button type="primary" @click="submitPlanItem" :loading="submittingPlanItem">保存</el-button>
      </template>
    </el-dialog>

    <!-- 编辑提现状态 -->
    <el-dialog v-model="showPayoutDialog" title="处理提现" width="460px">
      <el-form :model="payoutForm" label-width="100px">
        <el-form-item label="金额">¥{{ payoutForm.amount }}</el-form-item>
        <el-form-item label="状态"><el-select v-model="payoutForm.status"><el-option label="处理中" value="processing" /><el-option label="已完成" value="completed" /><el-option label="已取消" value="cancelled" /><el-option label="已失败" value="failed" /></el-select></el-form-item>
        <el-form-item label="交易ID"><el-input v-model="payoutForm.transaction_id" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="payoutForm.notes" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayoutDialog = false">取消</el-button>
        <el-button type="primary" @click="submitPayout" :loading="submittingPayout">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/commission';

const activeTab = ref('agents');

// ── 统计 ──
const stats = reactive({});

function fetchDashboard() {
  api.dashboard().then(res => {
    Object.assign(stats, res.data.data || {});
  });
}

// ── 代理 ──
const agents = ref([]);
const loadingAgents = ref(false);
const agentTotal = ref(0);
const searchAgent = ref('');
const filterAgentStatus = ref('');

function fetchAgents(page = 1) {
  loadingAgents.value = true;
  const params = { page, per_page: 20 };
  if (searchAgent.value) params.search = searchAgent.value;
  if (filterAgentStatus.value) params.status = filterAgentStatus.value;
  api.listAgents(params).then(res => {
    agents.value = res.data.data?.data || [];
    agentTotal.value = res.data.data?.total || 0;
  }).finally(() => loadingAgents.value = false);
}

const showAgentDialog = ref(false);
const editingAgent = ref(null);
const agentForm = reactive({ user_id: null, level: 'regular', commission_rate: null, contact_name: '', contact_phone: '', company: '', notes: '', status: 'active' });
const submittingAgent = ref(false);
const userOptions = ref([]);
const searchingUsers = ref(false);

function searchUsers(query) {
  if (!query) return;
  searchingUsers.value = true;
  import('@/api/client').then(m => {
    m.default.get('/admin/users/search', { params: { q: query } }).then(res => {
      userOptions.value = res.data.data || [];
    }).finally(() => searchingUsers.value = false);
  });
}

function openAgentDialog() {
  editingAgent.value = null;
  Object.assign(agentForm, { user_id: null, level: 'regular', commission_rate: null, contact_name: '', contact_phone: '', company: '', notes: '', status: 'active' });
  showAgentDialog.value = true;
}

function editAgent(agent) {
  editingAgent.value = agent;
  Object.assign(agentForm, {
    level: agent.level,
    commission_rate: agent.commission_rate,
    contact_name: agent.contact_name,
    contact_phone: agent.contact_phone,
    company: agent.company,
    notes: agent.notes,
    status: agent.status,
  });
  showAgentDialog.value = true;
}

function submitAgent() {
  submittingAgent.value = true;
  const promise = editingAgent.value
    ? api.updateAgent(editingAgent.value.id, agentForm)
    : api.createAgent(agentForm);
  promise.then(() => {
    ElMessage.success(editingAgent.value ? '代理已更新' : '代理已创建');
    showAgentDialog.value = false;
    fetchAgents();
  }).catch(() => ElMessage.error('操作失败'))
  .finally(() => submittingAgent.value = false);
}

function viewAgent(agent) {
  // 查看代理详情 - TODO: 详情弹出
  ElMessage.info(`代理 ${agent.agent_code}`);
}

// ── 佣金计划 ──
const plans = ref([]);
const loadingPlans = ref(false);

function fetchPlans() {
  loadingPlans.value = true;
  api.listPlans().then(res => {
    plans.value = res.data.data?.data || [];
  }).finally(() => loadingPlans.value = false);
}

const showPlanDialog = ref(false);
const editingPlan = ref(null);
const planForm = reactive({ name: '', slug: '', is_active: true, description: '' });
const submittingPlan = ref(false);

function openPlanDialog() {
  editingPlan.value = null;
  Object.assign(planForm, { name: '', slug: '', is_active: true, description: '' });
  showPlanDialog.value = true;
}

function editPlan(plan) {
  editingPlan.value = plan;
  Object.assign(planForm, { name: plan.name, slug: plan.slug, is_active: plan.is_active, description: plan.description || '' });
  showPlanDialog.value = true;
}

function submitPlan() {
  submittingPlan.value = true;
  const promise = editingPlan.value
    ? api.updatePlan(editingPlan.value.id, planForm)
    : api.createPlan(planForm);
  promise.then(() => {
    ElMessage.success('佣金计划已保存');
    showPlanDialog.value = false;
    fetchPlans();
  }).catch(() => ElMessage.error('操作失败'))
  .finally(() => submittingPlan.value = false);
}

// ── 计划明细 ──
const showPlanItemsDialog = ref(false);
const currentPlanId = ref(null);
const currentPlanName = ref('');
const planItems = ref([]);
const loadingPlanItems = ref(false);
const showPlanItemDialog = ref(false);
const editingPlanItem = ref(null);
const planItemForm = reactive({ product_id: null, product_category: '', agent_level: '', commission_rate: 10, priority: 0 });
const submittingPlanItem = ref(false);
const productOptions = ref([]);

function managePlanItems(plan) {
  currentPlanId.value = plan.id;
  currentPlanName.value = plan.name;
  fetchPlanItems();
  showPlanItemsDialog.value = true;
  fetchProducts();
}

function fetchPlanItems() {
  loadingPlanItems.value = true;
  api.listPlanItems(currentPlanId.value).then(res => {
    planItems.value = res.data.data || [];
  }).finally(() => loadingPlanItems.value = false);
}

function fetchProducts() {
  import('@/api/product').then(m => {
    m.default.list().then(res => {
      productOptions.value = res.data.data?.data || [];
    }).catch(() => {});
  });
}

function openPlanItemDialog(item) {
  editingPlanItem.value = item;
  if (item) {
    Object.assign(planItemForm, { product_id: item.product_id, product_category: item.product_category || '', agent_level: item.agent_level, commission_rate: item.commission_rate, priority: item.priority });
  } else {
    Object.assign(planItemForm, { product_id: null, product_category: '', agent_level: '', commission_rate: 10, priority: 0 });
  }
  showPlanItemDialog.value = true;
}

function submitPlanItem() {
  submittingPlanItem.value = true;
  const data = { ...planItemForm };
  if (!data.product_id) data.product_id = null;
  const promise = editingPlanItem.value
    ? api.updatePlanItem(editingPlanItem.value.id, data)
    : api.createPlanItem(currentPlanId.value, data);
  promise.then(() => {
    ElMessage.success('明细已保存');
    showPlanItemDialog.value = false;
    fetchPlanItems();
  }).catch(() => ElMessage.error('操作失败'))
  .finally(() => submittingPlanItem.value = false);
}

function deletePlanItem(id) {
  api.deletePlanItem(id).then(() => {
    ElMessage.success('明细已删除');
    fetchPlanItems();
  }).catch(() => ElMessage.error('删除失败'));
}

// ── 结算 ──
const settlements = ref([]);
const loadingSettlements = ref(false);
const filterSettlementAgent = ref('');
const filterSettlementStatus = ref('');
const filterPeriod = ref('');

function fetchSettlements(page = 1) {
  loadingSettlements.value = true;
  const params = { page, per_page: 20 };
  if (filterSettlementAgent.value) params.agent_id = filterSettlementAgent.value;
  if (filterSettlementStatus.value) params.status = filterSettlementStatus.value;
  if (filterPeriod.value) params.period = filterPeriod.value;
  api.listSettlements(params).then(res => {
    settlements.value = res.data.data?.data || [];
  }).finally(() => loadingSettlements.value = false);
}

// ── 提现 ──
const payouts = ref([]);
const loadingPayouts = ref(false);

function fetchPayouts(page = 1) {
  loadingPayouts.value = true;
  api.listPayouts({ page, per_page: 20 }).then(res => {
    payouts.value = res.data.data?.data || [];
  }).finally(() => loadingPayouts.value = false);
}

const showPayoutDialog = ref(false);
const payoutForm = reactive({ amount: 0, status: 'processing', transaction_id: '', notes: '' });
const submittingPayout = ref(false);
const editingPayoutId = ref(null);

function editPayout(payout) {
  editingPayoutId.value = payout.id;
  Object.assign(payoutForm, { amount: payout.amount, status: payout.status, transaction_id: payout.transaction_id || '', notes: payout.notes || '' });
  showPayoutDialog.value = true;
}

function submitPayout() {
  submittingPayout.value = true;
  api.processPayout(editingPayoutId.value, payoutForm).then(() => {
    ElMessage.success('提现状态已更新');
    showPayoutDialog.value = false;
    fetchPayouts();
    fetchDashboard();
  }).catch(() => ElMessage.error('操作失败'))
  .finally(() => submittingPayout.value = false);
}

// ── Helper ──
function levelTag(level) { return ({ regular: 'info', silver: '', gold: 'warning', platinum: 'danger' })[level] || 'info'; }
function levelLabel(level) { return ({ regular: '普通', silver: '白银', gold: '黄金', platinum: '铂金' })[level] || level; }
function settlementStatusTag(s) { return ({ pending: 'warning', pending_release: '', released: 'success', cancelled: 'info', refunded: 'danger' })[s] || 'info'; }
function settlementStatusLabel(s) { return ({ pending: '待处理', pending_release: '待释放', released: '已释放', cancelled: '已取消', refunded: '已退款' })[s] || s; }
function payoutStatusTag(s) { return ({ pending: 'warning', processing: '', completed: 'success', failed: 'danger', cancelled: 'info' })[s] || 'info'; }
function payoutStatusLabel(s) { return ({ pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', cancelled: '已取消' })[s] || s; }

onMounted(() => {
  fetchDashboard();
  fetchAgents();
  fetchPlans();
  fetchSettlements();
  fetchPayouts();
});
</script>

<style scoped>
.commission-manager { padding: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.stat-box { text-align: center; }
.stat-label { font-size: 13px; color: #6b7280; margin-bottom: 6px; }
.stat-value { font-size: 18px; font-weight: 600; }
.text-gray { color: #6b7280; font-size: 13px; }
</style>
