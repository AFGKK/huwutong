<template>
  <div class="api-gateway-page">
    <!-- 顶部栏 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">API 网关统一层</h2>
        <p class="page-desc text-secondary">Kong / APISIX API 网关管理与监控</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
      </el-col>
    </el-row>

    <!-- 引擎状态 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" :class="engineStatusClass">
          <div class="stat-label">引擎状态</div>
          <div class="stat-value">
            <el-icon v-if="dashboard?.available" class="status-icon success"><CircleCheckFilled /></el-icon>
            <el-icon v-else class="status-icon danger"><CircleCloseFilled /></el-icon>
            {{ dashboard?.available ? '已连接' : '未连接' }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">引擎类型</div>
          <div class="stat-value">
            <el-tag :type="engineTagType" effect="dark" size="large">{{ engineLabel }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">版本</div>
          <div class="stat-value">{{ dashboard?.version || '-' }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">节点数</div>
          <div class="stat-value">{{ dashboard?.node_count ?? 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 资源统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="3" v-for="stat in resourceStats" :key="stat.label">
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
          <el-tag :type="plugin.enabled ? 'success' : 'info'" size="small">{{ plugin.enabled ? '已启用' : '已禁用' }}</el-tag>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主面板 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 路由管理 -->
        <el-tab-pane label="路由管理" name="routes">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="handleSyncRoutes" :loading="syncing">
              <el-icon class="mr-1"><Refresh /></el-icon>同步路由
            </el-button>
            <el-tag v-if="syncResult" :type="syncResult.success ? 'success' : 'danger'" class="ml-2">
              同步 {{ syncResult.synced }}/{{ syncResult.total }} 条
            </el-tag>
          </div>
          <el-table :data="routes" border stripe size="small" :loading="routesLoading" max-height="500">
            <el-table-column prop="name" label="名称" min-width="180" />
            <el-table-column prop="paths" label="路径" min-width="200">
              <template #default="{ row }">
                <el-tag v-for="p in (row.paths || [])" :key="p" size="small" class="mr-1">{{ p }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="methods" label="方法" width="200">
              <template #default="{ row }">
                <el-tag v-for="m in (row.methods || [])" :key="m" size="small" :type="methodTag(m)" class="mr-1">{{ m }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="hosts" label="Hosts" min-width="120" />
            <el-table-column prop="enabled" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'" size="small">{{ row.enabled ? '启用' : '禁用' }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 服务列表 -->
        <el-tab-pane label="服务列表" name="services">
          <el-table :data="services" border stripe size="small" :loading="servicesLoading" max-height="500">
            <el-table-column prop="name" label="名称" min-width="180" />
            <el-table-column prop="host" label="Host" width="160" />
            <el-table-column prop="port" label="端口" width="70" />
            <el-table-column prop="protocol" label="协议" width="80" />
            <el-table-column prop="retries" label="重试" width="60" />
            <el-table-column prop="connect_timeout" label="连接超时" width="100">
              <template #default="{ row }">{{ row.connect_timeout }}ms</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: Upstream -->
        <el-tab-pane label="上游节点" name="upstreams">
          <el-table :data="upstreams" border stripe size="small" :loading="upstreamsLoading" max-height="500">
            <el-table-column prop="name" label="名称" min-width="200" />
            <el-table-column prop="algorithm" label="算法" width="120" />
            <el-table-column prop="targets_count" label="目标数" width="80" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 插件 -->
        <el-tab-pane label="插件列表" name="plugins">
          <el-table :data="plugins" border stripe size="small" :loading="pluginsLoading" max-height="500">
            <el-table-column prop="name" label="插件名称" min-width="160" />
            <el-table-column prop="enabled" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'" size="small">{{ row.enabled ? '启用' : '禁用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="route" label="所属路由" min-width="200" />
            <el-table-column prop="service" label="所属服务" min-width="200" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 5: 声明式配置 -->
        <el-tab-pane label="声明式配置" name="export">
          <div class="mb-2">
            <el-button size="small" @click="handleExport">
              <el-icon class="mr-1"><Download /></el-icon>导出声明式配置
            </el-button>
            <el-button size="small" @click="handleClearCache">
              <el-icon class="mr-1"><Delete /></el-icon>清除缓存
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
        <el-tab-pane label="配置状态" name="config">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="引擎">{{ dashboard?.engine || '-' }}</el-descriptions-item>
            <el-descriptions-item label="路由同步">
              <el-tag :type="configStatus?.route_sync ? 'success' : 'info'" size="small">{{ configStatus?.route_sync ? '已启用' : '已禁用' }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="全局限流">{{ configStatus?.rate_limiting ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="IP 黑白名单">{{ configStatus?.ip_restriction ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="CORS">{{ configStatus?.cors ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="SSL">{{ configStatus?.ssl ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="日志采集">{{ configStatus?.logging ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="Prometheus 监控">{{ configStatus?.prometheus ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="认证卸载">{{ configStatus?.auth_unloading ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="代理缓存">{{ configStatus?.proxy_cache ? '✅' : '❌' }}</el-descriptions-item>
            <el-descriptions-item label="健康检查">{{ configStatus?.healthcheck ? '✅' : '❌' }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Delete } from '@element-plus/icons-vue';
import {
  getApiGatewayDashboard, getApiGatewayRoutes, syncApiGatewayRoutes,
  getApiGatewayServices, getApiGatewayUpstreams, getApiGatewayPlugins,
  getApiGatewayConfig, exportApiGatewayConfig, clearApiGatewayCache,
} from '../../api/apiGateway';

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

const engineLabel = computed(() => {
  const map = { kong: 'Kong', apisix: 'APISIX', none: '直连模式（未启用）' };
  return map[dashboard.value?.engine] || dashboard.value?.engine || '-';
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
  return [
    { label: '路由', value: s.routes ?? 0 },
    { label: '服务', value: s.services ?? 0 },
    { label: '上游节点', value: s.upstreams ?? 0 },
    { label: '插件', value: s.plugins ?? 0 },
  ];
});

const pluginStatusList = computed(() => {
  const c = dashboard.value?.config || {};
  return [
    { key: 'rate_limiting', label: '限流', enabled: c.rate_limiting },
    { key: 'ip_restriction', label: 'IP黑白名单', enabled: c.ip_restriction },
    { key: 'cors', label: 'CORS', enabled: c.cors },
    { key: 'ssl', label: 'SSL', enabled: c.ssl },
    { key: 'logging', label: '日志', enabled: c.logging },
  ];
});

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
    ElMessage.error('获取网关状态失败');
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
    ElMessage.error('获取路由列表失败');
  } finally {
    routesLoading.value = false;
  }
}

async function handleSyncRoutes() {
  syncing.value = true;
  try {
    const res = await syncApiGatewayRoutes();
    syncResult.value = res.data;
    ElMessage.success(res.message || '路由同步完成');
    await loadRoutes();
  } catch (e) {
    ElMessage.error('同步失败');
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
    ElMessage.success('声明式配置已导出');
  } catch (e) {
    ElMessage.error('导出失败');
  }
}

async function handleClearCache() {
  try {
    await clearApiGatewayCache();
    ElMessage.success('缓存已清除');
    await refreshAll();
  } catch (e) {
    ElMessage.error('清除失败');
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
