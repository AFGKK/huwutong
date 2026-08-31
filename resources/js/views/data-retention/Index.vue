<template>
  <div class="data-retention-page">
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">{{ t(`${P}.title`) }}</h2>
        <p class="page-desc text-secondary">{{ t(`${P}.subtitle`) }}</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ t(`${P}.refresh`) }}
        </el-button>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.active`) }}</div>
          <div class="stat-value">{{ dashboard?.policies?.active || 0 }}<small class="text-secondary"> / {{ dashboard?.policies?.total || 0 }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.exec_7d`) }}</div>
          <div class="stat-value">{{ dashboard?.execution_stats?.total || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.cleaned_30d`) }}</div>
          <div class="stat-value">{{ formatNumber(dashboard?.estimated_monthly_cleaned || 0) }}<small>{{ t(`${P}.records_unit`) }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.failed`) }}</div>
          <div class="stat-value text-danger">{{ dashboard?.execution_stats?.failed || 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.policies`)" name="policies">
          <div class="mb-2 flex">
            <el-button size="small" @click="handleSyncPolicies" :loading="syncing">
              <el-icon class="mr-1"><Download /></el-icon>{{ t(`${P}.sync`) }}
            </el-button>
            <el-button size="small" @click="handleDryRun">
              <el-icon class="mr-1"><Search /></el-icon>{{ t(`${P}.dry_run`) }}
            </el-button>
            <el-button size="small" type="danger" plain @click="handleApplyCleanup">
              <el-icon class="mr-1"><Delete /></el-icon>{{ t(`${P}.cleanup`) }}
            </el-button>
          </div>
          <el-table :data="policies" border stripe size="small" :loading="policyLoading">
            <el-table-column prop="key" :label="t(`${P}.cols.key`)" width="140" />
            <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="160" />
            <el-table-column prop="category" :label="t(`${P}.cols.category`)" width="100">
              <template #default="{ row }">
                <el-tag :color="categoryColor(row.category)" size="small" effect="dark">{{ row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="table_name" :label="t(`${P}.cols.table`)" width="150" />
            <el-table-column prop="retention_days" :label="t(`${P}.cols.days`)" width="100" />
            <el-table-column prop="action" :label="t(`${P}.cols.action`)" width="100">
              <template #default="{ row }">
                <el-tag :type="actionTag(row.action)" size="small">{{ actionLabel(row.action) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="archive_enabled" :label="t(`${P}.cols.archive`)" width="70">
              <template #default="{ row }">
                <el-tag :type="row.archive_enabled ? 'success' : 'info'" size="small">{{ row.archive_enabled ? t(`${P}.yes`) : t(`${P}.no`) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="is_active" :label="t(`${P}.cols.status`)" width="70">
              <template #default="{ row }">
                <el-switch v-model="row.is_active" @change="handleToggle(row)" size="small" />
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.executions`)" name="executions">
          <el-table :data="executions" border stripe size="small" :loading="execLoading" max-height="500">
            <el-table-column prop="created_at" :label="t(`${P}.cols.time`)" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="policy_key" :label="t(`${P}.cols.policy`)" width="140" />
            <el-table-column prop="action" :label="t(`${P}.cols.action_short`)" width="80" />
            <el-table-column prop="status" :label="t(`${P}.cols.status`)" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="total_records" :label="t(`${P}.cols.total`)" width="70" />
            <el-table-column prop="affected_records" :label="t(`${P}.cols.affected`)" width="70" />
            <el-table-column prop="batch_count" :label="t(`${P}.cols.batches`)" width="60" />
            <el-table-column prop="duration_ms" :label="t(`${P}.cols.duration`)" width="90">
              <template #default="{ row }">{{ row.duration_ms }}ms</template>
            </el-table-column>
            <el-table-column prop="is_dry_run" :label="t(`${P}.cols.preview`)" width="60">
              <template #default="{ row }">
                <el-tag :type="row.is_dry_run ? 'warning' : 'info'" size="small">{{ row.is_dry_run ? t(`${P}.yes`) : t(`${P}.no`) }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.storage`)" name="storage">
          <el-descriptions :column="2" border size="small" class="mb-4">
            <el-descriptions-item :label="t(`${P}.storage.monthly_data`)">{{ storageStats?.estimated_monthly_data_gb }} GB</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.storage.monthly_cost`)">${{ storageStats?.estimated_monthly_cost }}</el-descriptions-item>
          </el-descriptions>
          <h4 class="mb-2">{{ t(`${P}.storage.tiers`) }}</h4>
          <el-table :data="tierList" border stripe size="small">
            <el-table-column prop="label" :label="t(`${P}.cols.tier`)" min-width="200" />
            <el-table-column prop="tier" :label="t(`${P}.cols.tier_id`)" width="120" />
            <el-table-column prop="cost_per_gb" :label="t(`${P}.cols.cost_gb`)" width="120">
              <template #default="{ row }">${{ row.cost_per_gb }}</template>
            </el-table-column>
            <el-table-column prop="retrieval_time" :label="t(`${P}.cols.retrieval`)" width="150" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Download, Search, Delete } from '@element-plus/icons-vue';
