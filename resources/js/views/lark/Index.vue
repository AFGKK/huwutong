<template>
  <div class="lark-page">
    <div class="page-header">
      <h2>{{ t('lark_page.title') }}</h2>
      <div class="header-actions">
        <el-button @click="loadConfig" :loading="loading.load" plain>{{ t('lark_page.refresh') }}</el-button>
        <el-button type="primary" @click="saveConfig" :loading="loading.save" :disabled="!isDirty">
          <el-icon><Check /></el-icon> {{ t('lark_page.save_config') }}
        </el-button>
      </div>
    </div>

    <div v-if="pageLoading" class="loading-container">
      <el-skeleton :rows="8" animated />
    </div>

    <template v-else>
      <!-- 集成状态 -->
      <el-alert
        :title="integration.configured ? t('lark_page.alert.configured') : t('lark_page.alert.not_configured')"
        :type="integration.configured ? 'success' : 'info'"
        :closable="false"
        show-icon
        class="mb-4"
      >
        <template #default>
          <p v-if="integration.configured">
            {{ t('lark_page.alert.configured_app_id') }} <strong>{{ integration.data?.app_id }}</strong>，
            {{ t('lark_page.status.label') }}: <el-tag :type="integration.data?.is_enabled ? 'success' : 'danger'" size="small">
              {{ integration.data?.is_enabled ? t('lark_page.status.enabled') : t('lark_page.status.disabled') }}
            </el-tag>
          </p>
          <p v-else>
            {{ t('lark_page.alert.not_configured_desc') }}
          </p>
        </template>
      </el-alert>

      <!-- 配置表单 -->
      <el-row :gutter="16">
        <el-col :span="16">
          <el-card shadow="never">
            <template #header>
              <div class="card-header">
                <span><el-icon><Setting /></el-icon> {{ t('lark_page.form.card_title') }}</span>
              </div>
            </template>

            <el-form :model="form" label-width="160px" label-position="top">
              <el-form-item :label="t('lark_page.form.name')">
                <el-input v-model="form.name" :placeholder="t('lark_page.form.name_ph')" maxlength="100" />
              </el-form-item>

              <el-form-item :label="t('lark_page.form.enabled')">
                <el-switch v-model="form.is_enabled" :active-text="t('actions.enable')" :inactive-text="t('actions.disable')" />
              </el-form-item>

              <el-divider>{{ t('lark_page.form.divider_credentials') }}</el-divider>

              <el-form-item :label="t('lark_page.form.app_id')" required>
                <el-input v-model="form.app_id" :placeholder="t('lark_page.form.app_id_ph')" maxlength="100" />
              </el-form-item>

              <el-form-item :label="t('lark_page.form.app_secret')">
                <el-input v-model="form.app_secret" type="password" show-password :placeholder="t('lark_page.form.app_secret_ph')" maxlength="500" />
                <div class="form-tip">{{ t('lark_page.form.app_secret_tip') }}</div>
              </el-form-item>

              <el-form-item :label="t('lark_page.form.encrypt_key')">
                <el-input v-model="form.encrypt_key" :placeholder="t('lark_page.form.encrypt_key_ph')" maxlength="100" />
              </el-form-item>

              <el-form-item :label="t('lark_page.form.verification_token')">
                <el-input v-model="form.verification_token" :placeholder="t('lark_page.form.verification_token_ph')" maxlength="100" />
              </el-form-item>

              <el-divider>{{ t('lark_page.form.divider_bot') }}</el-divider>

              <el-form-item :label="t('lark_page.form.bot_webhook')">
                <el-input v-model="form.bot_webhook_url" :placeholder="t('lark_page.form.bot_webhook_ph')" maxlength="500" />
                <div class="form-tip">
                  {{ t('lark_page.form.bot_webhook_tip') }}
                  <el-link type="primary" :href="docsUrl" target="_blank" style="margin-left:4px;">{{ t('lark_page.form.view_docs') }}</el-link>
                </div>
              </el-form-item>

              <el-form-item :label="t('lark_page.form.notify_enabled')">
                <el-switch v-model="form.notify_enabled" :active-text="t('actions.enable')" :inactive-text="t('actions.disable')" />
                <div class="form-tip">{{ t('lark_page.form.notify_tip') }}</div>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>

        <el-col :span="8">
          <!-- 操作面板 -->
          <el-card shadow="never" class="mb-4">
            <template #header>
              <div class="card-header">
                <span><el-icon><Connection /></el-icon> {{ t('lark_page.connection.card_title') }}</span>
              </div>
            </template>
            <el-button @click="testConnection" :loading="loading.test" type="success" plain style="width:100%;margin-bottom:8px;">
              <el-icon><Select /></el-icon> {{ t('lark_page.connection.test') }}
            </el-button>
            <el-button @click="showTestMessage = true" :disabled="!integration.configured" plain style="width:100%;">
              <el-icon><ChatDotSquare /></el-icon> {{ t('lark_page.connection.send_test') }}
            </el-button>
            <div v-if="testResult" class="test-result" :class="testResult.success ? 'success' : 'fail'">
              <div class="test-result-title">{{ testResult.success ? t('lark_page.connection.pass') : t('lark_page.connection.fail') }}</div>
              <div class="test-result-msg">{{ testResult.message }}</div>
              <div class="test-result-detail" v-if="testResult.results">
                <div>{{ t('lark_page.connection.tenant_token') }}: {{ testResult.results.tenant_token ? t('lark_page.connection.ok') : t('lark_page.connection.ng') }}</div>
                <div>{{ t('lark_page.connection.webhook') }}: {{ testResult.results.webhook ? t('lark_page.connection.ok') : t('lark_page.connection.ng') }}</div>
              </div>
            </div>
          </el-card>

          <!-- 快速参考 -->
          <el-card shadow="never">
            <template #header>
              <div class="card-header">
                <span><el-icon><InfoFilled /></el-icon> {{ t('lark_page.setup.card_title') }}</span>
              </div>
            </template>
            <div class="setup-guide">
              <h4>{{ t('lark_page.setup.steps_title') }}</h4>
              <ol>
                <li>{{ t('lark_page.setup.step1_prefix') }} <el-link type="primary" :href="appConsoleUrl" target="_blank">{{ t('lark_page.setup.platform') }}</el-link> {{ t('lark_page.setup.step1_suffix') }}</li>
                <li>{{ t('lark_page.setup.step2') }}</li>
                <li>{{ t('lark_page.setup.step3') }}</li>
                <li>{{ t('lark_page.setup.step4') }}</li>
                <li>{{ t('lark_page.setup.step5') }}</li>
              </ol>
              <el-divider />
              <h4>{{ t('lark_page.setup.callback_title') }}</h4>
              <p class="callback-url" v-if="callbackUrl">{{ callbackUrl }}</p>
              <p v-else class="text-muted callback-url">{{ t('lark_page.setup.callback_pending') }}</p>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </template>

    <!-- 测试消息弹窗 -->
    <el-dialog v-model="showTestMessage" :title="t('lark_page.test_dialog.title')" width="480px">
      <el-form>
        <el-form-item :label="t('lark_page.test_dialog.send_method')">
          <el-select v-model="testMsg.type" style="width:100%;">
            <el-option v-for="opt in testMsgTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('lark_page.test_dialog.target_id')" v-if="testMsg.type !== 'webhook'">
          <el-input v-model="testMsg.target" :placeholder="testMsg.type === 'user' ? t('lark_page.test_dialog.target_user_ph') : t('lark_page.test_dialog.target_group_ph')" />
        </el-form-item>
        <el-form-item :label="t('lark_page.test_dialog.message')">
          <el-input v-model="testMsg.message" type="textarea" :rows="3" :placeholder="t('lark_page.test_dialog.message_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTestMessage = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="sendTestMsg" :loading="loading.testMsg">{{ t('lark_page.test_dialog.send') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Check, Setting, Connection, Select, ChatDotSquare, InfoFilled } from '@element-plus/icons-vue';
