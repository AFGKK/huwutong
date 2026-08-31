<template>
  <div class="bi-export-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_connections }}</div>
            <div class="stat-label">{{ t('bi_export_page.stats.connections') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_datasets }}</div>
            <div class="stat-label">{{ t('bi_export_page.stats.datasets') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_syncs }}</div>
            <div class="stat-label">{{ t('bi_export_page.stats.total_syncs') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.recent_syncs }}</div>
            <div class="stat-label">{{ t('bi_export_page.stats.recent_syncs') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作工具栏 -->
    <el-card class="mb-4">
      <div class="flex items-center justify-between">
        <span class="text-lg font-medium">{{ t('bi_export_page.toolbar.title') }}</span>
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon> {{ t('bi_export_page.toolbar.new_connection') }}
        </el-button>
      </div>
    </el-card>

    <!-- 连接列表 -->
    <el-card v-loading="loading">
      <el-empty v-if="!loading && connections.length === 0" :description="t('bi_export_page.empty.no_connections')" />
      <div v-else class="connection-grid">
        <div v-for="conn in connections" :key="conn.id" class="connection-card">
          <div class="card-header">
            <span class="platform-icon">{{ platformIcon(conn.platform) }}</span>
            <span class="platform-name">{{ conn.name }}</span>
            <el-tag :type="conn.status === 'connected' ? 'success' : 'danger'" size="small">
              {{ conn.status === 'connected' ? t('bi_export_page.connection.connected') : t('bi_export_page.connection.disconnected') }}
            </el-tag>
          </div>
          <div class="card-body">
            <div class="info-row">
              <span class="label">{{ t('bi_export_page.connection.platform') }}:</span>
              <span class="value">{{ platformLabel(conn.platform) }}</span>
            </div>
            <div class="info-row">
              <span class="label">{{ t('bi_export_page.connection.datasets') }}:</span>
              <span class="value">{{ t('bi_export_page.connection.datasets_count', { n: conn.datasets_count || 0 }) }}</span>
            </div>
            <div class="info-row">
              <span class="label">{{ t('bi_export_page.connection.created_at') }}:</span>
              <span class="value">{{ formatDate(conn.created_at) }}</span>
            </div>
          </div>
          <div class="card-actions">
            <el-button size="small" @click="testConn(conn)">{{ t('scim_page.actions.test') }}</el-button>
            <el-button size="small" @click="viewDatasets(conn)">{{ t('bi_export_page.row_actions.datasets') }}</el-button>
            <el-button size="small" type="danger" plain @click="confirmDelete(conn)">{{ t('actions.delete') }}</el-button>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 新建连接对话框 -->
    <el-dialog v-model="showCreateDialog" :title="t('bi_export_page.dialog.create_connection')" width="520px" destroy-on-close>
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item :label="t('bi_export_page.form.name')" prop="name">
          <el-input v-model="createForm.name" :placeholder="t('bi_export_page.form.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('bi_export_page.form.platform')" prop="platform">
          <el-select v-model="createForm.platform" style="width:100%" @change="loadConfigTemplate">
            <el-option v-for="p in platforms" :key="p.key" :label="p.name" :value="p.key">
              <span>{{ p.icon }} {{ p.name }}</span>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item v-if="configFields.length > 0" :label="t('bi_export_page.form.config')" prop="config">
          <div class="config-fields">
            <div v-for="field in configFields" :key="field.key" class="config-field-row">
              <label>{{ field.label }}<span v-if="field.required" class="text-red-500">*</span></label>
              <el-input
                v-if="field.type === 'password'"
                v-model="createForm.config[field.key]"
                :placeholder="field.placeholder"
                show-password
              />
              <el-input v-else v-model="createForm.config[field.key]" :placeholder="field.placeholder" />
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreate">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 数据集面板 -->
    <el-drawer
      v-model="showDatasetDrawer"
      :title="t('bi_export_page.dialog.datasets_title', { name: currentConn?.name })"
      size="500px"
      destroy-on-close
    >
      <div class="mb-3">
        <el-button type="primary" size="small" @click="showCreateDataset = true">
          <el-icon><Plus /></el-icon> {{ t('bi_export_page.dialog.create_dataset') }}
        </el-button>
      </div>

      <el-timeline v-if="datasets.length > 0">
        <el-timeline-item v-for="ds in datasets" :key="ds.id" :timestamp="formatDate(ds.created_at)" placement="top">
          <el-card shadow="hover">
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ ds.name }}</span>
              <el-tag size="small">{{ ds.source_table }}</el-tag>
            </div>
            <div class="text-sm text-gray-500 mt-1">
              {{ t('bi_export_page.form.frequency_label') }}: {{ freqLabel(ds.sync_frequency) }}
            </div>
            <div class="mt-2 flex gap-2">
              <el-button size="small" type="primary" :loading="syncingId === ds.id" @click="handleSync(ds)">
                {{ syncingId === ds.id ? t('actions.loading') : t('bi_export_page.row_actions.sync') }}
              </el-button>
              <el-button size="small" @click="viewLogs(ds)">{{ t('scim_page.actions.logs') }}</el-button>
              <el-button size="small" type="danger" plain @click="confirmDeleteDataset(ds)">{{ t('actions.delete') }}</el-button>
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>
      <el-empty v-else :description="t('bi_export_page.empty.no_datasets')" />

      <!-- 新建数据集 -->
      <el-dialog v-model="showCreateDataset" :title="t('bi_export_page.dialog.create_dataset')" width="420px" append-to-body destroy-on-close>
        <el-form ref="dsFormRef" :model="dsForm" :rules="dsRules" label-width="100px">
          <el-form-item :label="t('bi_export_page.form.name')" prop="name">
            <el-input v-model="dsForm.name" :placeholder="t('bi_export_page.form.dataset_name_ph')" />
          </el-form-item>
          <el-form-item :label="t('bi_export_page.form.source_table')" prop="source_table">
            <el-select v-model="dsForm.source_table" style="width:100%">
              <el-option v-for="opt in sourceTableOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('bi_export_page.form.sync_frequency')" prop="sync_frequency">
            <el-select v-model="dsForm.sync_frequency" style="width:100%">
              <el-option v-for="opt in frequencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="showCreateDataset = false">{{ t('actions.cancel') }}</el-button>
          <el-button type="primary" :loading="submitting" @click="handleCreateDataset">{{ t('actions.create') }}</el-button>
        </template>
      </el-dialog>

      <!-- 同步日志 -->
      <el-dialog v-model="showLogDialog" :title="t('bi_export_page.dialog.sync_logs')" width="700px" append-to-body destroy-on-close>
        <el-table :data="syncLogs" stripe size="small" max-height="400">
          <el-table-column prop="created_at" :label="t('bi_export_page.logs.time')" width="160" />
          <el-table-column prop="status" :label="t('bi_export_page.logs.status')" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 'success' ? 'success' : row.status === 'running' ? 'warning' : 'danger'" size="small">
                {{ logStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="total_records" :label="t('bi_export_page.logs.total')" width="60" />
          <el-table-column prop="synced_records" :label="t('bi_export_page.logs.success')" width="60" />
          <el-table-column prop="error_message" :label="t('bi_export_page.logs.error')" min-width="200" show-overflow-tooltip />
        </el-table>
        <div v-if="logPagination.total > logPagination.perPage" class="mt-3 text-center">
          <el-pagination
            v-model:current-page="logPage"
            :page-size="logPagination.perPage"
            :total="logPagination.total"
            layout="prev, pager, next"
            small
            @current-change="(p) => loadLogs(currentLogDs, p)"
          />
        </div>
      </el-dialog>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getPlatforms, getConfigTemplate, getStats,
  getConnections, createConnection, deleteConnection, testConnection,
  getDatasets, createDataset, deleteDataset, syncDataset, getSyncLogs,
} from '../../api/biExport';

const { t, locale } = useI18n();

const frequencyKeys = ['manual', 'hourly', 'daily', 'weekly', 'monthly'];
const sourceTableKeys = ['licenses', 'customers', 'orders', 'invoices', 'subscriptions'];

const frequencyOptions = computed(() =>
  frequencyKeys.map((value) => ({
    value,
    label: value === 'monthly'
      ? t('bi_export_page.frequencies.monthly')
      : t(`scim_page.frequencies.${value}`),
  }))
);

const freqLabels = computed(() =>
  Object.fromEntries(frequencyKeys.map((k) => [
    k,
    k === 'monthly' ? t('bi_export_page.frequencies.monthly') : t(`scim_page.frequencies.${k}`),
  ]))
);

const sourceTableOptions = computed(() =>
  sourceTableKeys.map((value) => ({
    value,
    label: t(`bi_export_page.source_tables.${value}`),
  }))
);

const logStatusLabels = computed(() => ({
  success: t('scim_page.log_status.success'),
  running: t('bi_export_page.log_status.running'),
  partial: t('scim_page.log_status.partial'),
  failed: t('bi_export_page.log_status.failed'),
}));

const createRules = computed(() => ({
  name: [{ required: true, message: t('bi_export_page.validation.name_required'), trigger: 'blur' }],
  platform: [{ required: true, message: t('bi_export_page.validation.platform_required'), trigger: 'change' }],
}));

const dsRules = computed(() => ({
  name: [{ required: true, message: t('bi_export_page.validation.dataset_name_required'), trigger: 'blur' }],
  source_table: [{ required: true, message: t('bi_export_page.validation.source_table_required'), trigger: 'change' }],
}));

const loading = ref(false);
const submitting = ref(false);
const syncingId = ref(null);
const connections = ref([]);
const platforms = ref([]);
const stats = reactive({ total_connections: 0, total_datasets: 0, total_syncs: 0, recent_syncs: 0 });
const showCreateDialog = ref(false);
const configFields = ref([]);
const createForm = reactive({ name: '', platform: '', config: {} });

// 数据集
const showDatasetDrawer = ref(false);
const currentConn = ref(null);
const datasets = ref([]);
const showCreateDataset = ref(false);
const dsForm = reactive({ name: '', source_table: 'licenses', sync_frequency: 'manual' });

// 日志
const showLogDialog = ref(false);
const syncLogs = ref([]);
const currentLogDs = ref(null);
const logPage = ref(1);
const logPagination = reactive({ total: 0, perPage: 20 });

function platformIcon() {
  return '';
}

function platformLabel(key) {
  return key;
}

function formatDate(date) {
  if (!date) return '';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return new Date(date).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function freqLabel(f) {
  return freqLabels.value[f] || f;
}

function logStatusLabel(status) {
  return logStatusLabels.value[status] || status;
}

async function loadStats() {
  try {
    const { data } = await getStats();
    Object.assign(stats, data.data);
  } catch { /* ignore */ }
}

async function loadConnections() {
  loading.value = true;
  try {
    const { data } = await getConnections();
    connections.value = data.data;
  } catch (e) {
    ElMessage.error(t('bi_export_page.messages.load_connections_failed'));
  } finally {
    loading.value = false;
  }
}

async function loadPlatforms() {
  try {
    const { data } = await getPlatforms();
    platforms.value = data.data;
  } catch { /* ignore */ }
}

async function loadConfigTemplate(platform) {
  if (!platform) { configFields.value = []; return; }
  try {
    const { data } = await getConfigTemplate(platform);
    configFields.value = data.data.fields || [];
    if (!createForm.config) createForm.config = {};
  } catch {
    configFields.value = [];
  }
}

async function handleCreate() {
  submitting.value = true;
  try {
    await createConnection({ ...createForm });
    ElMessage.success(t('bi_export_page.messages.connection_created'));
    showCreateDialog.value = false;
    createForm.name = '';
    createForm.platform = '';
    createForm.config = {};
    configFields.value = [];
    await loadConnections();
    await loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('bi_export_page.messages.create_failed'));
  } finally {
    submitting.value = false;
  }
}

async function testConn(conn) {
  try {
    await testConnection(conn.id);
    ElMessage.success(t('scim_page.messages.test_ok'));
    await loadConnections();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('scim_page.messages.test_failed'));
  }
}

