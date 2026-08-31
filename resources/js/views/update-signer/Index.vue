<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">{{ t(`${P}.title`) }}</h2>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.pass_rate`) }}</div>
            <div class="text-2xl font-bold mt-1" :class="passRateColor">{{ dashboard.pass_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.pending_rollbacks`) }}</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_rollbacks || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.active_gray_releases`) }}</div>
            <div class="text-2xl font-bold mt-1 text-blue-500">{{ dashboard.active_gray_releases || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.total_verifications`) }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_verifications || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 签名验证 -->
        <el-tab-pane :label="t(`${P}.tabs.verification`)" name="verification">
          <div class="mb-3 flex gap-2">
            <el-select v-model="vFilters.verified" :placeholder="t('sdk_integrity_page.filters.result')" clearable size="small" style="width:120px">
              <el-option v-for="opt in verificationResultOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchVerifications">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="verifications" v-loading="loadingV" stripe style="width:100%">
            <el-table-column prop="update_package_id" :label="colLabels.package_id" width="80" />
            <el-table-column :label="colLabels.result" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.verified ? 'success' : 'danger'" size="small">{{ row.verified ? verificationResultLabels.passed : verificationResultLabels.failed }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="algorithm" :label="colLabels.algorithm" width="100" />
            <el-table-column prop="sdk_instance_id" :label="colLabels.sdk_instance" width="160" show-overflow-tooltip />
            <el-table-column prop="file_hash" :label="colLabels.file_hash" width="140" show-overflow-tooltip />
            <el-table-column prop="error_message" :label="colLabels.error_message" min-width="200" show-overflow-tooltip />
            <el-table-column prop="created_at" :label="colLabels.time" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t(`${P}.list_total`, { total: vPagination.total }) }}</span>
            <el-pagination v-model:current-page="vPagination.page" :page-size="vPagination.per_page" :total="vPagination.total" layout="prev, pager, next" small @current-change="fetchVerifications" />
          </div>
        </el-tab-pane>

        <!-- 回滚管理 -->
        <el-tab-pane :label="t(`${P}.tabs.rollback`)" name="rollback">
          <div class="mb-3 flex gap-2">
            <el-select v-model="rFilters.status" :placeholder="t('sdk_integrity_page.filters.status')" clearable size="small" style="width:140px">
              <el-option v-for="opt in rollbackStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchRollbacks">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="rollbacks" v-loading="loadingR" stripe style="width:100%">
            <el-table-column prop="from_version" :label="colLabels.from_version" width="120" />
            <el-table-column prop="to_version" :label="colLabels.to_version" width="120" />
            <el-table-column prop="trigger_type" :label="colLabels.trigger_type" width="120" />
            <el-table-column :label="colLabels.status" width="100">
              <template #default="{ row }">
                <el-tag :type="rbStatusType(row.status)" size="small">{{ rbStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="reason" :label="colLabels.reason" min-width="220" show-overflow-tooltip />
            <el-table-column prop="affected_instances" :label="colLabels.affected_instances" width="80" align="center" />
            <el-table-column prop="created_at" :label="colLabels.created_at" width="180" />
            <el-table-column :label="colLabels.actions" width="160" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">{{ t(`${P}.actions.approve`) }}</el-button>
                <el-button v-if="row.status === 'approved'" size="small" type="primary" @click="handleExecute(row)">{{ t(`${P}.actions.execute`) }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t(`${P}.list_total`, { total: rPagination.total }) }}</span>
            <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRollbacks" />
          </div>
        </el-tab-pane>

        <!-- 灰度发布 -->
        <el-tab-pane :label="t(`${P}.tabs.gray`)" name="gray">
          <div class="mb-3 flex gap-2">
            <el-select v-model="gFilters.status" :placeholder="t('sdk_integrity_page.filters.status')" clearable size="small" style="width:140px">
              <el-option v-for="opt in grayStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button size="small" @click="fetchGrayReleases">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="grayReleases" v-loading="loadingG" stripe style="width:100%">
            <el-table-column prop="update_package_id" :label="colLabels.package_id" width="80" />
            <el-table-column prop="strategy" :label="colLabels.strategy" width="100" />
            <el-table-column prop="current_stage" :label="colLabels.current_stage" width="100" />
            <el-table-column prop="current_percentage" :label="colLabels.percentage" width="80" align="center">
              <template #default="{ row }">{{ row.current_percentage }}%</template>
            </el-table-column>
            <el-table-column :label="colLabels.status" width="100">
              <template #default="{ row }">
                <el-tag :type="gsStatusType(row.status)" size="small">{{ gsStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="target_regions" :label="colLabels.target_regions" width="150" show-overflow-tooltip>
              <template #default="{ row }">{{ row.target_regions?.join(', ') || '—' }}</template>
            </el-table-column>
            <el-table-column prop="stage_started_at" :label="colLabels.stage_started_at" width="180" />
            <el-table-column prop="stage_ends_at" :label="colLabels.stage_ends_at" width="180" />
            <el-table-column :label="colLabels.actions" width="180" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleStartGray(row)">{{ t(`${P}.actions.start`) }}</el-button>
                <el-button v-if="row.status === 'running'" size="small" type="success" @click="handleAdvanceGray(row)">{{ t(`${P}.actions.advance`) }}</el-button>
                <el-button v-if="row.status === 'running'" size="small" type="warning" @click="handlePauseGray(row)">{{ t(`${P}.actions.pause`) }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t(`${P}.list_total`, { total: gPagination.total }) }}</span>
            <el-pagination v-model:current-page="gPagination.page" :page-size="gPagination.per_page" :total="gPagination.total" layout="prev, pager, next" small @current-change="fetchGrayReleases" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getSignerDashboard, getVerificationLogs, getRollbacks, getGrayReleases,
  approveRollback, executeRollback, startGrayRelease, advanceGrayRelease, pauseGrayRelease,
} from '../../api/updateSigner';

const P = 'update_signer_page';
const { t } = useI18n();

const rollbackStatusKeys = ['pending', 'approved', 'rejected', 'executed', 'failed'];
const grayStatusKeys = ['pending', 'running', 'paused', 'completed'];

// ── 状态 ──
const loadingV = ref(false);
const loadingR = ref(false);
const loadingG = ref(false);
const activeTab = ref('verification');

const dashboard = ref({});
const verifications = ref([]);
const rollbacks = ref([]);
const grayReleases = ref([]);

const vPagination = ref({ page: 1, per_page: 20, total: 0 });
const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const gPagination = ref({ page: 1, per_page: 20, total: 0 });

const vFilters = ref({ verified: '' });
const rFilters = ref({ status: '' });
const gFilters = ref({ status: '' });

// ── 计算属性 ──
const passRateColor = computed(() => {
  const rate = dashboard.value.pass_rate || 100;
  if (rate >= 95) return 'text-green-500';
  if (rate >= 80) return 'text-yellow-500';
  return 'text-red-500';
});

const colLabels = computed(() => ({
  package_id: t(`${P}.cols.package_id`),
  result: t('sdk_integrity_page.cols.result'),
  algorithm: t('public_key_page.cols.algorithm'),
  sdk_instance: t('sdk_integrity_page.cols.sdk_instance'),
  file_hash: t('updates_page.field_file_hash'),
  error_message: t(`${P}.cols.error_message`),
  time: t(`${P}.cols.time`),
  from_version: t(`${P}.cols.from_version`),
  to_version: t(`${P}.cols.to_version`),
  trigger_type: t(`${P}.cols.trigger_type`),
  status: t('sdk_integrity_page.cols.status'),
  reason: t(`${P}.cols.reason`),
  affected_instances: t(`${P}.cols.affected_instances`),
  created_at: t('sdk_integrity_page.cols.created_at'),
  actions: t('sdk_integrity_page.cols.actions'),
  strategy: t(`${P}.cols.strategy`),
  current_stage: t(`${P}.cols.current_stage`),
  percentage: t(`${P}.cols.percentage`),
  target_regions: t(`${P}.cols.target_regions`),
  stage_started_at: t(`${P}.cols.stage_started_at`),
  stage_ends_at: t(`${P}.cols.stage_ends_at`),
}));

const verificationResultLabels = computed(() => ({
  passed: t('sdk_integrity_page.result.passed'),
  failed: t('sdk_integrity_page.result.failed'),
}));

const verificationResultOptions = computed(() => [
  { label: t('sdk_integrity_page.result.passed'), value: 'true' },
  { label: t('sdk_integrity_page.result.failed'), value: 'false' },
]);

const rollbackStatusLabels = computed(() =>
  Object.fromEntries(
    [...rollbackStatusKeys, 'rolled_forward'].map((key) => [key, t(`${P}.rollback_status.${key}`)])
  )
);

const rollbackStatusOptions = computed(() =>
  rollbackStatusKeys.map((value) => ({
    value,
    label: t(`${P}.rollback_status.${value}`),
  }))
);

const grayStatusLabels = computed(() =>
  Object.fromEntries(
    [...grayStatusKeys, 'rolled_back'].map((key) => [key, t(`${P}.gray_status.${key}`)])
  )
);

const grayStatusOptions = computed(() =>
  grayStatusKeys.map((value) => ({
    value,
    label: t(`${P}.gray_status.${value}`),
  }))
);

// ── 方法 ──
onMounted(async () => {
  await fetchDashboard();
  await fetchVerifications();
});

async function fetchDashboard() {
  try { const res = await getSignerDashboard(); dashboard.value = res.data.data || {}; } catch (e) {}
}

async function fetchVerifications() {
  loadingV.value = true;
  try {
    const params = { ...vFilters.value, page: vPagination.value.page, per_page: vPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getVerificationLogs(params);
    const data = res.data.data;
    verifications.value = data.data || [];
    vPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingV.value = false;
}

async function fetchRollbacks() {
  loadingR.value = true;
  try {
    const params = { ...rFilters.value, page: rPagination.value.page, per_page: rPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getRollbacks(params);
    const data = res.data.data;
    rollbacks.value = data.data || [];
    rPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}

async function fetchGrayReleases() {
  loadingG.value = true;
  try {
    const params = { ...gFilters.value, page: gPagination.value.page, per_page: gPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getGrayReleases(params);
    const data = res.data.data;
    grayReleases.value = data.data || [];
    gPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingG.value = false;
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(
      t(`${P}.confirm.approve_rollback`, { from: row.from_version, to: row.to_version }),
      t('actions.confirm')
    );
    await approveRollback(row.id);
    ElMessage.success(t(`${P}.messages.approved`));
    await fetchRollbacks();
    await fetchDashboard();
  } catch (e) {}
}

async function handleExecute(row) {
  try {
    await ElMessageBox.confirm(
      t(`${P}.confirm.execute_rollback`, { from: row.from_version, to: row.to_version }),
      t('actions.confirm'),
      { type: 'warning' }
    );
    await executeRollback(row.id);
    ElMessage.success(t(`${P}.messages.rollback_executed`));
    await fetchRollbacks();
    await fetchDashboard();
  } catch (e) {}
}

async function handleStartGray(row) {
  try {
    await startGrayRelease(row.id);
    ElMessage.success(t(`${P}.messages.gray_started`));
    await fetchGrayReleases();
    await fetchDashboard();
  } catch (e) {}
}

async function handleAdvanceGray(row) {
  try {
    const res = await advanceGrayRelease(row.id);
    ElMessage.success(res.data.message || t(`${P}.messages.gray_advanced`));
    await fetchGrayReleases();
    await fetchDashboard();
  } catch (e) {}
}

async function handlePauseGray(row) {
  try {
    await pauseGrayRelease(row.id);
    ElMessage.success(t(`${P}.messages.paused`));
    await fetchGrayReleases();
  } catch (e) {}
}

function rbStatusType(s) {
  return { pending: 'warning', approved: 'primary', rejected: 'danger', executed: 'success', failed: 'danger', rolled_forward: 'info' }[s] || 'info';
}
function rbStatusLabel(s) {
  return rollbackStatusLabels.value[s] || s;
}

function gsStatusType(s) {
  return { pending: 'info', running: 'success', paused: 'warning', completed: 'primary', rolled_back: 'danger' }[s] || 'info';
}
function gsStatusLabel(s) {
  return grayStatusLabels.value[s] || s;
}
</script>