import api from '@/api/lark';

const { t } = useI18n();

const TEST_MSG_TYPES = ['webhook', 'user', 'group'];

const testMsgTypeOptions = computed(() =>
  TEST_MSG_TYPES.map((value) => ({
    value,
    label: t(`lark_page.test_msg_types.${value}`),
  })),
);

function createDefaultForm() {
  return {
    name: t('lark_page.default_name'),
    is_enabled: false,
    app_id: '',
    app_secret: '',
    encrypt_key: '',
    verification_token: '',
    bot_webhook_url: '',
    notify_enabled: true,
  };
}

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

const defaultForm = createDefaultForm();
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
      Object.assign(form, createDefaultForm());
      Object.assign(originalForm, createDefaultForm());
    }
    testResult.value = null;
  } catch (e) {
    ElMessage.error(t('lark_page.messages.load_config_failed'));
  } finally {
    loading.load = false;
  }
}

async function saveConfig() {
  loading.save = true;
  try {
    await api.saveConfig({
      name: form.name,
      is_enabled: form.is_enabled,
      app_id: form.app_id,
      app_secret: form.app_secret || undefined,
      encrypt_key: form.encrypt_key || undefined,
      verification_token: form.verification_token || undefined,
      bot_webhook_url: form.bot_webhook_url || undefined,
      notify_enabled: form.notify_enabled,
    });
    ElMessage.success(t('lark_page.messages.config_saved'));
    await loadConfig();
  } catch (e) {
    ElMessage.error(t('lark_page.messages.save_config_failed'));
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
      ElMessage.success(t('lark_page.messages.test_connection_ok'));
    } else {
      ElMessage.warning(data.message || t('lark_page.messages.test_partial_fail'));
    }
  } catch (e) {
    testResult.value = { success: false, message: t('lark_page.messages.test_connection_failed'), results: {} };
    ElMessage.error(t('lark_page.messages.test_connection_failed'));
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
    ElMessage.success(t('lark_page.messages.test_message_sent'));
    showTestMessage.value = false;
  } catch (e) {
    ElMessage.error(t('lark_page.messages.send_failed'));
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
