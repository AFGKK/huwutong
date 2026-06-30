<template>
  <div class="seat-pool-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        License 席位池管理
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_licenses }}</div>
          <div class="stat-label">License 总数</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.pool_enabled_licenses }}</div>
          <div class="stat-label">启用席位池</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_seats }}</div>
          <div class="stat-label">总席位</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active_assignments }}</div>
          <div class="stat-label">活跃席位</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.waiting_queue > 0 ? 'stat-warning' : ''">{{ stats.waiting_queue }}</div>
          <div class="stat-label">排队等待</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 席位池 License 列表 -->
        <el-tab-pane label="席位池列表" name="licenses">
          <div class="tab-toolbar">
            <el-select v-model="listFilter.pool_mode" placeholder="席位模式" clearable style="width:140px;margin-right:8px" @change="loadLicenses">
              <el-option label="全部模式" value="" />
              <el-option label="共享模式" value="shared" />
              <el-option label="独占模式" value="exclusive" />
              <el-option label="自动排队" value="auto" />
            </el-select>
            <el-input v-model="listFilter.search" placeholder="搜索 License Key/客户..." clearable style="width:240px" @clear="loadLicenses" @keyup.enter="loadLicenses" />
            <el-button type="success" style="margin-left:auto" @click="handleBatchReleaseExpired" :loading="releasing">
              <el-icon><Delete /></el-icon> 批量清理过期席位
            </el-button>
          </div>
          <el-table :data="licenses" stripe v-loading="licensesLoading" @row-click="showLicenseDetail">
            <el-table-column label="License Key" min-width="180">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.license_key }}</span>
              </template>
            </el-table-column>
            <el-table-column label="模式" width="100">
              <template #default="{ row }">
                <el-tag :type="modeType(row.pool_mode)" size="small">{{ modeLabel(row.pool_mode) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="总席位" prop="seats" width="80" align="center" />
            <el-table-column label="活跃" prop="active_count" width="80" align="center" />
            <el-table-column label="可用" prop="available" width="80" align="center" />
            <el-table-column label="利用率" width="140">
              <template #default="{ row }">
                <el-progress :percentage="row.utilization_percent" :stroke-width="10" :status="row.utilization_percent > 90 ? 'exception' : row.utilization_percent > 70 ? 'warning' : 'success'" />
              </template>
            </el-table-column>
            <el-table-column label="超时(分钟)" prop="pool_timeout_minutes" width="100" align="center" />
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showLicenseDetail(row)">详情</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
            <el-pagination
              v-model:current-page="pagination.current_page"
              :page-size="pagination.per_page"
              :total="pagination.total"
              layout="prev, pager, next"
              @current-change="loadLicenses"
            />
          </div>
        </el-tab-pane>

        <!-- 分配历史 -->
        <el-tab-pane label="分配历史" name="history">
          <div class="tab-toolbar">
            <el-select v-model="historyFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadHistory">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="已释放" value="inactive" />
            </el-select>
          </div>
          <el-table :data="historyRecords" stripe v-loading="historyLoading">
            <el-table-column label="License" min-width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="席位标识" prop="seat_identifier" width="150" />
            <el-table-column label="标签" prop="label" width="120" />
            <el-table-column label="设备" width="120">
              <template #default="{ row }">{{ row.device?.platform || '—' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="分配方式" prop="assigned_by" width="100" />
            <el-table-column label="分配时间" width="160">
              <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
            </el-table-column>
            <el-table-column label="释放时间" width="160">
              <template #default="{ row }">{{ row.released_at ? formatTime(row.released_at) : '—' }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="historyPagination.total > historyPagination.per_page">
            <el-pagination
              v-model:current-page="historyPagination.current_page"
              :page-size="historyPagination.per_page"
              :total="historyPagination.total"
              layout="prev, pager, next"
              @current-change="loadHistory"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- License 详情对话框 -->
    <el-dialog v-model="detailVisible" :title="'席位池详情 - ' + (detailLicense?.license_key || '')" width="800px" top="5vh">
      <template v-if="detailPool">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.active }} / {{ detailPool.total_seats }}</div>
              <div class="stat-label">活跃 / 总席位</div>
              <el-progress :percentage="detailPool.utilization_percent" :stroke-width="8" />
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.waiting_queue }}</div>
              <div class="stat-label">排队等待</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.available }}</div>
              <div class="stat-label">可用席位</div>
            </el-card>
          </el-col>
        </el-row>

        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="席位模式">{{ detailPool.pool_mode_label }}</el-descriptions-item>
          <el-descriptions-item label="超时(分钟)">{{ detailPool.timeout_minutes }}</el-descriptions-item>
          <el-descriptions-item label="总席位">{{ detailPool.total_seats }}</el-descriptions-item>
          <el-descriptions-item label="排队上限">{{ detailPool.waiting_limit }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4>配置</h4>
        <el-form :model="configForm" label-width="120px" size="small">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="总席位">
                <el-input-number v-model="configForm.seats" :min="0" :max="1000" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="席位模式">
                <el-select v-model="configForm.pool_mode" style="width:100%">
                  <el-option label="共享模式" value="shared" />
                  <el-option label="独占模式" value="exclusive" />
                  <el-option label="自动排队" value="auto" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="超时(分钟)">
                <el-input-number v-model="configForm.pool_timeout_minutes" :min="1" :max="1440" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="排队上限">
                <el-input-number v-model="configForm.pool_waiting_limit" :min="1" :max="200" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item>
            <el-button type="primary" @click="handleUpdateConfig" :loading="configSaving">保存配置</el-button>
          </el-form-item>
        </el-form>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Delete } from '@element-plus/icons-vue';
