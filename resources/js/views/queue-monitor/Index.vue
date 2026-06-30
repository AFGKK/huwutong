<template>
  <div class="queue-monitor-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        队列死信监控
      </h2>
      <div class="header-actions">
        <el-button type="danger" @click="handleCleanup" :loading="cleaning">
          <el-icon><Delete /></el-icon> 清理旧记录
        </el-button>
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 队列统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col v-for="(stat, queueName) in stats.by_queue || {}" :key="queueName" :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-header">{{ queueLabel(queueName) }}</div>
          <el-row :gutter="8">
            <el-col :span="12">
              <div class="stat-value stat-success">{{ stat.completed }}</div>
              <div class="stat-label">成功</div>
            </el-col>
            <el-col :span="12">
              <div class="stat-value stat-danger">{{ stat.failed }}</div>
              <div class="stat-label">失败</div>
            </el-col>
          </el-row>
          <el-divider style="margin:8px 0" />
          <div class="stat-meta">
            <span>死信: <strong class="text-warning">{{ stat.dead_letters }}</strong></span>
            <span style="margin-left:12px">平均: {{ stat.avg_duration_ms ? stat.avg_duration_ms.toFixed(0) + 'ms' : '—' }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 全局指标 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.total_dead_letters }}</div>
          <div class="stat-label">总死信数</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.recent_24h_failed > 0 ? 'stat-danger' : 'stat-success'">
            {{ stats.recent_24h_failed }}
          </div>
          <div class="stat-label">近24小时失败</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.total_retried }}</div>
          <div class="stat-label">已重试</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 失败任务 -->
        <el-tab-pane label="失败任务" name="failed">
          <div class="tab-toolbar">
            <el-select v-model="failedFilter.queue" placeholder="队列" clearable style="width:140px;margin-right:8px" @change="loadFailed">
              <el-option label="全部" value="" />
              <el-option v-for="(cfg, name) in queues" :key="name" :label="cfg.label" :value="name" />
            </el-select>
            <el-input v-model="failedFilter.search" placeholder="搜索任务类名..." clearable style="width:240px" @clear="loadFailed" @keyup.enter="loadFailed" />
          </div>
          <el-table :data="failedJobs" stripe v-loading="failedLoading">
            <el-table-column label="任务类" min-width="250">
              <template #default="{ row }">{{ row.job_class || '—' }}</template>
            </el-table-column>
            <el-table-column label="队列" width="100">{{ row.queue }}</el-table-column>
            <el-table-column label="尝试次数" prop="attempt" width="80" align="center" />
            <el-table-column label="耗时(ms)" prop="duration_ms" width="80" align="center">—</el-table-column>
            <el-table-column label="错误信息" min-width="250">
              <template #default="{ row }">
                <el-tooltip :content="row.error_message" placement="top">
                  <span class="text-danger">{{ row.error_message?.substring(0, 50) || '—' }}</span>
                </el-tooltip>
              </template>
            </el-table-column>
            <el-table-column label="失败时间" width="160">{{ formatTime(row.created_at) }}</el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="failedPagination.total > failedPagination.per_page">
            <el-pagination
              v-model:current-page="failedPagination.current_page"
              :page-size="failedPagination.per_page"
              :total="failedPagination.total"
              layout="prev, pager, next"
              @current-change="loadFailed"
            />
          </div>
        </el-tab-pane>

        <!-- 死信队列 -->
        <el-tab-pane label="死信队列" name="dead-letters">
          <div class="tab-toolbar">
            <el-select v-model="deadFilter.queue" placeholder="队列" clearable style="width:140px;margin-right:8px" @change="loadDeadLetters">
              <el-option label="全部" value="" />
              <el-option v-for="(cfg, name) in queues" :key="name" :label="cfg.label" :value="name" />
            </el-select>
            <el-select v-model="deadFilter.status" placeholder="状态" clearable style="width:120px;margin-right:8px" @change="loadDeadLetters">
              <el-option label="全部" value="" />
              <el-option label="待处理" value="dead" />
              <el-option label="已重试" value="retried" />
              <el-option label="已忽略" value="ignored" />
            </el-select>
            <el-button type="primary" style="margin-left:auto" @click="handleBatchRetry" :disabled="!selectedDeadLetters.length" :loading="batchRetrying">
              <el-icon><Refresh /></el-icon> 批量重试 ({{ selectedDeadLetters.length }})
            </el-button>
          </div>
          <el-table :data="deadLetters" stripe v-loading="deadLoading" @selection-change="onDeadSelectionChange">
            <el-table-column type="selection" width="40" />
            <el-table-column label="任务类" min-width="250">
              <template #default="{ row }">{{ row.job_class }}</template>
            </el-table-column>
            <el-table-column label="队列" width="100">{{ row.queue }}</el-table-column>
            <el-table-column label="尝试次数" prop="attempts" width="80" align="center" />
            <el-table-column label="最后错误" min-width="250">
              <template #default="{ row }">
                <el-tooltip :content="row.last_error"><span class="text-danger">{{ row.last_error?.substring(0, 50) }}</span></el-tooltip>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'dead' ? 'danger' : row.status === 'retried' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="失败时间" width="160">{{ formatTime(row.failed_at) }}</el-table-column>
            <el-table-column label="操作" width="140" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'dead'" size="small" type="success" @click="handleRetry(row)">重试</el-button>
                <el-button v-if="row.status === 'dead'" size="small" @click="handleIgnore(row)">忽略</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="deadPagination.total > deadPagination.per_page">
            <el-pagination
              v-model:current-page="deadPagination.current_page"
              :page-size="deadPagination.per_page"
              :total="deadPagination.total"
              layout="prev, pager, next"
              @current-change="loadDeadLetters"
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
import { Monitor, Refresh, Delete } from '@element-plus/icons-vue';
import queueApi from '@/api/queueMonitor';

