<template>
  <div class="edge-verifier-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        Edge 授权验证
      </h2>
      <div class="header-actions">
        <el-tag type="warning" effect="dark" v-if="!config.has_secret" class="mr-2">
          未配置签名密钥
        </el-tag>
        <el-button @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 信息提示 -->
    <el-alert
      title="Cloudflare Workers 边缘授权验证 — 全球 200+ 节点 &lt;10ms 验证，回源降级兜底"
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
          <div class="stat-label">Token 缓存数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ cache.revoked_count }}</div>
          <div class="stat-label">已吊销 License</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ config.edge_cache_ttl }}s</div>
          <div class="stat-label">边缘缓存 TTL</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ deployment.worker_version }}</div>
          <div class="stat-label">Worker 版本</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: Token 生成 -->
        <el-tab-pane label="Token 生成" name="token">
          <el-form :model="tokenForm" label-width="140px" class="mb-4" @submit.prevent="handleGenerate">
            <el-form-item label="License Key">
              <el-input v-model="tokenForm.license_key" placeholder="输入 License Key" style="width:400px">
                <template #prepend><el-icon><Key /></el-icon></template>
              </el-input>
            </el-form-item>
            <el-form-item label="有效期 (秒)">
              <el-input-number v-model="tokenForm.ttl" :min="60" :max="86400" :step="300" />
              <span class="ml-2 text-gray">默认 3600 (1 小时)</span>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="generating">
                <el-icon><MagicStick /></el-icon> 生成 Token
              </el-button>
              <el-button @click="showBatchDialog = true">
                <el-icon><List /></el-icon> 批量生成
              </el-button>
            </el-form-item>
          </el-form>

          <!-- Token 结果 -->
          <el-card v-if="tokenResult" shadow="never" class="mb-3">
            <template #header>
              <span>生成的 Token</span>
              <el-button size="small" style="float:right" @click="copyToken">复制 Token</el-button>
            </template>
            <pre class="token-display">{{ tokenResult.token }}</pre>
            <el-descriptions :column="2" border size="small" class="mt-2">
              <el-descriptions-item label="License Key">{{ tokenResult.license_key }}</el-descriptions-item>
              <el-descriptions-item label="过期时间">{{ formatTime(tokenResult.expires_at) }}</el-descriptions-item>
              <el-descriptions-item label="有效剩余">{{ tokenResult.expires_in }} 秒</el-descriptions-item>
            </el-descriptions>
          </el-card>

          <!-- Token 验证 -->
          <h4 class="mb-2 mt-3">验证 Token</h4>
          <el-form :model="verifyForm" label-width="100px" @submit.prevent="handleVerify">
            <el-form-item label="Token">
              <el-input v-model="verifyForm.token" type="textarea" :rows="2" placeholder="粘贴 Token 进行验证" />
            </el-form-item>
            <el-form-item>
              <el-button type="success" native-type="submit" :loading="verifying">
                <el-icon><CircleCheck /></el-icon> 验证
              </el-button>
            </el-form-item>
          </el-form>

          <el-alert
            v-if="verifyResult !== null"
            :title="verifyResult.valid ? '✅ Token 有效' : '❌ Token 无效'"
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
        <el-tab-pane label="吊销管理" name="revoke">
          <el-form :model="revokeForm" label-width="140px" @submit.prevent="handleRevoke">
            <el-form-item label="License Key">
              <el-input v-model="revokeForm.license_key" placeholder="输入要吊销的 License Key" style="width:400px" />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" native-type="submit" :loading="revoking">
                <el-icon><Delete /></el-icon> 吊销边缘缓存
              </el-button>
            </el-form-item>
          </el-form>

          <el-divider />
          <el-button @click="handleSyncRevocation" :loading="syncing" type="primary" plain>
            <el-icon><Refresh /></el-icon> 同步吊销列表到边缘
          </el-button>
        </el-tab-pane>

        <!-- Tab 3: 部署配置 -->
        <el-tab-pane label="部署配置" name="deploy">
          <el-descriptions :column="1" border class="mb-4">
            <el-descriptions-item label="Worker 名称">{{ deployment.worker_name }}</el-descriptions-item>
            <el-descriptions-item label="版本">{{ deployment.worker_version }}</el-descriptions-item>
            <el-descriptions-item label="Compatibility Date">{{ deployment.compatibility_date }}</el-descriptions-item>
            <el-descriptions-item label="KV Namespace">{{ deployment.kv_namespace }}</el-descriptions-item>
            <el-descriptions-item label="签名算法">{{ deployment.signing_algorithm }}</el-descriptions-item>
            <el-descriptions-item label="回源端点">{{ deployment.origin_endpoint }}</el-descriptions-item>
          </el-descriptions>

          <h4 class="mb-2">环境变量</h4>
          <el-table :data="envVarList" stripe size="small" class="mb-3">
            <el-table-column prop="name" label="变量名" width="250">
              <template #default="{ row }"><code>{{ row.name }}</code></template>
            </el-table-column>
            <el-table-column prop="value" label="默认值" width="150" />
            <el-table-column prop="desc" label="说明" />
          </el-table>

          <h4 class="mb-2">部署步骤</h4>
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
            title="⚠️ 签名密钥未配置"
            description="请在 .env 中设置 EDGE_VERIFIER_SECRET=your-64-char-random-secret，并同步到 Cloudflare Worker"
            type="warning"
            show-icon
            class="mt-3"
          />
        </el-tab-pane>

        <!-- Tab 4: 路由列表 -->
        <el-tab-pane label="API 路由" name="routes">
          <el-table :data="routeList" stripe size="small">
            <el-table-column prop="method" label="方法" width="80">
              <template #default="{ row }">
                <el-tag :type="row.method === 'POST' ? 'success' : 'info'" size="small">{{ row.method }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="path" label="路径" min-width="300">
              <template #default="{ row }"><code>{{ row.path }}</code></template>
            </el-table-column>
            <el-table-column prop="desc" label="说明" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 批量生成对话框 -->
    <el-dialog v-model="showBatchDialog" title="批量生成 Token" width="600px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item label="License Keys">
          <el-input
            v-model="batchForm.license_keys"
            type="textarea"
            :rows="6"
            placeholder="每行一个 License Key"
          />
          <span class="text-gray" style="font-size:0.85em">最多 100 个，每行一个</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchDialog = false">取消</el-button>
        <el-button type="primary" @click="handleBatchGenerate" :loading="batchLoading">
          批量生成
        </el-button>
      </template>
    </el-dialog>

    <!-- 批量结果 -->
    <el-dialog v-model="showBatchResult" title="批量生成结果" width="600px">
      <el-alert
        :title="`成功 ${batchResult.success} / 失败 ${batchResult.failed}`"
        :type="batchResult.failed > 0 ? 'warning' : 'success'"
        show-icon
      />
      <el-table :data="batchResult.tokens" stripe size="small" class="mt-2" v-if="batchResult.tokens?.length">
        <el-table-column label="Token (截断)" min-width="300">
          <template #default="{ row }">
            <code>{{ row.token?.substring(0, 40) }}...</code>
          </template>
        </el-table-column>
        <el-table-column prop="expires_at" label="过期" width="180" />
      </el-table>
      <el-table :data="batchResult.errors" stripe size="small" class="mt-2" v-if="batchResult.errors?.length">
        <el-table-column prop="license_key" label="License Key" width="200" />
        <el-table-column prop="error" label="错误" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
  Connection, Refresh, Key, MagicStick, List,
  CircleCheck, Delete, Document,
} from '@element-plus/icons-vue';
import edgeApi from '@/api/edge-verifier';

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