function confirmDelete(conn) {
  ElMessageBox.confirm(t('bi_export_page.confirm.delete_connection', { name: conn.name }), t('actions.confirm'), {
    confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel'), type: 'warning',
  }).then(async () => {
    await deleteConnection(conn.id);
    ElMessage.success(t('bi_export_page.messages.deleted'));
    await loadConnections();
    await loadStats();
  }).catch(() => {});
}

async function viewDatasets(conn) {
  currentConn.value = conn;
  showDatasetDrawer.value = true;
  try {
    const { data } = await getDatasets(conn.id);
    datasets.value = data.data;
  } catch {
    datasets.value = [];
  }
}

async function handleCreateDataset() {
  submitting.value = true;
  try {
    await createDataset(currentConn.value.id, { ...dsForm });
    ElMessage.success(t('bi_export_page.messages.dataset_created'));
    showCreateDataset.value = false;
    dsForm.name = '';
    dsForm.source_table = 'licenses';
    dsForm.sync_frequency = 'manual';
    const { data } = await getDatasets(currentConn.value.id);
    datasets.value = data.data;
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('bi_export_page.messages.create_failed'));
  } finally {
    submitting.value = false;
  }
}

function confirmDeleteDataset(ds) {
  ElMessageBox.confirm(t('bi_export_page.confirm.delete_dataset', { name: ds.name }), t('actions.confirm'), {
    confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel'), type: 'warning',
  }).then(async () => {
    await deleteDataset(ds.id);
    ElMessage.success(t('bi_export_page.messages.deleted'));
    const { data } = await getDatasets(currentConn.value.id);
    datasets.value = data.data;
  }).catch(() => {});
}

