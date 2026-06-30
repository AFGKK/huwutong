<template>
  <div class="lark-page">
    <div class="page-header">
      <h2>飞书/Lark 集成</h2>
      <div class="header-actions">
        <el-button @click="loadConfig" :loading="loading.load" plain>刷新</el-button>
        <el-button type="primary" @click="saveConfig" :loading="loading.save" :disabled="!isDirty">
          <el-icon><Check /></el-icon> 保存配置
        </el-button>
      </div>
    </div>

    <div v-if="pageLoading" class="loading-container">
      <el-skeleton :rows="8" animated />
    </div>

    <template v-else>
      <!-- 集成状态 -->
      <el-alert
        :title="integration.configured ? '飞书集成已配置' : '飞书集成未配置'"
        :type="integration.configured ? 'success' : 'info'"
        :closable="false"
        show-icon
        class="mb-4"
      >
        <template #default>
          <p v-if="integration.configured">
            飞书自建应用 App ID: <strong>{{ integration.data?.app_id }}</strong>，
            状态: <el-tag :type="integration.data?.is_enabled ? 'success' : 'danger'" size="small">
              {{ integration.data?.is_enabled ? '已启用' : '已禁用' }}
            </el-tag>
          </p>
          <p v-else>
            配置飞书自建应用后，可实现告警通知推送、飞书登录、消息推送等功能。
          </p>
        </template>
      </el-alert>

      <!-- 配置表单 -->
      <el-row :gutter="16">
        <el-col :span="16">
          <el-card shadow="never">
            <template #header>
              <div class="card-header">
                <span><el-icon><Setting /></el-icon> 飞书自建应用配置</span>
              </div>
            </template>

            <el-form :model="form" label-width="160px" label-position="top">
              <el-form-item label="集成名称">
                <el-input v-model="form.name" placeholder="飞书集成" maxlength="100" />
              </el-form-item>

              <el-form-item label="启用状态">
                <el-switch v-model="form.is_enabled" active-text="启用" inactive-text="禁用" />
              </el-form-item>

              <el-divider>应用凭证</el-divider>

              <el-form-item label="App ID" required>
                <el-input v-model="form.app_id" placeholder="飞书开放平台自建应用的 App ID" maxlength="100" />
              </el-form-item>

              <el-form-item label="App Secret">
                <el-input v-model="form.app_secret" type="password" show-password placeholder="App Secret（留空不修改）" maxlength="500" />
                <div class="form-tip">留空则保持原值不变。首次配置时必填。</div>
              </el-form-item>

              <el-form-item label="Encrypt Key">
                <el-input v-model="form.encrypt_key" placeholder="事件订阅加密密钥（可选）" maxlength="100" />
              </el-form-item>

              <el-form-item label="Verification Token">
                <el-input v-model="form.verification_token" placeholder="事件订阅验证令牌（可选）" maxlength="100" />
              </el-form-item>

              <el-divider>机器人配置</el-divider>

              <el-form-item label="群机器人 Webhook URL">
                <el-input v-model="form.bot_webhook_url" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/..." maxlength="500" />
                <div class="form-tip">
                  在飞书群中添加"群机器人"→"自定义机器人"，复制 Webhook 地址
                  <el-link type="primary" :href="docsUrl" target="_blank" style="margin-left:4px;">查看文档</el-link>
                </div>
              </el-form-item>

              <el-form-item label="启用通知推送">
                <el-switch v-model="form.notify_enabled" active-text="启用" inactive-text="禁用" />
                <div class="form-tip">启用后，系统告警将通过飞书 Webhook 推送到群聊</div>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>

        <el-col :span="8">
          <!-- 操作面板 -->
          <el-card shadow="never" class="mb-4">
            <template #header>
              <div class="card-header">
                <span><el-icon><Connection /></el-icon> 连接测试</span>
              </div>
            </template>
            <el-button @click="testConnection" :loading="loading.test" type="success" plain style="width:100%;margin-bottom:8px;">
              <el-icon><Select /></el-icon> 测试连接
            </el-button>
            <el-button @click="showTestMessage = true" :disabled="!integration.configured" plain style="width:100%;">
              <el-icon><ChatDotSquare /></el-icon> 发送测试消息
            </el-button>
            <div v-if="testResult" class="test-result" :class="testResult.success ? 'success' : 'fail'">
              <div class="test-result-title">{{ testResult.success ? '✓ 测试通过' : '✗ 测试失败' }}</div>
              <div class="test-result-msg">{{ testResult.message }}</div>
              <div class="test-result-detail" v-if="testResult.results">
                <div>Tenant Token: {{ testResult.results.tenant_token ? '✓' : '✗' }}</div>
                <div>Webhook: {{ testResult.results.webhook ? '✓' : '✗' }}</div>
              </div>
            </div>
          </el-card>

          <!-- 快速参考 -->
          <el-card shadow="never">
            <template #header>
              <div class="card-header">
                <span><el-icon><InfoFilled /></el-icon> 配置说明</span>
              </div>
            </template>
            <div class="setup-guide">
              <h4>配置步骤</h4>
              <ol>
                <li>前往 <el-link type="primary" :href="appConsoleUrl" target="_blank">飞书开放平台</el-link> 创建自建应用</li>
                <li>获取 App ID 和 App Secret</li>
                <li>在"权限管理"中添加需要的权限</li>
                <li>在群中添加"群机器人"并复制 Webhook 地址</li>
                <li>填入下方表单并保存</li>
              </ol>
              <el-divider />
              <h4>回调地址</h4>
              <p class="callback-url" v-if="callbackUrl">{{ callbackUrl }}</p>
              <p v-else class="text-muted callback-url">保存后才能生成</p>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </template>

    <!-- 测试消息弹窗 -->
    <el-dialog v-model="showTestMessage" title="发送测试消息" width="480px">
      <el-form>
        <el-form-item label="发送方式">
          <el-select v-model="testMsg.type" style="width:100%;">
            <el-option label="群机器人 Webhook" value="webhook" />
            <el-option label="API 用户消息 (open_id)" value="user" />
            <el-option label="API 群消息 (chat_id)" value="group" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标 ID" v-if="testMsg.type !== 'webhook'">
          <el-input v-model="testMsg.target" :placeholder="testMsg.type === 'user' ? '用户 open_id' : '群 chat_id'" />
        </el-form-item>
        <el-form-item label="测试消息">
          <el-input v-model="testMsg.message" type="textarea" :rows="3" placeholder="可选，默认为测试消息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTestMessage = false">取消</el-button>
        <el-button type="primary" @click="sendTestMsg" :loading="loading.testMsg">发送</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Check, Setting, Connection, Select, ChatDotSquare, InfoFilled } from '@element-plus/icons-vue';
