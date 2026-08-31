<template>
  <div class="redis-ha-page">
    <!-- 顶部操作栏 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">{{ t('redis_ha_page.title') }}</h2>
        <p class="page-desc text-secondary">{{ t('redis_ha_page.subtitle') }}</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ t('redis_ha_page.refresh') }}
        </el-button>
        <el-button type="danger" plain @click="handleFlush" :loading="flushing">
          <el-icon class="mr-1"><Delete /></el-icon>{{ t('redis_ha_page.flush_cache') }}
        </el-button>
      </el-col>
    </el-row>

    <!-- 状态概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" :class="statusClass">
          <div class="stat-label">{{ t('redis_ha_page.stats.status') }}</div>
          <div class="stat-value">
            <el-icon v-if="overallStatus === 'ok'" class="status-icon success"><CircleCheckFilled /></el-icon>
            <el-icon v-else-if="overallStatus === 'warning'" class="status-icon warning"><WarningFilled /></el-icon>
            <el-icon v-else class="status-icon danger"><CircleCloseFilled /></el-icon>
            {{ statusText }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.mode') }}</div>
          <div class="stat-value">
            <el-tag :type="modeTagType" effect="dark" size="large">{{ redisMode }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.latency') }}</div>
          <div class="stat-value" :class="latencyClass">{{ latency }} <small>ms</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.hit_ratio') }}</div>
          <div class="stat-value" :class="hitRatioClass">{{ hitRatio }}<small>%</small></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 健康指标行 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.role') }}</div>
          <div class="stat-value">
            <el-tag :type="role === 'master' ? 'success' : 'warning'" size="large">{{ roleText }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.slave_count') }}</div>
          <div class="stat-value">{{ connectedSlaves }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.failover') }}</div>
          <div class="stat-value">
            <el-tag :type="failoverAvailable ? 'success' : 'danger'" size="large">
              {{ failoverAvailable ? t('redis_ha_page.availability.yes') : t('redis_ha_page.availability.no') }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('redis_ha_page.stats.uptime') }}</div>
          <div class="stat-value">{{ uptimeFormatted }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 内存使用 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span><el-icon class="mr-1"><DataAnalysis /></el-icon> {{ t('redis_ha_page.memory.title') }}</span>
      </template>
      <el-row :gutter="16" align="middle">
        <el-col :span="16">
          <el-progress
            :percentage="memoryPercent"
            :status="memoryPercent > 80 ? 'exception' : memoryPercent > 50 ? 'warning' : 'success'"
            :stroke-width="20"
            :text-inside="true"
            :format="memoryFormat"
          />
        </el-col>
        <el-col :span="8" class="text-secondary">
          {{ t('redis_ha_page.memory.used_total', { used: memoryUsed, max: memoryMax }) }}
          <el-tag size="small" class="ml-2">{{ t('redis_ha_page.memory.peak', { peak: memoryPeak }) }}</el-tag>
        </el-col>
      </el-row>
    </el-card>

    <!-- 主面板：Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: Sentinel 状态 -->
        <el-tab-pane :label="t('redis_ha_page.tabs.sentinel')" name="sentinel">
          <div v-if="loadingSentinel" class="text-center py-4">
            <el-icon class="is-loading" :size="32"><Loading /></el-icon>
            <p class="mt-2">{{ t('redis_ha_page.sentinel.loading') }}</p>
          </div>
          <div v-else-if="sentinelError" class="text-center py-4">
            <el-empty :description="t('redis_ha_page.sentinel.unavailable')">
              <template #image>
                <el-icon :size="48" color="#e6a23c"><WarningFilled /></el-icon>
              </template>
              <p class="text-secondary">{{ sentinelError }}</p>
            </el-empty>
          </div>
          <div v-else>
            <!-- Master 信息 -->
            <h4 class="mb-2">{{ t('redis_ha_page.sentinel.master_title') }}</h4>
            <el-descriptions :column="3" border size="small" class="mb-4">
              <el-descriptions-item :label="t('redis_ha_page.cols.address')">{{ sentinelMaster?.host }}:{{ sentinelMaster?.port }}</el-descriptions-item>
              <el-descriptions-item :label="t('redis_ha_page.cols.status')">
                <el-tag :type="sentinelMaster?.status === 'ok' ? 'success' : 'danger'" size="small">
                  {{ sentinelMaster?.status }}
                </el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('redis_ha_page.cols.role')">{{ sentinelMaster?.role }}</el-descriptions-item>
            </el-descriptions>

            <!-- 从库列表 -->
            <h4 class="mb-2">
              {{ t('redis_ha_page.sentinel.slaves_title') }}
              <el-tag size="small">{{ sentinelSlaves?.length || 0 }}</el-tag>
            </h4>
            <el-table :data="sentinelSlaves" border stripe size="small" class="mb-4">
              <el-table-column prop="host" :label="t('redis_ha_page.cols.address')" min-width="160" />
              <el-table-column prop="port" :label="t('redis_ha_page.cols.port')" width="80" />
              <el-table-column :label="t('redis_ha_page.cols.status')" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'ok' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="last_hello" :label="t('redis_ha_page.cols.last_hello')" min-width="160" />
            </el-table>

            <!-- 哨兵列表 -->
            <h4 class="mb-2">
              {{ t('redis_ha_page.sentinel.sentinels_title') }}
              <el-tag size="small">{{ sentinelNodes?.length || 0 }}</el-tag>
            </h4>
            <el-table :data="sentinelNodes" border stripe size="small">
              <el-table-column prop="host" :label="t('redis_ha_page.cols.address')" min-width="160" />
              <el-table-column prop="port" :label="t('redis_ha_page.cols.port')" width="80" />
              <el-table-column :label="t('redis_ha_page.cols.status')" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'ok' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="last_hello" :label="t('redis_ha_page.cols.last_hello')" min-width="160" />
            </el-table>
          </div>
        </el-tab-pane>

        <!-- Tab 2: 告警信息 -->
        <el-tab-pane :label="t('redis_ha_page.tabs.alerts')" name="alerts">
          <div v-if="issues.length === 0" class="text-center py-4">
            <el-result
              icon="success"
              :title="t('redis_ha_page.alerts.all_ok_title')"
              :sub-title="t('redis_ha_page.alerts.all_ok_sub')"
            />
          </div>
          <div v-else>
            <el-timeline>
              <el-timeline-item
                v-for="(issue, idx) in issues"
                :key="idx"
                :type="issue.severity === 'critical' ? 'danger' : 'warning'"
                :color="issue.severity === 'critical' ? '#f56c6c' : '#e6a23c'"
              >
                <span class="font-500">{{ issue.component }}</span>
                <p class="text-secondary mt-1">{{ issue.message }}</p>
                <el-tag :type="issue.severity === 'critical' ? 'danger' : 'warning'" size="small">
                  {{ issue.severity === 'critical' ? t('redis_ha_page.alerts.severity_critical') : t('redis_ha_page.alerts.severity_warning') }}
                </el-tag>
              </el-timeline-item>
            </el-timeline>
          </div>
        </el-tab-pane>

        <!-- Tab 3: 详细统计 -->
        <el-tab-pane :label="t('redis_ha_page.tabs.stats')" name="stats">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item :label="t('redis_ha_page.detail.ping')">
              {{ statsData?.ping ? t('redis_ha_page.detail.ping_ok') : t('redis_ha_page.detail.ping_fail') }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.latency')">{{ statsData?.latency_ms }} ms</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.role')">{{ statsData?.role }}</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.slave_count')">{{ statsData?.connected_slaves }}</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.connections')">{{ statsData?.connections }}</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.commands')">{{ statsData?.commands }}</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.hit_ratio')">{{ statsData?.hit_ratio }}%</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.detail.uptime')">{{ uptimeFormatted }}</el-descriptions-item>
          </el-descriptions>

          <!-- 熔断器状态 -->
          <h4 class="mt-4 mb-2">{{ t('redis_ha_page.circuit_breaker.title') }}</h4>
          <el-descriptions :column="3" border size="small">
            <el-descriptions-item :label="t('redis_ha_page.circuit_breaker.status')">
              <el-tag :type="circuitBreakerOpen ? 'danger' : 'success'" size="small">
                {{ circuitBreakerOpen ? t('redis_ha_page.circuit_breaker.open') : t('redis_ha_page.circuit_breaker.closed') }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.circuit_breaker.failure_count')">{{ circuitBreaker?.failure_count || 0 }}</el-descriptions-item>
            <el-descriptions-item :label="t('redis_ha_page.circuit_breaker.last_failure')">
              {{ circuitBreaker?.last_failure_at || t('redis_ha_page.circuit_breaker.none') }}
            </el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <!-- Tab 4: 操作 -->
        <el-tab-pane :label="t('redis_ha_page.tabs.actions')" name="actions">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-card shadow="never" class="action-card" @click="handleFailover">
                <el-icon :size="32" color="#e6a23c"><SwitchButton /></el-icon>
                <h4>{{ t('redis_ha_page.ops.failover_title') }}</h4>
                <p class="text-secondary text-sm">{{ t('redis_ha_page.ops.failover_desc') }}</p>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="never" class="action-card" @click="handleFlush">
                <el-icon :size="32" color="#f56c6c"><Delete /></el-icon>
                <h4>{{ t('redis_ha_page.ops.flush_title') }}</h4>
                <p class="text-secondary text-sm">{{ t('redis_ha_page.ops.flush_desc') }}</p>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="never" class="action-card" @click="handleResetBreaker">
                <el-icon :size="32" color="#0f172a"><RefreshRight /></el-icon>
                <h4>{{ t('redis_ha_page.ops.reset_breaker_title') }}</h4>
                <p class="text-secondary text-sm">{{ t('redis_ha_page.ops.reset_breaker_desc') }}</p>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Refresh, Delete, CircleCheckFilled, WarningFilled, CircleCloseFilled,
  DataAnalysis, Loading, SwitchButton, RefreshRight,
} from '@element-plus/icons-vue';
import {
  getRedisHaStatus,
  getRedisHaSentinel,
  getRedisHaStats,
  triggerRedisFailover,
  flushRedisCache,
  resetRedisCircuitBreaker,
} from '../../api/redisHa';

const { t } = useI18n();

// ─── 状态 ───
const loading = ref(false);
const flushing = ref(false);
const activeTab = ref('sentinel');
const loadingSentinel = ref(false);

const statusData = ref(null);
const sentinelData = ref(null);
const statsData = ref(null);

// ─── 计算属性 ───
const overallStatus = computed(() => statusData.value?.overall_status || 'unknown');

const statusMap = computed(() => ({
  ok: t('redis_ha_page.status.ok'),
  warning: t('redis_ha_page.status.warning'),
  critical: t('redis_ha_page.status.critical'),
  unknown: t('redis_ha_page.status.unknown'),
}));

const statusText = computed(() => statusMap.value[overallStatus.value] || statusMap.value.unknown);

const statusClass = computed(() => {
  if (overallStatus.value === 'ok') return 'status-ok';
  if (overallStatus.value === 'warning') return 'status-warning';
  return 'status-critical';
});

const redisMode = computed(() => statusData.value?.mode || '-');
const modeTagType = computed(() => {
  const map = { sentinel: 'warning', cluster: 'success', single: 'info' };
  return map[redisMode.value] || 'info';
});

const latency = computed(() => statusData.value?.health?.latency_ms ?? '-');
const latencyClass = computed(() => {
  if (latency.value === '-') return '';
  if (latency.value > 200) return 'text-danger';
  if (latency.value > 50) return 'text-warning';
  return 'text-success';
});

const hitRatio = computed(() => statusData.value?.health?.keyspace_hit_ratio ?? '-');
const hitRatioClass = computed(() => {
  if (hitRatio.value === '-') return '';
  if (hitRatio.value < 50) return 'text-danger';
  if (hitRatio.value < 80) return 'text-warning';
  return 'text-success';
});

const role = computed(() => statusData.value?.health?.role || 'unknown');

const roleMap = computed(() => ({
  master: t('redis_ha_page.role.master'),
  slave: t('redis_ha_page.role.slave'),
  unknown: t('redis_ha_page.role.unknown'),
}));

const roleText = computed(() => roleMap.value[role.value] || role.value);

const connectedSlaves = computed(() => statusData.value?.health?.connected_slaves ?? 0);
const failoverAvailable = computed(() => statusData.value?.failover_available ?? false);

const uptimeFormatted = computed(() => {
  const secs = statusData.value?.health?.uptime_in_seconds ?? 0;
  if (!secs) return '-';
  const d = Math.floor(secs / 86400);
  const h = Math.floor((secs % 86400) / 3600);
  const m = Math.floor((secs % 3600) / 60);
  return d > 0
    ? t('redis_ha_page.uptime.with_days', { d, h, m })
    : t('redis_ha_page.uptime.without_days', { h, m });
});

const memoryPercent = computed(() => statusData.value?.health?.memory_usage?.percent ?? 0);
const memoryUsed = computed(() => statusData.value?.health?.memory_usage?.used ?? '-');
const memoryMax = computed(() => statusData.value?.health?.memory_usage?.max ?? '-');
const memoryPeak = computed(() => statusData.value?.health?.memory_usage?.peak ?? '-');
const memoryFormat = (pct) => t('redis_ha_page.memory.format', { pct });

const issues = computed(() => statusData.value?.issues || []);

const sentinelMaster = computed(() => sentinelData.value?.master || null);
const sentinelSlaves = computed(() => sentinelData.value?.slaves || []);
const sentinelNodes = computed(() => sentinelData.value?.sentinels || []);
const sentinelError = computed(() => sentinelData.value?.error || '');

const circuitBreaker = computed(() => statsData.value?.circuit_breaker || {});
const circuitBreakerOpen = computed(() => circuitBreaker.value?.open ?? false);

function errorMessage(e) {
  return e?.message || t('redis_ha_page.status.unknown');
}

// ─── 方法 ───
async function refreshAll() {
  loading.value = true;
  try {
    const [statusRes, sentinelRes, statsRes] = await Promise.all([
      getRedisHaStatus(),
      getRedisHaSentinel(),
      getRedisHaStats(),
    ]);
    statusData.value = statusRes.data;
    sentinelData.value = sentinelRes.data;
    statsData.value = statsRes.data;
    ElMessage.success(t('redis_ha_page.messages.refreshed'));
  } catch (e) {
    ElMessage.error(t('redis_ha_page.messages.fetch_failed', { error: errorMessage(e) }));
  } finally {
    loading.value = false;
  }
}

async function handleFailover() {
  try {
    await ElMessageBox.confirm(
      t('redis_ha_page.confirm.failover_message'),
      t('redis_ha_page.confirm.failover_title'),
      {
        confirmButtonText: t('redis_ha_page.confirm.failover_confirm'),
        cancelButtonText: t('actions.cancel'),
        type: 'warning',
      }
    );
    const res = await triggerRedisFailover();
    ElMessage.success(res?.message || t('redis_ha_page.messages.failover_triggered'));
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('redis_ha_page.messages.failover_failed', { error: errorMessage(e) }));
    }
  }
}

async function handleFlush() {
  try {
    await ElMessageBox.confirm(
      t('redis_ha_page.confirm.flush_message'),
      t('redis_ha_page.confirm.flush_title'),
      {
        confirmButtonText: t('redis_ha_page.confirm.flush_confirm'),
        cancelButtonText: t('actions.cancel'),
        type: 'error',
      }
    );
    flushing.value = true;
    const res = await flushRedisCache();
    ElMessage.success(res?.message || t('redis_ha_page.messages.cache_cleared'));
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('redis_ha_page.messages.flush_failed', { error: errorMessage(e) }));
    }
  } finally {
    flushing.value = false;
  }
}