const loading = ref(false);
const cleaning = ref(false);
const batchRetrying = ref(false);
const activeTab = ref('failed');

// 队列配置
const queues = {
  default: { label: '默认队列' },
  notifications: { label: '通知队列' },
  webhooks: { label: 'Webhook 队列' },
  emails: { label: '邮件队列' },
  batch: { label: '批量任务' },
};

function queueLabel(name) { return queues[name]?.label || name; }

// 仪表盘
const stats = ref({ by_queue: {}, total_dead_letters: 0, recent_24h_failed: 0, total_retried: 0 });

// 失败任务
const failedJobs = ref([]);
const failedLoading = ref(false);
const failedFilter = reactive({ queue: '', search: '' });
const failedPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 死信
const deadLetters = ref([]);
const deadLoading = ref(false);
const deadFilter = reactive({ queue: '', status: '' });
const deadPagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const selectedDeadLetters = ref([]);

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await queueApi.dashboard();
    stats.value = res.data;
  } finally { loading.value = false; }
  loadFailed();
  loadDeadLetters();
}

async function loadFailed() {
  failedLoading.value = true;
  try {
    const res = await queueApi.listFailedJobs({ ...failedFilter, page: failedPagination.current_page });
    failedJobs.value = res.data.data || [];
    Object.assign(failedPagination, res.data);
  } finally { failedLoading.value = false; }
}

async function loadDeadLetters() {
  deadLoading.value = true;
  try {
    const res = await queueApi.listDeadLetters({ ...deadFilter, page: deadPagination.current_page });
    deadLetters.value = res.data.data || [];
    Object.assign(deadPagination, res.data);
  } finally { deadLoading.value = false; }
}

function onDeadSelectionChange(val) { selectedDeadLetters.value = val.map(v => v.id); }

async function handleRetry(row) {
  try {
    await queueApi.retryDeadLetter(row.id);
    ElMessage.success('已重新加入队列');
    loadDeadLetters();
  } catch { ElMessage.error('重试失败'); }
}

async function handleIgnore(row) {
  try {
    await ElMessageBox.confirm('确定忽略此死信？', '确认');
    await queueApi.ignoreDeadLetter(row.id);
    ElMessage.success('已忽略');
    loadDeadLetters();
  } catch (e) { if (e !== 'cancel') ElMessage.error('操作失败'); }
}

async function handleBatchRetry() {
  if (!selectedDeadLetters.value.length) return;
  try {
    await ElMessageBox.confirm(`确定重试 ${selectedDeadLetters.value.length} 个死信任务？`, '批量重试');
    batchRetrying.value = true;
    const res = await queueApi.batchRetryDeadLetters(selectedDeadLetters.value);
    ElMessage.success(res.message || '批量重试完成');
    loadDeadLetters();
  } catch (e) { if (e !== 'cancel') ElMessage.error('批量重试失败'); }
  finally { batchRetrying.value = false; }
}

async function handleCleanup() {
  try {
    await ElMessageBox.confirm('确定清理过期队列记录？此操作不可撤销。', '清理确认');
    cleaning.value = true;
    const res = await queueApi.cleanup();
    ElMessage.success(res.message || '清理完成');
    refreshAll();
  } catch (e) { if (e !== 'cancel') ElMessage.error('清理失败'); }
  finally { cleaning.value = false; }
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.queue-monitor-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-header { font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #606266; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-label { font-size: 12px; color: #909399; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #409EFF; }
.stat-meta { font-size: 12px; color: #909399; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-danger { color: #F56C6C; }
.text-warning { color: #E6A23C; }
</style>
