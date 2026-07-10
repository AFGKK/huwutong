<template>
  <div class="bi-export-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_connections }}</div>
            <div class="stat-label">连接数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_datasets }}</div>
            <div class="stat-label">数据集</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total_syncs }}</div>
            <div class="stat-label">同步总次数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.recent_syncs }}</div>
            <div class="stat-label">今日同步</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作工具栏 -->
    <el-card class="mb-4">
      <div class="flex items-center justify-between">
        <span class="text-lg font-medium">BI 数据仓库连接</span>
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon> 新建连接
        </el-button>
      </div>
    </el-card>

    <!-- 连接列表 -->
    <el-card v-loading="loading">
      <el-empty v-if="!loading && connections.length === 0" description="暂无连接" />
      <div v-else class="connection-grid">
        <div v-for="conn in connections" :key="conn.id" class="connection-card">
          <div class="card-header">
            <span class="platform-icon">{{ platformIcon(conn.platform) }}</span>
            <span class="platform-name">{{ conn.name }}</span>
            <el-tag :type="conn.status === 'connected' ? 'success' : 'danger'" size="small">
              {{ conn.status === 'connected' ? '已连接' : '未连接' }}
            </el-tag>
          </div>
          <div class="card-body">
            <div class="info-row">
              <span class="label">平台:</span>
              <span class="value">{{ platformLabel(conn.platform) }}</span>
            </div>
            <div class="info-row">
              <span class="label">数据集:</span>
              <span class="value">{{ conn.datasets_count || 0 }} 个</span>
            </div>
            <div class="info-row">
              <span class="label">创建时间:</span>
              <span class="value">{{ formatDate(conn.created_at) }}</span>
            </div>
          </div>
          <div class="card-actions">
            <el-button size="small" @click="testConn(conn)">测试</el-button>
            <el-button size="small" @click="viewDatasets(conn)">数据集</el-button>
            <el-button size="small" type="danger" plain @click="confirmDelete(conn)">删除</el-button>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 新建连接对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建 BI 连接" width="520px" destroy-on-close>
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="createForm.name" placeholder="例: 生产数据导出" />
        </el-form-item>
        <el-form-item label="平台" prop="platform">
          <el-select v-model="createForm.platform" style="width:100%" @change="loadConfigTemplate">
            <el-option v-for="p in platforms" :key="p.key" :label="p.name" :value="p.key">
              <span>{{ p.icon }} {{ p.name }}</span>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item v-if="configFields.length > 0" label="配置" prop="config">
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
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>

    <!-- 数据集面板 -->
    <el-drawer v-model="showDatasetDrawer" :title="`数据集 - ${currentConn?.name}`" size="500px" destroy-on-close>
      <div class="mb-3">
        <el-button type="primary" size="small" @click="showCreateDataset = true">
          <el-icon><Plus /></el-icon> 新建数据集
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
              频率: {{ freqLabel(ds.sync_frequency) }}
            </div>
            <div class="mt-2 flex gap-2">
              <el-button size="small" type="primary" :loading="syncingId === ds.id" @click="handleSync(ds)">
                {{ syncingId === ds.id ? '同步中...' : '同步' }}
              </el-button>
              <el-button size="small" @click="viewLogs(ds)">日志</el-button>
              <el-button size="small" type="danger" plain @click="confirmDeleteDataset(ds)">删除</el-button>
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>
      <el-empty v-else description="暂无数据集" />

      <!-- 新建数据集 -->
      <el-dialog v-model="showCreateDataset" title="新建数据集" width="420px" append-to-body destroy-on-close>
        <el-form ref="dsFormRef" :model="dsForm" :rules="dsRules" label-width="100px">
          <el-form-item label="名称" prop="name">
            <el-input v-model="dsForm.name" placeholder="数据集名称" />
          </el-form-item>
          <el-form-item label="数据表" prop="source_table">
            <el-select v-model="dsForm.source_table" style="width:100%">
              <el-option label="License" value="licenses" />
              <el-option label="客户" value="customers" />
              <el-option label="订单" value="orders" />
              <el-option label="发票" value="invoices" />
              <el-option label="订阅" value="subscriptions" />
            </el-select>
          </el-form-item>
          <el-form-item label="同步频率" prop="sync_frequency">
            <el-select v-model="dsForm.sync_frequency" style="width:100%">
              <el-option label="手动" value="manual" />
              <el-option label="每小时" value="hourly" />
              <el-option label="每天" value="daily" />
              <el-option label="每周" value="weekly" />
              <el-option label="每月" value="monthly" />
            </el-select>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="showCreateDataset = false">取消</el-button>
          <el-button type="primary" :loading="submitting" @click="handleCreateDataset">创建</el-button>
        </template>
      </el-dialog>

      <!-- 同步日志 -->
      <el-dialog v-model="showLogDialog" title="同步日志" width="700px" append-to-body destroy-on-close>
        <el-table :data="syncLogs" stripe size="small" max-height="400">
          <el-table-column prop="created_at" label="时间" width="160" />
          <el-table-column prop="status" label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 'success' ? 'success' : row.status === 'running' ? 'warning' : 'danger'" size="small">
                {{ row.status === 'success' ? '成功' : row.status === 'running' ? '运行中' : row.status === 'partial' ? '部分' : '失败' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="total_records" label="总数" width="60" />
          <el-table-column prop="synced_records" label="成功" width="60" />
          <el-table-column prop="error_message" label="错误信息" min-width="200" show-overflow-tooltip />
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
import { ref, onMounted, reactive } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getPlatforms, getConfigTemplate, getStats,
  getConnections, createConnection, deleteConnection, testConnection,
  getDatasets, createDataset, deleteDataset, syncDataset, getSyncLogs,
} from '../../api/biExport';

const loading = ref(false);
const submitting = ref(false);
const syncingId = ref(null);
const connections = ref([]);
const platforms = ref([]);
const stats = reactive({ total_connections: 0, total_datasets: 0, total_syncs: 0, recent_syncs: 0 });
const showCreateDialog = ref(false);
const configFields = ref([]);
const createForm = reactive({ name: '', platform: '', config: {} });
const createRules = {
  name: [{ required: true, message: '请输入连接名称', trigger: 'blur' }],
  platform: [{ required: true, message: '请选择平台', trigger: 'change' }],
};

// 数据集
const showDatasetDrawer = ref(false);
const currentConn = ref(null);
const datasets = ref([]);
const showCreateDataset = ref(false);
const dsForm = reactive({ name: '', source_table: 'licenses', sync_frequency: 'manual' });
const dsRules = {
  name: [{ required: true, message: '请输入数据集名称', trigger: 'blur' }],
  source_table: [{ required: true, message: '请选择数据表', trigger: 'change' }],
};

// 日志
const showLogDialog = ref(false);
const syncLogs = ref([]);
const currentLogDs = ref(null);
const logPage = ref(1);
const logPagination = reactive({ total: 0, perPage: 20 });

function platformIcon(key) {
  return '📈';
}

function platformLabel(key) {
  return key;
}

function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function freqLabel(f) {
  const map = { manual: '手动', hourly: '每小时', daily: '每天', weekly: '每周', monthly: '每月' };
  return map[f] || f;
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
    ElMessage.error('加载连接列表失败');
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
    ElMessage.success('连接创建成功');
    showCreateDialog.value = false;
    createForm.name = '';
    createForm.platform = '';
    createForm.config = {};
    configFields.value = [];
    await loadConnections();
    await loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '创建失败');
  } finally {
    submitting.value = false;
  }
}

