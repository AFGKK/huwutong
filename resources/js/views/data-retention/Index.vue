<template>
  <div class="data-retention-page">
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">数据留存策略</h2>
        <p class="page-desc text-secondary">集中管理全系统数据生命周期 · 审计日志归档方案</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
      </el-col>
    </el-row>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">活跃策略</div>
          <div class="stat-value">{{ dashboard?.policies?.active || 0 }}<small class="text-secondary"> / {{ dashboard?.policies?.total || 0 }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">近7天执行</div>
          <div class="stat-value">{{ dashboard?.execution_stats?.total || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">近30天清理</div>
          <div class="stat-value">{{ formatNumber(dashboard?.estimated_monthly_cleaned || 0) }}<small>条</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">失败次数</div>
          <div class="stat-value text-danger">{{ dashboard?.execution_stats?.failed || 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 策略列表 -->
        <el-tab-pane label="留存策略" name="policies">
          <div class="mb-2 flex">
            <el-button size="small" @click="handleSyncPolicies" :loading="syncing">
              <el-icon class="mr-1"><Download /></el-icon>同步配置
            </el-button>
            <el-button size="small" @click="handleDryRun">
              <el-icon class="mr-1"><Search /></el-icon>预览清理
            </el-button>
            <el-button size="small" type="danger" plain @click="handleApplyCleanup">
              <el-icon class="mr-1"><Delete /></el-icon>执行清理
            </el-button>
          </div>
          <el-table :data="policies" border stripe size="small" :loading="policyLoading">
            <el-table-column prop="key" label="策略键" width="140" />
            <el-table-column prop="name" label="名称" min-width="160" />
            <el-table-column prop="category" label="分类" width="100">
              <template #default="{ row }">
                <el-tag :color="categoryColor(row.category)" size="small" effect="dark">{{ row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="table_name" label="数据表" width="150" />
            <el-table-column prop="retention_days" label="保留天数" width="100" />
            <el-table-column prop="action" label="到期动作" width="100">
              <template #default="{ row }">
                <el-tag :type="actionTag(row.action)" size="small">{{ actionLabel(row.action) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="archive_enabled" label="归档" width="70">
              <template #default="{ row }">
                <el-tag :type="row.archive_enabled ? 'success' : 'info'" size="small">{{ row.archive_enabled ? '是' : '否' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="is_active" label="状态" width="70">
              <template #default="{ row }">
                <el-switch v-model="row.is_active" @change="handleToggle(row)" size="small" />
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 执行历史 -->
        <el-tab-pane label="执行历史" name="executions">
          <el-table :data="executions" border stripe size="small" :loading="execLoading" max-height="500">
            <el-table-column prop="created_at" label="时间" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="policy_key" label="策略" width="140" />
            <el-table-column prop="action" label="动作" width="80" />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="total_records" label="总计" width="70" />
            <el-table-column prop="affected_records" label="影响" width="70" />
            <el-table-column prop="batch_count" label="批次" width="60" />
            <el-table-column prop="duration_ms" label="耗时" width="90">
              <template #default="{ row }">{{ row.duration_ms }}ms</template>
            </el-table-column>
            <el-table-column prop="is_dry_run" label="预览" width="60">
              <template #default="{ row }">
                <el-tag :type="row.is_dry_run ? 'warning' : 'info'" size="small">{{ row.is_dry_run ? '是' : '否' }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: 存储统计 -->
        <el-tab-pane label="存储统计" name="storage">
          <el-descriptions :column="2" border size="small" class="mb-4">
            <el-descriptions-item label="每月数据量">{{ storageStats?.estimated_monthly_data_gb }} GB</el-descriptions-item>
            <el-descriptions-item label="每月成本">${{ storageStats?.estimated_monthly_cost }}</el-descriptions-item>
          </el-descriptions>
          <h4 class="mb-2">存储分层</h4>
          <el-table :data="tierList" border stripe size="small">
            <el-table-column prop="label" label="层级" min-width="200" />
            <el-table-column prop="tier" label="标识" width="120" />
            <el-table-column prop="cost_per_gb" label="每GB成本" width="120">
              <template #default="{ row }">${{ row.cost_per_gb }}</template>
            </el-table-column>
            <el-table-column prop="retrieval_time" label="取回时间" width="150" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Download, Search, Delete } from '@element-plus/icons-vue';
import {
  getDataRetentionDashboard, getDataRetentionPolicies,
  updateDataRetentionPolicy, syncDataRetentionPolicies,
  runDataRetentionCleanup, getDataRetentionExecutions,
  getDataRetentionStorageStats,
} from '../../api/dataRetention';

const loading = ref(false);
const activeTab = ref('policies');
const dashboard = ref(null);
const policies = ref([]);
const policyLoading = ref(false);
const syncing = ref(false);
const executions = ref([]);
const execLoading = ref(false);
const storageStats = ref(null);

const tierList = computed(() => {
  const tiers = storageStats.value?.tiers || {};
  return Object.values(tiers);
});

function formatNumber(n) {
  if (n >= 10000) return (n / 10000).toFixed(1) + '万';
  if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
  return String(n);
}

function formatTime(t) {
  return t ? t.replace('T', ' ').substring(0, 19) : '-';
}

function categoryColor(cat) {
  const map = { audit: '#409eff', security: '#e6a23c', operation: '#67c23a', notification: '#909399', performance: '#f56c6c' };
  return map[cat] || '#909399';
}

function actionTag(a) {
  return a === 'archive' ? 'warning' : a === 'delete' ? 'danger' : 'info';
}

function actionLabel(a) {
  return a === 'archive' ? '归档' : a === 'delete' ? '删除' : '匿名化';
}

function statusTag(s) {
  return s === 'completed' ? 'success' : s === 'failed' ? 'danger' : s === 'running' ? 'warning' : 'info';
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, statsRes] = await Promise.all([
      getDataRetentionDashboard(),
      getDataRetentionStorageStats(),
    ]);
    dashboard.value = dashRes.data;
    storageStats.value = statsRes.data;
  } catch (e) {
    ElMessage.error('获取数据留存状态失败');
  } finally {
    loading.value = false;
  }
}

async function loadPolicies() {
  policyLoading.value = true;
  try {
    const res = await getDataRetentionPolicies();
    policies.value = res.data || [];
  } catch (e) {
    ElMessage.error('获取策略列表失败');
  } finally {
    policyLoading.value = false;
  }
}

async function loadExecutions() {
  execLoading.value = true;
  try {
    const res = await getDataRetentionExecutions();
    executions.value = res.data?.items || [];
  } catch (e) {
    // silencio
  } finally {
    execLoading.value = false;
  }
}

async function handleToggle(row) {
  try {
    await updateDataRetentionPolicy(row.id, { is_active: row.is_active });
    ElMessage.success(row.is_active ? '策略已启用' : '策略已禁用');
  } catch (e) {
    row.is_active = !row.is_active;
    ElMessage.error('更新失败');
  }
}

async function handleSyncPolicies() {
  syncing.value = true;
  try {
    const res = await syncDataRetentionPolicies();
    ElMessage.success(res.message);
    await loadPolicies();
  } catch (e) {
    ElMessage.error('同步失败');
  } finally {
    syncing.value = false;
  }
}

async function handleDryRun() {
  try {
    const res = await runDataRetentionCleanup({ dry_run: true });
    ElMessage.success(`预览完成: 共 ${res.data?.total_affected || 0} 条记录将受影响`);
    await loadExecutions();
  } catch (e) {
    ElMessage.error('预览失败');
  }
}

async function handleApplyCleanup() {
  try {
    await ElMessageBox.confirm(
      '确定要执行数据清理吗？此操作将按照留存策略删除到期数据，不可撤销。',
      '确认清理',
      { confirmButtonText: '确认执行', cancelButtonText: '取消', type: 'warning' }
    );
    const res = await runDataRetentionCleanup({ dry_run: false });
    ElMessage.success(`清理完成: 共清理 ${res.data?.total_affected || 0} 条记录`);
    await loadExecutions();
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('清理失败');
  }
}

const tabLoadMap = { policies: loadPolicies, executions: loadExecutions };
watch(() => activeTab.value, (tab) => { if (tabLoadMap[tab]) tabLoadMap[tab](); });

onMounted(() => {
  refreshAll();
  loadPolicies();
});
</script>

<style scoped>
.data-retention-page { padding: 16px; }
.page-title { margin: 0; font-size: 20px; font-weight: 600; }
.page-desc { margin: 4px 0 0; font-size: 13px; }
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.stat-value small { font-size: 13px; font-weight: 400; }
.text-danger { color: #f56c6c; }
.text-secondary { color: #909399; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mr-1 { margin-right: 4px; }
.flex { display: flex; gap: 8px; align-items: center; }
</style>