// ─── 计算属性 ───
const envVarList = computed(() => {
  const vars = deployment.value.env_vars || {};
  const descMap = {
    EDGE_CACHE_TTL: '边缘缓存时间 (秒)',
    FALLBACK_TIMEOUT: '回源超时 (毫秒)',
    MAX_TOKEN_AGE_SECONDS: 'Token 最大有效期 (秒)',
    RATE_LIMIT_PER_MINUTE: '每节点每分钟限流',
  };
  return Object.entries(vars).map(([name, value]) => ({
    name, value, desc: descMap[name] || '',
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
  return new Date(ts * 1000).toLocaleString('zh-CN');
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
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

async function handleGenerate() {
  if (!tokenForm.value.license_key) {
    ElMessage.warning('请输入 License Key');
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
      ElMessage.success('Token 生成成功');
    }
  } catch {
    ElMessage.error('Token 生成失败');
  } finally {
    generating.value = false;
  }
}

function copyToken() {
  if (!tokenResult.value?.token) return;
  navigator.clipboard.writeText(tokenResult.value.token);
  ElMessage.success('已复制到剪贴板');
}

async function handleVerify() {
  if (!verifyForm.value.token) {
    ElMessage.warning('请输入 Token');
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
    verifyResult.value = { valid: false, message: '验证请求失败' };
  } finally {
    verifying.value = false;
  }
}

async function handleRevoke() {
  if (!revokeForm.value.license_key) {
    ElMessage.warning('请输入 License Key');
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
    ElMessage.error('吊销失败');
  } finally {
    revoking.value = false;
  }
}

async function handleSyncRevocation() {
  syncing.value = true;
  try {
    const { data } = await edgeApi.syncRevocationList();
    if (data.success) {
      ElMessage.success(`同步完成，${data.data.active_revocations} 条活跃吊销`);
    }
  } catch {
    ElMessage.error('同步失败');
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
    ElMessage.warning('请输入至少一个 License Key');
    return;
  }

  if (keys.length > 100) {
    ElMessage.warning('最多 100 个');
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
    ElMessage.error('批量生成失败');
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
  color: #409eff;
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
