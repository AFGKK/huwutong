<template>
  <div class="grpc-page">
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">gRPC 服务间通信</h2>
        <p class="page-desc text-secondary">License · Device · Billing · Notification — 内部服务 gRPC 调用</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
      </el-col>
    </el-row>

    <!-- 状态概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" :class="enabled ? 'status-ok' : 'status-critical'">
          <div class="stat-label">服务状态</div>
          <div class="stat-value">
            <el-tag :type="enabled ? 'success' : 'info'" effect="dark" size="large">
              {{ enabled ? '已启用' : '未启用' }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">运行模式</div>
          <div class="stat-value">
            <el-tag :type="modeTagType" effect="dark" size="large">{{ modeLabel }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">健康服务</div>
          <div class="stat-value">{{ healthyCount }}<small class="text-secondary"> / {{ totalCount }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">客户端超时</div>
          <div class="stat-value">{{ clientTimeout }}<small>s</small></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 服务状态卡片 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span><el-icon class="mr-1"><Connection /></el-icon> 服务状态</span>
      </template>
      <el-table :data="serviceList" border stripe size="small">
        <el-table-column prop="name" label="服务" width="150">
          <template #default="{ row }">
            <el-tag size="small">{{ serviceIcon(row.name) }} {{ row.name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="健康状态" width="120">
          <template #default="{ row }">
            <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">
              {{ row.healthy ? '✓ 正常' : '✗ 异常' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="熔断器" width="120">
          <template #default="{ row }">
            <el-tag :type="row.circuit_breaker?.circuit_open ? 'danger' : 'info'" size="small">
              {{ row.circuit_breaker?.circuit_open ? '已打开' : '正常' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="circuit_breaker.failure_count" label="失败次数" width="80" />
        <el-table-column prop="circuit_breaker.threshold" label="熔断阈值" width="80" />
      </el-table>
    </el-card>

    <!-- 端点列表 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span><el-icon class="mr-1"><Position /></el-icon> 服务端点</span>
      </template>
      <el-table :data="endpointList" border stripe size="small">
        <el-table-column prop="name" label="服务" width="150" />
        <el-table-column prop="address" label="gRPC 地址" min-width="200" />
        <el-table-column prop="host" label="Host" width="160" />
        <el-table-column prop="port" label="Port" width="70" />
      </el-table>
    </el-card>

    <!-- 配置详情 -->
    <el-card shadow="never">
      <template #header>
        <span><el-icon class="mr-1"><Setting /></el-icon> 配置详情</span>
      </template>
      <el-descriptions :column="3" border size="small">
        <el-descriptions-item label="启用">{{ config?.enabled ? '是' : '否' }}</el-descriptions-item>
        <el-descriptions-item label="模式">{{ config?.mode }}</el-descriptions-item>
        <el-descriptions-item label="服务端端口">{{ config?.server_port }}</el-descriptions-item>
        <el-descriptions-item label="客户端超时">{{ config?.client_timeout }}s</el-descriptions-item>
        <el-descriptions-item label="重试次数">{{ config?.client_retries }}</el-descriptions-item>
        <el-descriptions-item label="服务发现">{{ config?.discovery }}</el-descriptions-item>
        <el-descriptions-item label="Proto 文件" :span="3">{{ protoList }}</el-descriptions-item>
      </el-descriptions>

      <el-button size="small" class="mt-2" @click="handleResetBreaker">
        <el-icon class="mr-1"><RefreshRight /></el-icon>重置所有熔断器
      </el-button>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Connection, Position, Setting, RefreshRight } from '@element-plus/icons-vue';
import {
  getGrpcDashboard, getGrpcConfig, getGrpcEndpoints,
  getGrpcCircuitBreaker, resetGrpcCircuitBreaker,
} from '../../api/grpc';

const loading = ref(false);
const dashboard = ref(null);
const config = ref(null);
const endpoints = ref(null);
const circuitBreaker = ref(null);

const enabled = computed(() => dashboard.value?.enabled ?? false);
const modeLabel = computed(() => {
  const map = { grpc: 'gRPC (原生)', http2: 'HTTP/2 (模拟)', rest: 'REST (回退)' };
  return map[dashboard.value?.mode] || dashboard.value?.mode || '-';
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

function serviceIcon(name) {
  const icons = { license: '🔑', device: '💻', billing: '💰', notification: '🔔' };
  return icons[name] || '📦';
}

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
    ElMessage.error('获取 gRPC 状态失败');
  } finally {
    loading.value = false;
  }
}

async function handleResetBreaker() {
  try {
    await resetGrpcCircuitBreaker();
    ElMessage.success('所有 gRPC 熔断器已重置');
    await refreshAll();
  } catch (e) {
    ElMessage.error('重置失败');
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