async function handleSync(ds) {
  syncingId.value = ds.id;
  try {
    const { data } = await syncDataset(ds.id);
    ElMessage.success(t('bi_export_page.messages.sync_complete', { n: data.data.synced_records || 0 }));
    await loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('scim_page.messages.sync_failed'));
  } finally {
    syncingId.value = null;
  }
}

async function viewLogs(ds) {
  currentLogDs.value = ds;
  logPage.value = 1;
  showLogDialog.value = true;
  await loadLogs(ds, 1);
}

async function loadLogs(ds, page) {
  try {
    const { data } = await getSyncLogs(ds.id, { page });
    syncLogs.value = data.data.data;
    logPagination.total = data.data.total;
    logPagination.perPage = data.data.per_page;
  } catch {
    syncLogs.value = [];
  }
}

onMounted(() => {
  loadStats();
  loadConnections();
  loadPlatforms();
});
</script>

<style scoped>
.bi-export-page {
  padding: 20px;
}
.stat-item {
  text-align: center;
  padding: 8px 0;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--el-color-primary);
}
.stat-label {
  font-size: 14px;
  color: #909399;
  margin-top: 4px;
}
.connection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.connection-card {
  border: 1px solid var(--el-border-color-light);
  border-radius: 8px;
  padding: 16px;
  background: var(--el-bg-color-overlay);
  transition: box-shadow 0.2s;
}
.connection-card:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.platform-icon {
  font-size: 24px;
}
.platform-name {
  flex: 1;
  font-weight: 600;
}
.card-body {
  margin-bottom: 12px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 13px;
}
.info-row .label {
  color: #909399;
}
.card-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  border-top: 1px solid var(--el-border-color-lighter);
  padding-top: 12px;
}
.config-fields {
  width: 100%;
}
.config-field-row {
  margin-bottom: 12px;
}
.config-field-row label {
  display: block;
  margin-bottom: 4px;
  font-size: 13px;
}
.mb-3 {
  margin-bottom: 12px;
}
.mb-4 {
  margin-bottom: 16px;
}
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
</style>
