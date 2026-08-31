<template>
  <div class="serverless-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        {{ t('serverless_auth_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('serverless_auth_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.total }}</div>
          <div class="stat-label">{{ t('serverless_auth_page.stats.total_functions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active }}</div>
          <div class="stat-label">{{ t('serverless_auth_page.stats.active_functions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalInvocations }}</div>
          <div class="stat-label">{{ t('serverless_auth_page.stats.total_invocations') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalLimit ? (stats.totalInvocations / stats.totalLimit * 100).toFixed(1) + '%' : '—' }}</div>
          <div class="stat-label">{{ t('serverless_auth_page.stats.quota_usage') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 云函数列表 -->
    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span class="font-semibold">{{ t('serverless_auth_page.card_title') }}</span>
          <el-button type="primary" size="small" @click="showCreateFunction">
            <el-icon><Plus /></el-icon> {{ t('serverless_auth_page.btn_register_function') }}
          </el-button>
        </div>
      </template>
      <el-table :data="functions" stripe v-loading="funcLoading">
        <el-table-column :label="t('serverless_auth_page.col_name')" prop="name" min-width="140" />
        <el-table-column :label="t('serverless_auth_page.col_function_id')" width="200">
          <template #default="{ row }">
            <span class="font-mono text-sm">{{ row.function_id }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('serverless_auth_page.col_runtime')" prop="runtime" width="80" />
        <el-table-column :label="t('serverless_auth_page.col_qps_limit')" prop="qps_limit" width="80" align="center" />
        <el-table-column :label="t('serverless_auth_page.col_timeout')" prop="timeout_seconds" width="80" align="center" />
        <el-table-column :label="t('serverless_auth_page.col_monthly_quota')" width="100" align="center">
          <template #default="{ row }">{{ (row.monthly_invocation_limit / 1000).toFixed(0) }}K</template>
        </el-table-column>
        <el-table-column :label="t('serverless_auth_page.col_invocations')" width="80" align="center">
          <template #default="{ row }">{{ row.invocations_used || 0 }}</template>
        </el-table-column>
        <el-table-column :label="t('serverless_auth_page.col_status')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('serverless_auth_page.col_actions')" width="120" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="warning" @click="handleGenerateToken(row)">{{ t('serverless_auth_page.btn_generate_token') }}</el-button>
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
    <el-dialog v-model="createVisible" :title="t('serverless_auth_page.create_dialog.title')" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-form-item :label="t('serverless_auth_page.col_name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('serverless_auth_page.create_dialog.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('serverless_auth_page.col_runtime')" prop="runtime">
          <el-select v-model="form.runtime" style="width:100%">
            <el-option v-for="opt in runtimeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('serverless_auth_page.create_dialog.label_qps_limit')">
          <el-input-number v-model="form.qps_limit" :min="1" :max="10000" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('serverless_auth_page.create_dialog.label_monthly_quota')">
          <el-input-number v-model="form.monthly_invocation_limit" :min="0" :step="10000" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('serverless_auth_page.create_dialog.label_timeout')">
          <el-input-number v-model="form.timeout_seconds" :min="1" :max="300" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('serverless_auth_page.create_dialog.btn_register') }}</el-button>
      </template>
    </el-dialog>

    <!-- Token 结果 -->
    <el-dialog v-model="tokenVisible" :title="t('serverless_auth_page.token_dialog.title')" width="500px">
      <template v-if="tokenResult">
        <el-alert :title="t('serverless_auth_page.token_dialog.generated')" type="success" show-icon style="margin-bottom:16px" />
        <el-descriptions :column="1" border>
          <el-descriptions-item :label="t('serverless_auth_page.token_dialog.label_token')">
            <span class="font-mono text-sm break-all">{{ tokenResult.token }}</span>
          </el-descriptions-item>
          <el-descriptions-item :label="t('serverless_auth_page.token_dialog.label_expires')">{{ t('serverless_auth_page.token_dialog.expires_seconds', { n: tokenResult.expires_in }) }}</el-descriptions-item>
          <el-descriptions-item :label="t('serverless_auth_page.token_dialog.label_function_id')">{{ tokenResult.function_id }}</el-descriptions-item>
        </el-descriptions>
        <el-button type="primary" style="margin-top:12px" @click="copyToken">{{ t('serverless_auth_page.token_dialog.btn_copy') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Monitor, Refresh, Plus } from '@element-plus/icons-vue';
import serverlessApi from '@/api/serverlessAuth';

const { t } = useI18n();

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

const runtimeOptions = [
  { label: 'Node.js', value: 'nodejs' },
  { label: 'Python', value: 'python' },
  { label: 'Go', value: 'go' },
  { label: 'Java', value: 'java' },
  { label: '.NET', value: 'dotnet' },
  { label: 'Custom', value: 'custom' },
];

const statusLabels = computed(() => ({
  active: t('serverless_auth_page.status.active'),
  inactive: t('serverless_auth_page.status.inactive'),
}));

const rules = computed(() => ({
  name: [{ required: true, message: t('serverless_auth_page.validation.name_required') }],
}));

function statusLabel(status) {
  return statusLabels.value[status] || status;
}

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
    ElMessage.success(t('serverless_auth_page.messages.register_success'));
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
    ElMessage.error(t('serverless_auth_page.messages.generate_token_failed'));
  }
}

function copyToken() {
  if (tokenResult.value?.token) {
    navigator.clipboard.writeText(tokenResult.value.token).then(() => {
      ElMessage.success(t('serverless_auth_page.messages.copied'));
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
.stat-primary { color: #0f172a; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.break-all { word-break: break-all; }
</style>
