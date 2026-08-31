<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">{{ t('audit_archive_page.title') }}</h2>
        <el-button size="small" @click="processExpired" :loading="processing">{{ t('audit_archive_page.process_expired') }}</el-button>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.archived_records') }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_archived_records || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.total_size') }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_size_human || '0 B' }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.pending_restores') }}</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_restores || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.available_restores') }}</div>
            <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.available_restores || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <!-- 存储分层 -->
      <el-row :gutter="16" class="mt-2">
        <el-col :span="6" v-for="(count, tier) in dashboard.by_tier || {}" :key="tier">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ tierLabel(tier) }}</div>
            <div class="text-xl font-bold mt-1" :class="tierColor(tier)">{{ count || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 归档策略 -->
        <el-tab-pane :label="t('audit_archive_page.tabs.policies')" name="policies">
          <div class="mb-3 flex justify-end">
            <el-button size="small" type="primary" @click="showPolicyDialog = true">{{ t('audit_archive_page.btn_new_policy') }}</el-button>
          </div>
          <el-table :data="policies" v-loading="loadingP" stripe style="width:100%">
            <el-table-column prop="name" :label="t('audit_archive_page.cols.policy_name')" width="180" />
            <el-table-column prop="type" :label="t('audit_archive_page.cols.type')" width="120" />
            <el-table-column :label="t('audit_archive_page.cols.storage_tier')" width="120">
              <template #default="{ row }">
                <el-tag :type="tierColor(row.storage_tier)" size="small">{{ tierLabel(row.storage_tier) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="retention_days" :label="t('audit_archive_page.cols.retention_days')" width="100" align="center" />
            <el-table-column prop="archive_records_count" :label="t('audit_archive_page.cols.archived_count')" width="80" align="center" />
            <el-table-column prop="is_active" :label="t('audit_archive_page.cols.enabled')" width="70" align="center">
              <template #default="{ row }">
                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('audit_retention_page.status_enabled') : t('audit_retention_page.status_disabled') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('audit_archive_page.cols.actions')" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="editPolicy(row)">{{ t('actions.edit') }}</el-button>
                <el-button size="small" type="primary" @click="handleArchive(row)" :loading="archiving === row.id">{{ t('audit_archive_page.btn_archive') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 归档记录 -->
        <el-tab-pane :label="t('audit_archive_page.tabs.records')" name="records">
          <div class="mb-3 flex gap-2">
            <el-select v-model="rFilters.tier" :placeholder="t('audit_archive_page.filters.storage_tier')" clearable size="small" style="width:140px">
              <el-option v-for="opt in tierOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchRecords">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="records" v-loading="loadingR" stripe style="width:100%">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column :label="t('audit_archive_page.cols.storage_tier')" width="100">
              <template #default="{ row }">
                <el-tag :type="tierColor(row.storage_tier)" size="small">{{ tierLabel(row.storage_tier) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="file_name" :label="t('audit_archive_page.cols.file_name')" min-width="200" show-overflow-tooltip />
            <el-table-column prop="file_size_human" :label="t('audit_archive_page.cols.size')" width="90" align="right" />
            <el-table-column prop="archived_at" :label="t('audit_archive_page.cols.archived_at')" width="180" />
            <el-table-column prop="expires_at" :label="t('audit_archive_page.cols.expires_at')" width="180" />
            <el-table-column :label="t('audit_archive_page.cols.actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.storage_tier !== 'hot'" size="small" type="warning" @click="showRestoreDialog(row)">{{ t('audit_archive_page.btn_restore') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t('audit_archive_page.list_total', { total: rPagination.total }) }}</span>
            <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRecords" />
          </div>
        </el-tab-pane>

        <!-- 恢复请求 -->
        <el-tab-pane :label="t('audit_archive_page.tabs.restores')" name="restores">
          <el-table :data="restoreRequests" v-loading="loadingS" stripe style="width:100%">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column :label="t('audit_archive_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="rsStatusType(row.status)" size="small">{{ rsStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="restore_reason" :label="t('audit_archive_page.cols.restore_reason')" min-width="220" show-overflow-tooltip />
            <el-table-column prop="requested_at" :label="t('audit_archive_page.cols.requested_at')" width="180" />
            <el-table-column prop="available_until" :label="t('audit_archive_page.cols.available_until')" width="180" />
            <el-table-column :label="t('audit_archive_page.cols.actions')" width="160" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleExecuteRestore(row)">{{ t('audit_archive_page.btn_execute_restore') }}</el-button>
                <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="handleCancelRestore(row)">{{ t('actions.cancel') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t('audit_archive_page.list_total', { total: sPagination.total }) }}</span>
            <el-pagination v-model:current-page="sPagination.page" :page-size="sPagination.per_page" :total="sPagination.total" layout="prev, pager, next" small @current-change="fetchRestoreRequests" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 策略对话框 -->
    <el-dialog v-model="showPolicyDialog" :title="editingPolicy ? t('audit_archive_page.dialog.edit_policy') : t('audit_archive_page.dialog.new_policy')" width="500px">
      <el-form :model="policyForm" label-width="120px">
        <el-form-item :label="t('audit_archive_page.form.policy_name')" required>
          <el-input v-model="policyForm.name" />
        </el-form-item>
        <el-form-item :label="t('audit_archive_page.form.type')" required>
          <el-select v-model="policyForm.type" style="width:100%">
            <el-option v-for="opt in policyTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('audit_archive_page.form.storage_tier')">
          <el-select v-model="policyForm.storage_tier" style="width:100%">
            <el-option v-for="opt in tierFormOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('audit_archive_page.form.retention_days')">
          <el-input-number v-model="policyForm.retention_days" :min="1" :max="7300" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('audit_archive_page.form.enabled')">
          <el-switch v-model="policyForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPolicyDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSavePolicy" :loading="savingP">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 恢复对话框 -->
    <el-dialog v-model="showRestoreDialogVisible" :title="t('audit_archive_page.dialog.request_restore')" width="400px">
      <p class="mb-3">{{ t('audit_archive_page.dialog.restore_confirm', { id: restoreTarget?.id }) }}</p>
      <el-input v-model="restoreReason" type="textarea" :rows="3" :placeholder="t('audit_archive_page.form.restore_reason_ph')" />
      <template #footer>
        <el-button @click="showRestoreDialogVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleRequestRestore" :loading="restoring">{{ t('audit_archive_page.btn_submit_restore') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getArchiverDashboard, getArchivePolicies, getArchiveRecords, getRestoreRequests,
  upsertArchivePolicy, executeArchive, requestRestore, executeRestore,
  cancelRestore, processExpiredRestores,
} from '../../api/logArchiver';

const { t } = useI18n();

const tierKeys = ['hot', 'warm', 'cold', 'frozen'];
const restoreStatusKeys = ['pending', 'restoring', 'available', 'expired', 'cancelled'];
const policyTypeKeys = ['activation', 'audit', 'event', 'webhook', 'error', 'custom'];

const loadingP = ref(false);
const loadingR = ref(false);
const loadingS = ref(false);
const processing = ref(false);
const archiving = ref(null);
const savingP = ref(false);
const restoring = ref(false);
const activeTab = ref('policies');

const dashboard = ref({});
const policies = ref([]);
const records = ref([]);
const restoreRequests = ref([]);

const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const sPagination = ref({ page: 1, per_page: 20, total: 0 });
const rFilters = ref({ tier: '' });

const showPolicyDialog = ref(false);
const editingPolicy = ref(null);
const policyForm = ref({ name: '', type: 'audit', storage_tier: 'cold', retention_days: 365, is_active: true });

const showRestoreDialogVisible = ref(false);
const restoreTarget = ref(null);
const restoreReason = ref('');

const tierLabels = computed(() =>
  Object.fromEntries(tierKeys.map((key) => [key, t(`audit_archive_page.tier.${key}`)]))
);

const tierOptions = computed(() =>
  tierKeys.map((value) => ({
    value,
    label: t(`audit_archive_page.tier.${value}`),
  }))
);

const tierFormOptions = computed(() =>
  tierKeys.map((value) => ({
    value,
    label: t(`audit_archive_page.tier.${value}_desc`),
  }))
);

const policyTypeOptions = computed(() =>
  policyTypeKeys.map((value) => ({
    value,
    label: t(`audit_archive_page.policy_type.${value}`),
  }))
);

const restoreStatusLabels = computed(() =>
  Object.fromEntries(restoreStatusKeys.map((key) => [key, t(`audit_archive_page.restore_status.${key}`)]))
);

onMounted(async () => {
  await fetchDashboard();
  await fetchPolicies();
});

async function fetchDashboard() {
  try { const res = await getArchiverDashboard(); dashboard.value = res.data.data || {}; } catch (e) {}
}

async function fetchPolicies() {
  loadingP.value = true;
  try { const res = await getArchivePolicies(); policies.value = res.data.data || []; } catch (e) {}
  loadingP.value = false;
}

async function fetchRecords() {
  loadingR.value = true;
  try {
    const params = { ...rFilters.value, page: rPagination.value.page, per_page: rPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getArchiveRecords(params);
    const data = res.data.data;
    records.value = data.data || [];
    rPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}

async function fetchRestoreRequests() {
  loadingS.value = true;
  try {
    const params = { page: sPagination.value.page, per_page: sPagination.value.per_page };
    const res = await getRestoreRequests(params);
    const data = res.data.data;
    restoreRequests.value = data.data || [];
    sPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingS.value = false;
}

function editPolicy(row) {
  editingPolicy.value = row;
  policyForm.value = { name: row.name, type: row.type, storage_tier: row.storage_tier, retention_days: row.retention_days, is_active: row.is_active, id: row.id };
  showPolicyDialog.value = true;
}

async function handleSavePolicy() {
  savingP.value = true;
  try {
    await upsertArchivePolicy(policyForm.value);
    ElMessage.success(t('audit_archive_page.messages.policy_saved'));
    showPolicyDialog.value = false;
    editingPolicy.value = null;
    policyForm.value = { name: '', type: 'audit', storage_tier: 'cold', retention_days: 365, is_active: true };
    await fetchPolicies();
    await fetchDashboard();
  } catch (e) {}
  savingP.value = false;
}

async function handleArchive(row) {
  archiving.value = row.id;
  try {
    const res = await executeArchive(row.id);
    ElMessage.success(res.data.message || t('audit_archive_page.messages.archive_started'));
    await fetchPolicies();
    await fetchDashboard();
  } catch (e) {}
  archiving.value = null;
}

function showRestoreDialog(row) {
  restoreTarget.value = row;
  restoreReason.value = '';
  showRestoreDialogVisible.value = true;
}

async function handleRequestRestore() {
  if (!restoreReason.value) { ElMessage.warning(t('audit_archive_page.messages.reason_required')); return; }
  restoring.value = true;
  try {
    await requestRestore(restoreTarget.value.id, { reason: restoreReason.value });
    ElMessage.success(t('audit_archive_page.messages.restore_requested'));
    showRestoreDialogVisible.value = false;
    await fetchRecords();
  } catch (e) {}
  restoring.value = false;
}

async function handleExecuteRestore(row) {
  try {
    await executeRestore(row.id);
    ElMessage.success(t('audit_archive_page.messages.restore_executed'));
    await fetchRestoreRequests();
    await fetchDashboard();
  } catch (e) {}
}

async function handleCancelRestore(row) {
  try {
    await ElMessageBox.confirm(t('audit_archive_page.confirm_cancel_restore'), t('actions.confirm'));
    await cancelRestore(row.id);
    ElMessage.success(t('audit_archive_page.messages.cancelled'));
    await fetchRestoreRequests();
  } catch (e) {}
}

async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredRestores();
    ElMessage.success(res.data.message || t('audit_archive_page.messages.process_done'));
    await fetchDashboard();
  } catch (e) {}
  processing.value = false;
}

function tierLabel(tier) { return tierLabels.value[tier] || tier; }
function tierColor(tier) { return { hot: 'success', warm: 'warning', cold: 'primary', frozen: 'danger' }[tier] || 'info'; }
function rsStatusType(s) { return { pending: 'warning', restoring: 'primary', available: 'success', expired: 'info', cancelled: 'default' }[s] || 'info'; }
function rsStatusLabel(s) { return restoreStatusLabels.value[s] || s; }
</script>
