<template>
  <div class="edge-verifier-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t('edge_verifier_page.title') }}
      </h2>
      <div class="header-actions">
        <el-tag type="warning" effect="dark" v-if="!config.has_secret" class="mr-2">
          {{ t('edge_verifier_page.no_secret') }}
        </el-tag>
        <el-button @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('edge_verifier_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 信息提示 -->
    <el-alert
      :title="t('edge_verifier_page.info_alert')"
      type="info"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <!-- 关键指标 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ cache.token_count }}</div>
          <div class="stat-label">{{ t('edge_verifier_page.stats.token_cache') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ cache.revoked_count }}</div>
          <div class="stat-label">{{ t('edge_verifier_page.stats.revoked_licenses') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ config.edge_cache_ttl }}s</div>
          <div class="stat-label">{{ t('edge_verifier_page.stats.edge_cache_ttl') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ deployment.worker_version }}</div>
          <div class="stat-label">{{ t('edge_verifier_page.stats.worker_version') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: Token 生成 -->
        <el-tab-pane :label="t('edge_verifier_page.tabs.token')" name="token">
          <el-form :model="tokenForm" label-width="140px" class="mb-4" @submit.prevent="handleGenerate">
            <el-form-item :label="t('edge_verifier_page.token.license_key')">
              <el-input v-model="tokenForm.license_key" :placeholder="t('edge_verifier_page.token.license_key_ph')" style="width:400px">
                <template #prepend><el-icon><Key /></el-icon></template>
              </el-input>
            </el-form-item>
            <el-form-item :label="t('edge_verifier_page.token.ttl')">
              <el-input-number v-model="tokenForm.ttl" :min="60" :max="86400" :step="300" />
              <span class="ml-2 text-gray">{{ t('edge_verifier_page.token.ttl_hint') }}</span>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="generating">
                <el-icon><MagicStick /></el-icon> {{ t('edge_verifier_page.token.generate') }}
              </el-button>
              <el-button @click="showBatchDialog = true">
                <el-icon><List /></el-icon> {{ t('edge_verifier_page.token.batch_generate') }}
              </el-button>
            </el-form-item>
          </el-form>

          <!-- Token 结果 -->
          <el-card v-if="tokenResult" shadow="never" class="mb-3">
            <template #header>
              <span>{{ t('edge_verifier_page.token.result_title') }}</span>
              <el-button size="small" style="float:right" @click="copyToken">{{ t('edge_verifier_page.token.copy_token') }}</el-button>
            </template>
            <pre class="token-display">{{ tokenResult.token }}</pre>
            <el-descriptions :column="2" border size="small" class="mt-2">
              <el-descriptions-item :label="t('edge_verifier_page.token.license_key')">{{ tokenResult.license_key }}</el-descriptions-item>
              <el-descriptions-item :label="t('edge_verifier_page.token.expires_at')">{{ formatTime(tokenResult.expires_at) }}</el-descriptions-item>
              <el-descriptions-item :label="t('edge_verifier_page.token.expires_in')">{{ t('edge_verifier_page.token.expires_in_unit', { n: tokenResult.expires_in }) }}</el-descriptions-item>
            </el-descriptions>
          </el-card>

          <!-- Token 验证 -->
          <h4 class="mb-2 mt-3">{{ t('edge_verifier_page.token.verify_section') }}</h4>
          <el-form :model="verifyForm" label-width="100px" @submit.prevent="handleVerify">
            <el-form-item :label="t('edge_verifier_page.token.token_label')">
              <el-input v-model="verifyForm.token" type="textarea" :rows="2" :placeholder="t('edge_verifier_page.token.token_ph')" />
            </el-form-item>
            <el-form-item>
              <el-button type="success" native-type="submit" :loading="verifying">
                <el-icon><CircleCheck /></el-icon> {{ t('edge_verifier_page.token.verify') }}
              </el-button>
            </el-form-item>
          </el-form>

          <el-alert
            v-if="verifyResult !== null"
            :title="verifyResult.valid ? t('edge_verifier_page.token.valid') : t('edge_verifier_page.token.invalid')"
            :type="verifyResult.valid ? 'success' : 'error'"
            show-icon
            :closable="false"
          >
            <template #default>
              <pre class="mt-1" style="font-size:0.85em">{{ JSON.stringify(verifyResult.data || verifyResult, null, 2) }}</pre>
            </template>
          </el-alert>
        </el-tab-pane>

        <!-- Tab 2: 吊销管理 -->
        <el-tab-pane :label="t('edge_verifier_page.tabs.revoke')" name="revoke">
          <el-form :model="revokeForm" label-width="140px" @submit.prevent="handleRevoke">
            <el-form-item :label="t('edge_verifier_page.token.license_key')">
              <el-input v-model="revokeForm.license_key" :placeholder="t('edge_verifier_page.revoke.license_key_ph')" style="width:400px" />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" native-type="submit" :loading="revoking">
                <el-icon><Delete /></el-icon> {{ t('edge_verifier_page.revoke.revoke_cache') }}
              </el-button>
            </el-form-item>
          </el-form>

          <el-divider />
          <el-button @click="handleSyncRevocation" :loading="syncing" type="primary" plain>
            <el-icon><Refresh /></el-icon> {{ t('edge_verifier_page.revoke.sync_list') }}
          </el-button>
        </el-tab-pane>

        <!-- Tab 3: 部署配置 -->
        <el-tab-pane :label="t('edge_verifier_page.tabs.deploy')" name="deploy">
          <el-descriptions :column="1" border class="mb-4">
            <el-descriptions-item :label="t('edge_verifier_page.deploy.worker_name')">{{ deployment.worker_name }}</el-descriptions-item>
            <el-descriptions-item :label="t('edge_verifier_page.deploy.version')">{{ deployment.worker_version }}</el-descriptions-item>
            <el-descriptions-item :label="t('edge_verifier_page.deploy.compatibility_date')">{{ deployment.compatibility_date }}</el-descriptions-item>
            <el-descriptions-item :label="t('edge_verifier_page.deploy.kv_namespace')">{{ deployment.kv_namespace }}</el-descriptions-item>
            <el-descriptions-item :label="t('edge_verifier_page.deploy.signing_algorithm')">{{ deployment.signing_algorithm }}</el-descriptions-item>
            <el-descriptions-item :label="t('edge_verifier_page.deploy.origin_endpoint')">{{ deployment.origin_endpoint }}</el-descriptions-item>
          </el-descriptions>

          <h4 class="mb-2">{{ t('edge_verifier_page.deploy.env_vars') }}</h4>
          <el-table :data="envVarList" stripe size="small" class="mb-3">
            <el-table-column prop="name" :label="t('edge_verifier_page.table.var_name')" width="250">
              <template #default="{ row }"><code>{{ row.name }}</code></template>
            </el-table-column>
            <el-table-column prop="value" :label="t('edge_verifier_page.table.default_value')" width="150" />
            <el-table-column prop="desc" :label="t('edge_verifier_page.table.description')" />
          </el-table>

          <h4 class="mb-2">{{ t('edge_verifier_page.deploy.deploy_steps') }}</h4>
          <el-steps direction="vertical" :active="-1" v-if="deploySteps.length">
            <el-step
              v-for="(step, i) in deploySteps"
              :key="i"
              :title="step.title"
              :description="step.command"
            />
          </el-steps>

          <el-alert
            v-if="!config.has_secret"
            :title="t('edge_verifier_page.deploy.secret_warning_title')"
            :description="t('edge_verifier_page.deploy.secret_warning_desc')"
            type="warning"
            show-icon
            class="mt-3"
          />
        </el-tab-pane>

        <!-- Tab 4: 路由列表 -->
        <el-tab-pane :label="t('edge_verifier_page.tabs.routes')" name="routes">
          <el-table :data="routeList" stripe size="small">
            <el-table-column prop="method" :label="t('edge_verifier_page.table.method')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.method === 'POST' ? 'success' : 'info'" size="small">{{ row.method }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="path" :label="t('edge_verifier_page.table.path')" min-width="300">
              <template #default="{ row }"><code>{{ row.path }}</code></template>
            </el-table-column>
            <el-table-column prop="desc" :label="t('edge_verifier_page.table.description')" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 批量生成对话框 -->
    <el-dialog v-model="showBatchDialog" :title="t('edge_verifier_page.batch.title')" width="600px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item :label="t('edge_verifier_page.token.license_key')">
          <el-input
            v-model="batchForm.license_keys"
            type="textarea"
            :rows="6"
            :placeholder="t('edge_verifier_page.batch.keys_ph')"
          />
          <span class="text-gray" style="font-size:0.85em">{{ t('edge_verifier_page.batch.keys_hint') }}</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleBatchGenerate" :loading="batchLoading">
          {{ t('edge_verifier_page.batch.generate') }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 批量结果 -->
    <el-dialog v-model="showBatchResult" :title="t('edge_verifier_page.batch.result_title')" width="600px">
      <el-alert
        :title="t('edge_verifier_page.batch.result_summary', { success: batchResult.success, failed: batchResult.failed })"
        :type="batchResult.failed > 0 ? 'warning' : 'success'"
        show-icon
      />
      <el-table :data="batchResult.tokens" stripe size="small" class="mt-2" v-if="batchResult.tokens?.length">
        <el-table-column :label="t('edge_verifier_page.batch.token_truncated')" min-width="300">
          <template #default="{ row }">
            <code>{{ row.token?.substring(0, 40) }}...</code>
          </template>
        </el-table-column>
        <el-table-column prop="expires_at" :label="t('edge_verifier_page.batch.expires')" width="180" />
      </el-table>
      <el-table :data="batchResult.errors" stripe size="small" class="mt-2" v-if="batchResult.errors?.length">
        <el-table-column prop="license_key" :label="t('edge_verifier_page.token.license_key')" width="200" />
        <el-table-column prop="error" :label="t('edge_verifier_page.batch.error')" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
  Connection, Refresh, Key, MagicStick, List,
  CircleCheck, Delete,
} from '@element-plus/icons-vue';
import edgeApi from '@/api/edge-verifier';

const { t, locale } = useI18n();

// ─── 状态 ───
const loading = ref(false);
const activeTab = ref('token');
const generating = ref(false);
const verifying = ref(false);
const revoking = ref(false);
const syncing = ref(false);
const batchLoading = ref(false);

const cache = ref({ token_count: 0, revoked_count: 0 });
const config = ref({});
const deployment = ref({});
const deploySteps = ref([]);

const tokenResult = ref(null);
const verifyResult = ref(null);
const batchResult = ref({});

const showBatchDialog = ref(false);
const showBatchResult = ref(false);

const tokenForm = ref({ license_key: '', ttl: 3600 });
const verifyForm = ref({ token: '' });
const revokeForm = ref({ license_key: '' });
const batchForm = ref({ license_keys: '' });

const ENV_VAR_KEYS = ['EDGE_CACHE_TTL', 'FALLBACK_TIMEOUT', 'MAX_TOKEN_AGE_SECONDS', 'RATE_LIMIT_PER_MINUTE'];

// ─── 计算属性 ───
const envVarList = computed(() => {
  const vars = deployment.value.env_vars || {};
  return Object.entries(vars).map(([name, value]) => ({
    name,
    value,
    desc: ENV_VAR_KEYS.includes(name) ? t(`edge_verifier_page.env_vars.${name}`) : '',
  }));
});

const routeList = computed(() => {
  const routes = deployment.value.routes || [];
  return routes.map(r => {
    const parts = r.split(' ');
    return {
      method: parts[0] || 'POST',
      path: parts[1] || r,
      desc: '',
    };
  });
});

// ─── 方法 ───
function formatTime(ts) {
  if (!ts) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(ts * 1000).toLocaleString(loc);
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, guideRes] = await Promise.all([
      edgeApi.getDashboard(),
      edgeApi.getDeploymentGuide(),
    ]);

    if (dashRes.data.success) {
      const d = dashRes.data.data;
      cache.value = d.cache;
      config.value = d.config;
      deployment.value = d.deployment;
    }

    if (guideRes.data.success) {
      deploySteps.value = guideRes.data.data.steps || [];
    }
  } catch {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

async function handleGenerate() {
  if (!tokenForm.value.license_key) {
    ElMessage.warning(t('edge_verifier_page.messages.license_key_required'));
    return;
  }

  generating.value = true;
  try {
    const { data } = await edgeApi.generateToken(
      tokenForm.value.license_key,
      tokenForm.value.ttl,
    );
    if (data.success) {
      tokenResult.value = data.data;
      ElMessage.success(t('edge_verifier_page.messages.token_generated'));
    }
  } catch {
    ElMessage.error(t('edge_verifier_page.messages.token_generate_failed'));
  } finally {
    generating.value = false;
  }
}

function copyToken() {
  if (!tokenResult.value?.token) return;
  navigator.clipboard.writeText(tokenResult.value.token);
  ElMessage.success(t('edge_verifier_page.messages.copied'));
}

async function handleVerify() {
  if (!verifyForm.value.token) {
    ElMessage.warning(t('edge_verifier_page.messages.token_required'));
    return;
  }

  verifying.value = true;
  try {
    const { data } = await edgeApi.verifyToken(verifyForm.value.token);
    if (data.success !== false) {
      verifyResult.value = {
        valid: data.valid,
        data: data.data || data,
      };
    } else {
      verifyResult.value = data;
    }
  } catch {
    verifyResult.value = { valid: false, message: t('edge_verifier_page.messages.verify_failed') };
  } finally {
    verifying.value = false;
  }
}

async function handleRevoke() {
  if (!revokeForm.value.license_key) {
    ElMessage.warning(t('edge_verifier_page.messages.license_key_required'));
    return;
  }

  revoking.value = true;
  try {
    const { data } = await edgeApi.revokeLicense(revokeForm.value.license_key);
    if (data.success) {
      ElMessage.success(data.message);
      revokeForm.value.license_key = '';
    }
  } catch {
    ElMessage.error(t('edge_verifier_page.messages.revoke_failed'));
  } finally {
    revoking.value = false;
  }
}

async function handleSyncRevocation() {
  syncing.value = true;
  try {
    const { data } = await edgeApi.syncRevocationList();
    if (data.success) {
      ElMessage.success(t('edge_verifier_page.messages.sync_done', { count: data.data.active_revocations }));
    }
  } catch {
    ElMessage.error(t('edge_verifier_page.messages.sync_failed'));
  } finally {
    syncing.value = false;
  }
}

async function handleBatchGenerate() {
  const keys = batchForm.value.license_keys
    .split('\n')
    .map(k => k.trim())
    .filter(k => k.length > 0);

  if (!keys.length) {
    ElMessage.warning(t('edge_verifier_page.messages.batch_keys_required'));
    return;
  }

  if (keys.length > 100) {
    ElMessage.warning(t('edge_verifier_page.messages.batch_max'));
    return;
  }

  batchLoading.value = true;
  try {
    const { data } = await edgeApi.batchGenerateTokens(keys);
    if (data.success) {
      batchResult.value = data.data;
      showBatchDialog.value = false;
      showBatchResult.value = true;
    }
  } catch {
    ElMessage.error(t('edge_verifier_page.messages.batch_failed'));
  } finally {
    batchLoading.value = false;
  }
}

// ─── 初始化 ───
onMounted(refreshAll);
</script>

<style scoped>
.edge-verifier-page { padding: 0; }
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.ml-2 { margin-left: 8px; }
.mr-2 { margin-right: 8px; }
.text-gray { color: #909399; }
.stat-card { text-align: center; }
.stat-card .stat-value {
  font-size: 1.8em;
  font-weight: 700;
  color: #0f172a;
}
.stat-card .stat-label {
  font-size: 0.85em;
  color: #909399;
  margin-top: 4px;
}
.token-display {
  background: #1d1e1f;
  color: #e6e6e6;
  padding: 12px;
  border-radius: 4px;
  font-size: 0.85em;
  word-break: break-all;
  white-space: pre-wrap;
  max-height: 120px;
  overflow-y: auto;
}
pre {
  background: #f5f5f5;
  padding: 8px;
  border-radius: 4px;
  font-size: 0.85em;
}
code {
  font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
  font-size: 0.9em;
}
</style>
