<template>
  <div class="backup-manager">
    <!-- 统计概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('backups_page.stat_database') }}</div>
            <div class="stat-value">{{ t('backups_page.count_times', { n: stats.database?.completed || 0 }) }}</div>
            <div class="stat-sub">{{ statSub(stats.database) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('backups_page.stat_files') }}</div>
            <div class="stat-value">{{ t('backups_page.count_times', { n: stats.files?.completed || 0 }) }}</div>
            <div class="stat-sub">{{ statSub(stats.files) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('backups_page.stat_disk') }}</div>
            <div class="stat-value">{{ stats.disk || 'local' }}</div>
            <div class="stat-sub">{{ t('backups_page.disk_used', { usage: stats.disk_usage || '0 B' }) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">{{ t('backups_page.stat_retention') }}</div>
            <div class="stat-value">{{ t('backups_page.retention_days', { n: cfg?.database?.retention_days || 30 }) }}</div>
            <div class="stat-sub">{{ t('backups_page.files_retention', { n: cfg?.files?.retention_days || 14 }) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作按钮 -->
    <el-row :gutter="16" class="mb-3">
      <el-col :span="4">
        <el-button type="primary" @click="runDbBackup" :loading="runningDb">
          <el-icon><Plus /></el-icon> {{ t('backups_page.run_db_backup') }}
        </el-button>
      </el-col>
      <el-col :span="4">
        <el-button type="warning" plain @click="runFileBackup" :loading="runningFile">
          <el-icon><Plus /></el-icon> {{ t('backups_page.run_file_backup') }}
        </el-button>
      </el-col>
      <el-col :span="16" class="text-right">
        <el-button @click="fetchBackups">{{ t('backups_page.refresh') }}</el-button>
      </el-col>
    </el-row>

    <!-- 备份记录表格 -->
    <el-table :data="backups" v-loading="loading" stripe>
      <el-table-column prop="name" :label="t('backups_page.col_name')" min-width="200" />
      <el-table-column :label="t('backups_page.col_type')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.type === 'database' ? 'primary' : 'warning'" size="small">
            {{ row.type === 'database' ? t('backups_page.type_db') : t('backups_page.type_file') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_status')" width="80">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_size')" width="90">
        <template #default="{ row }">{{ row.formatted_size || '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_duration')" width="70">
        <template #default="{ row }">{{ row.duration_seconds ? row.duration_seconds + 's' : '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_storage')" width="80" prop="disk" />
      <el-table-column :label="t('backups_page.col_completed_at')" width="150">
        <template #default="{ row }">{{ row.completed_at ? formatDateTime(row.completed_at) : '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_expires')" width="120">
        <template #default="{ row }">{{ row.expires_at ? formatDate(row.expires_at) : '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('backups_page.col_actions')" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 'completed'" size="small" @click="handleDownload(row)">{{ t('actions.download') }}</el-button>
          <el-popconfirm v-if="row.status === 'completed' && row.type === 'database'" :title="t('backups_page.restore_confirm')" @confirm="handleRestore(row)">
            <template #reference>
              <el-button size="small" type="warning" plain>{{ t('backups_page.restore') }}</el-button>
            </template>
          </el-popconfirm>
          <el-popconfirm :title="t('backups_page.delete_confirm')" @confirm="handleDelete(row)">
            <template #reference>
              <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination class="mt-3" layout="prev, pager, next" :total="total" :page-size="20" @current-change="page => fetchBackups(page)" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/backup';

const { t, locale } = useI18n();

const loading = ref(false);
const backups = ref([]);
const total = ref(0);
const stats = reactive({});
const cfg = ref(null);
const runningDb = ref(false);
const runningFile = ref(false);

function dateLocale() {
  return locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US';
}

function formatDateTime(dateStr) {
  return new Date(dateStr).toLocaleString(dateLocale());
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString(dateLocale());
}

function statSub(section) {
  const last = section?.last_backup
    ? ' ' + timeAgo(section.last_backup.completed_at)
    : t('backups_page.none');
  return t('backups_page.stat_sub', {
    size: formatBytes(section?.total_size),
    last,
  });
}

function fetchBackups(page = 1) {
  loading.value = true;
  api.list({ page, per_page: 20 }).then(res => {
    backups.value = res.data.data?.data || [];
    total.value = res.data.data?.total || 0;
  }).finally(() => loading.value = false);
}

function fetchStats() {
  api.stats().then(res => {
    Object.assign(stats, res.data.data || {});
  });
}

function fetchConfig() {
  api.config().then(res => {
    cfg.value = res.data.data || null;
  });
}

function runDbBackup() {
  runningDb.value = true;
  api.backupDatabase().then(res => {
    ElMessage.success(t('backups_page.msg_db_backup_done'));
    fetchBackups();
    fetchStats();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || t('backups_page.msg_backup_failed'));
  }).finally(() => runningDb.value = false);
}

function runFileBackup() {
  runningFile.value = true;
  api.backupFiles().then(res => {
    ElMessage.success(t('backups_page.msg_file_backup_done'));
    fetchBackups();
    fetchStats();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || t('backups_page.msg_backup_failed'));
  }).finally(() => runningFile.value = false);
}

function handleDownload(row) {
  api.download(row.id).then(res => {
    const url = window.URL.createObjectURL(new Blob([res.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', row.file_name);
    document.body.appendChild(link);
    link.click();
    link.remove();
  }).catch(() => ElMessage.error(t('backups_page.msg_download_failed')));
}

function handleDelete(row) {
  api.destroy(row.id).then(() => {
    ElMessage.success(t('backups_page.msg_deleted'));
    fetchBackups();
    fetchStats();
  }).catch(() => ElMessage.error(t('backups_page.msg_delete_failed')));
}

function handleRestore(row) {
  api.restore(row.id).then(() => {
    ElMessage.success(t('backups_page.msg_restored'));
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || t('backups_page.msg_restore_failed'));
  });
}

function statusTag(s) {
  return ({ completed: 'success', failed: 'danger', running: 'warning', pending: 'info', expired: 'info' })[s] || 'info';
}

function statusLabel(s) {
  const map = {
    completed: 'backups_page.status_completed',
    failed: 'backups_page.status_failed',
    running: 'backups_page.status_running',
    pending: 'backups_page.status_pending',
    expired: 'backups_page.status_expired',
  };
  return map[s] ? t(map[s]) : s;
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let i = 0;
  let b = bytes;
  while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
  return b.toFixed(2) + ' ' + units[i];
}

function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return t('backups_page.time_ago_mins', { n: mins });
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return t('backups_page.time_ago_hrs', { n: hrs });
  const days = Math.floor(hrs / 24);
  return t('backups_page.time_ago_days', { n: days });
}

onMounted(() => {
  fetchBackups();
  fetchStats();
  fetchConfig();
});
</script>

<style scoped>
.backup-manager { padding: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.stat-box { text-align: center; }
.stat-label { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
.stat-value { font-size: 18px; font-weight: 600; }
.stat-sub { font-size: 12px; color: #9ca3af; margin-top: 4px; }
.text-right { text-align: right; }
</style>
