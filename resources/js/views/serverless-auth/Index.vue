<template>
  <div class="serverless-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        云函数授权 · Serverless
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
          <div class="stat-value stat-primary">{{ stats.total }}</div>
          <div class="stat-label">函数总数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active }}</div>
          <div class="stat-label">活跃函数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalInvocations }}</div>
          <div class="stat-label">总调用次数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalLimit ? (stats.totalInvocations / stats.totalLimit * 100).toFixed(1) + '%' : '—' }}</div>
          <div class="stat-label">配额使用率</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 云函数列表 -->
    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span class="font-semibold">云函数管理</span>
          <el-button type="primary" size="small" @click="showCreateFunction">
            <el-icon><Plus /></el-icon> 注册函数
          </el-button>
        </div>
      </template>
      <el-table :data="functions" stripe v-loading="funcLoading">
        <el-table-column label="名称" prop="name" min-width="140" />
        <el-table-column label="函数 ID" width="200">
          <template #default="{ row }">
            <span class="font-mono text-sm">{{ row.function_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="运行时" prop="runtime" width="80" />
        <el-table-column label="QPS 限制" prop="qps_limit" width="80" align="center" />
        <el-table-column label="超时(秒)" prop="timeout_seconds" width="80" align="center" />
        <el-table-column label="月度配额" width="100" align="center">
          <template #default="{ row }">{{ (row.monthly_invocation_limit / 1000).toFixed(0) }}K</template>
        </el-table-column>
        <el-table-column label="已调用" width="80" align="center">
          <template #default="{ row }">{{ row.invocations_used || 0 }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="warning" @click="handleGenerateToken(row)">生成Token</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="loadFunctions"
        />
      </div>
    </el-card>

    <!-- 注册函数对话框 -->
    <el-dialog v-model="createVisible" title="注册云函数" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="函数名称" />
        </el-form-item>
        <el-form-item label="运行时" prop="runtime">
          <el-select v-model="form.runtime" style="width:100%">
            <el-option label="Node.js" value="nodejs" />
            <el-option label="Python" value="python" />
            <el-option label="Go" value="go" />
            <el-option label="Java" value="java" />
            <el-option label=".NET" value="dotnet" />
            <el-option label="Custom" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="QPS 限制">
          <el-input-number v-model="form.qps_limit" :min="1" :max="10000" style="width:100%" />
        </el-form-item>
        <el-form-item label="月度调用配额">
          <el-input-number v-model="form.monthly_invocation_limit" :min="0" :step="10000" style="width:100%" />
        </el-form-item>
        <el-form-item label="超时(秒)">
          <el-input-number v-model="form.timeout_seconds" :min="1" :max="300" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">注册</el-button>
      </template>
    </el-dialog>

    <!-- Token 结果 -->
    <el-dialog v-model="tokenVisible" title="短时授权 Token" width="500px">
      <template v-if="tokenResult">
        <el-alert title="Token 已生成" type="success" show-icon style="margin-bottom:16px" />
        <el-descriptions :column="1" border>
          <el-descriptions-item label="Token">
            <span class="font-mono text-sm break-all">{{ tokenResult.token }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="过期时间">{{ tokenResult.expires_in }} 秒</el-descriptions-item>
          <el-descriptions-item label="函数 ID">{{ tokenResult.function_id }}</el-descriptions-item>
        </el-descriptions>
        <el-button type="primary" style="margin-top:12px" @click="copyToken">复制 Token</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Monitor, Refresh, Plus } from '@element-plus/icons-vue';
import serverlessApi from '@/api/serverlessAuth';

const loading = ref(false);
const submitting = ref(false);
const funcLoading = ref(false);

// 统计
const stats = ref({ total: 0, active: 0, totalInvocations: 0, totalLimit: 0 });

// 函数列表
const functions = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 创建
const createVisible = ref(false);
const formRef = ref(null);
const form = reactive({ name: '', runtime: 'nodejs', qps_limit: 10, monthly_invocation_limit: 100000, timeout_seconds: 30 });
const rules = { name: [{ required: true, message: '请输入名称' }] };

// Token
const tokenVisible = ref(false);
const tokenResult = ref(null);

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await serverlessApi.dashboard();
    stats.value = res.data;
  } finally {
    loading.value = false;
  }
  loadFunctions();
}

async function loadFunctions() {
  funcLoading.value = true;
  try {
    const res = await serverlessApi.listFunctions({ page: pagination.current_page });
    functions.value = res.data.data || [];
    Object.assign(pagination, res.data);
  } finally {
    funcLoading.value = false;
  }
}

function showCreateFunction() {
  form.name = '';
  form.runtime = 'nodejs';
  form.qps_limit = 10;
  form.monthly_invocation_limit = 100000;
  form.timeout_seconds = 30;
  createVisible.value = true;
}

async function handleCreate() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await serverlessApi.registerFunction(form);
    ElMessage.success('云函数注册成功');
    createVisible.value = false;
    loadFunctions();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

async function handleGenerateToken(func) {
  try {
    const res = await serverlessApi.generateToken(func.id);
    tokenResult.value = res.data;
    tokenVisible.value = true;
  } catch {
    ElMessage.error('生成Token失败');
  }
}

function copyToken() {
  if (tokenResult.value?.token) {
    navigator.clipboard.writeText(tokenResult.value.token).then(() => {
      ElMessage.success('已复制到剪贴板');
    });
  }
}
</script>

<style scoped>
.serverless-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-primary { color: #409EFF; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.break-all { word-break: break-all; }
</style>
