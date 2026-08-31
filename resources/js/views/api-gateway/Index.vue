<template>
  <div class="api-gateway-page">
    <!-- 顶部栏 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">{{ t(`${P}.title`) }}</h2>
        <p class="page-desc text-secondary">{{ t(`${P}.subtitle`) }}</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ t(`${P}.actions.refresh`) }}
        </el-button>
      </el-col>
    </el-row>

    <!-- 引擎状态 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" :class="engineStatusClass">
          <div class="stat-label">{{ t(`${P}.stats.engine_status`) }}</div>
          <div class="stat-value">
            <el-icon v-if="dashboard?.available" class="status-icon success"><CircleCheckFilled /></el-icon>
            <el-icon v-else class="status-icon danger"><CircleCloseFilled /></el-icon>
            {{ dashboard?.available ? t(`${P}.connection.connected`) : t(`${P}.connection.disconnected`) }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.engine_type`) }}</div>
          <div class="stat-value">
            <el-tag :type="engineTagType" effect="dark" size="large">{{ engineLabel }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.version`) }}</div>
          <div class="stat-value">{{ dashboard?.version || '-' }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t(`${P}.stats.node_count`) }}</div>
          <div class="stat-value">{{ dashboard?.node_count ?? 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 资源统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="3" v-for="stat in resourceStats" :key="stat.key">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ stat.label }}</div>
          <div class="stat-value text-sm">{{ stat.value }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 插件配置状态 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="3" v-for="plugin in pluginStatusList" :key="plugin.key">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ plugin.label }}</div>
          <el-tag :type="plugin.enabled ? 'success' : 'info'" size="small">
            {{ plugin.enabled ? t(`${P}.status.enabled`) : t(`${P}.status.disabled`) }}
          </el-tag>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主面板 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 路由管理 -->
        <el-tab-pane :label="t(`${P}.tabs.routes`)" name="routes">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="handleSyncRoutes" :loading="syncing">
              <el-icon class="mr-1"><Refresh /></el-icon>{{ t(`${P}.actions.sync_routes`) }}
            </el-button>
            <el-tag v-if="syncResult" :type="syncResult.success ? 'success' : 'danger'" class="ml-2">
              {{ t(`${P}.sync_result`, { synced: syncResult.synced, total: syncResult.total }) }}
            </el-tag>
          </div>
          <el-table :data="routes" border stripe size="small" :loading="routesLoading" max-height="500">
            <el-table-column prop="name" :label="t(`${P}.columns.name`)" min-width="180" />
            <el-table-column prop="paths" :label="t(`${P}.columns.paths`)" min-width="200">
              <template #default="{ row }">
                <el-tag v-for="p in (row.paths || [])" :key="p" size="small" class="mr-1">{{ p }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="methods" :label="t(`${P}.columns.methods`)" width="200">
              <template #default="{ row }">
                <el-tag v-for="m in (row.methods || [])" :key="m" size="small" :type="methodTag(m)" class="mr-1">{{ m }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="hosts" :label="t(`${P}.columns.hosts`)" min-width="120" />
            <el-table-column prop="enabled" :label="t(`${P}.columns.status`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'" size="small">
                  {{ row.enabled ? t('actions.enable') : t('actions.disable') }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 服务列表 -->
        <el-tab-pane :label="t(`${P}.tabs.services`)" name="services">
          <el-table :data="services" border stripe size="small" :loading="servicesLoading" max-height="500">
            <el-table-column prop="name" :label="t(`${P}.columns.name`)" min-width="180" />
            <el-table-column prop="host" :label="t(`${P}.columns.host`)" width="160" />
            <el-table-column prop="port" :label="t(`${P}.columns.port`)" width="70" />
            <el-table-column prop="protocol" :label="t(`${P}.columns.protocol`)" width="80" />
            <el-table-column prop="retries" :label="t(`${P}.columns.retries`)" width="60" />
            <el-table-column prop="connect_timeout" :label="t(`${P}.columns.connect_timeout`)" width="100">
              <template #default="{ row }">{{ row.connect_timeout }}ms</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: Upstream -->
        <el-tab-pane :label="t(`${P}.tabs.upstreams`)" name="upstreams">
          <el-table :data="upstreams" border stripe size="small" :loading="upstreamsLoading" max-height="500">
            <el-table-column prop="name" :label="t(`${P}.columns.name`)" min-width="200" />
            <el-table-column prop="algorithm" :label="t(`${P}.columns.algorithm`)" width="120" />
            <el-table-column prop="targets_count" :label="t(`${P}.columns.targets_count`)" width="80" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 插件 -->
        <el-tab-pane :label="t(`${P}.tabs.plugins`)" name="plugins">
          <el-table :data="plugins" border stripe size="small" :loading="pluginsLoading" max-height="500">
            <el-table-column prop="name" :label="t(`${P}.columns.plugin_name`)" min-width="160" />
            <el-table-column prop="enabled" :label="t(`${P}.columns.status`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'" size="small">
                  {{ row.enabled ? t('actions.enable') : t('actions.disable') }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="route" :label="t(`${P}.columns.route`)" min-width="200" />
            <el-table-column prop="service" :label="t(`${P}.columns.service`)" min-width="200" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 5: 声明式配置 -->
        <el-tab-pane :label="t(`${P}.tabs.export`)" name="export">
          <div class="mb-2">
            <el-button size="small" @click="handleExport">
              <el-icon class="mr-1"><Download /></el-icon>{{ t(`${P}.actions.export_config`) }}
            </el-button>
            <el-button size="small" @click="handleClearCache">
              <el-icon class="mr-1"><Delete /></el-icon>{{ t(`${P}.actions.clear_cache`) }}
            </el-button>
          </div>
          <el-input
            v-model="exportedConfig"
            type="textarea"
            :rows="20"
            readonly
            class="config-textarea"
          />
        </el-tab-pane>

        <!-- Tab 6: 配置状态 -->
        <el-tab-pane :label="t(`${P}.tabs.config`)" name="config">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item :label="t(`${P}.config.engine`)">{{ dashboard?.engine || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.route_sync`)">
              <el-tag :type="configStatus?.route_sync ? 'success' : 'info'" size="small">
                {{ configStatus?.route_sync ? t(`${P}.status.enabled`) : t(`${P}.status.disabled`) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.rate_limiting`)">{{ configFlag(configStatus?.rate_limiting) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.ip_restriction`)">{{ configFlag(configStatus?.ip_restriction) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.cors`)">{{ configFlag(configStatus?.cors) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.ssl`)">{{ configFlag(configStatus?.ssl) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.logging`)">{{ configFlag(configStatus?.logging) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.prometheus`)">{{ configFlag(configStatus?.prometheus) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.auth_unloading`)">{{ configFlag(configStatus?.auth_unloading) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.proxy_cache`)">{{ configFlag(configStatus?.proxy_cache) }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.config.healthcheck`)">{{ configFlag(configStatus?.healthcheck) }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Delete } from '@element-plus/icons-vue';
import {
  getApiGatewayDashboard, getApiGatewayRoutes, syncApiGatewayRoutes,
  getApiGatewayServices, getApiGatewayUpstreams, getApiGatewayPlugins,
  getApiGatewayConfig, exportApiGatewayConfig, clearApiGatewayCache,
} from '../../api/apiGateway';

const P = 'api_gateway_page';
const { t } = useI18n();

const ENGINE_KEYS = ['kong', 'apisix', 'none'];
const RESOURCE_STAT_KEYS = ['routes', 'services', 'upstreams', 'plugins'];
const PLUGIN_KEYS = ['rate_limiting', 'ip_restriction', 'cors', 'ssl', 'logging'];

const loading = ref(false);
const activeTab = ref('routes');
const dashboard = ref(null);
const configStatus = ref(null);

const routes = ref([]);
const routesLoading = ref(false);
const syncing = ref(false);
const syncResult = ref(null);

const services = ref([]);
const servicesLoading = ref(false);
const upstreams = ref([]);
const upstreamsLoading = ref(false);
const plugins = ref([]);
const pluginsLoading = ref(false);
const exportedConfig = ref('');

const engineLabels = computed(() =>
  Object.fromEntries(ENGINE_KEYS.map((key) => [key, t(`${P}.engines.${key}`)])),
);

const engineLabel = computed(() => {
  const engine = dashboard.value?.engine;
  return engineLabels.value[engine] || engine || '-';
});

const engineTagType = computed(() => {
  if (dashboard.value?.engine === 'none') return 'info';
  return dashboard.value?.engine === 'kong' ? 'danger' : 'warning';
});

const engineStatusClass = computed(() => {
  return dashboard.value?.available ? 'status-ok' : 'status-critical';
});

const resourceStats = computed(() => {
  const s = dashboard.value?.stats || {};
  return RESOURCE_STAT_KEYS.map((key) => ({
    key,
    label: t(`${P}.stats.${key}`),
    value: s[key] ?? 0,
  }));
});

const pluginStatusList = computed(() => {
  const c = dashboard.value?.config || {};
  return PLUGIN_KEYS.map((key) => ({
    key,
    label: t(`${P}.plugins.${key}`),
    enabled: c[key],
  }));
});

function configFlag(value) {
  return value ? t(`${P}.flags.yes`) : t(`${P}.flags.no`);
}

function methodTag(m) {
  const map = { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'warning', DELETE: 'danger' };
  return map[m] || 'info';
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashboardRes, configRes] = await Promise.all([
      getApiGatewayDashboard(),
      getApiGatewayConfig(),
    ]);
    dashboard.value = dashboardRes.data;
    configStatus.value = configRes.data;
  } catch (e) {
    ElMessage.error(t(`${P}.messages.fetch_dashboard_failed`));
  } finally {
    loading.value = false;
  }
}

async function loadRoutes() {
  routesLoading.value = true;
  try {
    const res = await getApiGatewayRoutes();
    routes.value = res.data || [];
  } catch (e) {
    ElMessage.error(t(`${P}.messages.fetch_routes_failed`));
  } finally {
    routesLoading.value = false;
  }
}

async function handleSyncRoutes() {
  syncing.value = true;
  try {
    const res = await syncApiGatewayRoutes();
    syncResult.value = res.data;
    ElMessage.success(res.message || t(`${P}.messages.sync_done`));
    await loadRoutes();
  } catch (e) {
    ElMessage.error(t(`${P}.messages.sync_failed`));
  } finally {
    syncing.value = false;
  }
}

async function loadServices() {
  servicesLoading.value = true;
  try {
    const res = await getApiGatewayServices();
    services.value = res.data || [];
  } catch (e) {
    // silencio
  } finally {
    servicesLoading.value = false;
  }
}

async function loadUpstreams() {
  upstreamsLoading.value = true;
  try {
    const res = await getApiGatewayUpstreams();
    upstreams.value = res.data || [];
  } catch (e) {
    // silencio
  } finally {
    upstreamsLoading.value = false;
  }
}

async function loadPlugins() {
  pluginsLoading.value = true;
  try {
    const res = await getApiGatewayPlugins();
    plugins.value = res.data || [];
  } catch (e) {
    // silencio
  } finally {
    pluginsLoading.value = false;
  }
}

async function handleExport() {
  try {
    const res = await exportApiGatewayConfig();
    exportedConfig.value = JSON.stringify(res.data, null, 2);
    ElMessage.success(t(`${P}.messages.export_done`));
  } catch (e) {
    ElMessage.error(t(`${P}.messages.export_failed`));
  }
}

async function handleClearCache() {
  try {
    await clearApiGatewayCache();
    ElMessage.success(t(`${P}.messages.cache_cleared`));
    await refreshAll();
  } catch (e) {
    ElMessage.error(t(`${P}.messages.clear_cache_failed`));
  }
}

const tabLoadMap = {
  routes: loadRoutes,
  services: loadServices,
  upstreams: loadUpstreams,
  plugins: loadPlugins,
};

watch(() => activeTab.value, (tab) => {
  if (tabLoadMap[tab]) tabLoadMap[tab]();
});

onMounted(() => {
  refreshAll();
});
</script>

<style scoped>
.api-gateway-page { padding: 16px; }
.page-title { margin: 0; font-size: 20px; font-weight: 600; }
.page-desc { margin: 4px 0 0; font-size: 13px; }
.stat-card { border-radius: 8px; }
.stat-card.status-ok { border-left: 4px solid #67c23a; }
.stat-card.status-critical { border-left: 4px solid #f56c6c; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.stat-value.text-sm { font-size: 16px; }
.status-icon { font-size: 20px; }
.status-icon.success { color: #67c23a; }
.status-icon.danger { color: #f56c6c; }
.text-secondary { color: #909399; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.mr-1 { margin-right: 4px; }
.config-textarea { font-family: 'Courier New', monospace; font-size: 12px; }
</style>
