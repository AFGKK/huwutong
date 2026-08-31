<template>
  <div class="commission-manager">
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">{{ t('commission_page.stats.total_agents') }}</div><div class="stat-value">{{ stats.total_agents || 0 }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">{{ t('commission_page.stats.active_agents') }}</div><div class="stat-value">{{ stats.active_agents || 0 }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">{{ t('commission_page.stats.settled_commission') }}</div><div class="stat-value">¥{{ (stats.total_settled || 0).toLocaleString() }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-box"><div class="stat-label">{{ t('commission_page.stats.monthly_commission') }}</div><div class="stat-value">¥{{ (stats.monthly_settled || 0).toLocaleString() }}</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <el-tab-pane :label="t('commission_page.tabs.agents')" name="agents">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" size="small">
            <el-form-item><el-input v-model="searchAgent" :placeholder="t('commission_page.placeholders.search_agent')" clearable @clear="fetchAgents" @keyup.enter="fetchAgents" /></el-form-item>
            <el-form-item><el-select v-model="filterAgentStatus" :placeholder="t('commission_page.placeholders.status')" clearable @change="fetchAgents"><el-option v-for="opt in agentStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
            <el-form-item><el-button @click="fetchAgents">{{ t('commission_page.buttons.refresh') }}</el-button></el-form-item>
          </el-form>
          <el-button type="primary" @click="openAgentDialog"><el-icon><Plus /></el-icon>{{ t('commission_page.buttons.add_agent') }}</el-button>
        </div>
        <el-table :data="agents" v-loading="loadingAgents" stripe>
          <el-table-column :label="t('commission_page.cols.agent_code')" prop="agent_code" width="130" />
          <el-table-column :label="t('commission_page.cols.name')" width="120"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.email')" width="180"><template #default="{ row }">{{ row.user?.email }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.level')" width="80"><template #default="{ row }"><el-tag :type="levelTag(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag></template></el-table-column>
          <el-table-column :label="t('commission_page.cols.commission_rate')" width="80"><template #default="{ row }">{{ row.commission_rate || t('commission_page.default_rate') }}%</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.total_earned')" width="100"><template #default="{ row }">¥{{ row.total_earned.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.total_withdrawn')" width="100"><template #default="{ row }">¥{{ row.total_withdrawn.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.status')" width="70"><template #default="{ row }"><el-tag v-if="row.status==='active'" type="success" size="small">{{ agentStatusLabel(row.status) }}</el-tag><el-tag v-else type="info" size="small">{{ agentStatusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column :label="t('commission_page.cols.actions')" width="160" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewAgent(row)">{{ t('actions.view') }}</el-button>
              <el-button size="small" @click="editAgent(row)">{{ t('actions.edit') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination class="mt-3" layout="prev, pager, next" :total="agentTotal" :page-size="20" @current-change="page => fetchAgents(page)" />
      </el-tab-pane>

      <el-tab-pane :label="t('commission_page.tabs.plans')" name="plans">
        <div class="flex justify-between mb-3">
          <span class="text-gray">{{ t('commission_page.plans_hint') }}</span>
          <el-button type="primary" size="small" @click="openPlanDialog"><el-icon><Plus /></el-icon>{{ t('commission_page.buttons.create_plan') }}</el-button>
        </div>

        <el-table :data="plans" v-loading="loadingPlans" stripe>
          <el-table-column prop="name" :label="t('commission_page.cols.plan_name')" width="160" />
          <el-table-column prop="slug" :label="t('commission_page.cols.slug')" width="120" />
          <el-table-column :label="t('commission_page.cols.status')" width="70"><template #default="{ row }"><el-tag v-if="row.is_active" type="success" size="small">{{ t('commission_page.enabled') }}</el-tag><el-tag v-else type="info">{{ t('commission_page.disabled') }}</el-tag></template></el-table-column>
          <el-table-column :label="t('commission_page.cols.actions')" width="200">
            <template #default="{ row }">
              <el-button size="small" @click="editPlan(row)">{{ t('actions.edit') }}</el-button>
              <el-button size="small" type="primary" plain @click="managePlanItems(row)">{{ t('commission_page.buttons.manage_items') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('commission_page.tabs.settlements')" name="settlements">
        <el-form :inline="true" size="small" class="mb-3">
          <el-form-item><el-input v-model="filterSettlementAgent" :placeholder="t('commission_page.placeholders.agent_id')" style="width:120px" /></el-form-item>
          <el-form-item><el-select v-model="filterSettlementStatus" :placeholder="t('commission_page.placeholders.status')" clearable @change="fetchSettlements"><el-option v-for="opt in settlementFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
          <el-form-item><el-input v-model="filterPeriod" :placeholder="t('commission_page.placeholders.period')" style="width:130px" /></el-form-item>
          <el-form-item><el-button @click="fetchSettlements">{{ t('commission_page.buttons.query') }}</el-button></el-form-item>
        </el-form>
        <el-table :data="settlements" v-loading="loadingSettlements" stripe>
          <el-table-column :label="t('commission_page.cols.agent')" width="120"><template #default="{ row }">{{ row.agent?.user?.name || row.agent_id }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.invoice_amount')" width="100"><template #default="{ row }">¥{{ row.invoice_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.commission_rate')" width="70"><template #default="{ row }">{{ row.commission_rate }}%</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.commission_amount')" width="100"><template #default="{ row }">¥{{ row.commission_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.status')" width="100"><template #default="{ row }"><el-tag :type="settlementStatusTag(row.status)" size="small">{{ settlementStatusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column :label="t('commission_page.cols.period')" width="90" prop="period" />
          <el-table-column :label="t('commission_page.cols.settled_at')" width="100"><template #default="{ row }">{{ row.settled_at ? new Date(row.settled_at).toLocaleDateString() : '-' }}</template></el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('commission_page.tabs.payouts')" name="payouts">
        <el-table :data="payouts" v-loading="loadingPayouts" stripe>
          <el-table-column :label="t('commission_page.cols.agent')" width="120"><template #default="{ row }">{{ row.agent?.user?.name || row.agent_id }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.amount')" width="100"><template #default="{ row }">¥{{ row.amount.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.fee')" width="80"><template #default="{ row }">¥{{ row.fee.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.net_amount')" width="100"><template #default="{ row }">¥{{ row.net_amount.toFixed(2) }}</template></el-table-column>
          <el-table-column :label="t('commission_page.cols.payout_method')" width="100" prop="payout_method" />
          <el-table-column :label="t('commission_page.cols.status')" width="90"><template #default="{ row }"><el-tag :type="payoutStatusTag(row.status)" size="small">{{ payoutStatusLabel(row.status) }}</el-tag></template></el-table-column>
          <el-table-column :label="t('commission_page.cols.transaction_id')" width="140" prop="transaction_id" />
          <el-table-column :label="t('commission_page.cols.actions')" width="140">
            <template #default="{ row }">
              <el-button v-if="row.status === 'pending'" size="small" @click="editPayout(row)">{{ t('commission_page.buttons.process_payout') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('commission_page.tabs.trend')" name="trend">
        <el-table :data="stats.monthly_trend || []" stripe>
          <el-table-column prop="period" :label="t('commission_page.cols.month')" width="120" />
          <el-table-column prop="count" :label="t('commission_page.cols.settlement_count')" width="100" />
          <el-table-column :label="t('commission_page.cols.amount')"><template #default="{ row }">¥{{ (row.amount || 0).toFixed(2) }}</template></el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="showAgentDialog" :title="editingAgent ? t('commission_page.dialogs.edit_agent') : t('commission_page.dialogs.add_agent')" width="520px">
      <el-form :model="agentForm" ref="agentFormRef" label-width="120px">
        <el-form-item :label="t('commission_page.cols.user')" prop="user_id" v-if="!editingAgent">
          <el-select v-model="agentForm.user_id" filterable remote :remote-method="searchUsers" :loading="searchingUsers" style="width:100%">
            <el-option v-for="u in userOptions" :key="u.id" :label="`${u.name} (${u.email})`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('commission_page.cols.level')" prop="level">
          <el-select v-model="agentForm.level" style="width:100%">
            <el-option v-for="opt in levelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('commission_page.form.custom_rate')">
          <el-input-number v-model="agentForm.commission_rate" :min="0" :max="100" :precision="2" style="width:200px" /> %
        </el-form-item>
        <el-form-item :label="t('commission_page.cols.contact_name')"><el-input v-model="agentForm.contact_name" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.phone')"><el-input v-model="agentForm.contact_phone" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.company')"><el-input v-model="agentForm.company" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.notes')"><el-input v-model="agentForm.notes" type="textarea" :rows="2" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.status')" v-if="editingAgent">
          <el-select v-model="agentForm.status"><el-option v-for="opt in agentStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAgentDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitAgent" :loading="submittingAgent">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showAgentDetail" :title="t('commission_page.dialogs.agent_detail')" width="640px">
      <div v-loading="loadingAgentDetail">
        <template v-if="agentDetail">
          <el-descriptions :column="2" border>
            <el-descriptions-item :label="t('commission_page.cols.agent_code')">{{ agentDetail.agent_code }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.status')">
              <el-tag v-if="agentDetail.status==='active'" type="success" size="small">{{ agentStatusLabel(agentDetail.status) }}</el-tag>
              <el-tag v-else type="info" size="small">{{ agentStatusLabel(agentDetail.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.name')">{{ agentDetail.user?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.email')">{{ agentDetail.user?.email || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.level')">
              <el-tag :type="levelTag(agentDetail.level)" size="small">{{ levelLabel(agentDetail.level) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.commission_rate')">{{ agentDetail.commission_rate ?? t('commission_page.default_rate') }}%</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.contact_name')">{{ agentDetail.contact_name || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.phone')">{{ agentDetail.contact_phone || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.company')" :span="2">{{ agentDetail.company || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.total_earned')">¥{{ Number(agentDetail.total_earned || 0).toFixed(2) }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.total_withdrawn')">¥{{ Number(agentDetail.total_withdrawn || 0).toFixed(2) }}</el-descriptions-item>
            <el-descriptions-item :label="t('commission_page.cols.notes')" :span="2">{{ agentDetail.notes || '-' }}</el-descriptions-item>
          </el-descriptions>
          <template v-if="agentDetail.stats && typeof agentDetail.stats === 'object'">
            <el-divider />
            <el-descriptions :title="t('commission_page.dialogs.performance_stats')" :column="2" border size="small">
              <el-descriptions-item v-for="(val, key) in agentDetail.stats" :key="key" :label="String(key)">
                {{ typeof val === 'number' ? val : (val ?? '-') }}
              </el-descriptions-item>
            </el-descriptions>
          </template>
        </template>
      </div>
      <template #footer>
        <el-button @click="showAgentDetail = false">{{ t('actions.close') }}</el-button>
        <el-button type="primary" @click="editFromDetail" :disabled="!agentDetail">{{ t('actions.edit') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showPlanDialog" :title="editingPlan ? t('commission_page.dialogs.edit_plan') : t('commission_page.dialogs.create_plan')" width="480px">
      <el-form :model="planForm" ref="planFormRef" label-width="100px">
        <el-form-item :label="t('commission_page.cols.name')" prop="name"><el-input v-model="planForm.name" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.slug')" prop="slug" v-if="!editingPlan"><el-input v-model="planForm.slug" /></el-form-item>
        <el-form-item :label="t('commission_page.enabled')"><el-switch v-model="planForm.is_active" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.description')"><el-input v-model="planForm.description" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitPlan" :loading="submittingPlan">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showPlanItemsDialog" :title="t('commission_page.dialogs.plan_items', { name: currentPlanName })" width="700px">
      <div class="mb-3">
        <el-button type="primary" size="small" @click="openPlanItemDialog(null)">{{ t('commission_page.buttons.add_item') }}</el-button>
      </div>
      <el-table :data="planItems" v-loading="loadingPlanItems" stripe>
        <el-table-column :label="t('commission_page.cols.product')" width="150"><template #default="{ row }">{{ row.product?.name || row.product_category || t('commission_page.all_products') }}</template></el-table-column>
        <el-table-column :label="t('commission_page.cols.agent_level')" width="100" prop="agent_level" />
        <el-table-column :label="t('commission_page.cols.commission_rate')" width="100"><template #default="{ row }">{{ row.commission_rate }}%</template></el-table-column>
        <el-table-column :label="t('commission_page.cols.priority')" width="70" prop="priority" />
        <el-table-column :label="t('commission_page.cols.actions')" width="120">
          <template #default="{ row }">
            <el-button size="small" @click="openPlanItemDialog(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('commission_page.delete_confirm')" @confirm="deletePlanItem(row.id)">
              <template #reference><el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <el-dialog v-model="showPlanItemDialog" :title="editingPlanItem ? t('commission_page.dialogs.edit_item') : t('commission_page.dialogs.add_item')" width="460px">
      <el-form :model="planItemForm" label-width="100px">
        <el-form-item :label="t('commission_page.cols.product')"><el-select v-model="planItemForm.product_id" filterable clearable style="width:100%"><el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" /></el-select></el-form-item>
        <el-form-item :label="t('commission_page.cols.product_category')"><el-input v-model="planItemForm.product_category" :placeholder="t('commission_page.placeholders.category_or')" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.agent_level')"><el-select v-model="planItemForm.agent_level"><el-option v-for="opt in planItemLevelOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
        <el-form-item :label="t('commission_page.form.commission_pct')"><el-input-number v-model="planItemForm.commission_rate" :min="0" :max="100" :precision="2" /> %</el-form-item>
        <el-form-item :label="t('commission_page.cols.priority')"><el-input-number v-model="planItemForm.priority" :min="0" :max="99" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanItemDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitPlanItem" :loading="submittingPlanItem">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showPayoutDialog" :title="t('commission_page.dialogs.process_payout')" width="460px">
      <el-form :model="payoutForm" label-width="100px">
        <el-form-item :label="t('commission_page.cols.amount')">¥{{ payoutForm.amount }}</el-form-item>
        <el-form-item :label="t('commission_page.cols.status')"><el-select v-model="payoutForm.status"><el-option v-for="opt in payoutDialogStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
        <el-form-item :label="t('commission_page.cols.transaction_id')"><el-input v-model="payoutForm.transaction_id" /></el-form-item>
        <el-form-item :label="t('commission_page.cols.notes')"><el-input v-model="payoutForm.notes" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayoutDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitPayout" :loading="submittingPayout">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/commission';

const { t } = useI18n();

const activeTab = ref('agents');

const stats = reactive({});

function fetchDashboard() {
  api.dashboard().then(res => {
    Object.assign(stats, res.data.data || {});
  });
}

const levelLabels = computed(() => ({
  regular: t('commission_page.levels.regular'),
  silver: t('commission_page.levels.silver'),
  gold: t('commission_page.levels.gold'),
  platinum: t('commission_page.levels.platinum'),
}));

const levelOptions = computed(() => [
  { value: 'regular', label: levelLabels.value.regular },
  { value: 'silver', label: levelLabels.value.silver },
  { value: 'gold', label: levelLabels.value.gold },
  { value: 'platinum', label: levelLabels.value.platinum },
]);

const planItemLevelOptions = computed(() => [
  { value: '', label: t('commission_page.levels.all') },
  ...levelOptions.value,
]);

const agentStatusLabels = computed(() => ({
  active: t('commission_page.agent_status.active'),
  suspended: t('commission_page.agent_status.suspended'),
  terminated: t('commission_page.agent_status.terminated'),
}));

const agentStatusOptions = computed(() => [
  { value: 'active', label: agentStatusLabels.value.active },
  { value: 'suspended', label: agentStatusLabels.value.suspended },
  { value: 'terminated', label: agentStatusLabels.value.terminated },
]);

const settlementStatusLabels = computed(() => ({
  pending: t('commission_page.settlement_status.pending'),
  pending_release: t('commission_page.settlement_status.pending_release'),
  released: t('commission_page.settlement_status.released'),
  cancelled: t('commission_page.settlement_status.cancelled'),
  refunded: t('commission_page.settlement_status.refunded'),
}));

const settlementFilterOptions = computed(() => [
  { value: 'pending', label: settlementStatusLabels.value.pending },
  { value: 'released', label: settlementStatusLabels.value.released },
  { value: 'cancelled', label: settlementStatusLabels.value.cancelled },
  { value: 'refunded', label: settlementStatusLabels.value.refunded },
]);

const payoutStatusLabels = computed(() => ({
  pending: t('commission_page.payout_status.pending'),
  processing: t('commission_page.payout_status.processing'),
  completed: t('commission_page.payout_status.completed'),
  failed: t('commission_page.payout_status.failed'),
  cancelled: t('commission_page.payout_status.cancelled'),
}));

const payoutDialogStatusOptions = computed(() => [
  { value: 'processing', label: payoutStatusLabels.value.processing },
  { value: 'completed', label: payoutStatusLabels.value.completed },
  { value: 'cancelled', label: payoutStatusLabels.value.cancelled },
  { value: 'failed', label: payoutStatusLabels.value.failed },
]);

function levelTag(level) { return ({ regular: 'info', silver: '', gold: 'warning', platinum: 'danger' })[level] || 'info'; }
function levelLabel(level) { return levelLabels.value[level] || level; }
function agentStatusLabel(status) { return agentStatusLabels.value[status] || status; }
function settlementStatusTag(s) { return ({ pending: 'warning', pending_release: '', released: 'success', cancelled: 'info', refunded: 'danger' })[s] || 'info'; }
function settlementStatusLabel(s) { return settlementStatusLabels.value[s] || s; }
function payoutStatusTag(s) { return ({ pending: 'warning', processing: '', completed: 'success', failed: 'danger', cancelled: 'info' })[s] || 'info'; }
function payoutStatusLabel(s) { return payoutStatusLabels.value[s] || s; }

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
    ElMessage.success(editingAgent.value ? t('commission_page.messages.agent_updated') : t('commission_page.messages.agent_created'));
    showAgentDialog.value = false;
    fetchAgents();
  }).catch(() => ElMessage.error(t('messages.failed')))
  .finally(() => submittingAgent.value = false);
}

function viewAgent(agent) {
  showAgentDetail.value = true;
  loadingAgentDetail.value = true;
  agentDetail.value = null;
  api.showAgent(agent.id).then(res => {
    agentDetail.value = res.data.data || agent;
  }).catch(() => {
    agentDetail.value = agent;
    ElMessage.warning(t('commission_page.messages.detail_fallback'));
  }).finally(() => {
    loadingAgentDetail.value = false;
  });
}

function editFromDetail() {
  if (!agentDetail.value) return;
  showAgentDetail.value = false;
  editAgent(agentDetail.value);
}

const showAgentDetail = ref(false);
const loadingAgentDetail = ref(false);
const agentDetail = ref(null);

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
    ElMessage.success(t('commission_page.messages.plan_saved'));
    showPlanDialog.value = false;
    fetchPlans();
  }).catch(() => ElMessage.error(t('messages.failed')))
  .finally(() => submittingPlan.value = false);
}

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
    ElMessage.success(t('commission_page.messages.item_saved'));
    showPlanItemDialog.value = false;
    fetchPlanItems();
  }).catch(() => ElMessage.error(t('messages.failed')))
  .finally(() => submittingPlanItem.value = false);
}

function deletePlanItem(id) {
  api.deletePlanItem(id).then(() => {
    ElMessage.success(t('commission_page.messages.item_deleted'));
    fetchPlanItems();
  }).catch(() => ElMessage.error(t('messages.failed')));
}

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
    ElMessage.success(t('commission_page.messages.payout_updated'));
    showPayoutDialog.value = false;
    fetchPayouts();
    fetchDashboard();
  }).catch(() => ElMessage.error(t('messages.failed')))
  .finally(() => submittingPayout.value = false);
}

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
