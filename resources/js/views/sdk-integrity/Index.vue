<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">{{ t('sdk_integrity_page.title') }}</h2>
        <div class="flex gap-2">
          <el-button size="small" type="danger" @click="showBatchDestroy = true">{{ t('sdk_integrity_page.batch_disable') }}</el-button>
          <el-button size="small" @click="processExpired" :loading="processing">{{ t('sdk_integrity_page.process_expired') }}</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.stats.total_checks') }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_checks || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.stats.pass_rate') }}</div>
            <div class="text-2xl font-bold mt-1" :class="passRateColor">{{ dashboard.pass_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.stats.active_commands') }}</div>
            <div class="text-2xl font-bold mt-1" :class="activeCmdColor">{{ dashboard.active_commands || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.stats.unique_instances') }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.unique_instances || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <el-row :gutter="16" class="mt-2">
        <el-col :span="12">
          <el-card shadow="hover" class="mb-2">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.trend_24h') }}</div>
            <div class="mt-1">
              <el-tag size="small" type="success">{{ t('sdk_integrity_page.passed_count', { count: (dashboard.last_24h?.total || 0) - (dashboard.last_24h?.failed || 0) }) }}</el-tag>
              <el-tag v-if="dashboard.last_24h?.failed" size="small" type="danger" class="ml-1">{{ t('sdk_integrity_page.failed_count', { count: dashboard.last_24h?.failed }) }}</el-tag>
            </div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="hover" class="mb-2">
            <div class="text-xs text-gray-500">{{ t('sdk_integrity_page.by_language') }}</div>
            <div class="mt-1 flex flex-wrap gap-1">
              <el-tag v-for="(stats, lang) in dashboard.by_language" :key="lang" size="small">
                {{ t('sdk_integrity_page.lang_stat', { lang, total: stats.total, passed: stats.passed }) }}
              </el-tag>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs: 检查记录 / 停用命令 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 检查记录 -->
        <el-tab-pane :label="t('sdk_integrity_page.tabs.checks')" name="checks">
          <div class="mb-3 flex gap-2">
            <el-input v-model="checkFilters.sdk_instance_id" :placeholder="t('sdk_integrity_page.filters.sdk_instance_id_ph')" style="width:200px" size="small" />
            <el-select v-model="checkFilters.language" :placeholder="t('sdk_integrity_page.filters.language')" clearable size="small" style="width:120px">
              <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
            </el-select>
            <el-select v-model="checkFilters.passed" :placeholder="t('sdk_integrity_page.filters.result')" clearable size="small" style="width:120px">
              <el-option v-for="opt in resultOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchChecks">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="checks" v-loading="loadingChecks" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" :label="t('sdk_integrity_page.cols.sdk_instance')" width="180" show-overflow-tooltip />
            <el-table-column prop="language" :label="t('sdk_integrity_page.cols.language')" width="80" />
            <el-table-column prop="sdk_version" :label="t('sdk_integrity_page.cols.version')" width="100" />
            <el-table-column :label="t('sdk_integrity_page.cols.result')" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.passed ? 'success' : 'danger'" size="small">{{ row.passed ? t('sdk_integrity_page.result.passed') : t('sdk_integrity_page.result.failed') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="failed_files" :label="t('sdk_integrity_page.cols.failed_files')" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">
                <span v-if="row.failed_files?.length" class="text-red-500">{{ row.failed_files.join(', ') }}</span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column prop="checked_at" :label="t('sdk_integrity_page.cols.checked_at')" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t('sdk_integrity_page.list_total', { total: checkPagination.total }) }}</span>
            <el-pagination
              v-model:current-page="checkPagination.page"
              :page-size="checkPagination.per_page"
              :total="checkPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchChecks"
            />
          </div>
        </el-tab-pane>

        <!-- 停用命令 -->
        <el-tab-pane :label="t('sdk_integrity_page.tabs.commands')" name="commands">
          <div class="mb-3 flex gap-2">
            <el-select v-model="cmdFilters.status" :placeholder="t('sdk_integrity_page.filters.status')" clearable size="small" style="width:140px">
              <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchCommands">{{ t('actions.search') }}</el-button>
            <el-button size="small" type="primary" @click="showCreateCommand = true">{{ t('sdk_integrity_page.btn_create_command') }}</el-button>
          </div>
          <el-table :data="commands" v-loading="loadingCommands" stripe style="width:100%">
            <el-table-column prop="command_id" :label="t('sdk_integrity_page.cols.command_id')" width="200" show-overflow-tooltip />
            <el-table-column prop="sdk_instance_id" :label="t('sdk_integrity_page.cols.target_instance')" width="160" show-overflow-tooltip />
            <el-table-column prop="language" :label="t('sdk_integrity_page.cols.language')" width="70" />
            <el-table-column prop="destroy_mode" :label="t('sdk_integrity_page.cols.mode')" width="70">
              <template #default="{ row }">
                <el-tag :type="row.destroy_mode === 'hard' ? 'danger' : 'warning'" size="small">{{ row.destroy_mode }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="trigger_type" :label="t('sdk_integrity_page.cols.trigger_type')" width="150" />
            <el-table-column prop="status" :label="t('sdk_integrity_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="affected_count" :label="t('sdk_integrity_page.cols.affected_count')" width="80" align="center" />
            <el-table-column prop="reason" :label="t('sdk_integrity_page.cols.reason')" min-width="200" show-overflow-tooltip />
            <el-table-column prop="created_at" :label="t('sdk_integrity_page.cols.created_at')" width="180" />
            <el-table-column :label="t('sdk_integrity_page.cols.actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending' || row.status === 'dispatched'" size="small" type="warning" @click="handleCancel(row)">{{ t('actions.cancel') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t('sdk_integrity_page.list_total', { total: cmdPagination.total }) }}</span>
            <el-pagination
              v-model:current-page="cmdPagination.page"
              :page-size="cmdPagination.per_page"
              :total="cmdPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchCommands"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建停用命令对话框 -->
    <el-dialog v-model="showCreateCommand" :title="t('sdk_integrity_page.dialog_create_title')" width="500px">
      <el-form :model="cmdForm" label-width="120px">
        <el-form-item :label="t('sdk_integrity_page.form.sdk_instance_id')">
          <el-input v-model="cmdForm.sdk_instance_id" :placeholder="t('sdk_integrity_page.form.sdk_instance_id_ph')" />
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.language')">
          <el-select v-model="cmdForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.destroy_mode')">
          <el-radio-group v-model="cmdForm.destroy_mode">
            <el-radio value="soft">{{ t('sdk_integrity_page.form.soft_mode') }}</el-radio>
            <el-radio value="hard">{{ t('sdk_integrity_page.form.hard_mode') }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.reason')" required>
          <el-input v-model="cmdForm.reason" type="textarea" :rows="3" :placeholder="t('sdk_integrity_page.form.reason_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateCommand = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="danger" @click="handleIssueCommand" :loading="issuing">{{ t('sdk_integrity_page.btn_issue') }}</el-button>
      </template>
    </el-dialog>

    <!-- 批量停用对话框 -->
    <el-dialog v-model="showBatchDestroy" :title="t('sdk_integrity_page.dialog_batch_title')" width="500px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item :label="t('sdk_integrity_page.form.language')">
          <el-select v-model="batchForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.sdk_version')">
          <el-input v-model="batchForm.sdk_version" :placeholder="t('sdk_integrity_page.form.sdk_version_ph')" />
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.failed_only')">
          <el-switch v-model="batchForm.failed_only" />
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.date_before')">
          <el-date-picker v-model="batchForm.date_before" type="datetime" :placeholder="t('sdk_integrity_page.form.date_before_ph')" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.destroy_mode')">
          <el-radio-group v-model="batchForm.destroy_mode">
            <el-radio value="soft">{{ t('sdk_integrity_page.form.soft_short') }}</el-radio>
            <el-radio value="hard">{{ t('sdk_integrity_page.form.hard_short') }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('sdk_integrity_page.form.reason')" required>
          <el-input v-model="batchForm.reason" type="textarea" :rows="3" :placeholder="t('sdk_integrity_page.form.batch_reason_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchDestroy = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="danger" @click="handleBatchDestroy" :loading="batchLoading">{{ t('sdk_integrity_page.btn_batch_confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getIntegrityDashboard, getIntegrityChecks, getDestroyCommands,
  issueDestroyCommand, cancelDestroyCommand, batchDestroy, processExpiredCommands,
} from '../../api/sdkIntegrity';

const { t } = useI18n();

// ── 状态 ──
const loadingChecks = ref(false);
const loadingCommands = ref(false);
const processing = ref(false);
const issuing = ref(false);
const batchLoading = ref(false);
const activeTab = ref('checks');
const languages = ['php', 'node', 'python', 'go', 'java'];

const dashboard = ref({});
const checks = ref([]);
const commands = ref([]);

const checkPagination = ref({ page: 1, per_page: 20, total: 0 });
const cmdPagination = ref({ page: 1, per_page: 20, total: 0 });

const checkFilters = ref({ sdk_instance_id: '', language: '', passed: '' });
const cmdFilters = ref({ status: '' });

const showCreateCommand = ref(false);
const cmdForm = ref({
  sdk_instance_id: '', language: '', destroy_mode: 'soft', reason: '',
});

const showBatchDestroy = ref(false);
const batchForm = ref({
  language: '', sdk_version: '', failed_only: false, date_before: null,
  destroy_mode: 'soft', reason: '',
});

const statusKeys = ['pending', 'dispatched', 'confirmed', 'expired', 'cancelled'];

const resultOptions = computed(() => [
  { label: t('sdk_integrity_page.result.passed'), value: 'true' },
  { label: t('sdk_integrity_page.result.failed'), value: 'false' },
]);

const statusOptions = computed(() =>
  statusKeys.map((value) => ({
    value,
    label: t(`sdk_integrity_page.status.${value}`),
  }))
);

const statusLabels = computed(() =>
  Object.fromEntries(statusKeys.map((key) => [key, t(`sdk_integrity_page.status.${key}`)]))
);

// ── 计算属性 ──
const passRateColor = computed(() => {
  const rate = dashboard.value.pass_rate || 100;
  if (rate >= 95) return 'text-green-500';
  if (rate >= 80) return 'text-yellow-500';
  return 'text-red-500';
});
const activeCmdColor = computed(() => {
  const count = dashboard.value.active_commands || 0;
  return count > 0 ? 'text-red-500' : 'text-green-500';
});

// ── 方法 ──
onMounted(async () => {
  await fetchDashboard();
  await fetchChecks();
});

watch(activeTab, (tab) => {
  if (tab === 'commands') fetchCommands();
});

async function fetchDashboard() {
  try {
    const res = await getIntegrityDashboard();
    dashboard.value = res.data.data || {};
  } catch (e) { /* ignore */ }
}

async function fetchChecks() {
  loadingChecks.value = true;
  try {
    const params = { ...checkFilters.value, page: checkPagination.value.page, per_page: checkPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getIntegrityChecks(params);
    const data = res.data.data;
    checks.value = data.data || [];
    checkPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingChecks.value = false;
}

async function fetchCommands() {
  loadingCommands.value = true;
  try {
    const params = { ...cmdFilters.value, page: cmdPagination.value.page, per_page: cmdPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getDestroyCommands(params);
    const data = res.data.data;
    commands.value = data.data || [];
    cmdPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingCommands.value = false;
}

async function handleIssueCommand() {
  issuing.value = true;
  try {
    const payload = { ...cmdForm.value };
    Object.keys(payload).forEach(k => { if (!payload[k]) delete payload[k]; });
    await issueDestroyCommand(payload);
    ElMessage.success(t('sdk_integrity_page.messages.command_issued'));
    showCreateCommand.value = false;
    cmdForm.value = { sdk_instance_id: '', language: '', destroy_mode: 'soft', reason: '' };
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  issuing.value = false;
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm(
      t('sdk_integrity_page.confirm_cancel', { id: row.command_id.slice(0, 16) }),
      t('actions.confirm'),
      { type: 'warning' },
    );
    await cancelDestroyCommand(row.id);
    ElMessage.success(t('sdk_integrity_page.messages.command_cancelled'));
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function handleBatchDestroy() {
  batchLoading.value = true;
  try {
    const payload = { ...batchForm.value };
    if (payload.date_before) payload.date_before = payload.date_before.toISOString();
    Object.keys(payload).forEach(k => { if (payload[k] === '' || payload[k] === null) delete payload[k]; });
    const res = await batchDestroy(payload);
    ElMessage.success(res.data.message || t('sdk_integrity_page.messages.batch_issued'));
    showBatchDestroy.value = false;
    batchForm.value = { language: '', sdk_version: '', failed_only: false, date_before: null, destroy_mode: 'soft', reason: '' };
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  batchLoading.value = false;
}

async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredCommands();
    ElMessage.success(res.data.message || t('sdk_integrity_page.messages.process_done'));
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function statusType(s) {
  return { pending: 'info', dispatched: 'warning', confirmed: 'danger', expired: 'info', cancelled: 'default' }[s] || 'info';
}
function statusLabel(s) {
  return statusLabels.value[s] || s;
}
</script>
