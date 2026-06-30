<template>
  <div class="secret-manager">
    <!-- 健康状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">主密钥状态</div>
            <div class="stat-value">
              <el-tag v-if="health.has_current_key" type="success" size="large">活跃</el-tag>
              <el-tag v-else type="danger" size="large">未初始化</el-tag>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">凭据总数 / 活跃</div>
            <div class="stat-value">{{ health.total_secrets }} / {{ health.active_secrets }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">7日内过期</div>
            <div class="stat-value">
              <el-tag v-if="health.expiring_secrets_7d > 0" type="warning" size="large">
                {{ health.expiring_secrets_7d }}
              </el-tag>
              <span v-else>0</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">已过期</div>
            <div class="stat-value">
              <el-tag v-if="health.expired_secrets > 0" type="danger" size="large">
                {{ health.expired_secrets }}
              </el-tag>
              <span v-else>0</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 标签页 -->
    <el-tabs v-model="activeTab">
      <!-- 凭据列表 -->
      <el-tab-pane label="凭据管理" name="secrets">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" :model="filters" size="small">
            <el-form-item>
              <el-input v-model="filters.search" placeholder="搜索名称或 slug" clearable
                        @clear="fetchSecrets" @keyup.enter="fetchSecrets" />
            </el-form-item>
            <el-form-item>
              <el-select v-model="filters.type" placeholder="类型" clearable @change="fetchSecrets">
                <el-option v-for="t in secretTypes" :key="t.id" :label="t.name" :value="t.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="filters.status" placeholder="状态" clearable @change="fetchSecrets">
                <el-option label="活跃" value="active" />
                <el-option label="已吊销" value="revoked" />
                <el-option label="已过期" value="expired" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchSecrets">查询</el-button>
            </el-form-item>
          </el-form>
          <div>
            <el-button type="success" @click="openCreateDialog">
              <el-icon><Plus /></el-icon>新建凭据
            </el-button>
          </div>
        </div>

        <el-table :data="secrets" v-loading="loading" stripe>
          <el-table-column prop="name" label="名称" min-width="140" />
          <el-table-column prop="slug" label="Slug" min-width="120" />
          <el-table-column prop="type" label="类型" width="90">
            <template #default="{ row }">
              <el-tag :type="typeTag(row.type)" size="small">{{ row.type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="tenant_id" label="租户" width="70" />
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'active'" type="success" size="small">活跃</el-tag>
              <el-tag v-else-if="row.status === 'revoked'" type="danger" size="small">吊销</el-tag>
              <el-tag v-else type="warning" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="expires_at" label="过期时间" width="170">
            <template #default="{ row }">
              <span v-if="row.expires_at">{{ row.expires_at }}</span>
              <span v-else class="text-gray-400">永不过期</span>
            </template>
          </el-table-column>
          <el-table-column prop="last_used_at" label="最后使用" width="170" />
          <el-table-column label="操作" width="260" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewSecret(row)">查看</el-button>
              <el-button size="small" type="warning" @click="openRotateDialog(row)">轮换</el-button>
              <el-button v-if="row.status === 'active'" size="small" type="danger"
                         @click="confirmRevoke(row)">吊销</el-button>
              <el-button v-else size="small" type="info"
                         @click="confirmRestore(row)">恢复</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="flex justify-center mt-3">
          <el-pagination v-if="total > 0" background layout="prev, pager, next"
                         :total="total" :current-page="currentPage" :page-size="perPage"
                         @current-change="onPageChange" />
        </div>
      </el-tab-pane>

      <!-- 主密钥管理 -->
      <el-tab-pane label="主密钥" name="master-keys">
        <div class="mb-3">
          <el-button type="primary" @click="generateMasterKey">
            <el-icon><Plus /></el-icon>生成新主密钥
          </el-button>
          <el-button type="warning" @click="confirmRotateMasterKey" class="ml-2">
            <el-icon><Refresh /></el-icon>轮换主密钥
          </el-button>
        </div>
        <el-alert title="主密钥是密钥管理的根信任锚点。轮换主密钥将重新加密所有已存储的凭据，此操作不可撤销。" type="info" :closable="false" show-icon class="mb-3" />
        <el-table :data="masterKeys" v-loading="masterLoading" stripe>
          <el-table-column prop="key_id" label="密钥 ID" width="140" />
          <el-table-column prop="label" label="名称" min-width="160" />
          <el-table-column prop="algorithm" label="算法" width="100" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.is_current" type="success" size="small">当前</el-tag>
              <el-tag v-else-if="row.status === 'deprecated'" type="warning" size="small">已弃用</el-tag>
              <el-tag v-else :type="row.status === 'active' ? 'info' : 'danger'" size="small">
                {{ row.status === 'revoked' ? '已吊销' : row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="rotated_at" label="轮换时间" width="170" />
          <el-table-column prop="expires_at" label="过期时间" width="170" />
          <el-table-column prop="created_at" label="创建时间" width="170" />
        </el-table>
      </el-tab-pane>

      <!-- 访问审计 -->
      <el-tab-pane label="访问审计" name="logs">
        <div class="mb-3">
          <el-select v-model="logFilters.secret_id" placeholder="筛选凭据" clearable filterable
                     @change="fetchLogs" size="small" class="w-60">
            <el-option v-for="s in secrets" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </div>
        <el-table :data="auditLogs" v-loading="logsLoading" stripe>
          <el-table-column prop="action" label="操作" width="90">
            <template #default="{ row }">
              <el-tag :type="actionTag(row.action)" size="small">{{ actionLabel(row.action) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="secret.name" label="凭据" min-width="120" />
          <el-table-column prop="accessed_by" label="访问来源" min-width="140" />
          <el-table-column prop="ip_address" label="IP" width="130" />
          <el-table-column prop="context" label="上下文" min-width="120">
            <template #default="{ row }">
              <code class="text-xs" v-if="row.context">{{ JSON.stringify(row.context) }}</code>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="时间" width="170" />
        </el-table>
        <div class="flex justify-center mt-3">
          <el-pagination v-if="logTotal > 0" background layout="prev, pager, next"
                         :total="logTotal" :current-page="logCurrentPage" :page-size="logPerPage"
                         @current-change="onLogPageChange" />
        </div>
      </el-tab-pane>

      <!-- 健康状态详情 -->
      <el-tab-pane label="系统健康" name="health">
        <el-descriptions title="Secret Manager 健康状态" :column="2" border>
          <el-descriptions-item label="主密钥就绪">
            <el-tag :type="health.has_current_key ? 'success' : 'danger'">
              {{ health.has_current_key ? '是' : '否' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="当前密钥 ID">{{ health.current_key_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="加密算法">{{ health.key_algorithm || '-' }}</el-descriptions-item>
          <el-descriptions-item label="密钥创建时间">{{ health.current_key_created || '-' }}</el-descriptions-item>
          <el-descriptions-item label="密钥过期时间">{{ health.current_key_expires || '-' }}</el-descriptions-item>
          <el-descriptions-item label="凭据总数">{{ health.total_secrets }}</el-descriptions-item>
          <el-descriptions-item label="活跃凭据">{{ health.active_secrets }}</el-descriptions-item>
          <el-descriptions-item label="7日内过期">{{ health.expiring_secrets_7d }}</el-descriptions-item>
          <el-descriptions-item label="已过期">{{ health.expired_secrets }}</el-descriptions-item>
        </el-descriptions>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建 / 编辑凭据 Dialog -->
    <el-dialog v-model="createDialog" :title="editingSecret ? '编辑凭据' : '新建凭据'" width="520px">
      <el-form :model="secretForm" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="租户 ID" prop="tenant_id">
          <el-input-number v-model="secretForm.tenant_id" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="凭据名称" prop="name">
          <el-input v-model="secretForm.name" placeholder="例如：Stripe 生产密钥" />
        </el-form-item>
        <el-form-item label="Slug" prop="slug">
          <el-input v-model="secretForm.slug" placeholder="例如：stripe_secret_key" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="secretForm.type" style="width: 100%">
            <el-option v-for="t in secretTypes" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="凭据值" prop="value">
          <el-input v-model="secretForm.value" type="textarea" :rows="3"
                    placeholder="待加密存储的敏感值" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="secretForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker v-model="secretForm.expires_at" type="datetime" placeholder="留空则默认两年"
                          style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog = false">取消</el-button>
        <el-button type="primary" @click="submitSecret" :loading="submitting">提交</el-button>
      </template>
    </el-dialog>

    <!-- 查看凭据明文 Dialog -->
    <el-dialog v-model="viewDialog" title="查看凭据明文" width="500px">
      <el-alert title="出于安全考虑，查看凭证明文前需要确认操作。" type="warning" show-icon class="mb-3" />
      <div v-if="viewValue">
        <el-input v-model="viewValue" type="textarea" :rows="4" readonly />
        <div class="mt-2 text-xs text-gray-400">
          <el-button size="small" @click="copyValue">复制到剪贴板</el-button>
        </div>
      </div>
      <div v-else-if="viewLoading" class="text-center py-4">
        <el-icon class="is-loading" size="24"><Loading /></el-icon>
        <p class="mt-2">解密中...</p>
      </div>
      <template #footer>
        <el-button @click="viewDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 轮换 Dialog -->
    <el-dialog v-model="rotateDialog" title="轮换凭据" width="480px">
      <p class="mb-2 text-sm text-gray-600">正在轮换: <strong>{{ rotatingSecret?.name }}</strong> ({{ rotatingSecret?.slug }})</p>
      <el-input v-model="rotateValue" type="textarea" :rows="3" placeholder="输入新凭据值" />
      <template #footer>
        <el-button @click="rotateDialog = false">取消</el-button>
        <el-button type="warning" @click="submitRotate" :loading="rotateLoading">确认轮换</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Loading } from '@element-plus/icons-vue';
import api from '@/api/secret-manager';

const activeTab = ref('secrets');

// 健康状态
const health = reactive({
  has_current_key: false,
  current_key_id: null,
  total_secrets: 0,
  active_secrets: 0,
  expiring_secrets_7d: 0,
  expired_secrets: 0,
  key_algorithm: null,
  current_key_created: null,
  current_key_expires: null,
});

// 凭据管理
const loading = ref(false);
const secrets = ref([]);
const total = ref(0);
const currentPage = ref(1);
const perPage = ref(20);
const secretTypes = ref([]);

const filters = reactive({
  search: '',
  type: '',
  status: '',
});

function fetchSecrets() {
  loading.value = true;
  const params = { page: currentPage.value, per_page: perPage.value };
  if (filters.type) params.type = filters.type;
  if (filters.status) params.status = filters.status;
  api.list(params).then(res => {
    secrets.value = res.data.data || [];
    total.value = res.data.total || 0;
  }).finally(() => loading.value = false);
}

function onPageChange(page) {
  currentPage.value = page;
  fetchSecrets();
}

// 创建凭据
const createDialog = ref(false);
const editingSecret = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const secretForm = reactive({
  tenant_id: 1,
  name: '',
  slug: '',
  type: 'api_key',
  value: '',
  description: '',
  expires_at: null,
});

const formRules = {
  tenant_id: [{ required: true, message: '请填写租户 ID', trigger: 'blur' }],
  name: [{ required: true, message: '请输入凭据名称', trigger: 'blur' }],
  slug: [{ required: true, message: '请输入 Slug', trigger: 'blur' }],
  value: [{ required: true, message: '请输入凭据值', trigger: 'blur' }],
};

function openCreateDialog() {
  editingSecret.value = null;
  secretForm.tenant_id = 1;
  secretForm.name = '';
  secretForm.slug = '';
  secretForm.type = 'api_key';
  secretForm.value = '';
  secretForm.description = '';
  secretForm.expires_at = null;
  createDialog.value = true;
}

function submitSecret() {
  formRef.value.validate(valid => {
    if (!valid) return;
    submitting.value = true;
    api.create({ ...secretForm, expires_at: secretForm.expires_at || undefined })
      .then(() => {
        ElMessage.success('凭据创建成功');
        createDialog.value = false;
        fetchSecrets();
      })
      .catch(err => {
        ElMessage.error(err.response?.data?.errors?.slug?.[0] || '创建失败');
      })
      .finally(() => submitting.value = false);
  });
}

// 查看凭据明文
const viewDialog = ref(false);
const viewValue = ref('');
const viewLoading = ref(false);

function viewSecret(row) {
  viewDialog.value = true;
  viewValue.value = '';
  viewLoading.value = true;
  api.show(row.id)
    .then(res => {
      viewValue.value = res.data.data?.value || '无法解密';
    })
    .catch(() => {
      ElMessage.error('解密失败，凭据可能已吊销');
      viewValue.value = '解密失败';
    })
    .finally(() => viewLoading.value = false);
}

function copyValue() {
  navigator.clipboard.writeText(viewValue.value).then(() => {
    ElMessage.success('已复制');
  });
}

// 轮换
const rotateDialog = ref(false);
const rotatingSecret = ref(null);
const rotateValue = ref('');
const rotateLoading = ref(false);

function openRotateDialog(row) {
  rotatingSecret.value = row;
  rotateValue.value = '';
  rotateDialog.value = true;
}

function submitRotate() {
  if (!rotateValue.value) {
    ElMessage.warning('请输入新凭据值');
    return;
  }
  rotateLoading.value = true;
  api.rotate(rotatingSecret.value.id, rotateValue.value)
    .then(() => {
      ElMessage.success('凭据已轮换');
      rotateDialog.value = false;
      fetchSecrets();
    })
    .catch(() => ElMessage.error('轮换失败'))
    .finally(() => rotateLoading.value = false);
}

// 吊销
function confirmRevoke(row) {
  ElMessageBox.confirm(`确定吊销凭据 "${row.name}"？此操作不可撤销。`, '确认吊销', {
    confirmButtonText: '确定吊销',
    cancelButtonText: '取消',
    type: 'warning',
  }).then(() => {
    api.revoke(row.id).then(() => {
      ElMessage.success('已吊销');
      fetchSecrets();
    });
  }).catch(() => {});
}

// 恢复
function confirmRestore(row) {
  api.restore(row.id).then(() => {
    ElMessage.success('已恢复');
    fetchSecrets();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || '恢复失败');
  });
}

// 主密钥管理
const masterKeys = ref([]);
const masterLoading = ref(false);

function fetchMasterKeys() {
  masterLoading.value = true;
  api.masterKeys().then(res => {
    masterKeys.value = res.data.data || [];
  }).finally(() => masterLoading.value = false);
}

function generateMasterKey() {
  ElMessageBox.prompt('为此主密钥输入一个标签（可选）：', '生成新主密钥', {
    confirmButtonText: '生成',
    cancelButtonText: '取消',
    inputPattern: /.*/,
  }).then(({ value }) => {
    api.generateMasterKey(value || '').then(res => {
      ElMessage.success(`主密钥已生成: ${res.data.data?.key_id}`);
      fetchMasterKeys();
      fetchHealth();
    });
  }).catch(() => {});
}

function confirmRotateMasterKey() {
  ElMessageBox.confirm(
    '确定轮换主密钥？<br>这将重新加密所有存储的凭据。<br><strong>此操作不可撤销。</strong>',
    '轮换主密钥',
    {
      confirmButtonText: '确认轮换',
      cancelButtonText: '取消',
      type: 'warning',
      dangerouslyUseHTMLString: true,
    }
  ).then(() => {
    api.rotateMasterKey().then(res => {
      ElMessage.success(res.data.message || '主密钥已轮换');
      fetchMasterKeys();
      fetchHealth();
    });
  }).catch(() => {});
}

// 审计日志
const auditLogs = ref([]);
const logsLoading = ref(false);
const logTotal = ref(0);
const logCurrentPage = ref(1);
const logPerPage = ref(30);
const logFilters = reactive({
  secret_id: '',
});

function fetchLogs() {
  logsLoading.value = true;
  const params = { page: logCurrentPage.value, per_page: logPerPage.value };
  if (logFilters.secret_id) {
    params.secret_id = logFilters.secret_id;
  }
  api.logs(logFilters.secret_id || null, params)
    .then(res => {
      auditLogs.value = res.data.data || [];
      logTotal.value = res.data.total || 0;
    })
    .finally(() => logsLoading.value = false);
}

function onLogPageChange(page) {
  logCurrentPage.value = page;
  fetchLogs();
}

// 健康状态
function fetchHealth() {
  api.health().then(res => {
    const d = res.data.data || {};
    Object.assign(health, d);
  }).catch(() => {});
}

// 类型列表
function fetchTypes() {
  api.types().then(res => {
    secretTypes.value = res.data.data || [];
  }).catch(() => {});
}

// 工具方法
function typeTag(type) {
  const map = { api_key: 'primary', password: 'warning', certificate: 'success', token: 'info' };
  return map[type] || 'default';
}

function actionTag(action) {
  const map = { create: 'success', access: 'primary', rotate: 'warning', revoke: 'danger', restore: 'info' };
  return map[action] || 'default';
}

function actionLabel(action) {
  const map = { create: '创建', access: '访问', rotate: '轮换', revoke: '吊销', restore: '恢复' };
  return map[action] || action;
}

onMounted(() => {
  fetchHealth();
  fetchSecrets();
  fetchTypes();
});
</script>

<style scoped>
.secret-manager {
  padding: 8px;
}
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.ml-2 { margin-left: 8px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.w-60 { width: 240px; }
.text-xs { font-size: 12px; }
.text-gray-400 { color: #9ca3af; }
.text-gray-600 { color: #6b7280; }
.text-sm { font-size: 13px; }
.py-4 { padding: 16px 0; }
.stat-item { text-align: center; }
.stat-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
.stat-value { font-size: 20px; font-weight: 600; }
</style>
