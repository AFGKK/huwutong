<template>
  <div class="pwa-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-tag v-if="dashboard.enabled" type="success" effect="dark" size="small">{{ t(`${P}.status.enabled`) }}</el-tag>
        <el-tag v-else type="info" effect="dark" size="small">{{ t(`${P}.status.disabled`) }}</el-tag>
        <el-button @click="loadDashboard" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <el-alert
      :title="t(`${P}.intro_alert`)"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.service_worker?.registered ? 'text-success' : 'text-danger'">
            {{ dashboard.service_worker?.registered ? t(`${P}.status.ok`) : t(`${P}.status.ng`) }}
          </div>
          <div class="stat-label">{{ t(`${P}.stats.service_worker`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.manifest?.exists ? 'text-success' : 'text-danger'">
            {{ dashboard.manifest?.exists ? t(`${P}.status.ok`) : t(`${P}.status.ng`) }}
          </div>
          <div class="stat-label">{{ t(`${P}.stats.manifest`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.push_notifications?.subscribers || 0 }}</div>
          <div class="stat-label">{{ t(`${P}.stats.push_subscribers`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.caching?.strategy }}</div>
          <div class="stat-label">{{ t(`${P}.stats.cache_strategy`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 概览 -->
        <el-tab-pane :label="t(`${P}.tabs.overview`)" name="overview">
          <el-descriptions :column="2" border>
            <el-descriptions-item :label="overviewLabels.app_name">{{ dashboard.manifest?.name }}</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.short_name">{{ dashboard.manifest?.short_name }}</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.theme_color">
              <span :style="{ color: dashboard.manifest?.theme_color }">{{ dashboard.manifest?.theme_color }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.display">{{ dashboard.manifest?.display }}</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.sw_version">{{ dashboard.service_worker?.cache_version }}</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.sw_scope">{{ dashboard.service_worker?.scope }}</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.api_cache_ttl">{{ dashboard.caching?.api_cache_ttl }}s</el-descriptions-item>
            <el-descriptions-item :label="overviewLabels.offline_fallback">{{ dashboard.offline?.fallback_page }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <!-- Tab 2: 推送通知 -->
        <el-tab-pane :label="t(`${P}.tabs.push`)" name="push">
          <div class="section-toolbar">
            <span class="text-gray mb-2">
              {{ dashboard.push_notifications?.configured ? t(`${P}.push.vapid_ok`) : t(`${P}.push.vapid_missing`) }}
            </span>
          </div>

          <el-form :model="pushForm" label-width="80px" class="mb-4" @submit.prevent="sendPush">
            <el-form-item :label="t(`${P}.push.title`)">
              <el-input v-model="pushForm.title" :placeholder="t(`${P}.placeholders.push_title`)" maxlength="100" />
            </el-form-item>
            <el-form-item :label="t(`${P}.push.body`)">
              <el-input v-model="pushForm.body" type="textarea" :rows="3" :placeholder="t(`${P}.placeholders.push_body`)" maxlength="500" />
            </el-form-item>
            <el-form-item :label="t(`${P}.push.url`)">
              <el-input v-model="pushForm.url" :placeholder="t(`${P}.placeholders.push_url`)" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="pushSending">
                <el-icon><Bell /></el-icon> {{ t(`${P}.push.send`) }}
              </el-button>
              <span class="ml-2 text-gray">{{ t(`${P}.push.subscriber_count`, { count: dashboard.push_notifications?.subscribers || 0 }) }}</span>
            </el-form-item>
          </el-form>

          <!-- 订阅列表 -->
          <h4 class="mb-2">{{ t(`${P}.push.subscription_list`) }}</h4>
          <el-table :data="subscriptions" stripe size="small" v-loading="subLoading">
            <el-table-column prop="endpoint_prefix" :label="t(`${P}.push.col_endpoint`)" min-width="250" />
            <el-table-column prop="user_agent" :label="t(`${P}.push.col_user_agent`)" min-width="200" show-overflow-tooltip />
            <el-table-column prop="subscribed_at" :label="t(`${P}.push.col_subscribed_at`)" width="180" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: 缓存管理 -->
        <el-tab-pane :label="t(`${P}.tabs.cache`)" name="cache">
          <el-alert
            :title="t(`${P}.cache.warning`)"
            type="warning" show-icon :closable="false" class="mb-3"
          />
          <el-button type="danger" @click="handleClearCache" :loading="clearing">
            <el-icon><Delete /></el-icon> {{ t(`${P}.cache.clear_all`) }}
          </el-button>
          <el-divider />
          <el-button type="warning" @click="handleUpdateWorker" :loading="updating">
            <el-icon><Refresh /></el-icon> {{ t(`${P}.cache.update_sw`) }}
          </el-button>
          <p class="text-gray mt-2" style="font-size:0.85em">
            {{ t(`${P}.cache.sw_version`, { version: dashboard.stats?.last_sw_update || '—' }) }} |
            {{ t(`${P}.cache.estimated_size`, { size: formatBytes(dashboard.stats?.estimated_cache_size || 0) }) }}
          </p>
        </el-tab-pane>

        <!-- Tab 4: 部署指引 -->
        <el-tab-pane :label="t(`${P}.tabs.guide`)" name="guide">
          <el-steps direction="vertical" :active="-1">
            <el-step
              v-for="(step, i) in guideSteps"
              :key="i"
              :title="step.title"
              :description="step.description"
            />
          </el-steps>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, Refresh, Bell, Delete } from '@element-plus/icons-vue';
import pwaApi from '@/api/pwa';

const P = 'pwa_page';
const { t } = useI18n();

const loading = ref(false);
const activeTab = ref('overview');
const dashboard = ref({});
const subscriptions = ref([]);
const subLoading = ref(false);
const pushSending = ref(false);
const clearing = ref(false);
const updating = ref(false);

const pushForm = ref({ title: '', body: '', url: '' });

const overviewLabels = computed(() => ({
  app_name: t(`${P}.overview.app_name`),
  short_name: t(`${P}.overview.short_name`),
  theme_color: t(`${P}.overview.theme_color`),
  display: t(`${P}.overview.display`),
  sw_version: t(`${P}.overview.sw_version`),
  sw_scope: t(`${P}.overview.sw_scope`),
  api_cache_ttl: t(`${P}.overview.api_cache_ttl`),
  offline_fallback: t(`${P}.overview.offline_fallback`),
}));

const guideStepMeta = [
  { titleKey: `${P}.guide.step1_title`, descKey: `${P}.guide.step1_desc` },
  { titleKey: `${P}.guide.step2_title`, descKey: `${P}.guide.step2_desc` },
  { titleKey: `${P}.guide.step3_title`, descKey: `${P}.guide.step3_desc` },
  { titleKey: `${P}.guide.step4_title`, descKey: `${P}.guide.step4_desc` },
  { titleKey: `${P}.guide.step5_title`, descKey: `${P}.guide.step5_desc` },
  { titleKey: `${P}.guide.step6_title`, descKey: `${P}.guide.step6_desc` },
  { titleKey: `${P}.guide.step7_title`, descKey: `${P}.guide.step7_desc` },
];

const guideSteps = computed(() => guideStepMeta.map((m) => ({
  title: t(m.titleKey),
  description: t(m.descKey),
})));

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

async function loadDashboard() {
  loading.value = true;
  try {
    const { data } = await pwaApi.getDashboard();
    if (data.success) dashboard.value = data.data;
  } catch {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

async function loadSubscriptions() {
  subLoading.value = true;
  try {
    const { data } = await pwaApi.getSubscriptions();
    if (data.success) subscriptions.value = data.data;
  } finally {
    subLoading.value = false;
  }
}

async function sendPush() {
  if (!pushForm.value.title || !pushForm.value.body) {
    ElMessage.warning(t(`${P}.messages.enter_title_body`));
    return;
  }

  pushSending.value = true;
  try {
    const { data } = await pwaApi.sendNotification(pushForm.value);
    if (data.success) {
      ElMessage.success(data.message);
      pushForm.value = { title: '', body: '', url: '' };
    }
  } catch {
    ElMessage.error(t('messages.failed'));
  } finally {
    pushSending.value = false;
  }
}

async function handleClearCache() {
  try {
    await ElMessageBox.confirm(t(`${P}.messages.clear_cache_confirm`), t('actions.confirm'), {
      type: 'warning', confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'),
    });
    clearing.value = true;
    const { data } = await pwaApi.clearCache();
    if (data.success) ElMessage.success(data.message);
  } catch {
    // cancelled
  } finally {
    clearing.value = false;
  }
}

async function handleUpdateWorker() {
  updating.value = true;
  try {
    const { data } = await pwaApi.updateWorker();
    if (data.success) ElMessage.success(data.message);
    await loadDashboard();
  } catch {
    ElMessage.error(t('messages.failed'));
  } finally {
    updating.value = false;
  }
}

onMounted(async () => {
  await loadDashboard();
  if (activeTab.value === 'push') await loadSubscriptions();
});
</script>

<style scoped>
.pwa-page { padding: 0; }
.page-header {
  display: flex; justify-content: space-between;
  align-items: center; margin-bottom: 16px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.ml-2 { margin-left: 8px; }
.text-gray { color: #909399; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { margin-bottom: 12px; }
</style>
