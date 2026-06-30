<template>
  <div class="air-gapped-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Lock /></el-icon>
        气隙部署管理
      </h2>
      <div class="header-actions">
        <el-button @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 运行模式状态 -->
    <el-alert
      v-if="status.air_gapped_mode"
      title="当前处于气隙模式 — 系统运行于完全离线环境"
      type="warning"
      show-icon
      :closable="false"
      class="mb-4"
    />
    <el-alert
      v-else
      title="检测到网络连接 — 当前非气隙模式"
      type="info"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ status.license_count }}</div>
          <div class="stat-label">已导入 License</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ status.update_count }}</div>
          <div class="stat-label">离线更新包</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-value">{{ status.last_import || '-' }}</div>
          <div class="stat-label">最近 License 导入</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ formatBytes(status.disk_usage || 0) }}</div>
          <div class="stat-label">离线存储占用</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: License 导入 -->
        <el-tab-pane label="License 导入" name="license">
          <div class="tab-toolbar">
            <el-button type="primary" size="small" @click="scanUsbDrives" :loading="scanning">
              <el-icon><Search /></el-icon> 扫描 U 盘
            </el-button>
            <el-upload
              :before-upload="handleLicenseUpload"
              :show-file-list="false"
              accept=".lic,.license,.key,.pem,.bin"
            >
              <el-button size="small" type="success">
                <el-icon><Upload /></el-icon> 上传 License 文件
              </el-button>
            </el-upload>
          </div>

          <!-- U 盘扫描结果 -->
          <el-card v-if="usbCandidates.length" shadow="never" class="mb-3">
            <template #header><span>U 盘中发现以下 License 文件</span></template>
            <el-table :data="usbCandidates" stripe size="small">
              <el-table-column prop="name" label="文件名" min-width="200" />
              <el-table-column prop="path" label="路径" min-width="300">
                <template #default="{ row }">
                  <span class="mono">{{ row.path }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="size" label="大小" width="100">
                <template #default="{ row }">{{ formatBytes(row.size) }}</template>
              </el-table-column>
              <el-table-column prop="modified" label="修改时间" width="160" />
              <el-table-column label="操作" width="120" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" type="primary" @click="importLicenseFile(row.path)">
                    导入
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>

          <!-- 已导入 License 列表 -->
          <h4 class="mb-2">已导入 License 文件</h4>
          <el-table :data="licenses" stripe v-loading="licensesLoading" empty-text="暂无已导入的 License">
            <el-table-column prop="name" label="文件名" min-width="250">
              <template #default="{ row }">
                <el-icon><Document /></el-icon>
                <span class="ml-1 mono">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="size" label="大小" width="120">
              <template #default="{ row }">{{ formatBytes(row.size) }}</template>
            </el-table-column>
            <el-table-column prop="modified" label="导入时间" width="180" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 离线更新 -->
        <el-tab-pane label="离线更新包" name="updates">
          <div class="tab-toolbar">
            <el-upload
              :before-upload="handleUpdateUpload"
              :show-file-list="false"
              accept=".tar,.gz,.tgz"
            >
              <el-button size="small" type="primary">
                <el-icon><Upload /></el-icon> 上传更新包
              </el-button>
            </el-upload>
          </div>

          <el-table :data="updates" stripe v-loading="updatesLoading" empty-text="暂无离线更新包">
            <el-table-column prop="name" label="更新包名称" min-width="300">
              <template #default="{ row }">
                <el-icon><Connection /></el-icon>
                <span class="ml-1 mono">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="size" label="大小" width="120">
              <template #default="{ row }">{{ formatBytes(row.size) }}</template>
            </el-table-column>
            <el-table-column prop="modified" label="上传时间" width="180" />
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="success" @click="applyUpdatePackage(row.name)">
                  应用更新
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: Docker 信息 -->
        <el-tab-pane label="Docker 状态" name="docker">
          <div class="tab-toolbar">
            <el-button size="small" @click="fetchDockerInfo" :loading="dockerLoading">
              <el-icon><Refresh /></el-icon> 刷新 Docker 信息
            </el-button>
          </div>

          <el-descriptions :column="1" border class="mb-4" v-if="dockerInfo.version">
            <el-descriptions-item label="Docker 版本">
              {{ dockerInfo.version }}
            </el-descriptions-item>
            <el-descriptions-item label="Docker Compose">
              {{ dockerInfo.compose_version || '未安装' }}
            </el-descriptions-item>
          </el-descriptions>

          <h4 class="mb-2">运行容器</h4>
          <el-table :data="dockerContainers" stripe size="small" empty-text="无运行容器">
            <el-table-column label="容器名" min-width="200">
              <template #default="{ row }">
                <span class="mono">{{ row.split('\t')[0] }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" min-width="200">
              <template #default="{ row }">
                <span class="mono">{{ row.split('\t')[1] }}</span>
              </template>
            </el-table-column>
            <el-table-column label="端口" min-width="200">
              <template #default="{ row }">
                <span class="mono">{{ row.split('\t')[2] }}</span>
              </template>
            </el-table-column>
          </el-table>

          <h4 class="mb-2 mt-3">本地镜像</h4>
          <el-table :data="dockerImages" stripe size="small" empty-text="无镜像">
            <el-table-column label="镜像名" min-width="300">
              <template #default="{ row }">
                <span class="mono">{{ row.split('\t')[0] }}</span>
              </template>
            </el-table-column>
            <el-table-column label="大小" min-width="100">
              <template #default="{ row }">
                {{ row.split('\t')[1] }}
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 健康检查 -->
        <el-tab-pane label="健康检查" name="health">
          <div class="tab-toolbar">
            <el-button size="small" @click="runHealthCheck" :loading="healthLoading">
              <el-icon><Monitor /></el-icon> 执行检查
            </el-button>
          </div>

          <el-descriptions :column="1" border v-if="healthData.timestamp">
            <el-descriptions-item label="PHP 版本">
              {{ healthData.php_version }}
            </el-descriptions-item>
            <el-descriptions-item label="气隙模式">
              <el-tag :type="healthData.air_gapped_mode ? 'warning' : 'info'" size="small">
                {{ healthData.air_gapped_mode ? '是' : '否' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="存储可写">
              <el-tag :type="healthData.storage ? 'success' : 'danger'" size="small">
                {{ healthData.storage ? '可写' : '不可写' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="检查时间">
              {{ healthData.timestamp }}
            </el-descriptions-item>
          </el-descriptions>

          <h4 class="mb-2 mt-3">PHP 扩展检查</h4>
          <el-table :data="extensionList" stripe size="small" v-if="healthData.extensions">
            <el-table-column prop="name" label="扩展名" width="200" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.loaded ? 'success' : 'danger'" size="small">
                  {{ row.loaded ? '已加载' : '缺失' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 导入 License 对话框 -->
    <el-dialog v-model="showImportDialog" title="导入 License" width="500px">
      <el-form :model="importForm" label-width="120px">
        <el-form-item label="文件路径">
          <el-input v-model="importForm.file_path" placeholder="输入 License 文件路径">
            <template #prepend><el-icon><Folder /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item label="签名验证">
          <el-switch v-model="importForm.validate" active-text="验证签名" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showImportDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmImport" :loading="importing">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Lock, Refresh, Search, Upload, Folder,
  Document, Connection, Monitor,
} from '@element-plus/icons-vue';
import airGappedApi from '@/api/air-gapped';

// ─── 状态 ───
const loading = ref(false);
const activeTab = ref('license');
const scanning = ref(false);
const importing = ref(false);
const licensesLoading = ref(false);
const updatesLoading = ref(false);
const dockerLoading = ref(false);
const healthLoading = ref(false);

const status = ref({
  air_gapped_mode: false,
  detected: false,
  license_count: 0,
  update_count: 0,
  last_import: null,
  last_update: null,
  disk_usage: 0,
  php_extensions: {},
  storage_writable: false,
});

const usbCandidates = ref([]);
const licenses = ref([]);
const updates = ref([]);
const dockerInfo = ref({});
const healthData = ref({});

const showImportDialog = ref(false);
const importForm = ref({
  file_path: '',
  validate: true,
});

// ─── 计算属性 ───
const dockerContainers = computed(() => dockerInfo.value.containers || []);
const dockerImages = computed(() => dockerInfo.value.images || []);

const extensionList = computed(() => {
  if (!healthData.value.extensions) return [];
  return Object.entries(healthData.value.extensions).map(([name, loaded]) => ({
    name,
    loaded,
  }));
});

// ─── 方法 ───
function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

async function refreshAll() {
  loading.value = true;
  try {
    await Promise.all([
      fetchStatus(),
      fetchLicenses(),
      fetchUpdates(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchStatus() {
  try {
    const { data } = await airGappedApi.getStatus();
    if (data.success) Object.assign(status.value, data.data);
  } catch {
    // ignore
  }
}

async function fetchLicenses() {
  licensesLoading.value = true;
  try {
    const { data } = await airGappedApi.listLicenses();
    if (data.success) licenses.value = data.data;
  } finally {
    licensesLoading.value = false;
  }
}

async function fetchUpdates() {
  updatesLoading.value = true;
  try {
    const { data } = await airGappedApi.listUpdates();
    if (data.success) updates.value = data.data;
  } finally {
    updatesLoading.value = false;
  }
}

async function scanUsbDrives() {
  scanning.value = true;
  try {
    const { data } = await airGappedApi.scanUsb();
    if (data.success) {
      usbCandidates.value = data.data.candidates;
      ElMessage.success(`发现 ${data.data.count} 个 License 文件`);
      if (data.data.count === 0) {
        ElMessage.info('未在 U 盘找到 License 文件');
      }
    }
  } catch {
    ElMessage.error('U 盘扫描失败');
  } finally {
    scanning.value = false;
  }
}

async function importLicenseFile(filePath) {
  importing.value = true;
  try {
    const { data } = await airGappedApi.importLicense(filePath, true);
    if (data.success) {
      ElMessage.success(data.message);
      await fetchLicenses();
      await fetchStatus();
    } else {
      ElMessage.error(data.message);
    }
  } catch {
    ElMessage.error('License 导入失败');
  } finally {
    importing.value = false;
  }
}

function handleLicenseUpload(file) {
  const formData = new FormData();
  formData.append('license_file', file);

  airGappedApi.uploadLicense(file).then(({ data }) => {
    if (data.success) {
      ElMessage.success(data.message);
      fetchLicenses();
    } else {
      ElMessage.error(data.message);
    }
  }).catch(() => {
    ElMessage.error('License 上传失败');
  });

  return false; // prevent default upload
}

function handleUpdateUpload(file) {
  airGappedApi.uploadUpdate(file).then(({ data }) => {
    if (data.success) {
      ElMessage.success('更新包上传成功');
      fetchUpdates();
    } else {
      ElMessage.error(data.message);
    }
  }).catch(() => {
    ElMessage.error('更新包上传失败');
  });

  return false;
}

async function applyUpdatePackage(packageName) {
  try {
    await ElMessageBox.confirm(
      `确定要应用更新包 "${packageName}" 吗？此操作将重启服务。`,
      '确认应用更新',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    );

    const { data } = await airGappedApi.applyUpdate(packageName);
    if (data.success) {
      ElMessage.success(data.message);
      await fetchUpdates();
      await fetchStatus();
    } else {
      ElMessage.error(data.message);
    }
  } catch {
    // cancelled
  }
}

async function fetchDockerInfo() {
  dockerLoading.value = true;
  try {
    const { data } = await airGappedApi.getDockerInfo();
    if (data.success) dockerInfo.value = data.data;
  } catch {
    ElMessage.warning('无法获取 Docker 信息（Docker 可能未运行）');
  } finally {
    dockerLoading.value = false;
  }
}

async function runHealthCheck() {
  healthLoading.value = true;
  try {
    const { data } = await airGappedApi.healthCheck();
    if (data.success) healthData.value = data.data;
  } catch {
    ElMessage.error('健康检查失败');
  } finally {
    healthLoading.value = false;
  }
}

function confirmImport() {
  if (!importForm.value.file_path) {
    ElMessage.warning('请输入 License 文件路径');
    return;
  }
  importLicenseFile(importForm.value.file_path);
  showImportDialog.value = false;
}

// ─── 初始化 ───
onMounted(() => {
  refreshAll();
});
</script>

<style scoped>
.air-gapped-page {
  padding: 0;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.page-header h2 {
  margin: 0;
}
.header-actions {
  display: flex;
  gap: 8px;
}
.mb-4 {
  margin-bottom: 16px;
}
.mb-3 {
  margin-bottom: 12px;
}
.mb-2 {
  margin-bottom: 8px;
}
.mt-3 {
  margin-top: 12px;
}
.ml-1 {
  margin-left: 4px;
}
.mono {
  font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
  font-size: 0.9em;
}
.stat-card {
  text-align: center;
}
.stat-card .stat-value {
  font-size: 1.8em;
  font-weight: 700;
  color: #409eff;
}
.stat-card.stat-success .stat-value {
  font-size: 1em;
  font-weight: 600;
  color: #67c23a;
}
.stat-card .stat-label {
  font-size: 0.85em;
  color: #909399;
  margin-top: 4px;
}
.tab-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}
</style>