import {
  getDataRetentionDashboard, getDataRetentionPolicies,
  updateDataRetentionPolicy, syncDataRetentionPolicies,
  runDataRetentionCleanup, getDataRetentionExecutions,
  getDataRetentionStorageStats,
} from '../../api/dataRetention';

const { t, locale } = useI18n();
const P = 'data_retention_page';

const loading = ref(false);
const activeTab = ref('policies');
const dashboard = ref(null);
const policies = ref([]);
const policyLoading = ref(false);
const syncing = ref(false);
const executions = ref([]);
const execLoading = ref(false);
const storageStats = ref(null);

const tierList = computed(() => {
  const tiers = storageStats.value?.tiers || {};
  return Object.values(tiers);
});

function formatNumber(n) {
  if (locale.value?.startsWith('zh')) {
    if (n >= 10000) return (n / 10000).toFixed(1) + t(`${P}.wan`);
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
  } else {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
  }
  return String(n);
}

function formatTime(time) {
  return time ? time.replace('T', ' ').substring(0, 19) : '-';
}

function categoryColor(cat) {
  const map = { audit: '#0f172a', security: '#e6a23c', operation: '#67c23a', notification: '#909399', performance: '#f56c6c' };
  return map[cat] || '#909399';
}

function actionTag(a) {
  return a === 'archive' ? 'warning' : a === 'delete' ? 'danger' : 'info';
}

function actionLabel(a) {
  const key = `${P}.actions.${a}`;
  const translated = t(key);
  return translated === key ? a : translated;
}

function statusTag(s) {
  return s === 'completed' ? 'success' : s === 'failed' ? 'danger' : s === 'running' ? 'warning' : 'info';
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, statsRes] = await Promise.all([
      getDataRetentionDashboard(),
      getDataRetentionStorageStats(),
    ]);
    dashboard.value = dashRes.data;
    storageStats.value = statsRes.data;
  } catch (e) {
    ElMessage.error(t(`${P}.messages.load_failed`));
  } finally {
    loading.value = false;
  }
}

async function loadPolicies() {
  policyLoading.value = true;
  try {
    const res = await getDataRetentionPolicies();
    policies.value = res.data || [];
  } catch (e) {
    ElMessage.error(t(`${P}.messages.policies_failed`));
  } finally {
    policyLoading.value = false;
  }
}

async function loadExecutions() {
  execLoading.value = true;
  try {
    const res = await getDataRetentionExecutions();
    executions.value = res.data?.items || [];
  } catch (e) {
    // silencio
  } finally {
    execLoading.value = false;
  }
}

async function handleToggle(row) {
  try {
    await updateDataRetentionPolicy(row.id, { is_active: row.is_active });
    ElMessage.success(row.is_active ? t(`${P}.messages.enabled`) : t(`${P}.messages.disabled`));
  } catch (e) {
    row.is_active = !row.is_active;
    ElMessage.error(t(`${P}.messages.update_failed`));
  }
}

async function handleSyncPolicies() {
  syncing.value = true;
  try {
    const res = await syncDataRetentionPolicies();
    ElMessage.success(res.message);
    await loadPolicies();
  } catch (e) {
    ElMessage.error(t(`${P}.messages.sync_failed`));
  } finally {
    syncing.value = false;
  }
}

async function handleDryRun() {
  try {
    const res = await runDataRetentionCleanup({ dry_run: true });
    ElMessage.success(t(`${P}.messages.dry_done`, { n: res.data?.total_affected || 0 }));
    await loadExecutions();
  } catch (e) {
    ElMessage.error(t(`${P}.messages.dry_failed`));
  }
}

async function handleApplyCleanup() {
  try {
    await ElMessageBox.confirm(
      t(`${P}.confirm_cleanup`),
      t(`${P}.confirm_title`),
      { confirmButtonText: t(`${P}.confirm_run`), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    const res = await runDataRetentionCleanup({ dry_run: false });
    ElMessage.success(t(`${P}.messages.cleanup_done`, { n: res.data?.total_affected || 0 }));
    await loadExecutions();
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t(`${P}.messages.cleanup_failed`));
  }
}

const tabLoadMap = { policies: loadPolicies, executions: loadExecutions };
watch(() => activeTab.value, (tab) => { if (tabLoadMap[tab]) tabLoadMap[tab](); });

onMounted(() => {
  refreshAll();
  loadPolicies();
});
</script>

<style scoped>
.data-retention-page { padding: 16px; }
.page-title { margin: 0; font-size: 20px; font-weight: 600; }
.page-desc { margin: 4px 0 0; font-size: 13px; }
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.stat-value small { font-size: 13px; font-weight: 400; }
.text-danger { color: #f56c6c; }
.text-secondary { color: #909399; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mr-1 { margin-right: 4px; }
.flex { display: flex; gap: 8px; align-items: center; }
</style>
