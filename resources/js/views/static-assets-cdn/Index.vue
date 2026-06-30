<template>
  <div class="static-asset-cdn-page">
    <!-- 顶部统计 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_files }}</div>
          <div class="stat-label">总文件数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.current_version }}</div>
          <div class="stat-label">当前版本</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_versions }}</div>
          <div class="stat-label">历史版本</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_size_mb }} MB</div>
          <div class="stat-label">总大小</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-card class="action-card">
      <div class="action-bar">
        <div class="action-left">
          <el-tag v-if="stats.cdn_configured" type="success" effect="dark">
            CDN 已配置
          </el-tag>
          <el-tag v-else type="warning" effect="dark">
            CDN 未配置（使用本地模式）
          </el-tag>
          <span v-if="stats.cdn_domain" class="cdn-domain-info">
            {{ stats.cdn_domain }}
          </span>
        </div>
        <div class="action-right">
          <el-button type="primary" :loading="deploying" @click="handleDeploy">
            <el-icon><Upload /></el-icon>
            部署到 CDN
          </el-button>
          <el-button :loading="loading" @click="loadStats">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>
      </div>
    </el-card>

    <!-- 版本列表 -->
    <el-card class="versions-card">
      <template #header>
        <div class="card-header">
          <span>版本列表</span>
          <el-tag type="info">{{ versions.length }} 个版本</el-tag>
        </div>
      </template>

      <el-table :data="versions" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="version" label="版本号" width="200" />
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.is_current" type="success" size="small">当前版本</el-tag>
            <el-tag v-else type="info" size="small">历史版本</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="file_count" label="文件数" width="100" align="center" />
        <el-table-column prop="deployed_at" label="部署时间" min-width="180" />
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="!row.is_current"
              size="small"
              type="primary"
              plain
              @click="handleActivate(row.version)"
            >
              激活
            </el-button>
            <el-button
              v-if="!row.is_current"
              size="small"
              type="warning"
              plain
              @click="handleRollback(row.version)"
            >
              回滚
            </el-button>
            <el-button
              v-if="!row.is_current"
              size="small"
              type="danger"
              plain
              @click="handleDelete(row.version)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Upload, Refresh } from '@element-plus/icons-vue';
import staticAssetCdnApi from '@/api/staticAssetCdn';

const stats = ref({
  total_files: 0,
  current_version: '-',
  total_versions: 0,
  total_size_mb: 0,
  cdn_configured: false,
  cdn_domain: '',
  base_url: '',
});

const versions = ref([]);
const loading = ref(false);
const deploying = ref(false);

async function loadStats() {
  loading.value = true;
  try {
    const [statsRes, versionsRes] = await Promise.all([
      staticAssetCdnApi.stats(),
      staticAssetCdnApi.versions(),
    ]);
    stats.value = statsRes.data.data;
    versions.value = versionsRes.data.data.versions || [];
  } catch (err) {
    ElMessage.error('加载 CDN 状态失败');
    console.error(err);
  } finally {
    loading.value = false;
  }
}

async function loadVersions() {
  try {
    const res = await staticAssetCdnApi.versions();
    versions.value = res.data.data.versions || [];
  } catch (err) {
    console.error('加载版本列表失败', err);
  }
}

async function handleDeploy() {
  deploying.value = true;
  try {
    const res = await staticAssetCdnApi.deploy();
    ElMessage.success(`部署成功: ${res.data.data.total} 个文件`);
    await loadStats();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '部署失败');
  } finally {
    deploying.value = false;
  }
}

async function handleActivate(version) {
  try {
    await staticAssetCdnApi.activate(version);
    ElMessage.success(`已激活版本 ${version}`);
    await loadStats();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '激活失败');
  }
}

async function handleRollback(version) {
  try {
    await ElMessageBox.confirm(
      `确定回滚到版本 ${version}？`,
      '确认回滚',
      { type: 'warning', confirmButtonText: '确认回滚', cancelButtonText: '取消' }
    );
    await staticAssetCdnApi.rollback(version);
    ElMessage.success(`已回滚到版本 ${version}`);
    await loadStats();
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(err.response?.data?.message || '回滚失败');
    }
  }
}

async function handleDelete(version) {
  try {
    await ElMessageBox.confirm(
      `确定删除版本 ${version} 的所有文件？此操作不可恢复。`,
      '确认删除',
      { type: 'error', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    );
    await staticAssetCdnApi.deleteVersion(version);
    ElMessage.success(`版本 ${version} 已删除`);
    await loadStats();
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(err.response?.data?.message || '删除失败');
    }
  }
}

onMounted(() => {
  loadStats();
});
</script>

<style scoped>
.static-asset-cdn-page {
  padding: 20px;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  text-align: center;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: var(--el-color-primary);
}

.stat-label {
  font-size: 14px;
  color: var(--el-text-color-secondary);
  margin-top: 8px;
}

.action-card {
  margin-bottom: 20px;
}

.action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.action-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.cdn-domain-info {
  font-size: 13px;
  color: var(--el-text-color-secondary);
}

.action-right {
  display: flex;
  gap: 10px;
}

.versions-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
