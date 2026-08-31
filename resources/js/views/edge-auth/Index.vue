<template>
  <div class="edge-auth-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t('edge_auth_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('edge_auth_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.nodes }}</div>
          <div class="stat-label">{{ t('edge_auth_page.stat_total_nodes') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.activeNodes }}</div>
          <div class="stat-label">{{ t('edge_auth_page.stat_active_nodes') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.healthyNodes === stats.activeNodes ? 'stat-success' : 'stat-warning'">
            {{ stats.healthyNodes }}/{{ stats.activeNodes }}
          </div>
          <div class="stat-label">{{ t('edge_auth_page.stat_healthy_nodes') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalTokens ? (stats.totalTokens / 1000).toFixed(0) + 'K' : 0 }}</div>
          <div class="stat-label">{{ t('edge_auth_page.stat_tokens_used') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span class="font-semibold">{{ t('edge_auth_page.section_nodes') }}</span>
          <el-button type="primary" size="small" @click="showCreateNode">
            <el-icon><Plus /></el-icon> {{ t('edge_auth_page.register_node') }}
          </el-button>
        </div>
      </template>
      <el-table :data="nodes" stripe v-loading="nodesLoading">
        <el-table-column :label="t('edge_auth_page.col_name')" prop="name" min-width="140" />
        <el-table-column :label="t('edge_auth_page.col_node_id')" width="200">
          <template #default="{ row }">
            <span class="font-mono text-sm">{{ row.node_id }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('edge_auth_page.col_type')" prop="node_type" width="90" />
        <el-table-column :label="t('edge_auth_page.col_region')" prop="region" width="120" />
        <el-table-column :label="t('edge_auth_page.col_geo')" width="140">
          <template #default="{ row }">
            <span v-if="row.geo_allowed?.length">{{ row.geo_allowed.join(', ') }}</span>
            <span v-else class="text-muted">{{ t('edge_auth_page.geo_unrestricted') }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('edge_auth_page.col_status')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('edge_auth_page.col_heartbeat')" width="160">
          <template #default="{ row }">
            <span :class="isHealthy(row.last_heartbeat_at) ? 'text-success' : 'text-danger'">
              {{ row.last_heartbeat_at ? formatTime(row.last_heartbeat_at) : t('edge_auth_page.heartbeat_never') }}
            </span>
          </template>
        </el-table-column>
        <el-table-column :label="t('edge_auth_page.col_created_at')" width="160">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="loadNodes"
        />
      </div>
    </el-card>

    <!-- 注册节点对话框 -->
    <el-dialog v-model="createVisible" :title="t('edge_auth_page.dialog_register_title')" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('edge_auth_page.label_name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('edge_auth_page.ph_name')" />
        </el-form-item>
        <el-form-item :label="t('edge_auth_page.label_node_type')" prop="node_type">
          <el-select v-model="form.node_type" style="width:100%">
            <el-option v-for="opt in nodeTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('edge_auth_page.label_region')">
          <el-input v-model="form.region" :placeholder="t('edge_auth_page.ph_region')" />
        </el-form-item>
        <el-form-item :label="t('edge_auth_page.label_geo')">
          <el-select v-model="form.geo_allowed" multiple filterable style="width:100%" :placeholder="t('edge_auth_page.ph_geo')">
            <el-option v-for="opt in geoOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('edge_auth_page.register') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Plus } from '@element-plus/icons-vue';
import edgeApi from '@/api/edgeAuth';

const { t, locale } = useI18n();

const loading = ref(false);
const submitting = ref(false);
const nodesLoading = ref(false);

const stats = ref({ nodes: 0, activeNodes: 0, healthyNodes: 0, totalTokens: 0, totalLimit: 0 });

const nodes = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const createVisible = ref(false);
const formRef = ref(null);
const form = reactive({ name: '', node_type: 'cloudflare', region: '', geo_allowed: [] });

const nodeTypeOptions = computed(() => [
  { value: 'cloudflare', label: t('edge_auth_page.node_type_cloudflare') },
  { value: 'akamai', label: t('edge_auth_page.node_type_akamai') },
  { value: 'fastly', label: t('edge_auth_page.node_type_fastly') },
  { value: 'custom', label: t('edge_auth_page.node_type_custom') },
]);

const geoOptions = computed(() => [
  { value: 'CN', label: t('edge_auth_page.geo_cn') },
  { value: 'US', label: t('edge_auth_page.geo_us') },
  { value: 'EU', label: t('edge_auth_page.geo_eu') },
  { value: 'APAC', label: t('edge_auth_page.geo_apac') },
  { value: 'ME', label: t('edge_auth_page.geo_me') },
  { value: 'AF', label: t('edge_auth_page.geo_af') },
  { value: 'LATAM', label: t('edge_auth_page.geo_latam') },
]);

const rules = computed(() => ({
  name: [{ required: true, message: t('edge_auth_page.rules.name_required'), trigger: 'blur' }],
}));

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await edgeApi.dashboard();
    stats.value = res.data;
  } finally {
    loading.value = false;
  }
  loadNodes();
}

async function loadNodes() {
  nodesLoading.value = true;
  try {
    const res = await edgeApi.listNodes({ page: pagination.current_page });
    nodes.value = res.data.data || [];
    Object.assign(pagination, res.data);
  } finally {
    nodesLoading.value = false;
  }
}

function showCreateNode() {
  form.name = '';
  form.node_type = 'cloudflare';
  form.region = '';
  form.geo_allowed = [];
  createVisible.value = true;
}

async function handleCreate() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await edgeApi.registerNode(form);
    ElMessage.success(t('edge_auth_page.messages.node_registered'));
    createVisible.value = false;
    loadNodes();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

function statusLabel(status) {
  const map = {
    active: t('edge_auth_page.status_active'),
    inactive: t('edge_auth_page.status_inactive'),
  };
  return map[status] || status;
}

function isHealthy(lastHeartbeat) {
  if (!lastHeartbeat) return false;
  const diff = Date.now() - new Date(lastHeartbeat).getTime();
  return diff < 10 * 60 * 1000; // 10分钟内
}

function formatTime(time) {
  if (!time) return '—';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(time).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.edge-auth-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #0f172a; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.text-success { color: #67C23A; }
.text-danger { color: #F56C6C; }
.text-muted { color: #C0C4CC; }
</style>
