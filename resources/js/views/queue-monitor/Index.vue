<template>
  <div class="queue-monitor-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-button type="danger" @click="handleCleanup" :loading="cleaning">
          <el-icon><Delete /></el-icon> {{ t(`${P}.cleanup`) }}
        </el-button>
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col v-for="(stat, queueName) in stats.by_queue || {}" :key="queueName" :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-header">{{ queueLabel(queueName) }}</div>
          <el-row :gutter="8">
            <el-col :span="12">
              <div class="stat-value stat-success">{{ stat.completed }}</div>
              <div class="stat-label">{{ t(`${P}.success`) }}</div>
            </el-col>
            <el-col :span="12">
              <div class="stat-value stat-danger">{{ stat.failed }}</div>
              <div class="stat-label">{{ t(`${P}.failed`) }}</div>
            </el-col>
          </el-row>
          <el-divider style="margin:8px 0" />
          <div class="stat-meta">
            <span>{{ t(`${P}.dead_letter`) }}: <strong class="text-warning">{{ stat.dead_letters }}</strong></span>
            <span style="margin-left:12px">{{ t(`${P}.avg`) }}: {{ stat.avg_duration_ms ? stat.avg_duration_ms.toFixed(0) + 'ms' : '—' }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.total_dead_letters }}</div>
          <div class="stat-label">{{ t(`${P}.total_dead`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.recent_24h_failed > 0 ? 'stat-danger' : 'stat-success'">
            {{ stats.recent_24h_failed }}
          </div>
          <div class="stat-label">{{ t(`${P}.failed_24h`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.total_retried }}</div>
          <div class="stat-label">{{ t(`${P}.retried`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.failed`)" name="failed">
          <div class="tab-toolbar">
            <el-select v-model="failedFilter.queue" :placeholder="t(`${P}.queue`)" clearable style="width:140px;margin-right:8px" @change="loadFailed">
              <el-option :label="t(`${P}.all`)" value="" />
              <el-option v-for="(cfg, name) in queues" :key="name" :label="cfg.label" :value="name" />
            </el-select>
            <el-input v-model="failedFilter.search" :placeholder="t(`${P}.search_ph`)" clearable style="width:240px" @clear="loadFailed" @keyup.enter="loadFailed" />
          </div>
          <el-table :data="failedJobs" stripe v-loading="failedLoading">
            <el-table-column :label="t(`${P}.cols.job`)" min-width="250">
              <template #default="{ row }">{{ row.job_class || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.queue`)" width="100">
              <template #default="{ row }">{{ row.queue }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.attempts`)" prop="attempt" width="80" align="center" />
            <el-table-column :label="t(`${P}.cols.duration`)" prop="duration_ms" width="80" align="center">
              <template #default>—</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.error`)" min-width="250">
              <template #default="{ row }">
                <el-tooltip :content="row.error_message" placement="top">
                  <span class="text-danger">{{ row.error_message?.substring(0, 50) || '—' }}</span>
                </el-tooltip>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.failed_at`)" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
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

        <el-tab-pane :label="t(`${P}.tabs.dead`)" name="dead-letters">
          <div class="tab-toolbar">
            <el-select v-model="deadFilter.queue" :placeholder="t(`${P}.queue`)" clearable style="width:140px;margin-right:8px" @change="loadDeadLetters">
              <el-option :label="t(`${P}.all`)" value="" />
              <el-option v-for="(cfg, name) in queues" :key="name" :label="cfg.label" :value="name" />
            </el-select>
            <el-select v-model="deadFilter.status" :placeholder="t(`${P}.cols.status`)" clearable style="width:120px;margin-right:8px" @change="loadDeadLetters">
              <el-option :label="t(`${P}.all`)" value="" />
              <el-option :label="t(`${P}.status.dead`)" value="dead" />
              <el-option :label="t(`${P}.status.retried`)" value="retried" />
              <el-option :label="t(`${P}.status.ignored`)" value="ignored" />
            </el-select>
            <el-button type="primary" style="margin-left:auto" @click="handleBatchRetry" :disabled="!selectedDeadLetters.length" :loading="batchRetrying">
              <el-icon><Refresh /></el-icon> {{ t(`${P}.batch_retry`, { n: selectedDeadLetters.length }) }}
            </el-button>
          </div>
          <el-table :data="deadLetters" stripe v-loading="deadLoading" @selection-change="onDeadSelectionChange">
            <el-table-column type="selection" width="40" />
            <el-table-column :label="t(`${P}.cols.job`)" min-width="250">
              <template #default="{ row }">{{ row.job_class }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.queue`)" width="100">
              <template #default="{ row }">{{ row.queue }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.attempts`)" prop="attempts" width="80" align="center" />
            <el-table-column :label="t(`${P}.cols.last_error`)" min-width="250">
              <template #default="{ row }">
                <el-tooltip :content="row.last_error"><span class="text-danger">{{ row.last_error?.substring(0, 50) }}</span></el-tooltip>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.status`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'dead' ? 'danger' : row.status === 'retried' ? 'success' : 'info'" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.failed_at`)" width="160">
              <template #default="{ row }">{{ formatTime(row.failed_at) }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.actions`)" width="140" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'dead'" size="small" type="success" @click="handleRetry(row)">{{ t(`${P}.retry`) }}</el-button>
                <el-button v-if="row.status === 'dead'" size="small" @click="handleIgnore(row)">{{ t(`${P}.ignore`) }}</el-button>
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, Refresh, Delete } from '@element-plus/icons-vue';
import queueApi from '@/api/queueMonitor';

const { t, locale } = useI18n();
const P = 'queue_monitor_page';
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'));

const loading = ref(false);
const cleaning = ref(false);
const batchRetrying = ref(false);
const activeTab = ref('failed');

const queues = computed(() => ({
  default: { label: t(`${P}.queues.default`) },
  notifications: { label: t(`${P}.queues.notifications`) },
  webhooks: { label: t(`${P}.queues.webhooks`) },
  emails: { label: t(`${P}.queues.emails`) },
  batch: { label: t(`${P}.queues.batch`) },
}));

function queueLabel(name) { return queues.value[name]?.label || name; }
function statusLabel(s) {
  const key = `${P}.status.${s}`;
  const translated = t(key);
  return translated === key ? s : translated;
}

const stats = ref({ by_queue: {}, total_dead_letters: 0, recent_24h_failed: 0, total_retried: 0 });

const failedJobs = ref([]);
const failedLoading = ref(false);
const failedFilter = reactive({ queue: '', search: '' });
const failedPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

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
    ElMessage.success(t(`${P}.messages.requeued`));
    loadDeadLetters();
  } catch { ElMessage.error(t(`${P}.messages.retry_failed`)); }
}

async function handleIgnore(row) {
  try {
    await ElMessageBox.confirm(t(`${P}.confirm_ignore`), t('actions.confirm'));
    await queueApi.ignoreDeadLetter(row.id);
    ElMessage.success(t(`${P}.messages.ignored`));
    loadDeadLetters();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t(`${P}.messages.action_failed`)); }
}

