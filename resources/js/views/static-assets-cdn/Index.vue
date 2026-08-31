<template>
  <div class="static-asset-cdn-page">
    <!-- 顶部统计 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_files }}</div>
          <div class="stat-label">{{ t('static_assets_cdn_page.stat_total_files') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.current_version }}</div>
          <div class="stat-label">{{ t('static_assets_cdn_page.stat_current_version') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_versions }}</div>
          <div class="stat-label">{{ t('static_assets_cdn_page.stat_total_versions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_size_mb }} MB</div>
          <div class="stat-label">{{ t('static_assets_cdn_page.stat_total_size') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-card class="action-card">
      <div class="action-bar">
        <div class="action-left">
          <el-tag v-if="stats.cdn_configured" type="success" effect="dark">
            {{ t('static_assets_cdn_page.cdn_configured') }}
          </el-tag>
          <el-tag v-else type="warning" effect="dark">
            {{ t('static_assets_cdn_page.cdn_not_configured') }}
          </el-tag>
          <span v-if="stats.cdn_domain" class="cdn-domain-info">
            {{ stats.cdn_domain }}
          </span>
        </div>
        <div class="action-right">
          <el-button type="primary" :loading="deploying" @click="handleDeploy">
            <el-icon><Upload /></el-icon>
            {{ t('static_assets_cdn_page.deploy_to_cdn') }}
          </el-button>
          <el-button :loading="loading" @click="loadStats">
            <el-icon><Refresh /></el-icon>
            {{ t('static_assets_cdn_page.refresh') }}
          </el-button>
        </div>
      </div>
    </el-card>

    <!-- 版本列表 -->
    <el-card class="versions-card">
      <template #header>
        <div class="card-header">
          <span>{{ t('static_assets_cdn_page.version_list') }}</span>
          <el-tag type="info">{{ t('static_assets_cdn_page.version_count', { count: versions.length }) }}</el-tag>
        </div>
      </template>

      <el-table :data="versions" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="version" :label="t('static_assets_cdn_page.col_version')" width="200" />
        <el-table-column :label="t('static_assets_cdn_page.col_status')" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.is_current" type="success" size="small">{{ t('static_assets_cdn_page.status_current') }}</el-tag>
            <el-tag v-else type="info" size="small">{{ t('static_assets_cdn_page.status_history') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="file_count" :label="t('static_assets_cdn_page.col_file_count')" width="100" align="center" />
        <el-table-column prop="deployed_at" :label="t('static_assets_cdn_page.col_deployed_at')" min-width="180" />
        <el-table-column :label="t('static_assets_cdn_page.col_actions')" width="260" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="!row.is_current"
              size="small"
              type="primary"
              plain
              @click="handleActivate(row.version)"
            >
              {{ t('static_assets_cdn_page.activate') }}
            </el-button>
            <el-button
              v-if="!row.is_current"
              size="small"
              type="warning"
              plain
              @click="handleRollback(row.version)"
            >
              {{ t('static_assets_cdn_page.rollback') }}
            </el-button>
            <el-button
              v-if="!row.is_current"
              size="small"
              type="danger"
              plain
              @click="handleDelete(row.version)"
            >
              {{ t('actions.delete') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Upload, Refresh } from '@element-plus/icons-vue';
import staticAssetCdnApi from '@/api/staticAssetCdn';

const { t } = useI18n();

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
    ElMessage.error(t('static_assets_cdn_page.messages.load_failed'));
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
    console.error(t('static_assets_cdn_page.messages.load_failed'), err);
  }
}

async function handleDeploy() {
  deploying.value = true;
  try {
    const res = await staticAssetCdnApi.deploy();
    ElMessage.success(t('static_assets_cdn_page.messages.deploy_success', { count: res.data.data.total }));
    await loadStats();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || t('static_assets_cdn_page.messages.deploy_failed'));
  } finally {
    deploying.value = false;
  }
}

async function handleActivate(version) {
  try {
    await staticAssetCdnApi.activate(version);
    ElMessage.success(t('static_assets_cdn_page.messages.activate_success', { version }));
    await loadStats();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || t('static_assets_cdn_page.messages.activate_failed'));
  }
}

async function handleRollback(version) {
  try {
    await ElMessageBox.confirm(
      t('static_assets_cdn_page.rollback_confirm_msg', { version }),
      t('static_assets_cdn_page.rollback_confirm_title'),
      { type: 'warning', confirmButtonText: t('static_assets_cdn_page.rollback_confirm_btn'), cancelButtonText: t('actions.cancel') }
    );
    await staticAssetCdnApi.rollback(version);
    ElMessage.success(t('static_assets_cdn_page.messages.rollback_success', { version }));
    await loadStats();
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(err.response?.data?.message || t('static_assets_cdn_page.messages.rollback_failed'));
    }
  }
}

async function handleDelete(version) {
  try {
    await ElMessageBox.confirm(
      t('static_assets_cdn_page.delete_confirm_msg', { version }),
      t('static_assets_cdn_page.delete_confirm_title'),
      { type: 'error', confirmButtonText: t('static_assets_cdn_page.delete_confirm_btn'), cancelButtonText: t('actions.cancel') }
    );
    await staticAssetCdnApi.deleteVersion(version);
    ElMessage.success(t('static_assets_cdn_page.messages.delete_success', { version }));
    await loadStats();
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(err.response?.data?.message || t('static_assets_cdn_page.messages.delete_failed'));
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
