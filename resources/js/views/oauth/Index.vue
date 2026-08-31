<template>
  <div class="oauth-settings">
    <div class="page-header">
      <div>
        <h2>{{ t('oauth_page.title') }}</h2>
        <p class="text-muted">{{ t('oauth_page.subtitle') }}</p>
      </div>
      <el-button type="primary" :loading="saving" @click="handleSave">{{ t('actions.save') }}</el-button>
    </div>

    <el-alert :title="t('oauth_page.alert')" type="info" show-icon :closable="false" class="mb-4" />

    <el-card v-for="p in providers" :key="p.provider" shadow="never" class="mb-4">
      <template #header>
        <div class="card-header">
          <div class="card-title">
            <span class="provider-badge" :style="{ background: p.color, color: '#fff' }">{{ p.displayIcon }}</span>
            <span>{{ t('oauth_page.provider_title', { name: p.name, provider: p.provider }) }}</span>
          </div>
          <el-switch
            v-model="p.enabled"
            :active-text="t('actions.enable')"
            :inactive-text="t('oauth_page.switch_off')"
          />
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
        <el-form-item :label="t('oauth_page.config_ref')">
          <span class="guide-text">{{ p.guide }}</span>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';

const { t } = useI18n();

const saving = ref(false);
const providers = ref([]);

const providerDefs = computed(() => ({
  wechat: {
    name: t('oauth_page.providers.wechat.name'),
    displayIcon: t('oauth_page.providers.wechat.icon'),
    color: '#07c160',
    fields: [
      {
        key: 'oauth_wechat_app_id',
        label: t('oauth_page.providers.wechat.fields.app_id.label'),
        placeholder: t('oauth_page.providers.wechat.fields.app_id.placeholder'),
        sensitive: false,
      },
      {
        key: 'oauth_wechat_app_secret',
        label: t('oauth_page.providers.wechat.fields.app_secret.label'),
        placeholder: t('oauth_page.providers.wechat.fields.app_secret.placeholder'),
        sensitive: true,
      },
    ],
    guide: t('oauth_page.providers.wechat.guide'),
  },
  qq: {
    name: t('oauth_page.providers.qq.name'),
    displayIcon: 'QQ',
    color: '#12b7f5',
    fields: [
      {
        key: 'oauth_qq_app_id',
        label: t('oauth_page.providers.qq.fields.app_id.label'),
        placeholder: t('oauth_page.providers.qq.fields.app_id.placeholder'),
        sensitive: false,
      },
      {
        key: 'oauth_qq_app_key',
        label: t('oauth_page.providers.qq.fields.app_key.label'),
        placeholder: t('oauth_page.providers.qq.fields.app_key.placeholder'),
        sensitive: true,
      },
    ],
    guide: t('oauth_page.providers.qq.guide'),
  },
  google: {
    name: t('oauth_page.providers.google.name'),
    displayIcon: 'G',
    color: '#4285f4',
    fields: [
      {
        key: 'oauth_google_client_id',
        label: t('oauth_page.providers.google.fields.client_id.label'),
        placeholder: t('oauth_page.providers.google.fields.client_id.placeholder'),
        sensitive: false,
      },
      {
        key: 'oauth_google_client_secret',
        label: t('oauth_page.providers.google.fields.client_secret.label'),
        placeholder: t('oauth_page.providers.google.fields.client_secret.placeholder'),
        sensitive: true,
      },
    ],
    guide: t('oauth_page.providers.google.guide'),
  },
  github: {
    name: t('oauth_page.providers.github.name'),
    displayIcon: 'GH',
    color: '#333',
    fields: [
      {
        key: 'oauth_github_client_id',
        label: t('oauth_page.providers.github.fields.client_id.label'),
        placeholder: t('oauth_page.providers.github.fields.client_id.placeholder'),
        sensitive: false,
      },
      {
        key: 'oauth_github_client_secret',
        label: t('oauth_page.providers.github.fields.client_secret.label'),
        placeholder: t('oauth_page.providers.github.fields.client_secret.placeholder'),
        sensitive: true,
      },
    ],
    guide: t('oauth_page.providers.github.guide'),
  },
}));

async function loadSettings() {
  try {
    const { data: res } = await apiClient.get('/settings');
    const groups = res.data || [];
    const oauthGroup = groups.find(g => g.group === 'oauth');
    if (!oauthGroup) return;
    const settingMap = {};
    oauthGroup.settings.forEach(s => { settingMap[s.key] = s; });
    providers.value = Object.entries(providerDefs.value).map(([provider, def]) => ({
      provider,
      ...def,
      enabled: (settingMap[`oauth_${provider}_enabled`]?.value) === '1',
      enabledKey: `oauth_${provider}_enabled`,
      fields: (def.fields || []).map(f => ({ ...f, value: settingMap[f.key]?.value || '' })),
    }));
  } catch {
    ElMessage.error(t('messages.load_failed'));
  }
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
    ElMessage.success(t('oauth_page.messages.saved'));
  } catch {
    ElMessage.error(t('messages.failed'));
  } finally {
    saving.value = false;
  }
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
