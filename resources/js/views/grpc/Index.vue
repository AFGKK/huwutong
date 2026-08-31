<template>
  <div class="grpc-page">
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">{{ t('grpc_page.title') }}</h2>
        <p class="page-desc text-secondary">{{ t('grpc_page.desc') }}</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ t('grpc_page.refresh') }}
        </el-button>
      </el-col>
    </el-row>

    <!-- 状态概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" :class="enabled ? 'status-ok' : 'status-critical'">
          <div class="stat-label">{{ t('grpc_page.stats.service_status') }}</div>
          <div class="stat-value">
            <el-tag :type="enabled ? 'success' : 'info'" effect="dark" size="large">
              {{ enabled ? statusLabels.enabled : statusLabels.disabled }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('grpc_page.stats.run_mode') }}</div>
          <div class="stat-value">
            <el-tag :type="modeTagType" effect="dark" size="large">{{ modeLabel }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('grpc_page.stats.healthy_services') }}</div>
          <div class="stat-value">{{ healthyCount }}<small class="text-secondary"> / {{ totalCount }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('grpc_page.stats.client_timeout') }}</div>
          <div class="stat-value">{{ clientTimeout }}<small>s</small></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 服务状态卡片 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span><el-icon class="mr-1"><Connection /></el-icon> {{ t('grpc_page.sections.service_status') }}</span>
      </template>
      <el-table :data="serviceList" border stripe size="small">
        <el-table-column prop="name" :label="t('grpc_page.cols.service')" width="150">
          <template #default="{ row }">
            <el-tag size="small">{{ row.name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('grpc_page.cols.health')" width="120">
          <template #default="{ row }">
            <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">
              {{ row.healthy ? statusLabels.healthy : statusLabels.unhealthy }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('grpc_page.cols.circuit_breaker')" width="120">
          <template #default="{ row }">
            <el-tag :type="row.circuit_breaker?.circuit_open ? 'danger' : 'info'" size="small">
              {{ row.circuit_breaker?.circuit_open ? statusLabels.circuit_open : statusLabels.circuit_closed }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="circuit_breaker.failure_count" :label="t('grpc_page.cols.failure_count')" width="80" />
        <el-table-column prop="circuit_breaker.threshold" :label="t('grpc_page.cols.threshold')" width="80" />
      </el-table>
    </el-card>

    <!-- 端点列表 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span><el-icon class="mr-1"><Position /></el-icon> {{ t('grpc_page.sections.endpoints') }}</span>
      </template>
      <el-table :data="endpointList" border stripe size="small">
        <el-table-column prop="name" :label="t('grpc_page.cols.service')" width="150" />
        <el-table-column prop="address" :label="t('grpc_page.cols.address')" min-width="200" />
        <el-table-column prop="host" :label="t('grpc_page.cols.host')" width="160" />
        <el-table-column prop="port" :label="t('grpc_page.cols.port')" width="70" />
      </el-table>
    </el-card>

    <!-- 配置详情 -->
    <el-card shadow="never">
      <template #header>
        <span><el-icon class="mr-1"><Setting /></el-icon> {{ t('grpc_page.sections.config') }}</span>
      </template>
      <el-descriptions :column="3" border size="small">
        <el-descriptions-item :label="t('grpc_page.config.enabled')">{{ config?.enabled ? statusLabels.yes : statusLabels.no }}</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.mode')">{{ config?.mode }}</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.server_port')">{{ config?.server_port }}</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.client_timeout')">{{ config?.client_timeout }}s</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.client_retries')">{{ config?.client_retries }}</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.discovery')">{{ config?.discovery }}</el-descriptions-item>
        <el-descriptions-item :label="t('grpc_page.config.proto_files')" :span="3">{{ protoList }}</el-descriptions-item>
      </el-descriptions>

      <el-button size="small" class="mt-2" @click="handleResetBreaker">
        <el-icon class="mr-1"><RefreshRight /></el-icon>{{ t('grpc_page.btn_reset_breakers') }}
      </el-button>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Connection, Position, Setting, RefreshRight } from '@element-plus/icons-vue';
import {
  getGrpcDashboard, getGrpcConfig, getGrpcEndpoints,
  getGrpcCircuitBreaker, resetGrpcCircuitBreaker,
} from '../../api/grpc';

const { t } = useI18n();

const loading = ref(false);
const dashboard = ref(null);
const config = ref(null);
const endpoints = ref(null);
const circuitBreaker = ref(null);

const statusLabels = computed(() => ({
  enabled: t('grpc_page.status.enabled'),
  disabled: t('grpc_page.status.disabled'),
  healthy: t('grpc_page.status.healthy'),
  unhealthy: t('grpc_page.status.unhealthy'),
  circuit_open: t('grpc_page.status.circuit_open'),
  circuit_closed: t('grpc_page.status.circuit_closed'),
  yes: t('grpc_page.status.yes'),
  no: t('grpc_page.status.no'),
}));

const modeLabels = computed(() => ({
  grpc: t('grpc_page.mode.grpc'),
  http2: t('grpc_page.mode.http2'),
  rest: t('grpc_page.mode.rest'),
}));

const enabled = computed(() => dashboard.value?.enabled ?? false);
const modeLabel = computed(() => {
  const mode = dashboard.value?.mode;
  return modeLabels.value[mode] || mode || '-';
});
const modeTagType = computed(() => {
  const map = { grpc: 'success', http2: 'warning', rest: 'info' };
  return map[dashboard.value?.mode] || 'info';
});
const healthyCount = computed(() => dashboard.value?.healthy_count ?? 0);
const totalCount = computed(() => dashboard.value?.total_count ?? 0);
const clientTimeout = computed(() => config.value?.client_timeout ?? 10);

const serviceList = computed(() => {
  const services = dashboard.value?.services || {};
  return Object.values(services);
});

const endpointList = computed(() => {
  const eps = endpoints.value || {};
  return Object.entries(eps).map(([name, ep]) => ({
    name,
    address: ep.address,
    host: ep.host,
    port: ep.port,
  }));
});

const protoList = computed(() => {
  return (config.value?.protos || []).join(', ');
});

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, configRes, epRes, cbRes] = await Promise.all([
      getGrpcDashboard(),
      getGrpcConfig(),
      getGrpcEndpoints(),
      getGrpcCircuitBreaker(),
    ]);
    dashboard.value = dashRes.data;
    config.value = configRes.data;
    endpoints.value = epRes.data;
    circuitBreaker.value = cbRes.data;
  } catch (e) {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

async function handleResetBreaker() {
  try {
    await resetGrpcCircuitBreaker();
    ElMessage.success(t('grpc_page.messages.breaker_reset'));
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('messages.failed'));
  }
}

onMounted(refreshAll);
</script>

<style scoped>
.grpc-page { padding: 16px; }
.page-title { margin: 0; font-size: 20px; font-weight: 600; }
.page-desc { margin: 4px 0 0; font-size: 13px; }
.stat-card { border-radius: 8px; }
.stat-card.status-ok { border-left: 4px solid #67c23a; }
.stat-card.status-critical { border-left: 4px solid #f56c6c; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.stat-value small { font-size: 13px; font-weight: 400; }
.text-secondary { color: #909399; }
.text-right { text-align: right; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.mr-1 { margin-right: 4px; }
</style>
