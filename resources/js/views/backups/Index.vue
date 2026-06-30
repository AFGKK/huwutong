<template>
  <div class="backup-manager">
    <!-- 统计概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">数据库备份</div>
            <div class="stat-value">{{ stats.database?.completed || 0 }} 次</div>
            <div class="stat-sub">共 {{ formatBytes(stats.database?.total_size) }} | 最近{{ stats.database?.last_backup ? ' ' + timeAgo(stats.database.last_backup.completed_at) : '无' }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">文件备份</div>
            <div class="stat-value">{{ stats.files?.completed || 0 }} 次</div>
            <div class="stat-sub">共 {{ formatBytes(stats.files?.total_size) }} | 最近{{ stats.files?.last_backup ? ' ' + timeAgo(stats.files.last_backup.completed_at) : '无' }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">存储磁盘</div>
            <div class="stat-value">{{ stats.disk || 'local' }}</div>
            <div class="stat-sub">已用 {{ stats.disk_usage || '0 B' }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-label">保留期限</div>
            <div class="stat-value">{{ cfg?.database?.retention_days || 30 }}天</div>
            <div class="stat-sub">文件 {{ cfg?.files?.retention_days || 14 }}天</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作按钮 -->
    <el-row :gutter="16" class="mb-3">
      <el-col :span="4">
        <el-button type="primary" @click="runDbBackup" :loading="runningDb">
          <el-icon><Plus /></el-icon> 立即数据库备份
        </el-button>
      </el-col>
      <el-col :span="4">
        <el-button type="warning" plain @click="runFileBackup" :loading="runningFile">
          <el-icon><Plus /></el-icon> 立即文件备份
        </el-button>
      </el-col>
      <el-col :span="16" class="text-right">
        <el-button @click="fetchBackups">刷新</el-button>
      </el-col>
    </el-row>

    <!-- 备份记录表格 -->
    <el-table :data="backups" v-loading="loading" stripe>
      <el-table-column prop="name" label="名称" min-width="200" />
      <el-table-column label="类型" width="80">
        <template #default="{ row }">
          <el-tag :type="row.type === 'database' ? 'primary' : 'warning'" size="small">
            {{ row.type === 'database' ? 'DB' : '文件' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="大小" width="90">
        <template #default="{ row }">{{ row.formatted_size || '-' }}</template>
      </el-table-column>
      <el-table-column label="耗时" width="70">
        <template #default="{ row }">{{ row.duration_seconds ? row.duration_seconds + 's' : '-' }}</template>
      </el-table-column>
      <el-table-column label="存储" width="80" prop="disk" />
      <el-table-column label="完成时间" width="150">
        <template #default="{ row }">{{ row.completed_at ? new Date(row.completed_at).toLocaleString() : '-' }}</template>
      </el-table-column>
      <el-table-column label="到期" width="120">
        <template #default="{ row }">{{ row.expires_at ? new Date(row.expires_at).toLocaleDateString() : '-' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 'completed'" size="small" @click="handleDownload(row)">下载</el-button>
          <el-popconfirm v-if="row.status === 'completed' && row.type === 'database'" title="恢复将覆盖当前数据库，确定?" @confirm="handleRestore(row)">
            <template #reference>
              <el-button size="small" type="warning" plain>恢复</el-button>
            </template>
          </el-popconfirm>
          <el-popconfirm title="确定删除此备份?" @confirm="handleDelete(row)">
            <template #reference>
              <el-button size="small" type="danger" plain>删除</el-button>
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
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/backup';

const loading = ref(false);
const backups = ref([]);
const total = ref(0);
const stats = reactive({});
const cfg = ref(null);
const runningDb = ref(false);
const runningFile = ref(false);

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
    ElMessage.success('数据库备份完成');
    fetchBackups();
    fetchStats();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || '备份失败');
  }).finally(() => runningDb.value = false);
}

function runFileBackup() {
  runningFile.value = true;
  api.backupFiles().then(res => {
    ElMessage.success('文件备份完成');
    fetchBackups();
    fetchStats();
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || '备份失败');
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
  }).catch(() => ElMessage.error('下载失败'));
}

function handleDelete(row) {
  api.destroy(row.id).then(() => {
    ElMessage.success('备份已删除');
    fetchBackups();
    fetchStats();
  }).catch(() => ElMessage.error('删除失败'));
}

function handleRestore(row) {
  api.restore(row.id).then(() => {
    ElMessage.success('数据库已从备份恢复');
  }).catch(err => {
    ElMessage.error(err.response?.data?.message || '恢复失败');
  });
}

function statusTag(s) {
  return ({ completed: 'success', failed: 'danger', running: 'warning', pending: 'info', expired: 'info' })[s] || 'info';
}
function statusLabel(s) {
  return ({ completed: '完成', failed: '失败', running: '运行中', pending: '等待', expired: '已过期' })[s] || s;
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
  if (mins < 60) return mins + '分钟前';
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return hrs + '小时前';
  const days = Math.floor(hrs / 24);
  return days + '天前';
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