import api from '@/api/lark';

const pageLoading = ref(true);
const loading = reactive({
  load: false,
  save: false,
  test: false,
  testMsg: false,
});
const docsUrl = 'https://open.feishu.cn/document';
const appConsoleUrl = 'https://open.feishu.cn/app';

const integration = reactive({
  configured: false,
  data: null,
});

const defaultForm = {
  name: '飞书集成',
  is_enabled: false,
  app_id: '',
  app_secret: '',
  encrypt_key: '',
  verification_token: '',
  bot_webhook_url: '',
  notify_enabled: true,
};

const form = reactive({ ...defaultForm });
const originalForm = reactive({ ...defaultForm });
const isDirty = computed(() => {
  return Object.keys(defaultForm).some(k => form[k] !== originalForm[k]);
});

const showTestMessage = ref(false);
const testResult = ref(null);
const testMsg = reactive({
  type: 'webhook',
  target: '',
  message: '',
});

// callback URL
const callbackUrl = computed(() => {
  if (!integration.data?.id) return '';
  const base = window.location.origin;
  return base + '/api/admin/lark/callback/' + integration.data.id;
});

async function loadConfig() {
  loading.load = true;
  try {
    const res = await api.getConfig();
    const cfg = res.data || {};
    integration.configured = cfg.configured || false;
    integration.data = cfg.data || null;

    if (integration.data) {
      Object.keys(defaultForm).forEach(k => {
        const val = integration.data[k] !== undefined ? integration.data[k] : defaultForm[k];
        form[k] = val;
        originalForm[k] = val;
      });
    } else {
      Object.assign(form, defaultForm);
      Object.assign(originalForm, defaultForm);
    }
    testResult.value = null;
  } catch (e) {
    ElMessage.error('加载配置失败');
  } finally {
    loading.load = false;
  }
}