import seatPoolApi from '@/api/seatPool';

const loading = ref(false);
const releasing = ref(false);
const configSaving = ref(false);
const activeTab = ref('licenses');

// 仪表盘
const stats = ref({
  total_licenses: 0, pool_enabled_licenses: 0, total_seats: 0,
  active_assignments: 0, waiting_queue: 0, mode_distribution: {},
});

// License 列表
const licenses = ref([]);
const licensesLoading = ref(false);
const listFilter = reactive({ pool_mode: '', search: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 历史记录
const historyRecords = ref([]);
const historyLoading = ref(false);
const historyFilter = reactive({ status: '' });
const historyPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 详情弹窗
const detailVisible = ref(false);
const detailLicense = ref(null);
const detailPool = ref(null);
const configForm = reactive({
  seats: 0, pool_mode: 'shared', pool_timeout_minutes: 30, pool_waiting_limit: 50,
});

onMounted(() => {
  refreshAll();
});

async function refreshAll() {
  loading.value = true;
  try {
    const res = await seatPoolApi.dashboard();
    stats.value = res.data;
  } finally {
    loading.value = false;
  }
  loadLicenses();
  loadHistory();
}

// ═══════ License 列表 ═══════

async function loadLicenses() {
  licensesLoading.value = true;
  try {
    const res = await seatPoolApi.listLicenses({ ...listFilter, page: pagination.current_page });
    licenses.value = res.data.data || [];
    Object.assign(pagination, res.data);
  } finally {
    licensesLoading.value = false;
  }
}

async function showLicenseDetail(row) {
  detailLicense.value = row;
  detailVisible.value = true;
  try {
    const res = await seatPoolApi.getLicenseDetail(row.id);
    detailPool.value = res.data.pool_status;
    const cfg = res.data.license;
    configForm.seats = cfg.seats ?? 0;
    configForm.pool_mode = cfg.pool_mode || 'shared';
    configForm.pool_timeout_minutes = cfg.pool_timeout_minutes ?? 30;
    configForm.pool_waiting_limit = cfg.pool_waiting_limit ?? 50;
  } catch {
    ElMessage.error('加载详情失败');
  }
}

async function handleUpdateConfig() {
  configSaving.value = true;
  try {
    await seatPoolApi.updateConfig(detailLicense.value.id, configForm);
    ElMessage.success('配置已更新');
    loadLicenses();
  } finally {
    configSaving.value = false;
  }
}

// ═══════ 批量清理 ═══════

async function handleBatchReleaseExpired() {
  releasing.value = true;
  try {
    const res = await seatPoolApi.batchReleaseExpired();
    ElMessage.success(res.message || '清理完成');
    loadLicenses();
  } finally {
    releasing.value = false;
  }
}

// ═══════ 分配历史 ═══════

async function loadHistory() {
  historyLoading.value = true;
  try {
    const res = await seatPoolApi.assignmentHistory({ ...historyFilter, page: historyPagination.current_page });
    historyRecords.value = res.data.data || [];
    Object.assign(historyPagination, res.data);
  } finally {
    historyLoading.value = false;
  }
}

// ═══════ 工具函数 ═══════

function modeType(mode) {
  const map = { shared: 'success', exclusive: 'primary', auto: 'warning' };
  return map[mode] || 'info';
}

function modeLabel(mode) {
  const map = { shared: '共享', exclusive: '独占', auto: '自动排队' };
  return map[mode] || mode;
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.seat-pool-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #409EFF; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
</style>
