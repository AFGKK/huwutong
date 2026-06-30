<template>
  <div class="edge-auth-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        边缘计算授权 · AI Token 配额
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.nodes }}</div>
          <div class="stat-label">节点总数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.activeNodes }}</div>
          <div class="stat-label">活跃节点</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.healthyNodes === stats.activeNodes ? 'stat-success' : 'stat-warning'">
            {{ stats.healthyNodes }}/{{ stats.activeNodes }}
          </div>
          <div class="stat-label">健康节点</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalTokens ? (stats.totalTokens / 1000).toFixed(0) + 'K' : 0 }}</div>
          <div class="stat-label">已用 Token</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span class="font-semibold">边缘节点管理</span>
          <el-button type="primary" size="small" @click="showCreateNode">
            <el-icon><Plus /></el-icon> 注册节点
          </el-button>
        </div>
      </template>
      <el-table :data="nodes" stripe v-loading="nodesLoading">
        <el-table-column label="名称" prop="name" min-width="140" />
        <el-table-column label="节点 ID" width="200">
          <template #default="{ row }">
            <span class="font-mono text-sm">{{ row.node_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" prop="node_type" width="90" />
        <el-table-column label="区域" prop="region" width="120" />
        <el-table-column label="Geo 限制" width="140">
          <template #default="{ row }">
            <span v-if="row.geo_allowed?.length">{{ row.geo_allowed.join(', ') }}</span>
            <span v-else class="text-muted">无限制</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="心跳" width="160">
          <template #default="{ row }">
            <span :class="isHealthy(row.last_heartbeat_at) ? 'text-success' : 'text-danger'">
              {{ row.last_heartbeat_at ? formatTime(row.last_heartbeat_at) : '从未' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
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
    <el-dialog v-model="createVisible" title="注册边缘节点" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="节点名称" />
        </el-form-item>
        <el-form-item label="节点类型" prop="node_type">
          <el-select v-model="form.node_type" style="width:100%">
            <el-option label="Cloudflare" value="cloudflare" />
            <el-option label="Akamai" value="akamai" />
            <el-option label="Fastly" value="fastly" />
            <el-option label="自定义" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="区域">
          <el-input v-model="form.region" placeholder="如: ap-southeast-1" />
        </el-form-item>
        <el-form-item label="Geo 限制">
          <el-select v-model="form.geo_allowed" multiple filterable style="width:100%" placeholder="留空不限">
            <el-option label="中国" value="CN" />
            <el-option label="美国" value="US" />
            <el-option label="欧洲" value="EU" />
            <el-option label="亚太" value="APAC" />
            <el-option label="中东" value="ME" />
            <el-option label="非洲" value="AF" />
            <el-option label="拉美" value="LATAM" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">注册</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Plus } from '@element-plus/icons-vue';
import edgeApi from '@/api/edgeAuth';

const loading = ref(false);
const submitting = ref(false);
const nodesLoading = ref(false);

const stats = ref({ nodes: 0, activeNodes: 0, healthyNodes: 0, totalTokens: 0, totalLimit: 0 });

const nodes = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const createVisible = ref(false);
const formRef = ref(null);
const form = reactive({ name: '', node_type: 'cloudflare', region: '', geo_allowed: [] });
const rules = { name: [{ required: true, message: '请输入名称' }] };

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
    ElMessage.success('边缘节点注册成功');
    createVisible.value = false;
    loadNodes();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

function isHealthy(lastHeartbeat) {
  if (!lastHeartbeat) return false;
  const diff = Date.now() - new Date(lastHeartbeat).getTime();
  return diff < 10 * 60 * 1000; // 10分钟内
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
.stat-primary { color: #409EFF; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.text-success { color: #67C23A; }
.text-danger { color: #F56C6C; }
.text-muted { color: #C0C4CC; }
</style>