async function handleBatchRetry() {
  if (!selectedDeadLetters.value.length) return;
  try {
    await ElMessageBox.confirm(t(`${P}.confirm_batch`, { n: selectedDeadLetters.value.length }), t(`${P}.batch_retry_title`));
    batchRetrying.value = true;
    const res = await queueApi.batchRetryDeadLetters(selectedDeadLetters.value);
    ElMessage.success(res.message || t(`${P}.messages.batch_done`));
    loadDeadLetters();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t(`${P}.messages.batch_failed`)); }
  finally { batchRetrying.value = false; }
}

async function handleCleanup() {
  try {
    await ElMessageBox.confirm(t(`${P}.confirm_cleanup`), t(`${P}.cleanup_title`));
    cleaning.value = true;
    const res = await queueApi.cleanup();
    ElMessage.success(res.message || t(`${P}.messages.cleanup_done`));
    refreshAll();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t(`${P}.messages.cleanup_failed`)); }
  finally { cleaning.value = false; }
}

function formatTime(time) {
  if (!time) return '—';
  return new Date(time).toLocaleString(dateLocale.value, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.queue-monitor-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-header { font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #606266; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-label { font-size: 12px; color: #909399; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-primary { color: #0f172a; }
.stat-meta { font-size: 12px; color: #909399; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-danger { color: #F56C6C; }
.text-warning { color: #E6A23C; }
</style>
