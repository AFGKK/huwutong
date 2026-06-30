<template>
  <div class="oauth-settings">
    <div class="page-header">
      <div>
        <h2>OAuth 登录配置</h2>
        <p class="text-muted">配置各社交平台的 OAuth App 凭据，开启后登录页将显示对应按钮</p>
      </div>
      <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
    </div>

    <el-alert title="需先在对应平台注册 OAuth App 获取凭据，填入下方并开启开关后，登录页即显示该社交登录按钮。" type="info" show-icon :closable="false" class="mb-4" />

    <el-card v-for="p in providers" :key="p.provider" shadow="never" class="mb-4">
      <template #header>
        <div class="card-header">
          <div class="card-title">
            <span class="provider-badge" :style="{ background: p.color, color: '#fff' }">{{ p.displayIcon }}</span>
            <span>{{ p.name }}（{{ p.provider }}）</span>
          </div>
          <el-switch v-model="p.enabled" active-text="启用" inactive-text="关闭" />
        </div>
      </template>

      <el-form :model="p" label-width="140px" size="small">
        <el-form-item v-for="field in p.fields" :key="field.key" :label="field.label">
          <el-input
            v-model="field.value"
            :placeholder="field.placeholder"
            :type="field.sensitive ? 'password' : 'text'"
            show-password
            clearable
          />
        </el-form-item>
        <el-form-item label="配置参考">
          <span class="guide-text">{{ p.guide }}</span>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';

const saving = ref(false);
const providers = ref([]);

const providerDefs = {
  wechat: {
    name: '微信', displayIcon: '微', color: '#07c160',
    fields: [
      { key: 'oauth_wechat_app_id', label: 'AppID', placeholder: 'wx1234567890abcdef', sensitive: false },
      { key: 'oauth_wechat_app_secret', label: 'AppSecret', placeholder: '请填写微信 AppSecret', sensitive: true },
    ],
    guide: '微信开放平台 → 网站应用 → 查看 AppID / AppSecret',
  },
  qq: {
    name: 'QQ', displayIcon: 'QQ', color: '#12b7f5',
    fields: [
      { key: 'oauth_qq_app_id', label: 'AppID', placeholder: '101234567', sensitive: false },
      { key: 'oauth_qq_app_key', label: 'AppKey', placeholder: '请填写 QQ AppKey', sensitive: true },
    ],
    guide: 'QQ互联 → 应用管理 → 查看 AppID / AppKey',
  },
  apple: {
    name: 'Apple', displayIcon: '🍎', color: '#000',
    fields: [
      { key: 'oauth_apple_service_id', label: 'Service ID', placeholder: 'com.example.service', sensitive: false },
      { key: 'oauth_apple_key_id', label: 'Key ID', placeholder: 'ABC123DEFG', sensitive: false },
      { key: 'oauth_apple_team_id', label: 'Team ID', placeholder: 'TEAM123456', sensitive: false },
    ],
    guide: 'Apple Developer → Certificates, Identifiers & Profiles → 配置 Sign in with Apple',
  },
  google: {
    name: 'Google', displayIcon: 'G', color: '#4285f4',
    fields: [
      { key: 'oauth_google_client_id', label: 'Client ID', placeholder: '123456-xxx.apps.googleusercontent.com', sensitive: false },
      { key: 'oauth_google_client_secret', label: 'Client Secret', placeholder: 'GOCSPX-xxxxxxxxxxxx', sensitive: true },
    ],
    guide: 'Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID',
  },
  github: {
    name: 'GitHub', displayIcon: 'GH', color: '#333',
    fields: [
      { key: 'oauth_github_client_id', label: 'Client ID', placeholder: 'Iv1.xxxxxxxxxxxx', sensitive: false },
      { key: 'oauth_github_client_secret', label: 'Client Secret', placeholder: 'ghp_xxxxxxxxxxxxxxxxxxxx', sensitive: true },
    ],
    guide: 'GitHub Settings → Developer settings → OAuth Apps → 查看 Client ID / Secret',
  },
};

async function loadSettings() {
  try {
    const { data: res } = await apiClient.get('/settings');
    const groups = res.data || [];
    const oauthGroup = groups.find(g => g.group === 'oauth');
    if (!oauthGroup) return;
    const settingMap = {};
    oauthGroup.settings.forEach(s => { settingMap[s.key] = s; });
    providers.value = Object.entries(providerDefs).map(([provider, def]) => ({
      provider, ...def,
      enabled: (settingMap[`oauth_${provider}_enabled`]?.value) === '1',
      enabledKey: `oauth_${provider}_enabled`,
      fields: (def.fields || []).map(f => ({ ...f, value: settingMap[f.key]?.value || '' })),
    }));
    console.log('OAuth providers loaded:', providers.value.length);
  } catch { ElMessage.error('加载配置失败'); }
}

async function handleSave() {
  saving.value = true;
  try {
    const settings = [];
    providers.value.forEach(p => {
      settings.push({ key: p.enabledKey, value: p.enabled ? '1' : '0' });
      p.fields.forEach(f => settings.push({ key: f.key, value: f.value }));
    });
    await apiClient.post('/settings', { settings });
    ElMessage.success('OAuth 配置已保存');
  } catch { ElMessage.error('保存失败'); }
  finally { saving.value = false; }
}

onMounted(loadSettings);
</script>

<style scoped>
.oauth-settings { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.provider-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-size: 13px; font-weight: 700; }
.guide-text { font-size: 12px; color: #909399; }
</style>
