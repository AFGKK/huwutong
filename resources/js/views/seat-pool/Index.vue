<template>
  <div class="seat-pool-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t('seat_pool_index_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('seat_pool_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_licenses }}</div>
          <div class="stat-label">{{ t('seat_pool_index_page.stats.total_licenses') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.pool_enabled_licenses }}</div>
          <div class="stat-label">{{ t('seat_pool_index_page.stats.pool_enabled') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_seats }}</div>
          <div class="stat-label">{{ t('seat_pool_index_page.stats.total_seats') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active_assignments }}</div>
          <div class="stat-label">{{ t('seat_pool_index_page.stats.active_assignments') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.waiting_queue > 0 ? 'stat-warning' : ''">{{ stats.waiting_queue }}</div>
          <div class="stat-label">{{ t('seat_pool_page.stat_queue_waiting') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 席位池 License 列表 -->
        <el-tab-pane :label="t('seat_pool_index_page.tabs.licenses')" name="licenses">
          <div class="tab-toolbar">
            <el-select v-model="listFilter.pool_mode" :placeholder="t('seat_pool_index_page.filters.pool_mode')" clearable style="width:140px;margin-right:8px" @change="loadLicenses">
              <el-option :label="t('seat_pool_index_page.filters.all_modes')" value="" />
              <el-option v-for="opt in poolModeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-input v-model="listFilter.search" :placeholder="t('seat_pool_index_page.filters.search_ph')" clearable style="width:240px" @clear="loadLicenses" @keyup.enter="loadLicenses" />
            <el-button type="success" style="margin-left:auto" @click="handleBatchReleaseExpired" :loading="releasing">
              <el-icon><Delete /></el-icon> {{ t('seat_pool_index_page.btn_batch_cleanup') }}
            </el-button>
          </div>
          <el-table :data="licenses" stripe v-loading="licensesLoading" @row-click="showLicenseDetail">
            <el-table-column :label="t('licenses_page.license_key')" min-width="180">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.license_key }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.cols.mode')" width="100">
              <template #default="{ row }">
                <el-tag :type="modeType(row.pool_mode)" size="small">{{ modeLabel(row.pool_mode) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.cols.seats')" prop="seats" width="80" align="center" />
            <el-table-column :label="t('seat_pool_index_page.cols.active')" prop="active_count" width="80" align="center" />
            <el-table-column :label="t('seat_pool_index_page.cols.available')" prop="available" width="80" align="center" />
            <el-table-column :label="t('seat_pool_index_page.cols.utilization')" width="140">
              <template #default="{ row }">
                <el-progress :percentage="row.utilization_percent" :stroke-width="10" :status="row.utilization_percent > 90 ? 'exception' : row.utilization_percent > 70 ? 'warning' : 'success'" />
              </template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.cols.timeout')" prop="pool_timeout_minutes" width="100" align="center" />
            <el-table-column :label="t('licenses_page.col_actions')" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showLicenseDetail(row)">{{ t('actions.view_details') }}</el-button>
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
        <el-tab-pane :label="t('seat_pool_index_page.tabs.history')" name="history">
          <div class="tab-toolbar">
            <el-select v-model="historyFilter.status" :placeholder="t('seat_pool_index_page.filters.status')" clearable style="width:130px;margin-right:8px" @change="loadHistory">
              <el-option :label="t('licenses_page.all')" value="" />
              <el-option :label="historyStatusLabels.active" value="active" />
              <el-option :label="historyStatusLabels.inactive" value="inactive" />
            </el-select>
          </div>
          <el-table :data="historyRecords" stripe v-loading="historyLoading">
            <el-table-column :label="t('seat_pool_index_page.cols.license')" min-width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_page.col_seat_identifier')" prop="seat_identifier" width="150" />
            <el-table-column :label="t('seat_pool_page.col_label')" prop="label" width="120" />
            <el-table-column :label="t('seat_pool_page.col_device')" width="120">
              <template #default="{ row }">{{ row.device?.platform || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.filters.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ historyStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.cols.assigned_by')" prop="assigned_by" width="100" />
            <el-table-column :label="t('seat_pool_page.col_assigned_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('seat_pool_index_page.cols.released_at')" width="160">
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
    <el-dialog v-model="detailVisible" :title="t('seat_pool_index_page.detail_title', { key: detailLicense?.license_key || '' })" width="800px" top="5vh">
      <template v-if="detailPool">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.active }} / {{ detailPool.total_seats }}</div>
              <div class="stat-label">{{ t('seat_pool_page.stat_active_total') }}</div>
              <el-progress :percentage="detailPool.utilization_percent" :stroke-width="8" />
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.waiting_queue }}</div>
              <div class="stat-label">{{ t('seat_pool_page.stat_queue_waiting') }}</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover">
              <div class="stat-value">{{ detailPool.available }}</div>
              <div class="stat-label">{{ t('seat_pool_page.stat_available') }}</div>
            </el-card>
          </el-col>
        </el-row>

        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('seat_pool_page.pool_mode')">{{ detailPool.pool_mode_label }}</el-descriptions-item>
          <el-descriptions-item :label="t('seat_pool_page.timeout_minutes')">{{ detailPool.timeout_minutes }}</el-descriptions-item>
          <el-descriptions-item :label="t('seat_pool_index_page.form_total_seats')">{{ detailPool.total_seats }}</el-descriptions-item>
          <el-descriptions-item :label="t('seat_pool_page.queue_limit')">{{ detailPool.waiting_limit }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4>{{ t('seat_pool_index_page.section_config') }}</h4>
        <el-form :model="configForm" label-width="120px" size="small">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item :label="t('seat_pool_index_page.form_total_seats')">
                <el-input-number v-model="configForm.seats" :min="0" :max="1000" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('seat_pool_page.pool_mode')">
                <el-select v-model="configForm.pool_mode" style="width:100%">
                  <el-option v-for="opt in poolModeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('seat_pool_page.timeout_minutes')">
                <el-input-number v-model="configForm.pool_timeout_minutes" :min="1" :max="1440" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('seat_pool_page.queue_limit')">
                <el-input-number v-model="configForm.pool_waiting_limit" :min="1" :max="200" style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item>
            <el-button type="primary" @click="handleUpdateConfig" :loading="configSaving">{{ t('seat_pool_index_page.btn_save_config') }}</el-button>
          </el-form-item>
        </el-form>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Delete } from '@element-plus/icons-vue';
import seatPoolApi from '@/api/seatPool';

const { t, locale } = useI18n();

const loading = ref(false);
const releasing = ref(false);
const configSaving = ref(false);
const activeTab = ref('licenses');

const modeLabels = computed(() => ({
  shared: t('seat_pool_page.mode_shared'),
  exclusive: t('seat_pool_page.mode_exclusive'),
  auto: t('seat_pool_page.mode_auto'),
}));

const poolModeFilterOptions = computed(() => [
  { value: 'shared', label: modeLabels.value.shared },
  { value: 'exclusive', label: modeLabels.value.exclusive },
  { value: 'auto', label: modeLabels.value.auto },
]);

const poolModeOptions = computed(() => [
  { value: 'shared', label: t('seat_pool_page.mode_shared_option') },
  { value: 'exclusive', label: t('seat_pool_page.mode_exclusive_option') },
  { value: 'auto', label: t('seat_pool_page.mode_auto_option') },
]);

const historyStatusLabels = computed(() => ({
  active: t('seat_pool_page.status_active'),
  inactive: t('seat_pool_page.status_released'),
}));

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
    ElMessage.error(t('seat_pool_page.messages.load_failed'));
  }
}

async function handleUpdateConfig() {
  configSaving.value = true;
  try {
    await seatPoolApi.updateConfig(detailLicense.value.id, configForm);
    ElMessage.success(t('seat_pool_page.messages.config_ok'));
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
    ElMessage.success(res.message || t('seat_pool_index_page.messages.cleanup_done'));
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
  return modeLabels.value[mode] || mode;
}

function historyStatusLabel(status) {
  return historyStatusLabels.value[status] || status;
}

function formatTime(val) {
  if (!val) return '—';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return new Date(val).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
.stat-primary { color: #0f172a; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
</style>