async function handleResetBreaker() {
  try {
    await ElMessageBox.confirm(
      t('redis_ha_page.confirm.reset_message'),
      t('redis_ha_page.confirm.reset_title'),
      {
        confirmButtonText: t('actions.confirm'),
        cancelButtonText: t('actions.cancel'),
        type: 'info',
      }
    );
    await resetRedisCircuitBreaker();
    ElMessage.success(t('redis_ha_page.messages.breaker_reset'));
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('redis_ha_page.messages.reset_failed', { error: errorMessage(e) }));
    }
  }
}

onMounted(() => {
  refreshAll();
});
</script>

<style scoped>
.redis-ha-page {
  padding: 16px;
}

.page-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.page-desc {
  margin: 4px 0 0;
  font-size: 13px;
}

.stat-card {
  border-radius: 8px;
  transition: border-color 0.3s;
}

.stat-card.status-ok {
  border-left: 4px solid #67c23a;
}

.stat-card.status-warning {
  border-left: 4px solid #e6a23c;
}

.stat-card.status-critical {
  border-left: 4px solid #f56c6c;
}

.stat-label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 22px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-value small {
  font-size: 13px;
  font-weight: 400;
}

.status-icon {
  font-size: 22px;
}

.status-icon.success { color: #67c23a; }
.status-icon.warning { color: #e6a23c; }
.status-icon.danger { color: #f56c6c; }

.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-secondary { color: #909399; }
.text-sm { font-size: 12px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-500 { font-weight: 500; }

.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.mr-1 { margin-right: 4px; }
.ml-2 { margin-left: 8px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }

.action-card {
  cursor: pointer;
  text-align: center;
  transition: all 0.2s;
  border-radius: 8px;
}

.action-card:hover {
  border-color: #0f172a;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.15);
  transform: translateY(-2px);
}

.action-card h4 {
  margin: 8px 0 4px;
  font-size: 14px;
}
</style>