async function testConn(conn) {
  try {
    await testConnection(conn.id);
    ElMessage.success('连接测试成功');
    await loadConnections();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '连接测试失败');
  }
}

function confirmDelete(conn) {
  ElMessageBox.confirm(`确定删除连接「${conn.name}」？所有数据集将被一并删除。`, '确认', {
    confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    await deleteConnection(conn.id);
    ElMessage.success('已删除');
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
    ElMessage.success('数据集创建成功');
    showCreateDataset.value = false;
    dsForm.name = '';
    dsForm.source_table = 'licenses';
    dsForm.sync_frequency = 'manual';
    const { data } = await getDatasets(currentConn.value.id);
    datasets.value = data.data;
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '创建失败');
  } finally {
    submitting.value = false;
  }
}

function confirmDeleteDataset(ds) {
  ElMessageBox.confirm(`确定删除数据集「${ds.name}」？`, '确认', {
    confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    await deleteDataset(ds.id);
    ElMessage.success('已删除');
    const { data } = await getDatasets(currentConn.value.id);
    datasets.value = data.data;
  }).catch(() => {});
}

async function handleSync(ds) {
  syncingId.value = ds.id;
  try {
    const { data } = await syncDataset(ds.id);
    ElMessage.success(`同步完成，共 ${data.data.synced_records || 0} 条记录`);
    await loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '同步失败');
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