async function saveConfig() {
  loading.save = true;
  try {
    const res = await api.saveConfig({
      name: form.name,
      is_enabled: form.is_enabled,
      app_id: form.app_id,
      app_secret: form.app_secret || undefined,
      encrypt_key: form.encrypt_key || undefined,
      verification_token: form.verification_token || undefined,
      bot_webhook_url: form.bot_webhook_url || undefined,
      notify_enabled: form.notify_enabled,
    });
    ElMessage.success('配置已保存');
    await loadConfig();
  } catch (e) {
    ElMessage.error('保存配置失败');
  } finally {
    loading.save = false;
  }
}

async function testConnection() {
  loading.test = true;
  testResult.value = null;
  try {
    const res = await api.testConnection();
    const data = res.data || {};
    testResult.value = data;
    if (data.success) {
      ElMessage.success('连接测试通过');
    } else {
      ElMessage.warning(data.message || '部分测试未通过');
    }
  } catch (e) {
    testResult.value = { success: false, message: '连接测试失败', results: {} };
    ElMessage.error('连接测试失败');
  } finally {
    loading.test = false;
  }
}

async function sendTestMsg() {
  loading.testMsg = true;
  try {
    await api.sendTestMessage({
      type: testMsg.type,
      target: testMsg.target || undefined,
      message: testMsg.message || undefined,
    });
    ElMessage.success('测试消息已发送');
    showTestMessage.value = false;
  } catch (e) {
    ElMessage.error('发送失败');
  } finally {
    loading.testMsg = false;
  }
}

onMounted(async () => {
  await loadConfig();
  pageLoading.value = false;
});
</script>

<style scoped>
.lark-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { font-size: 22px; font-weight: 600; margin: 0; }
.mb-4 { margin-bottom: 16px; }
.card-header { display: flex; align-items: center; gap: 6px; }
.card-header el-icon { vertical-align: middle; }
.loading-container { padding: 40px 0; }
.form-tip { font-size: 12px; color: #909399; margin-top: 4px; line-height: 1.5; }
.test-result { margin-top: 12px; padding: 12px; border-radius: 6px; font-size: 13px; }
.test-result.success { background: #f0f9eb; border: 1px solid #e1f3d8; }
.test-result.fail { background: #fef0f0; border: 1px solid #fde2e2; }
.test-result-title { font-weight: 600; margin-bottom: 4px; }
.test-result-msg { color: #606266; margin-bottom: 8px; }
.test-result-detail { font-size: 12px; color: #909399; }
.test-result-detail div { margin: 2px 0; }
.setup-guide { font-size: 13px; color: #606266; }
.setup-guide h4 { margin: 0 0 8px; font-size: 14px; color: #303133; }
.setup-guide ol { padding-left: 20px; line-height: 1.8; }
.setup-guide li { margin: 4px 0; }
.callback-url { font-family: monospace; font-size: 12px; background: #f5f7fa; padding: 8px; border-radius: 4px; word-break: break-all; }
.text-muted { color: #c0c4cc; }
</style>
