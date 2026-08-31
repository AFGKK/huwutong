<template>
  <div class="secret-manager">
    <!-- 健康状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t(`${P}.stats.master_key_status`) }}</div>
            <div class="stat-value">
              <el-tag v-if="health.has_current_key" type="success" size="large">{{ t(`${P}.stats.active`) }}</el-tag>
              <el-tag v-else type="danger" size="large">{{ t(`${P}.stats.not_initialized`) }}</el-tag>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t(`${P}.stats.total_active_secrets`) }}</div>
            <div class="stat-value">{{ health.total_secrets }} / {{ health.active_secrets }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t(`${P}.stats.expiring_7d`) }}</div>
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
            <div class="stat-label">{{ t(`${P}.stats.expired`) }}</div>
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
      <el-tab-pane :label="t(`${P}.tabs.secrets`)" name="secrets">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" :model="filters" size="small">
            <el-form-item>
              <el-input v-model="filters.search" :placeholder="t(`${P}.filters.search_ph`)" clearable
                        @clear="fetchSecrets" @keyup.enter="fetchSecrets" />
            </el-form-item>
            <el-form-item>
              <el-select v-model="filters.type" :placeholder="t(`${P}.filters.type`)" clearable @change="fetchSecrets">
                <el-option v-for="st in secretTypes" :key="st.id" :label="st.name" :value="st.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="filters.status" :placeholder="t(`${P}.filters.status`)" clearable @change="fetchSecrets">
                <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchSecrets">{{ t('actions.search') }}</el-button>
            </el-form-item>
          </el-form>
          <div>
            <el-button type="success" @click="openCreateDialog">
              <el-icon><Plus /></el-icon>{{ t(`${P}.create_secret`) }}
            </el-button>
          </div>
        </div>

        <el-table :data="secrets" v-loading="loading" stripe>
          <el-table-column prop="name" :label="t(`${P}.columns.name`)" min-width="140" />
          <el-table-column prop="slug" :label="t(`${P}.columns.slug`)" min-width="120" />
          <el-table-column prop="type" :label="t(`${P}.columns.type`)" width="90">
            <template #default="{ row }">
              <el-tag :type="typeTag(row.type)" size="small">{{ row.type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="tenant_id" :label="t(`${P}.columns.tenant`)" width="70" />
          <el-table-column :label="t(`${P}.columns.status`)" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'active'" type="success" size="small">{{ t(`${P}.status.active`) }}</el-tag>
              <el-tag v-else-if="row.status === 'revoked'" type="danger" size="small">{{ t(`${P}.status.revoked_short`) }}</el-tag>
              <el-tag v-else type="warning" size="small">{{ secretStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="expires_at" :label="t(`${P}.columns.expires_at`)" width="170">
            <template #default="{ row }">
              <span v-if="row.expires_at">{{ row.expires_at }}</span>
              <span v-else class="text-gray-400">{{ t(`${P}.never_expires`) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="last_used_at" :label="t(`${P}.columns.last_used_at`)" width="170" />
          <el-table-column :label="t(`${P}.columns.actions`)" width="260" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewSecret(row)">{{ t('actions.view') }}</el-button>
              <el-button size="small" type="warning" @click="openRotateDialog(row)">{{ t(`${P}.rotate`) }}</el-button>
              <el-button v-if="row.status === 'active'" size="small" type="danger"
                         @click="confirmRevoke(row)">{{ t(`${P}.revoke`) }}</el-button>
              <el-button v-else size="small" type="info"
                         @click="confirmRestore(row)">{{ t(`${P}.restore`) }}</el-button>
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
      <el-tab-pane :label="t(`${P}.tabs.master_keys`)" name="master-keys">
        <div class="mb-3">
          <el-button type="primary" @click="generateMasterKey">
            <el-icon><Plus /></el-icon>{{ t(`${P}.generate_master_key`) }}
          </el-button>
          <el-button type="warning" @click="confirmRotateMasterKey" class="ml-2">
            <el-icon><Refresh /></el-icon>{{ t(`${P}.rotate_master_key`) }}
          </el-button>
        </div>
        <el-alert :title="t(`${P}.master_key_alert`)" type="info" :closable="false" show-icon class="mb-3" />
        <el-table :data="masterKeys" v-loading="masterLoading" stripe>
          <el-table-column prop="key_id" :label="t(`${P}.columns.key_id`)" width="140" />
          <el-table-column prop="label" :label="t(`${P}.columns.name`)" min-width="160" />
          <el-table-column prop="algorithm" :label="t(`${P}.columns.algorithm`)" width="100" />
          <el-table-column :label="t(`${P}.columns.status`)" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.is_current" type="success" size="small">{{ t(`${P}.status.current`) }}</el-tag>
              <el-tag v-else-if="row.status === 'deprecated'" type="warning" size="small">{{ t(`${P}.status.deprecated`) }}</el-tag>
              <el-tag v-else :type="row.status === 'active' ? 'info' : 'danger'" size="small">
                {{ masterKeyStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="rotated_at" :label="t(`${P}.columns.rotated_at`)" width="170" />
          <el-table-column prop="expires_at" :label="t(`${P}.columns.expires_at`)" width="170" />
          <el-table-column prop="created_at" :label="t(`${P}.columns.created_at`)" width="170" />
        </el-table>
      </el-tab-pane>

      <!-- 访问审计 -->
      <el-tab-pane :label="t(`${P}.tabs.logs`)" name="logs">
        <div class="mb-3">
          <el-select v-model="logFilters.secret_id" :placeholder="t(`${P}.filters.secret_ph`)" clearable filterable
                     @change="fetchLogs" size="small" class="w-60">
            <el-option v-for="s in secrets" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </div>
        <el-table :data="auditLogs" v-loading="logsLoading" stripe>
          <el-table-column prop="action" :label="t(`${P}.columns.action`)" width="90">
            <template #default="{ row }">
              <el-tag :type="actionTag(row.action)" size="small">{{ actionLabel(row.action) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="secret.name" :label="t(`${P}.columns.secret`)" min-width="120" />
          <el-table-column prop="accessed_by" :label="t(`${P}.columns.accessed_by`)" min-width="140" />
          <el-table-column prop="ip_address" :label="t(`${P}.columns.ip`)" width="130" />
          <el-table-column prop="context" :label="t(`${P}.columns.context`)" min-width="120">
            <template #default="{ row }">
              <code class="text-xs" v-if="row.context">{{ JSON.stringify(row.context) }}</code>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" :label="t(`${P}.columns.time`)" width="170" />
        </el-table>
        <div class="flex justify-center mt-3">
          <el-pagination v-if="logTotal > 0" background layout="prev, pager, next"
                         :total="logTotal" :current-page="logCurrentPage" :page-size="logPerPage"
                         @current-change="onLogPageChange" />
        </div>
      </el-tab-pane>

      <!-- 健康状态详情 -->
      <el-tab-pane :label="t(`${P}.tabs.health`)" name="health">
        <el-descriptions :title="t(`${P}.health.title`)" :column="2" border>
          <el-descriptions-item :label="t(`${P}.health.master_key_ready`)">
            <el-tag :type="health.has_current_key ? 'success' : 'danger'">
              {{ health.has_current_key ? yesLabel : noLabel }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.current_key_id`)">{{ health.current_key_id || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.key_algorithm`)">{{ health.key_algorithm || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.key_created`)">{{ health.current_key_created || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.key_expires`)">{{ health.current_key_expires || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.total_secrets`)">{{ health.total_secrets }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.active_secrets`)">{{ health.active_secrets }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.expiring_7d`)">{{ health.expiring_secrets_7d }}</el-descriptions-item>
          <el-descriptions-item :label="t(`${P}.health.expired`)">{{ health.expired_secrets }}</el-descriptions-item>
        </el-descriptions>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建 / 编辑凭据 Dialog -->
    <el-dialog v-model="createDialog" :title="editingSecret ? t(`${P}.dialog.edit_title`) : t(`${P}.dialog.create_title`)" width="520px">
      <el-form :model="secretForm" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item :label="t(`${P}.form.tenant_id`)" prop="tenant_id">
          <el-input-number v-model="secretForm.tenant_id" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item :label="t(`${P}.form.name`)" prop="name">
          <el-input v-model="secretForm.name" :placeholder="t(`${P}.form.name_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.form.slug`)" prop="slug">
          <el-input v-model="secretForm.slug" :placeholder="t(`${P}.form.slug_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.form.type`)" prop="type">
          <el-select v-model="secretForm.type" style="width: 100%">
            <el-option v-for="st in secretTypes" :key="st.id" :label="st.name" :value="st.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.form.value`)" prop="value">
          <el-input v-model="secretForm.value" type="textarea" :rows="3"
                    :placeholder="t(`${P}.form.value_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.form.description`)">
          <el-input v-model="secretForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t(`${P}.form.expires_at`)">
          <el-date-picker v-model="secretForm.expires_at" type="datetime" :placeholder="t(`${P}.form.expires_at_ph`)"
                          style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitSecret" :loading="submitting">{{ t('actions.submit') }}</el-button>
      </template>
    </el-dialog>

    <!-- 查看凭据明文 Dialog -->
    <el-dialog v-model="viewDialog" :title="t(`${P}.dialog.view_title`)" width="500px">
      <el-alert :title="t(`${P}.view.security_alert`)" type="warning" show-icon class="mb-3" />
      <div v-if="viewValue">
        <el-input v-model="viewValue" type="textarea" :rows="4" readonly />
        <div class="mt-2 text-xs text-gray-400">
          <el-button size="small" @click="copyValue">{{ t(`${P}.view.copy_clipboard`) }}</el-button>
        </div>
      </div>
      <div v-else-if="viewLoading" class="text-center py-4">
        <el-icon class="is-loading" size="24"><Loading /></el-icon>
        <p class="mt-2">{{ t(`${P}.view.decrypting`) }}</p>
      </div>
      <template #footer>
        <el-button @click="viewDialog = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>

    <!-- 轮换 Dialog -->
    <el-dialog v-model="rotateDialog" :title="t(`${P}.dialog.rotate_title`)" width="480px">
      <p class="mb-2 text-sm text-gray-600">
        {{ t(`${P}.dialog.rotate_intro`, { name: rotatingSecret?.name, slug: rotatingSecret?.slug }) }}
      </p>
      <el-input v-model="rotateValue" type="textarea" :rows="3" :placeholder="t(`${P}.form.rotate_value_ph`)" />
      <template #footer>
        <el-button @click="rotateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="submitRotate" :loading="rotateLoading">{{ t(`${P}.confirm_rotate`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Loading } from '@element-plus/icons-vue';
import api from '@/api/secret-manager';

const { t } = useI18n();
const P = 'secret_manager_page';

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

const yesLabel = computed(() => t(`${P}.yes`));
const noLabel = computed(() => t(`${P}.no`));

const statusOptions = computed(() => [
  { value: 'active', label: t(`${P}.status.active`) },
  { value: 'revoked', label: t(`${P}.status.revoked`) },
  { value: 'expired', label: t(`${P}.status.expired`) },
]);

const auditActionLabels = computed(() => Object.fromEntries(
  ['create', 'access', 'rotate', 'revoke', 'restore'].map((k) => [k, t(`${P}.audit_action.${k}`)])
));

const formRules = computed(() => ({
  tenant_id: [{ required: true, message: t(`${P}.rules.tenant_id_required`), trigger: 'blur' }],
  name: [{ required: true, message: t(`${P}.rules.name_required`), trigger: 'blur' }],
  slug: [{ required: true, message: t(`${P}.rules.slug_required`), trigger: 'blur' }],
  value: [{ required: true, message: t(`${P}.rules.value_required`), trigger: 'blur' }],
}));

function secretStatusLabel(status) {
  if (status === 'expired') return t(`${P}.status.expired`);
  return status;
}

function masterKeyStatusLabel(status) {
  if (status === 'revoked') return t(`${P}.status.revoked`);
  return status;
}

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
        ElMessage.success(t(`${P}.messages.created`));
        createDialog.value = false;
        fetchSecrets();
      })
      .catch(err => {
        ElMessage.error(err.response?.data?.errors?.slug?.[0] || t(`${P}.messages.create_failed`));
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
      viewValue.value = res.data.data?.value || t(`${P}.messages.decrypt_failed`);
    })
    .catch(() => {
      ElMessage.error(t(`${P}.messages.decrypt_error`));
      viewValue.value = t(`${P}.messages.decrypt_failed_short`);
    })
    .finally(() => viewLoading.value = false);
}

function copyValue() {
  navigator.clipboard.writeText(viewValue.value).then(() => {
    ElMessage.success(t(`${P}.messages.copied`));
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
    ElMessage.warning(t(`${P}.messages.rotate_value_required`));
    return;
  }
  rotateLoading.value = true;
  api.rotate(rotatingSecret.value.id, rotateValue.value)
    .then(() => {
      ElMessage.success(t(`${P}.messages.rotated`));
      rotateDialog.value = false;
      fetchSecrets();
    })
    .catch(() => ElMessage.error(t(`${P}.messages.rotate_failed`)))
    .finally(() => rotateLoading.value = false);
}

// 吊销
function confirmRevoke(row) {
  ElMessageBox.confirm(t(`${P}.confirm.revoke`, { name: row.name }), t(`${P}.confirm.revoke_title`), {
    confirmButtonText: t(`${P}.confirm.revoke_confirm`),
    cancelButtonText: t('actions.cancel'),
    type: 'warning',
  }).then(() => {
    api.revoke(row.id).then(() => {
      ElMessage.success(t(`${P}.messages.revoked`));
      fetchSecrets();
    });
  }).catch(() => {});
}

// 恢复
function confirmRestore(row) {
  api.restore(row.id).then(() => {
    ElMessage.success(t(`${P}.messages.restored`));
    fetchSecrets();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || t(`${P}.messages.restore_failed`));
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
  ElMessageBox.prompt(t(`${P}.prompt.master_key_label`), t(`${P}.prompt.generate_master_key_title`), {
    confirmButtonText: t(`${P}.prompt.generate`),
    cancelButtonText: t('actions.cancel'),
    inputPattern: /.*/,
  }).then(({ value }) => {
    api.generateMasterKey(value || '').then(res => {
      ElMessage.success(t(`${P}.messages.master_key_generated`, { key_id: res.data.data?.key_id }));
      fetchMasterKeys();
      fetchHealth();
    });
  }).catch(() => {});
}

function confirmRotateMasterKey() {
  ElMessageBox.confirm(
    t(`${P}.confirm.rotate_master`),
    t(`${P}.confirm.rotate_master_title`),
    {
      confirmButtonText: t(`${P}.confirm_rotate`),
      cancelButtonText: t('actions.cancel'),
      type: 'warning',
      dangerouslyUseHTMLString: true,
    }
  ).then(() => {
    api.rotateMasterKey().then(res => {
      ElMessage.success(res.data.message || t(`${P}.messages.master_key_rotated`));
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
  return auditActionLabels.value[action] || action;
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
