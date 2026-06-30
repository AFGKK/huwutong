<template>
  <div class="two-phase-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Timer /></el-icon>
        授权预申请 · 两阶段提交
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.active_count }}</div>
          <div class="stat-label">活跃预留</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.committed_count }}</div>
          <div class="stat-label">已提交</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.expired_count }}</div>
          <div class="stat-label">已过期</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.active_licenses }}</div>
          <div class="stat-label">使用中 License</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 活跃预留 -->
        <el-tab-pane label="活跃预留" name="active">
          <div class="tab-toolbar">
            <el-select v-model="activeFilter.license_id" placeholder="选择 License" clearable filterable style="width:240px;margin-right:8px" @change="loadActive">
              <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
            </el-select>
          </div>
          <el-table :data="activeReservations" stripe v-loading="activeLoading">
            <el-table-column label="预留Token" min-width="200">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.reservation_token }}</span>
              </template>
            </el-table-column>
            <el-table-column label="License" width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="设备指纹" width="140">
              <template #default="{ row }">
                <el-tooltip :content="row.fingerprint || '—'">
                  <span>{{ row.fingerprint?.substring(0, 16) || '—' }}...</span>
                </el-tooltip>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : row.status === 'committed' ? 'primary' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="过期时间" width="160">
              <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
            </el-table-column>
            <el-table-column label="剩余(秒)" width="80" align="center">
              <template #default="{ row }">
                <span :class="row.seconds_remaining < 30 ? 'text-danger' : ''">
                  {{ row.seconds_remaining ?? '—' }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="danger" @click="handleCancel(row)">取消</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="activePagination.total > activePagination.per_page">
            <el-pagination
              v-model:current-page="activePagination.current_page"
              :page-size="activePagination.per_page"
              :total="activePagination.total"
              layout="prev, pager, next"
              @current-change="loadActive"
            />
          </div>
        </el-tab-pane>

        <!-- 预留历史 -->
        <el-tab-pane label="预留历史" name="history">
          <div class="tab-toolbar">
            <el-select v-model="historyFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadHistory">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="已提交" value="committed" />
              <el-option label="已过期" value="expired" />
              <el-option label="已取消" value="cancelled" />
            </el-select>
          </div>
          <el-table :data="historyRecords" stripe v-loading="historyLoading">
            <el-table-column label="预留Token" min-width="200">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.reservation_token }}</span>
              </template>
            </el-table-column>
            <el-table-column label="License" width="160">
              <template #default="{ row }">{{ row.license?.license_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="设备指纹" width="140">
              <template #default="{ row }">{{ row.fingerprint?.substring(0, 16) || '—' }}...</template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'committed' ? 'primary' : row.status === 'expired' ? 'danger' : row.status === 'cancelled' ? 'warning' : 'success'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="过期时间" width="160">
              <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="日志" min-width="200">
              <template #default="{ row }">
                <div v-if="row.logs?.length" class="log-list">
                  <el-tag v-for="log in row.logs.slice(0, 3)" :key="log.id" size="small" type="info" effect="plain" style="margin-right:4px;margin-bottom:2px">
                    {{ log.action }}
                  </el-tag>
                </div>
                <span v-else>—</span>
              </template>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Timer, Refresh } from '@element-plus/icons-vue';
import twoPhaseApi from '@/api/twoPhaseCommit';

const loading = ref(false);
const activeTab = ref('active');

// 统计
const stats = ref({
  active_count: 0, committed_count: 0, expired_count: 0, active_licenses: 0,
});

// 活跃预留
const activeReservations = ref([]);
const activeLoading = ref(false);
const activeFilter = reactive({ license_id: '' });
const activePagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 预留历史
const historyRecords = ref([]);
const historyLoading = ref(false);
const historyFilter = reactive({ status: '' });
const historyPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// License 选项
const licenseOptions = ref([]);

onMounted(() => {
  refreshAll();
});

async function refreshAll() {
  loading.value = true;
  try {
    // 加载统计
    const [activeRes, historyRes] = await Promise.all([
      twoPhaseApi.getActiveReservations({ per_page: 5 }),
      twoPhaseApi.getReservationHistory({ per_page: 5 }),
    ]);

    const activeData = activeRes.data?.data || [];
    const historyData = historyRes.data?.data || [];
    const allData = [...activeData, ...historyData];

    stats.value = {
      active_count: activeRes.data?.total || activeData.length,
      committed_count: allData.filter(r => r.status === 'committed').length,
      expired_count: allData.filter(r => r.status === 'expired').length,
      active_licenses: [...new Set(allData.map(r => r.license_id))].length,
    };
  } finally {
    loading.value = false;
  }
  loadActive();
  loadHistory();
}

async function loadActive() {
  activeLoading.value = true;
  try {
    const res = await twoPhaseApi.getActiveReservations({ ...activeFilter, page: activePagination.current_page });
    activeReservations.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
    if (res.data?.total !== undefined) {
      Object.assign(activePagination, res.data);
    }
  } finally {
    activeLoading.value = false;
  }
}

async function loadHistory() {
  historyLoading.value = true;
  try {
    const res = await twoPhaseApi.getReservationHistory({ ...historyFilter, page: historyPagination.current_page });
    historyRecords.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
    if (res.data?.total !== undefined) {
      Object.assign(historyPagination, res.data);
    }
  } finally {
    historyLoading.value = false;
  }
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm(`确定取消预留 ${row.reservation_token?.substring(0, 16)}...？`, '取消确认');
    await twoPhaseApi.cancelReservation(row.reservation_token);
    ElMessage.success('预留已取消');
    loadActive();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('取消失败');
  }
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.two-phase-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-primary { color: #409EFF; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.text-danger { color: #F56C6C; font-weight: 700; }
.log-list { display: flex; flex-wrap: wrap; }
</style>
